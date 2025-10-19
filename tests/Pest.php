<?php

declare(strict_types=1);

use drahil\Tailor\Support\SessionManager;
use drahil\Tailor\Support\SessionTracker;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Tester\CommandTester;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses()->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a mock SessionManager for testing.
 */
function mockSessionManager(): SessionManager
{
    return Mockery::mock(SessionManager::class);
}

/**
 * Create a mock SessionTracker for testing.
 */
function mockSessionTracker(): SessionTracker
{
    return Mockery::mock(SessionTracker::class);
}

/**
 * Create a CommandTester for a given command.
 */
function createCommandTester($command): CommandTester
{
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('getHelperSet')
        ->andReturn(Mockery::mock(HelperSet::class));
    $application->shouldReceive('add')
        ->with($command)
        ->andReturn($command);

    $application->add($command);

    return new CommandTester($command);
}
