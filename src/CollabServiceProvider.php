<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Kevjo\LaravelCollab\Console\Commands\{InstallCommand, CleanupCommand};
use Kevjo\LaravelCollab\Http\Middleware\CheckLock;

class CollabServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     * 
     * This runs FIRST when Laravel loads the package.
     * We register our main Collab class into the service container.
     */
    public function register(): void
    {
        // Merge our config with user's config
        // User's config takes precedence
        $this->mergeConfigFrom(
            __DIR__.'/../config/collab.php',
            'collab'
        );

        // Register the main Collab class as a singleton
        // This means only ONE instance exists throughout the request
        $this->app->singleton('collab', fn($app): Collab => new Collab());

        // Register the facade alias
        // This allows users to use Collab::method() syntax
        $this->app->alias('collab', Collab::class);
    }

    /**
     * Bootstrap any package services.
     * 
     * This runs AFTER register(), when everything is ready.
     * We publish config, migrations, and register commands.
     */
    public function boot(): void
    {
        // Only do these things if we're running in console (CLI)
        if ($this->app->runningInConsole()) {
            
            // Publish configuration file
            // Users can run: php artisan vendor:publish --tag=collab-config
            $this->publishes([
                __DIR__.'/../config/collab.php' => config_path('collab.php'),
            ], 'collab-config');

            // Publish migrations
            // Users can run: php artisan vendor:publish --tag=collab-migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'collab-migrations');

            // Auto-load migrations (optional - auto-runs migrations)
            // Comment this out if you want users to manually publish
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            // Register artisan commands
            $this->commands([
                InstallCommand::class,      // php artisan collab:install
                CleanupCommand::class,      // php artisan collab:cleanup
            ]);
        }

        // Register middleware alias so users can use ->middleware('collab.lock')
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('collab.lock', CheckLock::class);
    }

    /**
     * Get the services provided by the provider.
     * 
     * This tells Laravel what services this provider offers.
     */
    public function provides(): array
    {
        return ['collab', Collab::class];
    }
}
