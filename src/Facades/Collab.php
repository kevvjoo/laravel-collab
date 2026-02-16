<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection activeLocks()
 * @method static \Illuminate\Support\Collection expiredLocks()
 * @method static int cleanupExpiredLocks()
 * @method static \Illuminate\Support\Collection getLocksFor(\Illuminate\Database\Eloquent\Model $model)
 * @method static \Kevjo\LaravelCollab\Models\Lock|null getActiveLockFor(\Illuminate\Database\Eloquent\Model $model)
 * @method static int releaseAllLocksForUser(int $userId)
 * @method static int releaseAllLocks()
 * @method static \Illuminate\Support\Collection getLocksForModelType(string $modelType)
 * @method static array getStatistics()
 * @method static \Illuminate\Support\Collection getHistoryFor(\Illuminate\Database\Eloquent\Model $model, int $limit = 50)
 * @method static \Illuminate\Support\Collection getUserHistory(int $userId, int $limit = 50)
 * @method static int cleanupOldHistory()
 * @method static bool isModelLocked(string $modelType, int $modelId)
 * @method static string version()
 * @method static array config()
 * @method static array runCleanup()
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
