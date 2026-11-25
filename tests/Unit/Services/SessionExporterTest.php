<?php

declare(strict_types=1);

namespace drahil\Tailor\Tests\Unit\Services;

use drahil\Tailor\Services\SessionExporter;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;
use PhpParser\ParserFactory;

class ExportTestPost {}

function exportSession(array $commands, ?string $description = null): SessionData
{
    return new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('blog-stats'),
            description: SessionDescription::fromNullable($description),
        ),
        commands: $commands,
    );
}

function exporter(): SessionExporter
{
    return new SessionExporter(['Post' => ExportTestPost::class, 'ModelsUser' => 'App\\Models\\User']);
}

function parsesAsPhp(string $code): bool
{
    (new ParserFactory())->createForNewestSupportedVersion()->parse($code);

    return true;
}

test('exports a script that imports Tailor aliases and leaves the code unchanged', function () {
    $script = exporter()->export(exportSession([
        'Post::count();',
        '$user = new ModelsUser();',
        'DB::table("posts")->count();',
    ], 'Quick numbers'), SessionExporter::SCRIPT);

    expect($script)->toBe(<<<'PHP'
    <?php

    use App\Models\User as ModelsUser;
    use drahil\Tailor\Tests\Unit\Services\ExportTestPost as Post;

    // Quick numbers

    Post::count();
    $user = new ModelsUser();
    DB::table("posts")->count();

    PHP)
        ->and(parsesAsPhp($script))->toBeTrue();
});

test('adds missing semicolons to commands saved without them', function () {
    expect(exporter()->export(exportSession(['$a = 1']), SessionExporter::SCRIPT))->toContain("\$a = 1;\n");
});

test('exports an Artisan command that asks in production and declares --force', function () {
    $command = exporter()->export(exportSession(['Post::count();']), SessionExporter::COMMAND, 'App\\Console\\Commands');

    expect($command)
        ->toContain('namespace App\\Console\\Commands;')
        ->toContain('class BlogStats extends Command')
        ->toContain('use ConfirmableTrait;')
        ->toContain("protected \$signature = 'tailor:blog-stats {--force : Run in production without confirmation}';")
        ->toContain("if (! \$this->confirmToProceed()) {\n            return self::FAILURE;\n        }")
        ->toContain("        Post::count();\n")
        ->toContain('use drahil\\Tailor\\Tests\\Unit\\Services\\ExportTestPost as Post;')
        ->and(parsesAsPhp($command))->toBeTrue();
});

test('imports global classes and facade aliases in commands, but not in scripts', function () {
    class_alias(\Illuminate\Support\Str::class, 'ExportTestStr');
    $session = exportSession(['throw new Exception(ExportTestStr::upper("x"));']);

    $command = exporter()->export($session, SessionExporter::COMMAND, 'App\\Console\\Commands');
    $script = exporter()->export($session, SessionExporter::SCRIPT);

    expect($command)->toContain("use Exception;\n")
        ->toContain("use Illuminate\\Support\\Str as ExportTestStr;\n")
        ->and($script)->not->toContain('use ');
});

test('collects class names from type hints, catch blocks and ::class', function () {
    $command = exporter()->export(exportSession([
        'try { $f = fn (?ArrayObject $o): Countable => $o; } catch (RuntimeException $e) {}',
        '$c = Post::class;',
    ]), SessionExporter::COMMAND, 'App');

    expect($command)->toContain("use ArrayObject;\n")
        ->toContain("use Countable;\n")
        ->toContain("use RuntimeException;\n")
        ->toContain(' as Post;');
});

test('reports class names it cannot resolve in commands', function () {
    $exporter = exporter();
    $exporter->export(exportSession(['NoSuchThing::run();', 'strlen("x");']), SessionExporter::COMMAND, 'App');

    expect($exporter->getUnresolved())->toBe(['NoSuchThing']);
});

test('uses the session description in the command', function () {
    $command = exporter()->export(exportSession(['$a = 1;'], "Count the author's posts"), SessionExporter::COMMAND, 'App');

    expect($command)->toContain("protected \$description = 'Count the author\\'s posts';")
        ->and(parsesAsPhp($command))->toBeTrue();
});

test('builds class and file names from the session name', function () {
    expect(SessionExporter::className('blog-stats'))->toBe('BlogStats')
        ->and(SessionExporter::className('2024_report'))->toBe('Session2024Report')
        ->and(SessionExporter::fileName('blog-stats', SessionExporter::SCRIPT))->toBe('blog-stats.php')
        ->and(SessionExporter::fileName('blog-stats', SessionExporter::COMMAND))->toBe('BlogStats.php');
});
