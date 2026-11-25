<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

/**
 * A real Tailor shell in a child process, driven line by line through its stdin.
 */
final class TailorProcess
{
    /** @var resource */
    private $process;

    /** @var array<int, resource> */
    private array $pipes = [];

    public function __construct(string $sessions)
    {
        $this->process = proc_open(
            [PHP_BINARY, __DIR__ . '/fixtures/tailor-shell.php'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $this->pipes,
            null,
            [...getenv(), 'TAILOR_SESSIONS' => $sessions, 'PSYSH_CONFIG' => __DIR__ . '/fixtures/psysh-config.php'],
        );

        stream_set_blocking($this->pipes[1], false);
        $this->waitForPrompt();
    }

    public function run(string $line): string
    {
        fwrite($this->pipes[0], $line . "\n");

        return $this->waitForPrompt();
    }

    public function close(): void
    {
        fwrite($this->pipes[0], "exit\n");
        fclose($this->pipes[0]);
        stream_set_blocking($this->pipes[1], true);
        stream_get_contents($this->pipes[1]);
        proc_close($this->process);
    }

    private function waitForPrompt(): string
    {
        $output = '';
        $deadline = microtime(true) + 10;

        while (! str_ends_with(rtrim($output, ' '), '>') && microtime(true) < $deadline) {
            $read = [$this->pipes[1]];
            $write = $except = null;

            if (stream_select($read, $write, $except, 0, 100_000) > 0) {
                $output .= (string) fread($this->pipes[1], 65536);
            }
        }

        if (! str_ends_with(rtrim($output, ' '), '>')) {
            throw new RuntimeException("Tailor did not show a prompt. Output so far:\n{$output}" . stream_get_contents($this->pipes[2]));
        }

        return $output;
    }
}

beforeEach(function () {
    $this->sessions = sys_get_temp_dir() . '/tailor-feature-' . uniqid();
    mkdir($this->sessions);
});

afterEach(function () {
    (new Filesystem())->deleteDirectory($this->sessions);
});

function sessionCode(string $directory, string $name): string
{
    return trim(preg_replace('#^<\?php\s*/\*\*.*?\*/#s', '', file_get_contents("{$directory}/{$name}.php")));
}

test('two shells open at once each save only their own commands', function () {
    $first = new TailorProcess($this->sessions);
    $second = new TailorProcess($this->sessions);

    $first->run('$a = 1');
    $second->run('$b = 2');
    $first->run('ls');
    $second->run('doc strlen');
    $first->run('$c = $a + 1');
    $second->run('$d = $b * 2');
    $first->run('session:save first');
    $second->run('session:save second');

    $first->close();
    $second->close();

    expect(sessionCode($this->sessions, 'first'))->toBe("\$a = 1;\n\$c = \$a + 1;")
        ->and(sessionCode($this->sessions, 'second'))->toBe("\$b = 2;\n\$d = \$b * 2;");
});

test('appends only the commands typed after session:execute', function () {
    $shell = new TailorProcess($this->sessions);

    $shell->run('$a = 2');
    $shell->run('session:save work');
    $shell->run('session:execute work');
    $shell->run('$b = $a * 3');
    $output = $shell->run('session:append');
    $shell->close();

    expect($output)->toContain("Appended 1 command(s) to 'work' (2 total)")
        ->and(sessionCode($this->sessions, 'work'))->toBe("\$a = 2;\n\$b = \$a * 3;");
});

test('session:execute stops at the first failing command', function () {
    file_put_contents("{$this->sessions}/broken.php", "<?php\n\n\$n = null;\n\$n->delete();\n\$after = 'ran';\n");
    $shell = new TailorProcess($this->sessions);

    $output = $shell->run('session:execute broken');
    $check = $shell->run('isset($after) ? "ran" : "not run"');
    $shell->close();

    expect($output)->toContain('Failed to execute: Call to a member function delete() on null')
        ->toContain('Stopped after 2 of 3 command(s); 1 not run.')
        ->and($check)->toContain('not run');
});
