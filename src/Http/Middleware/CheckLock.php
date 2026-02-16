<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kevjo\LaravelCollab\Exceptions\ModelLockedException;
use Kevjo\LaravelCollab\Traits\HasConcurrentEditing;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if a route-bound model is locked by another user.
 *
 * Throws ModelLockedException (HTTP 423) if the model is locked by someone
 * other than the authenticated user. Passes through if:
 * - The model is not locked
 * - The model is locked by the current user
 * - No authenticated user (skips check)
 *
 * Usage in routes:
 *
 *   // Using the alias registered by the service provider:
 *   Route::put('/posts/{post}', [PostController::class, 'update'])
 *       ->middleware('collab.lock:post');
 *
 *   // Multiple route model parameters:
 *   Route::put('/posts/{post}/sections/{section}', ...)
 *       ->middleware('collab.lock:post,section');
 *
 *   // Without parameter name (checks all lockable route models):
 *   Route::put('/posts/{post}', [PostController::class, 'update'])
 *       ->middleware('collab.lock');
 */
class CheckLock
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  string  ...$modelKeys  Route parameter names to check (e.g., 'post', 'section').
     *                                If empty, checks all route parameters that use HasConcurrentEditing.
     * @return Response
     *
     * @throws ModelLockedException
     */
    public function handle(Request $request, Closure $next, string ...$modelKeys): Response
    {
        $user = $request->user();

        // If no authenticated user, skip the check (auth middleware should guard this)
        if (!$user) {
            return $next($request);
        }

        $modelsToCheck = $this->resolveModels($request, $modelKeys);

        foreach ($modelsToCheck as $model) {
            $lock = $model->getActiveLock();

            if ($lock && $lock->user_id !== $user->id) {
                throw new ModelLockedException(
                    "This resource is currently locked by {$lock->user->name}",
                    $lock
                );
            }
        }

        return $next($request);
    }

    /**
     * Resolve the models to check from the route parameters.
     *
     * @param  Request  $request
     * @param  array<string>  $modelKeys
     * @return array<\Illuminate\Database\Eloquent\Model>
     */
    protected function resolveModels(Request $request, array $modelKeys): array
    {
        $route = $request->route();
        $models = [];

        if (empty($modelKeys)) {
            // No specific keys given — check all route-bound models that use the trait
            foreach ($route->parameters() as $parameter) {
                if (is_object($parameter) && $this->usesLockingTrait($parameter)) {
                    $models[] = $parameter;
                }
            }
        } else {
            // Check only the specified route parameters
            foreach ($modelKeys as $key) {
                $parameter = $route->parameter($key);

                if ($parameter && is_object($parameter) && $this->usesLockingTrait($parameter)) {
                    $models[] = $parameter;
                }
            }
        }

        return $models;
    }

    /**
     * Check if the given object uses the HasConcurrentEditing trait.
     */
    protected function usesLockingTrait(object $model): bool
    {
        return in_array(HasConcurrentEditing::class, class_uses_recursive($model));
    }
}
