<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\SessionCommands\SessionEditCommand;
use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;
use Psy\Shell;
use Symfony\Component\Console\Input\InputDefinition;

beforeEach(function () {
    $this->sessionManager = mockSessionManager();

    $this->command = new SessionEditCommand();

    $definition = Mockery::mock(InputDefinition::class)->shouldIgnoreMissing();
    $app = Mockery::mock(Shell::class)->shouldIgnoreMissing();
    $app->shouldReceive('getDefinition')->andReturn($definition);
    $app->shouldReceive('getScopeVariable')
        ->with('__sessionManager')
        ->andReturn($this->sessionManager);

    $this->command->setApplication($app);
    $this->tester = createCommandTester($this->command);
});

afterEach(function () {
    Mockery::close();
});

test('updates session description', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
            description: SessionDescription::from('Old description'),
            tags: ['api']
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $updatedData = null;
    $this->sessionManager
        ->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($data) use (&$updatedData) {
            $updatedData = $data;
            return true;
        }));

    $this->tester->execute([
        'name' => 'test-session',
        '--description' => 'New description',
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
    expect($this->tester->getDisplay())->toContain('Session updated successfully');
    expect($updatedData->metadata->description->toString())->toBe('New description');
    expect($updatedData->metadata->tags)->toBe(['api']);
});

test('adds tags to session', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
            tags: ['api']
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $updatedData = null;
    $this->sessionManager
        ->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($data) use (&$updatedData) {
            $updatedData = $data;
            return true;
        }));

    $this->tester->execute([
        'name' => 'test-session',
        '--add-tag' => ['debug', 'testing'],
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
    expect($updatedData->metadata->tags)->toHaveCount(3)
        ->and($updatedData->metadata->tags)->toContain('api')
        ->and($updatedData->metadata->tags)->toContain('debug')
        ->and($updatedData->metadata->tags)->toContain('testing');
});

test('removes tags from session', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
            tags: ['api', 'debug', 'testing']
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $updatedData = null;
    $this->sessionManager
        ->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($data) use (&$updatedData) {
            $updatedData = $data;
            return true;
        }));

    $this->tester->execute([
        'name' => 'test-session',
        '--remove-tag' => ['debug', 'testing'],
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
    expect($updatedData->metadata->tags)->toBe(['api']);
});

test('replaces all tags with set-tags option', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
            tags: ['old1', 'old2']
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $this->sessionManager
        ->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data->metadata->tags === ['new1', 'new2'];
        }));

    $this->tester->execute([
        'name' => 'test-session',
        '--set-tags' => ['new1', 'new2'],
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('set-tags takes precedence over add-tag and remove-tag', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
            tags: ['original']
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $this->sessionManager
        ->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data->metadata->tags === ['final'];
        }));

    $this->tester->execute([
        'name' => 'test-session',
        '--add-tag' => ['ignored'],
        '--remove-tag' => ['ignored-too'],
        '--set-tags' => ['final'],
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('updates both description and tags', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
            description: SessionDescription::from('Old'),
            tags: ['old']
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $updatedData = null;
    $this->sessionManager
        ->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($data) use (&$updatedData) {
            $updatedData = $data;
            return true;
        }));

    $this->tester->execute([
        'name' => 'test-session',
        '--description' => 'New description',
        '--add-tag' => ['new'],
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
    expect($updatedData->metadata->description->toString())->toBe('New description');
    expect($updatedData->metadata->tags)->toContain('old')->toContain('new');
});

test('fails with error when session does not exist', function () {
    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(false);

    $this->tester->execute([
        'name' => 'non-existent',
        '--description' => 'New description',
    ]);

    expect($this->tester->getStatusCode())->toBe(1);
    expect($this->tester->getDisplay())->toContain('does not exist');
});

test('fails with error when session name is invalid', function () {
    $this->tester->execute([
        'name' => 'invalid name!',
        '--description' => 'New description',
    ]);

    expect($this->tester->getStatusCode())->toBe(1);
});

test('fails with error when tag is invalid', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session')
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $this->tester->execute([
        'name' => 'test-session',
        '--add-tag' => ['invalid tag'],
    ]);

    expect($this->tester->getStatusCode())->toBe(1);
});

test('shows message when no changes specified', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session')
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $this->tester->execute([
        'name' => 'test-session',
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
    expect($this->tester->getDisplay())->toContain('No changes specified');
});

test('preserves created_at timestamp when updating', function () {
    $createdAt = new DateTime('2023-01-01 10:00:00');

    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
            tags: ['old'],
            createdAt: $createdAt
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $this->sessionManager
        ->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($data) use ($createdAt) {
            return $data->metadata->createdAt === $createdAt;
        }));

    $this->tester->execute([
        'name' => 'test-session',
        '--add-tag' => ['new'],
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
});

test('removes duplicates when adding tags', function () {
    $sessionData = new SessionData(
        metadata: new SessionMetadata(
            name: SessionName::from('test-session'),
            tags: ['api']
        ),
        commands: [['code' => 'test']],
        variables: [],
        sessionMetadata: ['total_commands' => 1, 'duration_seconds' => 0.0, 'project_path' => '', 'started_at' => null]
    );

    $this->sessionManager
        ->shouldReceive('exists')
        ->andReturn(true);

    $this->sessionManager
        ->shouldReceive('load')
        ->andReturn($sessionData);

    $updatedData = null;
    $this->sessionManager
        ->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($data) use (&$updatedData) {
            $updatedData = $data;
            return true;
        }));

    $this->tester->execute([
        'name' => 'test-session',
        '--add-tag' => ['api', 'debug'],
    ]);

    expect($this->tester->getStatusCode())->toBe(0);
    expect($updatedData->metadata->tags)->toHaveCount(2)
        ->and($updatedData->metadata->tags)->toContain('api')
        ->and($updatedData->metadata->tags)->toContain('debug');
});
