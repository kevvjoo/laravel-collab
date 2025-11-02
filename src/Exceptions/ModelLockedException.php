<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ModelLockedException extends Exception
{
    /**
     * The default error message.
     */
    protected $message = 'This resource is currently locked by another user';

    /**
     * The lock that's blocking the operation.
     */
    protected mixed $lock = null;

    /**
     * Create a new exception instance.
     */
    public function __construct(?string $message = null, mixed $lock = null)
    {
        parent::__construct($message ?? $this->message);
        $this->lock = $lock;
    }

    /**
     * Render the exception as an HTTP response.
     * 
     * This is automatically called by Laravel when the exception is thrown.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        // If request expects JSON (API call)
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->message,
                'error' => 'model_locked',
                'lock_info' => $this->getLockInfo(),
            ], 423); // 423 Locked is the proper HTTP status code
        }

        // For web requests, redirect back with error
        return back()
            ->with('error', $this->message)
            ->with('lock_info', $this->getLockInfo());
    }

    /**
     * Get information about the lock.
     */
    protected function getLockInfo(): ?array
    {
        if (!$this->lock) {
            return null;
        }

        return [
            'locked_by' => $this->lock->user?->name,
            'locked_at' => $this->lock->locked_at?->toIso8601String(),
            'expires_at' => $this->lock->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Set the lock that caused this exception.
     */
    public function setLock(mixed $lock): self
    {
        $this->lock = $lock;
        return $this;
    }

    /**
     * Get the lock that caused this exception.
     */
    public function getLock(): mixed
    {
        return $this->lock;
    }
}
