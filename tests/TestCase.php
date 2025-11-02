<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Kevjo\LaravelCollab\CollabServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Run package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Run Laravel's default migrations (for users table)
        $this->loadLaravelMigrations();

        // Create posts table for testing
        $this->createPostsTable();
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            CollabServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', TestUser::class);
    }

    /**
     * Create posts table for testing.
     */
    protected function createPostsTable(): void
    {
        if (!Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->text('content');
                $table->timestamps();
            });
        }
    }

    /**
     * Create a test user.
     */
    protected function createUser(array $attributes = []): TestUser
    {
        return TestUser::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ], $attributes));
    }

    /**
     * Create a test post (lockable model).
     */
    protected function createPost(array $attributes = []): TestPost
    {
        $this->createPostsTable();

        return TestPost::create(array_merge([
            'title' => 'Test Post',
            'content' => 'Test content',
        ], $attributes));
    }
}