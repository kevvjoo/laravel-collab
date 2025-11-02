<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

class LockHistory extends Model
{
    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'user_id',
        'action',
        'duration',
        'changes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
        ];
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->setTable(config('collab.tables.history', 'model_lock_history'));
    }

    /**
     * Get the model this history entry is about.
     */
    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('auth.providers.users.model', 'App\Models\User')
        );
    }

    /**
     * Scope to get history for a specific model.
     */
    public function scopeForModel($query, string $type, int $id)
    {
        return $query->where('lockable_type', $type)
                    ->where('lockable_id', $id);
    }

    /**
     * Scope to get history by action type.
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Boot method - automatic cleanup of old records.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-delete old history entries
        if (config('collab.history.retention_days')) {
            // This would typically be scheduled, but here for demonstration
            // In practice, use a scheduled command
        }
    }
}
