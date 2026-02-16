<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Lock Strategy
    |--------------------------------------------------------------------------
    |
    | This defines how locks behave by default.
    | - 'pessimistic': Time-based locks (lock before editing)
    | - 'optimistic': Version-based (detect conflicts on save)
    | - 'hybrid': Combination of both
    |
    */
    'default_strategy' => env('COLLAB_STRATEGY', 'pessimistic'),

    /*
    |--------------------------------------------------------------------------
    | Lock Duration Settings
    |--------------------------------------------------------------------------
    |
    | How long locks remain active before expiring
    |
    */
    'lock_duration' => [
        'default' => 3600,  // 1 hour in seconds
        'min' => 60,        // Minimum 1 minute
        'max' => 86400,     // Maximum 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Behaviors
    |--------------------------------------------------------------------------
    |
    | Control automatic package behavior
    |
    */
    
    // Automatically release lock when model is updated
    'auto_release_after_update' => true,

    // Prevent updates if model is locked by another user
    'prevent_update_if_locked' => true,

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Customize table names if needed
    |
    */
    'tables' => [
        'locks' => 'model_locks',
        'sessions' => 'model_lock_sessions',
        'history' => 'model_lock_history',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lock History
    |--------------------------------------------------------------------------
    |
    | Track lock acquisition/release history
    |
    */
    'history' => [
        'enabled' => true,
        'retention_days' => 30, // Auto-delete old history
    ],
];
