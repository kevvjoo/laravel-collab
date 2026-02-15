<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LockReleased
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $model,
        public readonly Authenticatable $user,
    ) {}
}
