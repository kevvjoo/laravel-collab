<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Traits;

use Kevjo\LaravelCollab\Models\{Lock, LockHistory};
use Kevjo\LaravelCollab\Exceptions\ModelLockedException;
use Kevjo\LaravelCollab\Support\LockResult;
use Illuminate\Support\Facades\{Auth, Request};
use Illuminate\Contracts\Auth\Authenticatable;
use Carbon\Carbon;
use Random\RandomException;

trait HasConcurrentEditing
{
    /**
     * Boot the trait.
     *
     * This automatically runs when Laravel boots the model.
     * We add hooks to prevent locked models from being updated.
     * @throws ModelLockedException
     */
    protected static function bootHasConcurrentEditing(): void
    {
        // BEFORE updating a model, check if it's locked
        static::updating(function (self $model): void {
            if (config('collab.prevent_update_if_locked', true)) {
                // If locked by someone else, throw exception
                if ($model->isLockedByAnother()) {
                    $lock = $model->getActiveLock();
                    throw new ModelLockedException(
                        "This {$model->getTable()} is currently locked by {$lock->user->name}"
                    );
                }
            }
        });

        // AFTER updating a model, optionally auto-release the lock
        static::updated(function (self $model): void {
            if (config('collab.auto_release_after_update', true)) {
                $model->releaseLock();
            }
        });

        // When model is deleted, release any locks
        static::deleting(function (self $model): void {
            $model->locks()->delete();
        });
    }

    /**
     * Get all locks for this model (relationship).
     * 
     * This creates a polymorphic relationship.
     * One Post can have many Locks.
     */
    public function locks()
    {
        return $this->morphMany(Lock::class, 'lockable');
    }

    /**
     * Get lock history for this model (relationship).
     */
    public function lockHistory()
    {
        return $this->morphMany(LockHistory::class, 'lockable');
    }

    /**
     * Acquire a lock on this model.
     *
     * HOW IT WORKS:
     * 1. Check if there's an existing active lock
     * 2. If locked by someone else, return failed result
     * 3. If locked by same user, extend the lock
     * 4. If not locked, create new lock
     *
     * @param Authenticatable|null $user
     * @param array{duration?: int, strategy?: string, fields?: array, metadata?: array} $options
     * @return LockResult
     * @throws RandomException
     */
    public function acquireLock(?Authenticatable $user = null, array $options = []): LockResult
    {
        // Get the user (use provided or current authenticated user)
        $user = $user ?? Auth::user();

        if (!$user) {
            return LockResult::failed('No authenticated user provided');
        }

        // VALIDATE DURATION FIRST - MOVE THIS TO THE TOP
        $duration = $options['duration'] ?? config('collab.lock_duration.default');
        $minDuration = config('collab.lock_duration.min', 60);
        $maxDuration = config('collab.lock_duration.max', 86400);
        $duration = max($minDuration, min($maxDuration, $duration));

        // Clean up any expired locks first
        $this->locks()->expired()->delete();

        // Check for existing active lock
        $existingLock = $this->getActiveLock();

        // If someone else has the lock, return failed
        if ($existingLock && $existingLock->user_id !== $user->id) {
            return LockResult::failed(
                'Model is already locked by another user',
                $existingLock
            );
        }

        // If same user already has lock, extend it (now using validated duration)
        if ($existingLock && $existingLock->user_id === $user->id) {
            $existingLock->update([
                'expires_at' => now()->addSeconds($duration),
                'metadata' => array_merge($existingLock->metadata ?? [], [
                    'extended_at' => now()->toIso8601String(),
                ]),
            ]);

            return LockResult::success($existingLock);
        }

        // Create new lock (duration already validated)
        $lock = $this->locks()->create([
            'user_id' => $user->id,
            'session_id' => session()->getId(),
            'strategy' => $options['strategy'] ?? config('collab.default_strategy'),
            'locked_fields' => $options['fields'] ?? null,
            'locked_at' => now(),
            'expires_at' => now()->addSeconds($duration),
            'lock_token' => Lock::generateToken(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => $options['metadata'] ?? null,
        ]);

        // Create history entry
        if (config('collab.history.enabled', true)) {
            LockHistory::create([
                'lockable_type' => get_class($this),
                'lockable_id' => $this->id,
                'user_id' => $user->id,
                'action' => 'acquired',
                'metadata' => [
                    'lock_token' => $lock->lock_token,
                    'duration' => $duration,
                ],
            ]);
        }

        return LockResult::success($lock);
    }

    /**
     * Release the lock on this model.
     */
    public function releaseLock(?Authenticatable $user = null): bool
    {
        $user = $user ?? Auth::user();
        
        $lock = $this->getActiveLock();
        
        // No active lock to release
        if (!$lock) {
            return false;
        }

        // Only the owner can release (unless forced)
        if ($user && $lock->user_id !== $user->id) {
            return false;
        }

        // Delete the lock (this triggers history creation in Lock model)
        return $lock->delete();
    }

    /**
     * Check if this model is currently locked.
     */
    public function isLocked(): bool
    {
        return $this->getActiveLock() !== null;
    }

    /**
     * Check if locked by a specific user.
     */
    public function isLockedByUser(?Authenticatable $user = null): bool
    {
        $user = $user ?? Auth::user();
        $lock = $this->getActiveLock();
        
        return $lock && $lock->user_id === $user?->id;
    }

    /**
     * Check if locked by another user (not the current user).
     */
    public function isLockedByAnother(?Authenticatable $user = null): bool
    {
        $user = $user ?? Auth::user();
        $lock = $this->getActiveLock();
        
        return $lock && $lock->user_id !== $user?->id;
    }

    /**
     * Get the currently active lock (if any).
     * 
     * This automatically cleans up expired locks.
     */
    public function getActiveLock(): ?Lock
    {
        // Clean up expired locks first
        $this->locks()->expired()->delete();
        
        // Get the active lock
        return $this->locks()
            ->with('user')  // Eager load user to avoid N+1 queries
            ->active()
            ->first();
    }

    /**
     * Get the user who currently owns the lock.
     */
    public function lockOwner(): ?Authenticatable
    {
        $lock = $this->getActiveLock();
        return $lock?->user;
    }

    /**
     * Get when the lock expires.
     */
    public function lockExpiresAt(): ?Carbon
    {
        $lock = $this->getActiveLock();
        return $lock?->expires_at;
    }

    /**
     * Get how long the lock has been held (in seconds).
     */
    public function lockDuration(): ?int
    {
        $lock = $this->getActiveLock();
        return $lock?->getDuration();
    }

    /**
     * Get remaining time before lock expires (in seconds).
     */
    public function lockRemainingTime(): float
    {
        $lock = $this->getActiveLock();
        return $lock ? $lock->getRemainingTime() : 0;
    }

    /**
     * Extend the current lock duration.
     */
    public function extendLock(?int $seconds = null, ?Authenticatable $user = null): bool
    {
        $user = $user ?? Auth::user();
        $lock = $this->getActiveLock();
        
        // No lock to extend
        if (!$lock) {
            return false;
        }

        // Only owner can extend
        if ($lock->user_id !== $user?->id) {
            return false;
        }

        $seconds = $seconds ?? config('collab.lock_duration.default');
        
        return $lock->extend($seconds);
    }

    /**
     * Force release the lock (admin function).
     * 
     * This bypasses ownership check.
     * Use carefully - typically only for admins.
     */
    public function forceReleaseLock(): bool
    {
        $lock = $this->getActiveLock();
        
        if (!$lock) {
            return false;
        }

        // Create history entry marking as forced
        if (config('collab.history.enabled', true)) {
            LockHistory::create([
                'lockable_type' => get_class($this),
                'lockable_id' => $this->id,
                'user_id' => $lock->user_id,
                'action' => 'forced_release',
                'duration' => $lock->getDuration(),
                'metadata' => [
                    'forced_by' => Auth::id(),
                    'lock_token' => $lock->lock_token,
                ],
            ]);
        }

        return $lock->delete();
    }

    /**
     * Request lock from current owner.
     * 
     * This creates a notification/event that the current owner
     * can respond to by releasing the lock.
     */
    public function requestLock(Authenticatable $requester): bool
    {
        $lock = $this->getActiveLock();
        
        if (!$lock) {
            // No lock to request
            return false;
        }

        // Can't request from yourself
        if ($lock->user_id === $requester->id) {
            return false;
        }

        // TODO: Fire event/notification to lock owner
        // event(new LockRequested($this, $requester, $lock->user));
        
        // For now, just log it in history
        if (config('collab.history.enabled', true)) {
            LockHistory::create([
                'lockable_type' => get_class($this),
                'lockable_id' => $this->id,
                'user_id' => $requester->id,
                'action' => 'requested',
                'metadata' => [
                    'requested_from' => $lock->user_id,
                    'lock_token' => $lock->lock_token,
                ],
            ]);
        }

        return true;
    }

    /**
     * Check if a specific field is locked.
     * 
     * Used for field-level locking strategy.
     */
    public function isFieldLocked(string $field): bool
    {
        $lock = $this->getActiveLock();
        
        if (!$lock) {
            return false;
        }

        // If no specific fields are locked, entire model is locked
        if (empty($lock->locked_fields)) {
            return true;
        }

        // Check if this specific field is in the locked fields array
        return in_array($field, $lock->locked_fields);
    }

    /**
     * Get all locked fields.
     */
    public function getLockedFields(): array
    {
        $lock = $this->getActiveLock();
        
        if (!$lock) {
            return [];
        }

        return $lock->locked_fields ?? [];
    }

    /**
     * Get lock information as array.
     * 
     * Useful for API responses.
     */
    public function getLockInfo(): ?array
    {
        $lock = $this->getActiveLock();
        
        if (!$lock) {
            return null;
        }

        return [
            'is_locked' => true,
            'locked_by' => [
                'id' => $lock->user->id,
                'name' => $lock->user->name,
                'email' => $lock->user->email,
            ],
            'locked_at' => $lock->locked_at->toIso8601String(),
            'expires_at' => $lock->expires_at->toIso8601String(),
            'remaining_seconds' => $lock->getRemainingTime(),
            'strategy' => $lock->strategy,
            'locked_fields' => $lock->locked_fields,
        ];
    }

    /**
     * Get lock status for current user.
     */
    public function getLockStatus(?Authenticatable $user = null): array
    {
        $user = $user ?? Auth::user();
        $lock = $this->getActiveLock();

        if (!$lock) {
            return [
                'is_locked' => false,
                'can_edit' => true,
                'is_owner' => false,
            ];
        }

        $isOwner = $lock->user_id === $user?->id;

        return [
            'is_locked' => true,
            'can_edit' => $isOwner,
            'is_owner' => $isOwner,
            'locked_by' => $lock->user->only(['id', 'name', 'email']),
            'expires_at' => $lock->expires_at->toIso8601String(),
            'remaining_seconds' => $lock->getRemainingTime(),
        ];
    }
}
