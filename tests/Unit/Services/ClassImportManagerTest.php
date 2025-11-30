<?php

declare(strict_types=1);

use drahil\Tailor\Services\ClassImportManager;

test('generates simple class_alias statement for unique class name', function () {
    $manager = new ClassImportManager();

    $classes = [
        'App\\Models\\User' => '/path/to/User.php',
    ];

    $statements = $manager->generateUseStatements($classes);

    expect($statements)->toHaveCount(1);
    expect($statements[0])->toBe("class_alias('App\\Models\\User', 'User');");
});

test('generates aliased class_alias statements for conflicting class names', function () {
    $manager = new ClassImportManager();

    $classes = [
        'App\\Models\\User' => '/path/to/models/User.php',
        'App\\Admin\\User' => '/path/to/admin/User.php',
    ];

    $statements = $manager->generateUseStatements($classes);

    expect($statements)->toHaveCount(2);
    expect($statements)->toContain("class_alias('App\\Models\\User', 'ModelsUser');");
    expect($statements)->toContain("class_alias('App\\Admin\\User', 'AdminUser');");
});

test('generates correct alias from namespace', function () {
    $manager = new ClassImportManager();

    $classes = [
        'App\\Http\\Controllers\\UserController' => '/path/to/UserController.php',
        'App\\Api\\Controllers\\UserController' => '/path/to/api/UserController.php',
    ];

    $statements = $manager->generateUseStatements($classes);

    expect($statements)->toHaveCount(2);
    expect($statements)->toContain("class_alias('App\\Http\\Controllers\\UserController', 'HttpControllersUserController');");
    expect($statements)->toContain("class_alias('App\\Api\\Controllers\\UserController', 'ApiControllersUserController');");
});

test('handles mix of unique and conflicting names', function () {
    $manager = new ClassImportManager();

    $classes = [
        'App\\Models\\User' => '/path/to/User.php',
        'App\\Models\\Post' => '/path/to/Post.php',
        'App\\Admin\\User' => '/path/to/admin/User.php',
    ];

    $statements = $manager->generateUseStatements($classes);

    expect($statements)->toHaveCount(3);
    expect($statements)->toContain("class_alias('App\\Models\\Post', 'Post');");
    expect($statements)->toContain("class_alias('App\\Models\\User', 'ModelsUser');");
    expect($statements)->toContain("class_alias('App\\Admin\\User', 'AdminUser');");
});
