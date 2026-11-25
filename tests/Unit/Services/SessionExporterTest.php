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

test('adds missing semicolons to commands saved without them', function () {
    expect(exporter()->export(exportSession(['$a = 1']), 'App'))->toContain("        \$a = 1;\n");
});

test('exports an Artisan command that asks in production and declares --force', function () {
    $command = exporter()->export(exportSession(['Post::count();']), 'App\\Console\\Commands');

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

test('imports global classes and facade aliases', function () {
    class_alias(\Illuminate\Support\Str::class, 'ExportTestStr');

    $command = exporter()->export(exportSession(['throw new Exception(ExportTestStr::upper("x"));']), 'App\\Console\\Commands');

    expect($command)->toContain("use Exception;\n")
        ->toContain("use Illuminate\\Support\\Str as ExportTestStr;\n");
});

test('collects class names from type hints, catch blocks and ::class', function () {
    $command = exporter()->export(exportSession([
        'try { $f = fn (?ArrayObject $o): Countable => $o; } catch (RuntimeException $e) {}',
        '$c = Post::class;',
    ]), 'App');

    expect($command)->toContain("use ArrayObject;\n")
        ->toContain("use Countable;\n")
        ->toContain("use RuntimeException;\n")
        ->toContain(' as Post;');
});

test('reports class names it cannot resolve in commands', function () {
    $exporter = exporter();
    $exporter->export(exportSession(['NoSuchThing::run();', 'strlen("x");']), 'App');

    expect($exporter->getUnresolved())->toBe(['NoSuchThing']);
});

test('uses the session description in the command', function () {
    $command = exporter()->export(exportSession(['$a = 1;'], "Count the author's posts"), 'App');

    expect($command)->toContain("protected \$description = 'Count the author\\'s posts';")
        ->and(parsesAsPhp($command))->toBeTrue();
});

test('builds class and file names from the session name', function () {
    expect(SessionExporter::className('blog-stats'))->toBe('BlogStats')
        ->and(SessionExporter::className('2024_report'))->toBe('Session2024Report')
        ->and(SessionExporter::fileName('blog-stats'))->toBe('BlogStats.php');
});

test('works out the namespace of a folder from the PSR-4 map', function () {
    $root = sys_get_temp_dir() . '/tailor-psr4-' . uniqid();
    mkdir("{$root}/app/Console", 0755, true);
    mkdir("{$root}/src", 0755, true);
    $psr4 = ['App\\' => ["{$root}/app"], 'App\\Console\\Special\\' => ["{$root}/src"]];

    expect(SessionExporter::namespaceFor("{$root}/app/Console/Commands/Tailor", $psr4))->toBe('App\\Console\\Commands\\Tailor')
        ->and(SessionExporter::namespaceFor("{$root}/app/", $psr4))->toBe('App')
        ->and(SessionExporter::namespaceFor("{$root}/src/Reports", $psr4))->toBe('App\\Console\\Special\\Reports')
        ->and(SessionExporter::namespaceFor("{$root}/app/my-scripts", $psr4))->toBeNull()
        ->and(SessionExporter::namespaceFor("{$root}/application", $psr4))->toBeNull()
        ->and(SessionExporter::namespaceFor(sys_get_temp_dir(), $psr4))->toBeNull();

    (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($root);
});

test('resolves relative folders from the working directory', function () {
    $root = sys_get_temp_dir() . '/tailor-psr4-' . uniqid();
    mkdir("{$root}/app", 0755, true);
    $cwd = getcwd();
    chdir($root);

    try {
        expect(SessionExporter::namespaceFor('app/Console/Commands', ['App\\' => ["{$root}/app"]]))->toBe('App\\Console\\Commands');
    } finally {
        chdir($cwd);
        (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($root);
    }
});
