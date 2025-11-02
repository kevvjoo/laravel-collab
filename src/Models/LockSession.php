<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LockSession extends Model
{
    protected $fillable = [
        'lock_id',
        'user_id',
        'channel_name',
        'last_heartbeat',
        'is_active',
        'cursor_position',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat' => 'datetime',
            'is_active' => 'boolean',
            'cursor_position' => 'array',
        ];
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->setTable(config('collab.tables.sessions', 'model_lock_sessions'));
    }

    /**
     * Get the lock this session belongs to.
     */
    public function lock(): BelongsTo
    {
        return $this->belongsTo(Lock::class);
    }

    /**
     * Get the user for this session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('auth.providers.users.model', 'App\Models\User')
        );
    }

    /**
     * Check if session is stale (no recent heartbeat).
     */
    public function isStale(): bool
    {
        $timeout = config('collab.heartbeat.timeout', 120);
        return $this->last_heartbeat->addSeconds($timeout)->isPast();
    }

    /**
     * Update the heartbeat timestamp.
     */
    public function heartbeat(): bool
    {
        return $this->update([
            'last_heartbeat' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Scope to get only active sessions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get stale sessions.
     */
    public function scopeStale($query)
    {
        $timeout = config('collab.heartbeat.timeout', 120);
        return $query->where('last_heartbeat', '<', now()->subSeconds($timeout));
    }
}
