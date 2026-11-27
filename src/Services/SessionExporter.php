<?php

declare(strict_types=1);

namespace drahil\Tailor\Services;

use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\Storage\SessionFile;
use Illuminate\Support\Str;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;

/**
 * Turns a saved session into an Artisan command class.
 */
class SessionExporter
{
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

    private const SILENT_FUNCTIONS = ['dd', 'dump', 'print_r', 'printf', 'var_dump', 'var_export'];

    public function export(SessionData $session, string $namespace): string
    {
        $commands = array_map(SessionFile::renderCommand(...), $session->commands);
        $imports = $this->imports(implode("\n", $commands));
        $shown = array_map($this->showResult(...), $commands);

        return $this->renderCommand($session, implode("\n", $shown), $imports, $namespace, $shown !== $commands);
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

    public static function fileName(string $sessionName): string
    {
        return self::className($sessionName) . '.php';
    }

    /**
     * Namespace of a folder according to Composer's PSR-4 map, or null when it is not autoloaded.
     *
     * @param array<string, array<int, string>> $psr4 Namespace prefix => folders
     */
    public static function namespaceFor(string $directory, array $psr4): ?string
    {
        $directory = self::normalizePath($directory);
        $match = null;

        foreach ($psr4 as $prefix => $folders) {
            foreach ($folders as $folder) {
                $folder = self::normalizePath($folder);

                if (($directory === $folder || str_starts_with($directory, $folder . '/'))
                    && ($match === null || strlen($folder) > strlen($match[1]))) {
                    $match = [$prefix, $folder];
                }
            }
        }

        if ($match === null) {
            return null;
        }

        $segments = array_filter(explode('/', substr($directory, strlen($match[1]))), fn (string $segment) => $segment !== '');

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                return null;
            }
        }

        return rtrim($match[0] . implode('\\', $segments), '\\');
    }

    /**
     * Absolute path with symlinks resolved, also for folders that do not exist yet.
     */
    private static function normalizePath(string $path): string
    {
        $path = str_starts_with($path, '/') ? $path : getcwd() . '/' . $path;
        $missing = [];

        while (($real = realpath($path)) === false && dirname($path) !== $path) {
            array_unshift($missing, basename($path));
            $path = dirname($path);
        }

        return rtrim(implode('/', [$real === false ? $path : $real, ...$missing]), '/');
    }

    /**
     * @param array<int, string> $imports
     */
    private function renderCommand(SessionData $session, string $code, array $imports, string $namespace, bool $shows): string
    {
        $name = $session->metadata->name->toString();
        $class = self::className($name);
        $description = str_replace(['\\', "'"], ['\\\\', "\\'"], $session->metadata->description?->toString() ?? "Run the {$name} Tailor session");
        $imports = array_merge($imports, ['Illuminate\\Console\\Command', 'Illuminate\\Console\\ConfirmableTrait']);
        $body = preg_replace('/^(?=.)/m', '        ', $code);
        $show = '';

        if ($shows) {
            $imports = array_merge($imports, [
                'Illuminate\\Contracts\\Support\\Arrayable',
                'JsonSerializable',
                'stdClass',
                'Symfony\\Component\\Console\\Formatter\\OutputFormatter',
                'Symfony\\Component\\Console\\Output\\OutputInterface',
            ]);
            $show = <<<'PHP'


                private function show(string $code, mixed $value): void
                {
                    $this->line('<comment>> ' . OutputFormatter::escape($code) . '</comment>');

                    if ($value instanceof Arrayable) {
                        $value = $value->toArray();
                    }

                    $text = is_object($value) && ! $value instanceof JsonSerializable && ! $value instanceof stdClass
                        ? $value::class
                        : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

                    $this->output->writeln((string) $text, OutputInterface::OUTPUT_RAW);
                }
            PHP;
        }

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
            }{$show}
        }

        PHP;
    }

    /**
     * Wrap the command's last statement in show() when it is an expression whose value Tailor would print.
     */
    private function showResult(string $command): string
    {
        try {
            $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse("<?php\n{$command}") ?? [];
        } catch (Error) {
            return $command;
        }

        $last = end($statements);

        if (! $last instanceof Node\Stmt\Expression || ! $this->hasPrintableValue($last->expr)) {
            return $command;
        }

        $offset = strlen("<?php\n");
        $start = $last->expr->getStartFilePos() - $offset;
        $end = $last->expr->getEndFilePos() - $offset + 1;
        $expression = substr($command, $start, $end - $start);
        $label = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) preg_replace('/\s+/', ' ', $expression));

        return substr($command, 0, $start) . "\$this->show('{$label}', {$expression})" . substr($command, $end);
    }

    private function hasPrintableValue(Node\Expr $expr): bool
    {
        if ($expr instanceof Node\Expr\FuncCall && $expr->name instanceof Name) {
            return ! in_array($expr->name->toLowerString(), self::SILENT_FUNCTIONS, true);
        }

        return ! ($expr instanceof Node\Expr\Assign
            || $expr instanceof Node\Expr\AssignOp
            || $expr instanceof Node\Expr\AssignRef
            || $expr instanceof Node\Expr\PreInc
            || $expr instanceof Node\Expr\PreDec
            || $expr instanceof Node\Expr\PostInc
            || $expr instanceof Node\Expr\PostDec
            || $expr instanceof Node\Expr\Print_
            || $expr instanceof Node\Expr\Exit_
            || $expr instanceof Node\Expr\Include_
            || $expr instanceof Node\Expr\Throw_);
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
        sort($imports, SORT_STRING | SORT_FLAG_CASE);

        return implode("\n", array_map(fn (string $import) => "use {$import};", $imports)) . "\n\n";
    }

    /**
     * Imports for the unqualified class names in the code, as the command lives in a namespace.
     *
     * @return array<int, string>
     */
    private function imports(string $code): array
    {
        $this->unresolved = [];
        $imports = [];

        foreach ($this->classNames($code) as $name) {
            $fqn = $this->aliases[$name] ?? $this->resolveDeclared($name);

            if ($fqn === null) {
                $this->unresolved[] = $name;
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
