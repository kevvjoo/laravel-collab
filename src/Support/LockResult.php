<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Support;

use Kevjo\LaravelCollab\Models\Lock;
use Carbon\Carbon;

readonly class LockResult
{
    /**
     * Create a new lock result instance.
     * 
     * @param bool $success Whether the operation succeeded
     * @param string|null $message Human-readable message
     * @param Lock|null $lock The lock object (if exists)
     */
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public ?Lock $lock = null,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(Lock $lock): self
    {
        return new self(
            success: true,
            message: 'Lock acquired successfully',
            lock: $lock
        );
    }

    /**
     * Create a failed result.
     *
     * @param string $message
     * @param Lock|null $existingLock The lock that's blocking us
     * @return LockResult
     */
    public static function failed(string $message, ?Lock $existingLock = null): self
    {
        return new self(
            success: false,
            message: $message,
            lock: $existingLock
        );
    }

    /**
     * Check if the operation was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if the operation failed.
     */
    public function isFailed(): bool
    {
        return !$this->success;
    }

    /**
     * Get the user who owns the lock.
     * 
     * Returns null if no lock or operation succeeded.
     */
    public function getLockedBy(): mixed
    {
        return $this->lock?->user;
    }

    /**
     * Get when the lock expires.
     */
    public function getExpiresAt(): ?Carbon
    {
        return $this->lock?->expires_at;
    }

    /**
     * Get the lock token.
     */
    public function getToken(): ?string
    {
        return $this->lock?->lock_token;
    }

    /**
     * Get remaining time in seconds.
     */
    public function getRemainingTime(): float
    {
        if (!$this->lock) {
            return 0;
        }

        return $this->lock->getRemainingTime();
    }

    /**
     * Convert to array (useful for JSON responses).
     */
    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'message' => $this->message,
        ];

        if ($this->lock) {
            $data['lock'] = [
                'id' => $this->lock->id,
                'locked_by' => $this->lock->user?->only(['id', 'name', 'email']),
                'expires_at' => $this->lock->expires_at->toIso8601String(),
                'remaining_seconds' => $this->lock->getRemainingTime(),
                'token' => $this->lock->lock_token,
            ];
        }

        return $data;
    }

    /**
     * Convert to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Magic method to convert to string.
     */
    public function __toString(): string
    {
        return $this->message ?? '';
    }
}
