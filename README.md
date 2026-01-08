# 🤝 Laravel Collab

[![Latest Version](https://img.shields.io/packagist/v/yourvendor/laravel-collab.svg?style=flat-square)](https://packagist.org/packages/yourvendor/laravel-collab)
[![Tests](https://github.com/yourvendor/laravel-collab/workflows/Tests/badge.svg)](https://github.com/yourvendor/laravel-collab/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/yourvendor/laravel-collab.svg?style=flat-square)](https://packagist.org/packages/yourvendor/laravel-collab)
[![License](https://img.shields.io/packagist/l/yourvendor/laravel-collab.svg?style=flat-square)](https://packagist.org/packages/yourvendor/laravel-collab)

Real-time collaborative editing for Laravel with intelligent locking and conflict resolution.

## ✨ Features

- 🔒 **Multiple Locking Strategies** - Pessimistic, optimistic, and hybrid approaches
- ⚡ **Simple API** - Just add a trait to your models
- 🎯 **Zero Configuration** - Works out of the box with sensible defaults
- 🛡️ **Conflict Prevention** - Automatically prevent concurrent editing conflicts
- 📊 **Lock History** - Track who locked what and when
- 🎨 **Extensible** - Easy to customize for your needs

## 📦 Installation

You can install the package via composer:

```bash
composer require yourvendor/laravel-collab
```

Install the package:

```bash
php artisan collab:install
```

This will:
- Publish configuration file
- Publish and run migrations
- Set up the necessary database tables

## 🚀 Quick Start

### 1. Add Trait to Your Model

```php
use Kevjo\LaravelCollab\Traits\HasConcurrentEditing;

class Post extends Model
{
    use HasConcurrentEditing;
}
```

### 2. Use in Your Controller

```php
public function edit(Post $post)
{
    $result = $post->acquireLock(auth()->user());
    
    if ($result->isFailed()) {
        return back()->with('error', 
            "This post is being edited by {$result->getLockedBy()->name}"
        );
    }
    
    return view('posts.edit', compact('post'));
}

public function update(Request $request, Post $post)
{
    $post->update($request->validated());
    // Lock is automatically released after update
    
    return redirect()->route('posts.index');
}
```

### 3. Check Lock Status

```php
// Check if locked
if ($post->isLocked()) {
    // Model is locked
}

// Check if locked by current user
if ($post->isLockedByUser(auth()->user())) {
    // Current user owns the lock
}

// Check if locked by another user
if ($post->isLockedByAnother(auth()->user())) {
    // Someone else has the lock
}

// Get lock owner
$owner = $post->lockOwner();

// Get lock expiration
$expiresAt = $post->lockExpiresAt();
```

## 📖 Documentation

### Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=collab-config
```

Available options in `config/collab.php`:

```php
return [
    // Default lock duration in seconds
    'lock_duration' => [
        'default' => 3600, // 1 hour
        'min' => 60,
        'max' => 86400,
    ],
    
    // Automatic behaviors
    'auto_release_after_update' => true,
    'prevent_update_if_locked' => true,
    
    // More options...
];
```

### Advanced Usage

#### Custom Lock Duration

```php
$post->acquireLock(auth()->user(), [
    'duration' => 600, // 10 minutes
]);
```

#### Extend Lock

```php
// Extend lock by default duration
$post->extendLock();

// Extend by specific duration
$post->extendLock(300); // 5 minutes
```

#### Force Release Lock

```php
// Admin can force release any lock
$post->forceReleaseLock();
```

#### Manual Lock Release

```php
$post->releaseLock(auth()->user());
```

### Artisan Commands

```bash
# Cleanup expired locks
php artisan collab:cleanup

# Install package
php artisan collab:install
```

### Events

The package fires several events you can listen to:

- `LockAcquired` - When a lock is acquired
- `LockReleased` - When a lock is released
- `LockExpired` - When a lock expires

## 🧪 Testing

```bash
composer test
```

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security related issues, please email algorythmx.id@proton.me instead of using the issue tracker.

## 👥 Credits

- [Kevin Jonathan](https://github.com/kevvjoo)

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
