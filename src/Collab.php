<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab;

use Kevjo\LaravelCollab\Models\{Lock, LockHistory, LockSession};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Collab
{
    /**
     * Get all currently active locks.
     * 
     * USAGE:
     * $locks = Collab::activeLocks();
     * foreach ($locks as $lock) {
     *     echo "User {$lock->user->name} has locked {$lock->lockable_type}";
     * }
     */
    public function activeLocks(): Collection
    {
        return Lock::with(['user', 'lockable'])
            ->active()
            ->get();
    }

    /**
     * Get all expired locks (should be rare - auto-cleanup runs).
     */
    public function expiredLocks(): Collection
    {
        return Lock::expired()->get();
    }

    /**
     * Cleanup all expired locks.
     * 
     * This should run periodically (via scheduler or cron).
     * 
     * USAGE:
     * $count = Collab::cleanupExpiredLocks();
     * echo "Cleaned up {$count} expired locks";
     */
    public function cleanupExpiredLocks(): int
    {
        // Get expired locks before deleting (for history)
        $expiredLocks = Lock::expired()->get();

        // Create history entries for expired locks
        if (config('collab.history.enabled', true)) {
            foreach ($expiredLocks as $lock) {
                LockHistory::create([
                    'lockable_type' => $lock->lockable_type,
                    'lockable_id' => $lock->lockable_id,
                    'user_id' => $lock->user_id,
                    'action' => 'expired',
                    'duration' => $lock->getDuration(),
                    'metadata' => [
                        'lock_token' => $lock->lock_token,
                        'expired_at' => $lock->expires_at->toIso8601String(),
                    ],
                ]);
            }
        }

        // Delete expired locks
        return Lock::expired()->delete();
    }

    /**
     * Get all locks for a specific model.
     * 
     * USAGE:
     * $post = Post::find(1);
     * $locks = Collab::getLocksFor($post);
     */
    public function getLocksFor(Model $model): Collection
    {
        return Lock::where('lockable_type', get_class($model))
            ->where('lockable_id', $model->id)
            ->with('user')
            ->get();
    }

    /**
     * Get active lock for a specific model.
     */
    public function getActiveLockFor(Model $model): ?Lock
    {
        return Lock::where('lockable_type', get_class($model))
            ->where('lockable_id', $model->id)
            ->active()
            ->with('user')
            ->first();
    }

    /**
     * Release all locks for a specific user.
     * 
     * Useful when user logs out or is deactivated.
     * 
     * USAGE:
     * $count = Collab::releaseAllLocksForUser($userId);
     */
    public function releaseAllLocksForUser(int $userId): int
    {
        return Lock::where('user_id', $userId)->delete();
    }

    /**
     * Force release all locks (admin function).
     * 
     * CAUTION: This releases ALL locks in the system!
     */
    public function releaseAllLocks(): int
    {
        return Lock::query()->delete();
    }

    /**
     * Get locks for a specific model type.
     * 
     * USAGE:
     * $postLocks = Collab::getLocksForModelType('App\Models\Post');
     */
    public function getLocksForModelType(string $modelType): Collection
    {
        return Lock::where('lockable_type', $modelType)
            ->active()
            ->with(['user', 'lockable'])
            ->get();
    }

    /**
     * Get package statistics.
     * 
     * Returns useful metrics about lock usage.
     * 
     * USAGE:
     * $stats = Collab::getStatistics();
     * echo "Active locks: {$stats['total_active_locks']}";
     */
    public function getStatistics(): array
    {
        return [
            'total_active_locks' => Lock::active()->count(),
            'total_expired_locks' => Lock::expired()->count(),
            'locks_by_strategy' => Lock::active()
                ->selectRaw('strategy, count(*) as count')
                ->groupBy('strategy')
                ->pluck('count', 'strategy')
                ->toArray(),
            'locks_by_model_type' => Lock::active()
                ->selectRaw('lockable_type, count(*) as count')
                ->groupBy('lockable_type')
                ->pluck('count', 'lockable_type')
                ->toArray(),
            'most_active_users' => Lock::active()
                ->selectRaw('user_id, count(*) as count')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->with('user')
                ->get()
                ->map(fn($lock) => [
                    'user_id' => $lock->user_id,
                    'user_name' => $lock->user->name ?? 'Unknown',
                    'lock_count' => $lock->count,
                ])
                ->toArray(),
        ];
    }

    /**
     * Get lock history for a model.
     */
    public function getHistoryFor(Model $model, int $limit = 50): Collection
    {
        return LockHistory::where('lockable_type', get_class($model))
            ->where('lockable_id', $model->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get user's lock history.
     */
    public function getUserHistory(int $userId, int $limit = 50): Collection
    {
        return LockHistory::where('user_id', $userId)
            ->with('lockable')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Cleanup old history records.
     * 
     * Removes history older than configured retention period.
     */
    public function cleanupOldHistory(): int
    {
        $retentionDays = config('collab.history.retention_days', 30);
        
        return LockHistory::where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
    }

    /**
     * Get stale sessions (no recent heartbeat).
     */
    public function getStaleSessions(): Collection
    {
        return LockSession::stale()->get();
    }

    /**
     * Cleanup stale sessions.
     */
    public function cleanupStaleSessions(): int
    {
        return LockSession::stale()->delete();
    }

    /**
     * Check if a model is locked.
     * 
     * Helper method for quick checks without loading the model.
     */
    public function isModelLocked(string $modelType, int $modelId): bool
    {
        return Lock::where('lockable_type', $modelType)
            ->where('lockable_id', $modelId)
            ->active()
            ->exists();
    }

    /**
     * Get package version.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * Get package configuration.
     */
    public function config(): array
    {
        return config('collab');
    }

    /**
     * Run all cleanup tasks.
     * 
     * This is useful for a single scheduled command.
     */
    public function runCleanup(): array
    {
        return [
            'expired_locks_deleted' => $this->cleanupExpiredLocks(),
            'stale_sessions_deleted' => $this->cleanupStaleSessions(),
            'old_history_deleted' => $this->cleanupOldHistory(),
        ];
    }
}
