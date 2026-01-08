<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{MorphTo, BelongsTo, HasMany};
use Carbon\Carbon;
use Random\RandomException;

class Lock extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'user_id',
        'session_id',
        'strategy',
        'locked_fields',
        'locked_at',
        'expires_at',
        'lock_token',
        'ip_address',
        'user_agent',
        'metadata',
        'extended_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'locked_fields' => 'array',
            'metadata' => 'array',
            'locked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Set the table name from config.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->setTable(config('collab.tables.locks', 'model_locks'));
    }

    /**
     * Get the model that owns the lock (polymorphic).
     * 
     * Example: $lock->lockable might return a Post, Article, etc.
     */
    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who owns this lock.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('auth.providers.users.model', 'App\Models\User')
        );
    }

    /**
     * Get active sessions for this lock.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(LockSession::class);
    }

    /**
     * Check if this lock has expired.
     * 
     * Returns true if the expires_at timestamp is in the past.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if this lock is still active.
     */
    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Get the duration this lock has been held (in seconds).
     */
    public function getDuration(): float
    {
        if ($this->isActive()) {
            // How long has it been locked? now - locked_at
            return $this->locked_at->diffInSeconds(now(), false);
        }

        // Total duration of the lock: expires_at - locked_at
        return $this->locked_at->diffInSeconds($this->expires_at, false);
    }

    /**
     * Get remaining time before expiration (in seconds).
     */
    public function getRemainingTime(): float
    {
        if ($this->isExpired()) {
            return 0;
        }

        // Time remaining: expires_at - now
        return now()->diffInSeconds($this->expires_at, false);
    }

    /**
     * Extend the lock expiration.
     */
    public function extend(int $seconds): bool
    {
        return $this->update([
            'expires_at' => now()->addSeconds($seconds),
        ]);
    }

    /**
     * Generate a unique lock token.
     *
     * Used to identify and verify locks.
     * @throws RandomException
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Scope to get only active locks.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired locks.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to get locks for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Boot method - runs when model is booted.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Automatically create history when lock is deleted
        static::deleting(function (Lock $lock): void {
            if (config('collab.history.enabled', true)) {
                LockHistory::create([
                    'lockable_type' => $lock->lockable_type,
                    'lockable_id' => $lock->lockable_id,
                    'user_id' => $lock->user_id,
                    'action' => 'released',
                    'duration' => $lock->getDuration(),
                    'metadata' => [
                        'lock_token' => $lock->lock_token,
                        'strategy' => $lock->strategy,
                    ],
                ]);
            }
        });
    }
}
