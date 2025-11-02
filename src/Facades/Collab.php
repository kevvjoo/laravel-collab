<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Database\Eloquent\Collection activeLocks()
 * @method static int cleanupExpiredLocks()
 * @method static \Illuminate\Database\Eloquent\Collection getLocksFor(\Illuminate\Database\Eloquent\Model $model)
 * @method static int releaseAllLocksForUser(int $userId)
 * @method static array getStatistics()
 * 
 * @see \Kevjo\LaravelCollab\Collab
 */
class Collab extends Facade
{
    /**
     * Get the registered name of the component.
     * 
     * This tells Laravel which service to pull from the container.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'collab';
    }
}
