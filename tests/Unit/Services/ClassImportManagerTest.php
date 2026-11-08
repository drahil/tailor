<?php

declare(strict_types=1);

use drahil\Tailor\Services\ClassImportManager;

function aliasStatement(string $fqn, string $alias): string
{
    return "try { class_alias('{$fqn}', '{$alias}'); } catch (\\Throwable) {}";
}

test('generates simple class_alias statement for unique class name', function () {
    $manager = new ClassImportManager();

    $classes = [
        'App\\Models\\User' => '/path/to/User.php',
    ];

    $statements = $manager->generateAliases($classes)['statements'];

    expect($statements)->toHaveCount(1);
    expect($statements[0])->toBe(aliasStatement('App\\Models\\User', 'User'));
});

test('generates aliased class_alias statements for conflicting class names', function () {
    $manager = new ClassImportManager();

    $classes = [
        'App\\Models\\User' => '/path/to/models/User.php',
        'App\\Admin\\User' => '/path/to/admin/User.php',
    ];

    $statements = $manager->generateAliases($classes)['statements'];

    expect($statements)->toHaveCount(2);
    expect($statements)->toContain(aliasStatement('App\\Models\\User', 'ModelsUser'));
    expect($statements)->toContain(aliasStatement('App\\Admin\\User', 'AdminUser'));
});

test('generates correct alias from namespace', function () {
    $manager = new ClassImportManager();

    $classes = [
        'App\\Http\\Controllers\\UserController' => '/path/to/UserController.php',
        'App\\Api\\Controllers\\UserController' => '/path/to/api/UserController.php',
    ];

    $statements = $manager->generateAliases($classes)['statements'];

    expect($statements)->toHaveCount(2);
    expect($statements)->toContain(aliasStatement('App\\Http\\Controllers\\UserController', 'HttpControllersUserController'));
    expect($statements)->toContain(aliasStatement('App\\Api\\Controllers\\UserController', 'ApiControllersUserController'));
});

test('handles mix of unique and conflicting names', function () {
    $manager = new ClassImportManager();

    $classes = [
        'App\\Models\\User' => '/path/to/User.php',
        'App\\Models\\Post' => '/path/to/Post.php',
        'App\\Admin\\User' => '/path/to/admin/User.php',
    ];

    $statements = $manager->generateAliases($classes)['statements'];

    expect($statements)->toHaveCount(3);
    expect($statements)->toContain(aliasStatement('App\\Models\\Post', 'Post'));
    expect($statements)->toContain(aliasStatement('App\\Models\\User', 'ModelsUser'));
    expect($statements)->toContain(aliasStatement('App\\Admin\\User', 'AdminUser'));
});

test('skips aliases that are reserved, ignoring case', function () {
    $result = (new ClassImportManager())->generateAliases([
        'App\\Models\\Event' => '/path/to/Event.php',
        'App\\Support\\log' => '/path/to/log.php',
        'App\\Models\\Post' => '/path/to/Post.php',
    ], ['Event', 'Log']);

    expect($result['statements'])->toBe([aliasStatement('App\\Models\\Post', 'Post')])
        ->and($result['skipped'])->toBe([
            'App\\Models\\Event' => 'Event',
            'App\\Support\\log' => 'log',
        ]);
});

test('skips prefixed aliases that clash with another alias', function () {
    $result = (new ClassImportManager())->generateAliases([
        'App\\Models\\User' => '/path/to/User.php',
        'App\\Admin\\User' => '/path/to/admin/User.php',
        'App\\AdminUser' => '/path/to/AdminUser.php',
    ]);

    expect($result['statements'])->toBe([aliasStatement('App\\Models\\User', 'ModelsUser')])
        ->and($result['skipped'])->toBe([
            'App\\Admin\\User' => 'AdminUser',
            'App\\AdminUser' => 'AdminUser',
        ]);
});

test('generated statements are valid PHP that survives a failing class', function () {
    $statements = (new ClassImportManager())->generateAliases([
        'App\\Missing\\Ghost' => '/path/to/Ghost.php',
    ])['statements'];

    set_error_handler(fn (int $severity, string $message) => throw new ErrorException($message, 0, $severity));

    try {
        eval(implode("\n", $statements));
    } finally {
        restore_error_handler();
    }

    expect(class_exists('Ghost', false))->toBeFalse();
});
