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

test('save writes the session without leaving temporary files behind', function () {
    expect(glob($this->storagePath . '/*'))->toBe([$this->storagePath . '/work.json'])
        ->and($this->manager->load('work')->getCommandCount())->toBe(2);
});

test('append adds commands after the existing ones', function () {
    $sessionData = $this->manager->append(SessionName::from('work'), ['$c = 3', '$a + $c']);

    expect(array_column($sessionData->commands, 'code'))->toBe(['$a = 1', '$b = 2', '$c = 3', '$a + $c'])
        ->and(array_column($sessionData->commands, 'order'))->toBe([1, 2, 3, 4])
        ->and($sessionData->sessionMetadata['total_commands'])->toBe(4);
});

test('append persists and keeps description, tags and creation date', function () {
    $original = $this->manager->load('work');

    $this->manager->append(SessionName::from('work'), ['$c = 3']);
    $reloaded = $this->manager->load('work');

    expect($reloaded->getCommandCount())->toBe(3)
        ->and($reloaded->metadata->description?->toString())->toBe('Original work')
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
    rename($this->storagePath . '/work.json', $this->storagePath . '/MyWork.json');

    expect($this->manager->exists('mywork'))->toBeTrue()
        ->and($this->manager->load('MYWORK')->getCommandCount())->toBe(2);
});

test('list filters by tags case-insensitively', function () {
    $data = json_decode(file_get_contents($this->storagePath . '/work.json'), true);
    $data['tags'] = ['API'];
    file_put_contents($this->storagePath . '/work.json', json_encode($data));

    expect(array_column($this->manager->list(['api']), 'name'))->toBe(['work'])
        ->and($this->manager->list(['other']))->toBe([]);
});

function writeSessionFile(string $storagePath, string $contents): void
{
    file_put_contents($storagePath . '/work.json', $contents);
}

test('reports damaged session files clearly', function (string $contents, string $reason) {
    writeSessionFile($this->storagePath, $contents);

    expect(fn () => $this->manager->load('work'))
        ->toThrow(RuntimeException::class, "Session 'work' is damaged ({$reason})");
})->with([
    'invalid json' => ['{"name": ', 'Syntax error'],
    'json null' => ['null', 'not a JSON object'],
    'commands not a list' => ['{"commands": "x"}', 'invalid commands'],
    'command without code' => ['{"commands": [{"output": null}]}', 'invalid commands'],
]);

test('reports wrongly typed fields as damaged', function () {
    writeSessionFile($this->storagePath, '{"tags": "api", "commands": []}');

    expect(fn () => $this->manager->load('work'))
        ->toThrow(RuntimeException::class, "Session 'work' is damaged");
});

test('uses the file name when the stored name is missing or different', function () {
    writeSessionFile($this->storagePath, '{"name": "other", "commands": [{"code": "$a = 1"}]}');

    expect($this->manager->load('work')->metadata->name->toString())->toBe('work');

    writeSessionFile($this->storagePath, '{"commands": [{"code": "$a = 1"}]}');

    expect($this->manager->load('work')->getCommandCount())->toBe(1);
});

test('save replaces a damaged session file', function () {
    writeSessionFile($this->storagePath, 'null');

    $tracker = new SessionTracker();
    $tracker->addCommand('$fresh = true');

    $this->manager->save(new SessionMetadata(name: SessionName::from('work')), $tracker);

    expect(array_column($this->manager->load('work')->commands, 'code'))->toBe(['$fresh = true']);
});
