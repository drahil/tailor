<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\Storage\SessionFile;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;

/**
 * Turns a saved session into a standalone PHP script or an Artisan command class.
 */
class SessionExporter
{
    public const SCRIPT = 'script';
    public const COMMAND = 'command';
    public const FORMATS = [self::SCRIPT, self::COMMAND];

    /**
     * Class names that could not be resolved during the last export.
     *
     * @var array<int, string>
     */
    private array $unresolved = [];

    /**
     * @param array<string, string> $aliases Alias => FQN of the classes Tailor auto-imports
     */
    public function __construct(private readonly array $aliases = []) {}

    public function export(SessionData $session, string $format, string $namespace = ''): string
    {
        $code = implode("\n", array_map(SessionFile::renderCommand(...), $session->commands));
        $imports = $this->imports($code, $format === self::COMMAND);

        return $format === self::COMMAND
            ? $this->renderCommand($session, $code, $imports, $namespace)
            : $this->renderScript($session, $code, $imports);
    }

    /**
     * @return array<int, string>
     */
    public function getUnresolved(): array
    {
        return $this->unresolved;
    }

    public static function className(string $sessionName): string
    {
        $class = Str::studly($sessionName);

        return ctype_digit($class[0] ?? '') ? 'Session' . $class : $class;
    }

    public static function fileName(string $sessionName, string $format): string
    {
        return ($format === self::COMMAND ? self::className($sessionName) : $sessionName) . '.php';
    }

    /**
     * @param array<int, string> $imports
     */
    private function renderScript(SessionData $session, string $code, array $imports): string
    {
        return "<?php\n\n" . $this->renderImports($imports) . $this->renderDescription($session) . $code . "\n";
    }

    /**
     * @param array<int, string> $imports
     */
    private function renderCommand(SessionData $session, string $code, array $imports, string $namespace): string
    {
        $name = $session->metadata->name->toString();
        $class = self::className($name);
        $description = str_replace(['\\', "'"], ['\\\\', "\\'"], $session->metadata->description?->toString() ?? "Run the {$name} Tailor session");
        $imports = array_merge($imports, ['Illuminate\\Console\\Command', 'Illuminate\\Console\\ConfirmableTrait']);
        $body = preg_replace('/^(?=.)/m', '        ', $code);

        return <<<PHP
        <?php

        namespace {$namespace};

        {$this->renderImports($imports)}class {$class} extends Command
        {
            use ConfirmableTrait;

            protected \$signature = 'tailor:{$name} {--force : Run in production without confirmation}';

            protected \$description = '{$description}';

            public function handle(): int
            {
                if (! \$this->confirmToProceed()) {
                    return self::FAILURE;
                }

        {$body}

                return self::SUCCESS;
            }
        }

        PHP;
    }

    /**
     * @param array<int, string> $imports
     */
    private function renderImports(array $imports): string
    {
        if ($imports === []) {
            return '';
        }

        $imports = array_unique($imports);
        sort($imports);

        return implode("\n", array_map(fn (string $import) => "use {$import};", $imports)) . "\n\n";
    }

    private function renderDescription(SessionData $session): string
    {
        $description = $session->metadata->description?->toString();

        return $description === null ? '' : "// {$description}\n\n";
    }

    /**
     * Imports for the unqualified class names in the code.
     *
     * Scripts only import Tailor's aliases, as facades and global classes work there by short name.
     * Commands live in a namespace, so every class name has to be imported.
     *
     * @return array<int, string>
     */
    private function imports(string $code, bool $importEverything): array
    {
        $this->unresolved = [];
        $imports = [];

        foreach ($this->classNames($code) as $name) {
            $fqn = $this->aliases[$name] ?? ($importEverything ? $this->resolveDeclared($name) : null);

            if ($fqn === null) {
                if ($importEverything) {
                    $this->unresolved[] = $name;
                }
                continue;
            }

            $imports[] = class_basename($fqn) === $name ? $fqn : "{$fqn} as {$name}";
        }

        return $imports;
    }

    private function resolveDeclared(string $name): ?string
    {
        if (! class_exists($name) && ! interface_exists($name) && ! trait_exists($name) && ! enum_exists($name)) {
            return null;
        }

        return (new ReflectionClass($name))->getName();
    }

    /**
     * Unqualified class names used in static calls, `new`, `instanceof`, `::class`, catch blocks and type hints.
     *
     * @return array<int, string>
     */
    private function classNames(string $code): array
    {
        $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse("<?php\n{$code}") ?? [];
        $names = [];

        foreach ((new NodeFinder())->find($statements, fn (Node $node) => true) as $node) {
            foreach ($this->classNameNodes($node) as $name) {
                if ($name instanceof Name && $name->isUnqualified() && ! $name->isSpecialClassName()) {
                    $names[$name->toString()] = true;
                }
            }
        }

        return array_keys($names);
    }

    /**
     * @return array<int, mixed>
     */
    private function classNameNodes(Node $node): array
    {
        return match (true) {
            $node instanceof Node\Expr\StaticCall,
            $node instanceof Node\Expr\New_,
            $node instanceof Node\Expr\Instanceof_,
            $node instanceof Node\Expr\ClassConstFetch,
            $node instanceof Node\Expr\StaticPropertyFetch => [$node->class],
            $node instanceof Node\Stmt\Catch_ => $node->types,
            $node instanceof Node\Param => [$node->type],
            $node instanceof Node\Expr\Closure,
            $node instanceof Node\Expr\ArrowFunction,
            $node instanceof Node\Stmt\Function_ => [$node->returnType],
            $node instanceof Node\NullableType => [$node->type],
            $node instanceof Node\UnionType,
            $node instanceof Node\IntersectionType => $node->types,
            default => [],
        };
    }
}
