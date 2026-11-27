<?php

declare(strict_types=1);

use drahil\Tailor\PsySH\AutoSaveLoopListener;
use drahil\Tailor\Services\AutoSaveService;
use Psy\Shell;

afterEach(function () {
    Mockery::close();
});

test('saves after a loop when auto-save is due', function () {
    $service = Mockery::mock(AutoSaveService::class);
    $service->shouldReceive('shouldAutoSave')->once()->andReturn(true);
    $service->shouldReceive('performAutoSave')->once();

    (new AutoSaveLoopListener($service))->afterLoop(Mockery::mock(Shell::class));
});

test('does not save when auto-save is not due', function () {
    $service = Mockery::mock(AutoSaveService::class);
    $service->shouldReceive('shouldAutoSave')->once()->andReturn(false);
    $service->shouldNotReceive('performAutoSave');

    (new AutoSaveLoopListener($service))->afterLoop(Mockery::mock(Shell::class));
});

test('reports a failed save once and then stops trying', function () {
    $service = Mockery::mock(AutoSaveService::class);
    $service->shouldReceive('shouldAutoSave')->once()->andReturn(true);
    $service->shouldReceive('performAutoSave')->once()->andThrow(new RuntimeException('disk full'));

    $shell = Mockery::mock(Shell::class);
    $shell->shouldReceive('writeException')
        ->once()
        ->with(Mockery::on(fn (Throwable $e) => $e->getMessage() === 'Auto-save disabled: disk full'));

    $listener = new AutoSaveLoopListener($service);
    $listener->afterLoop($shell);
    $listener->afterLoop($shell);
});
