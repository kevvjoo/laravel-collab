<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kevjo\LaravelCollab\Models\Lock;

class LockAcquired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $model,
        public readonly Lock $lock,
        public readonly Authenticatable $user,
    ) {}
}
