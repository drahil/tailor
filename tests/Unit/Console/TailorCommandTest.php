<?php

declare(strict_types=1);

use drahil\Tailor\Console\Commands\TailorCommand;
use drahil\Tailor\Services\ClassAutoImporter;
use drahil\Tailor\Services\IncludeFileManager;
use drahil\Tailor\Services\TailorShellBuilder;
use drahil\Tailor\Support\DTOs\ImportResult;
use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->autoImporter = Mockery::mock(ClassAutoImporter::class);
    $this->fileManager = Mockery::mock(IncludeFileManager::class);
    $this->shellBuilder = Mockery::mock(TailorShellBuilder::class);
    $this->shell = Mockery::mock(Shell::class);

    $this->shellBuilder->shouldReceive('build')->andReturn($this->shell);

    $this->tester = new CommandTester(new TailorCommand($this->autoImporter, $this->fileManager, $this->shellBuilder));
});

afterEach(function () {
    Mockery::close();
});

test('removes the include file after the shell exits', function () {
    $this->autoImporter->shouldReceive('prepare')->andReturn(ImportResult::success('/tmp/imports'));
    $this->shell->shouldReceive('run')->once();
    $this->fileManager->shouldReceive('cleanup')->once()->with('/tmp/imports');

    expect($this->tester->execute([]))->toBe(0);
});

test('removes the include file when the shell crashes', function () {
    $this->autoImporter->shouldReceive('prepare')->andReturn(ImportResult::success('/tmp/imports'));
    $this->shell->shouldReceive('run')->andThrow(new RuntimeException('boom'));
    $this->fileManager->shouldReceive('cleanup')->once()->with('/tmp/imports');

    expect(fn () => $this->tester->execute([]))->toThrow(RuntimeException::class, 'boom');
});

test('starts the shell without imports', function () {
    $this->autoImporter->shouldReceive('prepare')->andReturn(ImportResult::empty());
    $this->shell->shouldReceive('run')->once();
    $this->fileManager->shouldNotReceive('cleanup');

    expect($this->tester->execute([]))->toBe(0);
});
