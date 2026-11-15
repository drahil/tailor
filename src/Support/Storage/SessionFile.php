<?php

declare(strict_types=1);

namespace drahil\Tailor\Support\Storage;

use DateTime;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;
use drahil\Tailor\Support\ValueObjects\SessionTags;
use Exception;
use PhpParser\Error;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Reads and writes sessions as PHP files: a doc comment header with metadata, then one statement per command.
 */
final class SessionFile
{
    private const HEADER_KEYS = ['description', 'tags', 'created', 'updated'];

    public static function render(SessionData $session): string
    {
        $metadata = $session->metadata;
        $header = [];

        if ($metadata->description !== null) {
            $header[] = '@description ' . self::sanitizeHeaderValue($metadata->description->toString());
        }

        if ($metadata->tags !== []) {
            $header[] = '@tags ' . implode(', ', $metadata->tags);
        }

        if ($metadata->createdAt !== null) {
            $header[] = '@created ' . $metadata->createdAt->format('c');
        }

        if ($metadata->updatedAt !== null) {
            $header[] = '@updated ' . $metadata->updatedAt->format('c');
        }

        $content = "<?php\n";

        if ($header !== []) {
            $content .= "/**\n * " . implode("\n * ", $header) . "\n */\n";
        }

        $commands = array_map(self::renderCommand(...), $session->commands);

        return $content . "\n" . implode("\n", $commands) . "\n";
    }

    /**
     * @throws Error When the file is not valid PHP
     * @throws Exception When the header holds invalid metadata
     */
    public static function parse(string $source, SessionName $name): SessionData
    {
        $statements = self::parser()->parse($source) ?? [];
        $commands = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Nop || $statement instanceof InlineHTML) {
                continue;
            }

            $start = $statement->getStartFilePos();
            $commands[] = trim(substr($source, $start, $statement->getEndFilePos() - $start + 1));
        }

        return new SessionData(self::parseHeader($source, $name), $commands);
    }

    /**
     * Ensure a command is a complete statement, adding the semicolon PsySH lets you leave out.
     * Code that is not valid PHP is kept as a comment so the file stays parseable.
     */
    private static function renderCommand(string $code): string
    {
        $code = trim($code);

        foreach ([$code, $code . ';'] as $candidate) {
            try {
                self::parser()->parse('<?php ' . $candidate);

                return $candidate;
            } catch (Error) {
                continue;
            }
        }

        return '// Not valid PHP: ' . preg_replace('/\s+/', ' ', $code);
    }

    /**
     * @throws Exception
     */
    private static function parseHeader(string $source, SessionName $name): SessionMetadata
    {
        $values = [];

        foreach (token_get_all($source) as $token) {
            if (! is_array($token) || in_array($token[0], [T_OPEN_TAG, T_WHITESPACE], true)) {
                continue;
            }

            if ($token[0] === T_DOC_COMMENT) {
                preg_match_all('/^\s*\*\s*@(\w+)[ \t]*(.*?)\s*$/m', $token[1], $matches, PREG_SET_ORDER);

                foreach ($matches as [, $key, $value]) {
                    if (in_array($key, self::HEADER_KEYS, true)) {
                        $values[$key] = $value;
                    }
                }
            }

            break;
        }

        return new SessionMetadata(
            name: $name,
            description: SessionDescription::fromNullable($values['description'] ?? null),
            tags: (new SessionTags(isset($values['tags']) ? [$values['tags']] : []))->toArray(),
            createdAt: isset($values['created']) ? new DateTime($values['created']) : null,
            updatedAt: isset($values['updated']) ? new DateTime($values['updated']) : null,
        );
    }

    private static function sanitizeHeaderValue(string $value): string
    {
        return str_replace('*/', '* /', (string) preg_replace('/\s+/', ' ', trim($value)));
    }

    private static function parser(): Parser
    {
        static $parser = null;

        return $parser ??= (new ParserFactory())->createForNewestSupportedVersion();
    }
}
