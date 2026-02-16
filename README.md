# Laravel Collab

[![Latest Version](https://img.shields.io/packagist/v/kevjo/laravel-collab.svg?style=flat-square)](https://packagist.org/packages/kevjo/laravel-collab)
[![Tests](https://github.com/kevvjoo/laravel-collab/workflows/Tests/badge.svg)](https://github.com/kevvjoo/laravel-collab/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/kevjo/laravel-collab.svg?style=flat-square)](https://packagist.org/packages/kevjo/laravel-collab)
[![License](https://img.shields.io/packagist/l/kevjo/laravel-collab.svg?style=flat-square)](https://packagist.org/packages/kevjo/laravel-collab)

Pessimistic locking for Laravel Eloquent models. Prevent multiple users from editing the same record simultaneously.

## Requirements

- PHP 8.5+
- Laravel 12+
- MySQL 8+ or PostgreSQL (required for row-level locking via `lockForUpdate()`)

> **Note:** SQLite works for development/testing but does not support row-level locking. Race condition protection requires MySQL or PostgreSQL in production.

## Installation

```bash
composer require kevjo/laravel-collab
```

Run the install command:

```bash
php artisan collab:install
```

This publishes the config file, migrations, and runs the migrations.

## Quick Start

### 1. Add the Trait to Your Model

```php
use Kevjo\LaravelCollab\Traits\HasConcurrentEditing;

class Post extends Model
{
    use HasConcurrentEditing;
}
```

### 2. Acquire and Release Locks

```php
// Acquire a lock
$result = $post->acquireLock(auth()->user());

if ($result->isFailed()) {
    return back()->with('error',
        "This post is being edited by {$result->getLockedBy()->name}"
    );
}

// Release a lock
$post->releaseLock(auth()->user());

// Lock is also auto-released after model update (configurable)
$post->update($request->validated());
```

### 3. Check Lock Status

```php
$post->isLocked();                          // Is it locked by anyone?
$post->isLockedByUser(auth()->user());      // Is it locked by me?
$post->isLockedByAnother(auth()->user());   // Is it locked by someone else?
$post->lockOwner();                         // Get the User who holds the lock
$post->lockExpiresAt();                     // Carbon instance of expiration
$post->lockRemainingTime();                 // Seconds until expiration
```

## Middleware

The package provides a `collab.lock` middleware that returns HTTP 423 (Locked) when a route-bound model is locked by another user.

```php
// Specify which route parameter to check
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('collab.lock:post');

// Auto-detect all lockable models on the route
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('collab.lock');
```

The middleware:
- Returns **423 Locked** with lock info if the model is locked by another user
- Passes through if the model is unlocked or locked by the current user
- Skips the check entirely if no user is authenticated

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=collab-config
```

```php
// config/collab.php
return [
    'default_strategy' => 'pessimistic',

    'lock_duration' => [
        'default' => 3600,  // 1 hour
        'min'     => 60,    // 1 minute minimum
        'max'     => 86400, // 24 hours maximum
    ],

    'auto_release_after_update' => true,  // Release lock when model is updated
    'prevent_update_if_locked'  => true,  // Throw exception if locked by another

    'tables' => [
        'locks'   => 'model_locks',
        'history' => 'model_lock_history',
    ],

    'history' => [
        'enabled'        => true,
        'retention_days' => 30,
    ],
];
```

## Lock Options

```php
// Custom duration
$post->acquireLock($user, ['duration' => 600]); // 10 minutes

// Field-level locking
$post->acquireLock($user, ['fields' => ['title', 'content']]);

// Check field-level locks
$post->isFieldLocked('title'); // true
$post->getLockedFields();      // ['title', 'content']

// Custom metadata
$post->acquireLock($user, ['metadata' => ['reason' => 'bulk update']]);
```

## Lock Management

```php
// Extend a lock
$post->extendLock(1800, $user); // 30 more minutes

// Force release (admin use)
$post->forceReleaseLock();

// Request lock from owner (fires LockRequested event)
$post->requestLock($requester);

// Get structured lock info for API responses
$post->getLockInfo();
// Returns: ['is_locked' => true, 'locked_by' => [...], 'expires_at' => '...', ...]

$post->getLockStatus($user);
// Returns: ['is_locked' => true, 'can_edit' => false, 'is_owner' => false, ...]
```

## Facade

The `Collab` facade provides system-wide lock management:

```php
use Kevjo\LaravelCollab\Facades\Collab;

// Query locks
Collab::activeLocks();
Collab::expiredLocks();
Collab::getLocksFor($post);
Collab::getActiveLockFor($post);
Collab::isModelLocked(Post::class, 1);
Collab::getLocksForModelType(Post::class);

// Bulk operations
Collab::releaseAllLocksForUser($userId);
Collab::releaseAllLocks();

// Cleanup
Collab::cleanupExpiredLocks();
Collab::cleanupOldHistory();
Collab::runCleanup();

// History
Collab::getHistoryFor($post);
Collab::getUserHistory($userId);

// Stats
Collab::getStatistics();
```

## Events

All events are in the `Kevjo\LaravelCollab\Events` namespace:

| Event | Fired When | Properties |
|-------|-----------|------------|
| `LockAcquired` | Lock is successfully acquired | `$model`, `$lock`, `$user` |
| `LockReleased` | Lock is released by owner | `$model`, `$user` |
| `LockForceReleased` | Lock is force-released (admin) | `$model`, `$lockOwner`, `$releasedBy` |
| `LockRequested` | User requests lock from owner | `$model`, `$requester`, `$lockOwner` |
| `LockExpired` | Expired lock is cleaned up | `$model`, `$lock` |

Listen to events in your `EventServiceProvider` or with closures:

```php
use Kevjo\LaravelCollab\Events\LockRequested;

Event::listen(LockRequested::class, function (LockRequested $event) {
    $event->lockOwner->notify(new LockRequestNotification(
        $event->requester,
        $event->model
    ));
});
```

## Artisan Commands

```bash
# Clean up expired locks
php artisan collab:cleanup

# Clean up expired locks + old history
php artisan collab:cleanup --all

# Preview what would be deleted
php artisan collab:cleanup --dry-run

# Install the package
php artisan collab:install
```

Add to your scheduler for automatic cleanup:

```php
// app/Console/Kernel.php
$schedule->command('collab:cleanup')->hourly();
```

## Automatic Behaviors

The trait hooks into Eloquent model events:

- **Before update:** If `prevent_update_if_locked` is `true` and the model is locked by another user, a `ModelLockedException` (HTTP 423) is thrown.
- **After update:** If `auto_release_after_update` is `true`, the lock is automatically released.
- **On delete:** All locks on the model are released with history entries created.

## Testing

```bash
composer test
```

## Credits

- [Kevin Jonathan](https://github.com/kevvjoo)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
