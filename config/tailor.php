<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where Tailor stores sessions.
    |
    */

    'storage' => [
        'sessions' => storage_path('tailor/sessions'),
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
    | Turn Tab completion on or off.
    |
    */

    'autocomplete' => [
        'enabled' => true,
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
        'auto_save_min_commands' => 5, // Minimum commands before auto-save
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Configuration
    |--------------------------------------------------------------------------
    |
    | In production, session:execute asks for confirmation before running a
    | session unless the session has this tag.
    |
    */

    'production' => [
        'safe_tag' => 'safe-for-prod',
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Discovery Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which namespaces should be scanned for class auto-discovery.
    | These namespaces will be available for auto-import in the REPL.
    |
    */

    'discovery' => [
        'scannable_namespaces' => [
            'App\\',
        ],
    ],
];
