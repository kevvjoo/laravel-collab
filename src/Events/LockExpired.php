<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kevjo\LaravelCollab\Models\Lock;

class LockExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?Model $model,
        public readonly Lock $lock,
    ) {}
}
