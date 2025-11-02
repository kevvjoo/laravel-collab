<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab;

use Illuminate\Support\ServiceProvider;
use Kevjo\LaravelCollab\Console\Commands\{InstallCommand, CleanupCommand};

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

        // Load package routes (if you add API routes later)
        // Uncomment when you add routes/api.php
        // $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Load package views (if you add views later)
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'collab');
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
