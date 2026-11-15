<?php

declare(strict_types=1);

use drahil\Tailor\Services\SessionManager;
use drahil\Tailor\Services\SessionTracker;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;

beforeEach(function () {
    Facade::setFacadeApplication(['files' => new Filesystem()]);

    $this->storagePath = sys_get_temp_dir() . '/tailor-session-manager-' . uniqid();
    $this->manager = new SessionManager($this->storagePath);

    $tracker = new SessionTracker();
    $tracker->addCommand('$a = 1');
    $tracker->addCommand('$b = 2');

    $this->manager->save(new SessionMetadata(
        name: SessionName::from('work'),
        description: SessionDescription::from('Original work'),
        tags: ['api'],
    ), $tracker);
});

afterEach(function () {
    (new Filesystem())->deleteDirectory($this->storagePath);
    Facade::clearResolvedInstances();
});

function writeSessionFile(string $storagePath, string $name, string $contents): void
{
    file_put_contents("{$storagePath}/{$name}", $contents);
}

test('saves sessions as PHP files without leaving temporary files behind', function () {
    expect(glob($this->storagePath . '/*'))->toBe([$this->storagePath . '/work.php'])
        ->and(file_get_contents($this->storagePath . '/work.php'))
        ->toContain('@description Original work')
        ->toContain('@tags api')
        ->toContain("\$a = 1;\n\$b = 2;");
});

test('loads what it saved', function () {
    $session = $this->manager->load('work');

    expect($session->commands)->toBe(['$a = 1;', '$b = 2;'])
        ->and($session->metadata->description?->toString())->toBe('Original work')
        ->and($session->metadata->tags)->toBe(['api'])
        ->and($session->metadata->createdAt)->not->toBeNull();
});

test('loads a session edited by hand', function () {
    writeSessionFile($this->storagePath, 'work.php', <<<'PHP'
    <?php
    /**
     * @description Edited
     * @tags api, Reviewed
     */

    $posts = Post::query()
        ->where('views', '>', 100)
        ->get();

    // Comments are not commands
    $posts->count();
    PHP);

    $session = $this->manager->load('work');

    expect($session->commands)->toBe([
        "\$posts = Post::query()\n    ->where('views', '>', 100)\n    ->get();",
        '$posts->count();',
    ])
        ->and($session->metadata->description?->toString())->toBe('Edited')
        ->and($session->metadata->tags)->toBe(['api', 'reviewed']);
});

test('append adds commands after the existing ones', function () {
    $sessionData = $this->manager->append(SessionName::from('work'), ['$c = 3', '$a + $c']);

    expect($sessionData->commands)->toBe(['$a = 1;', '$b = 2;', '$c = 3', '$a + $c'])
        ->and($this->manager->load('work')->commands)->toBe(['$a = 1;', '$b = 2;', '$c = 3;', '$a + $c;']);
});

test('append keeps description, tags and creation date', function () {
    $original = $this->manager->load('work');

    $this->manager->append(SessionName::from('work'), ['$c = 3']);
    $reloaded = $this->manager->load('work');

    expect($reloaded->metadata->description?->toString())->toBe('Original work')
        ->and($reloaded->metadata->tags)->toBe(['api'])
        ->and($reloaded->metadata->createdAt?->format('c'))->toBe($original->metadata->createdAt?->format('c'));
});

test('append fails for a session that does not exist', function () {
    $this->manager->append(SessionName::from('missing'), ['$a = 1']);
})->throws(RuntimeException::class, "Session 'missing' does not exist.");

test('session names are case-insensitive', function () {
    expect($this->manager->exists('WORK'))->toBeTrue()
        ->and($this->manager->load('Work')->metadata->name->toString())->toBe('work');
});

test('finds sessions saved under a mixed-case file name', function () {
    rename($this->storagePath . '/work.php', $this->storagePath . '/MyWork.php');

    expect($this->manager->exists('mywork'))->toBeTrue()
        ->and($this->manager->load('MYWORK')->getCommandCount())->toBe(2);
});

test('lists sessions and filters by tags case-insensitively', function () {
    expect($this->manager->list(['API']))->toMatchArray([[
        'name' => 'work',
        'description' => 'Original work',
        'tags' => ['api'],
        'created_at' => $this->manager->load('work')->metadata->createdAt?->format('c'),
        'updated_at' => $this->manager->load('work')->metadata->updatedAt?->format('c'),
        'command_count' => 2,
    ]])
        ->and($this->manager->list(['other']))->toBe([]);
});

test('reports damaged session files clearly', function (string $contents, string $reason) {
    writeSessionFile($this->storagePath, 'work.php', $contents);

    expect(fn () => $this->manager->load('work'))
        ->toThrow(RuntimeException::class, "Session 'work' is damaged ({$reason}");
})->with([
    'invalid php' => ["<?php\n\$a = ;\n", 'Syntax error'],
    'invalid tag' => ["<?php\n/**\n * @tags not valid!\n */\n\$a = 1;\n", 'Validation failed: value: Tag must contain'],
    'invalid date' => ["<?php\n/**\n * @created yesterday-ish\n */\n\$a = 1;\n", 'Failed to parse'],
]);

test('save replaces a damaged session file', function () {
    writeSessionFile($this->storagePath, 'work.php', "<?php\n\$a = ;\n");

    $tracker = new SessionTracker();
    $tracker->addCommand('$fresh = true');

    $this->manager->save(new SessionMetadata(name: SessionName::from('work')), $tracker);

    expect($this->manager->load('work')->commands)->toBe(['$fresh = true;']);
});

test('converts legacy JSON sessions to PHP files', function () {
    writeSessionFile($this->storagePath, 'Legacy.json', json_encode([
        'name' => 'something-else',
        'description' => 'Old session',
        'tags' => ['api'],
        'created_at' => '2025-01-15T10:00:00+00:00',
        'updated_at' => '2025-01-16T10:00:00+00:00',
        'laravel_version' => '11.0.0',
        'commands' => [
            ['code' => '_HiStOrY_V2_'],
            ['code' => '$a\\040=\\0401', 'output' => null],
            ['code' => 'Post::count()', 'output' => null],
        ],
        'metadata' => ['project_path' => '/somewhere'],
    ]));

    $manager = new SessionManager($this->storagePath);
    $session = $manager->load('legacy');

    expect(file_exists($this->storagePath . '/Legacy.json'))->toBeFalse()
        ->and($session->commands)->toBe(['$a = 1;', 'Post::count();'])
        ->and($session->metadata->description?->toString())->toBe('Old session')
        ->and($session->metadata->createdAt?->format('c'))->toBe('2025-01-15T10:00:00+00:00')
        ->and(file_get_contents($this->storagePath . '/legacy.php'))->not->toContain('11.0.0')->not->toContain('/somewhere');
});

test('leaves legacy JSON sessions that cannot be converted', function () {
    writeSessionFile($this->storagePath, 'broken.json', 'null');

    new SessionManager($this->storagePath);

    expect(file_exists($this->storagePath . '/broken.json'))->toBeTrue()
        ->and(file_exists($this->storagePath . '/broken.php'))->toBeFalse();
});
