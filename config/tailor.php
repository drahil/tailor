<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where Tailor stores sessions and exported code.
    |
    */

    'storage' => [
        'sessions' => storage_path('tailor/sessions'),
        'exports' => storage_path('tailor/exports'),
    ],

    /*
    |--------------------------------------------------------------------------
    | History Configuration
    |--------------------------------------------------------------------------
    |
    | Configure command history behavior.
    |
    */

    'history' => [
        'limit' => 1000,
        'deduplicate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default export behavior.
    |
    */

    'export' => [
        'default_format' => 'php',
        'namespace' => 'App\\Scripts',
        'open_after_export' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Autocomplete Configuration
    |--------------------------------------------------------------------------
    |
    | Configure autocomplete behavior.
    |
    */

    'autocomplete' => [
        'enabled' => true,
        'cache_models' => true,
        'cache_ttl' => 3600, // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configure session behavior.
    |
    */

    'session' => [
        'auto_save' => false,
        'auto_save_interval' => 300, // 5 minutes
    ],
];
