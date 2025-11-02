<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests\Feature;

use Random\RandomException;
use Kevjo\LaravelCollab\Tests\{TestCase, TestPost};
use Kevjo\LaravelCollab\Models\Lock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class CommandsTest extends TestCase
{
    public function test_install_command_runs_successfully(): void
    {
        $this->artisan('collab:install', ['--no-migrate' => true])
            ->expectsOutput('✨ Laravel Collab installed successfully!')
            ->assertExitCode(0);
    }

    public function test_install_command_publishes_config(): void
    {
        // Clean up first
        if (File::exists(config_path('collab.php'))) {
            File::delete(config_path('collab.php'));
        }

        $this->artisan('collab:install', ['--no-migrate' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists(config_path('collab.php'));

        // Cleanup
        File::delete(config_path('collab.php'));
    }

    public function test_install_command_with_force_flag(): void
    {
        $this->artisan('collab:install', ['--force' => true, '--no-migrate' => true])
            ->assertExitCode(0);
    }

    public function test_cleanup_command_runs_successfully(): void
    {
        $this->artisan('collab:cleanup')
            ->expectsOutput('✨ Cleanup completed successfully!')
            ->assertExitCode(0);
    }

    /**
     * @throws RandomException
     */
    public function test_cleanup_command_deletes_expired_locks(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        // Create expired locks
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(3),
            'expires_at' => now()->subHours(2),
            'lock_token' => Lock::generateToken(),
        ]);

        $this->assertCount(2, Lock::all());

        $this->artisan('collab:cleanup')
            ->expectsOutput('   ✓ Deleted: 2 expired locks')
            ->assertExitCode(0);

        $this->assertCount(0, Lock::all());
    }

    /**
     * @throws RandomException
     */
    public function test_cleanup_command_preserves_active_locks(): void
    {
        $user = $this->createUser();
        $post1 = $this->createPost();
        $post2 = $this->createPost();

        // Create active lock
        $post1->acquireLock($user);

        // Create expired lock
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post2->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $this->artisan('collab:cleanup')
            ->assertExitCode(0);

        $this->assertCount(1, Lock::all()); // Active lock remains
    }

    /**
     * @throws RandomException
     */
    public function test_cleanup_command_with_dry_run_flag(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        // Create expired lock
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $this->artisan('collab:cleanup', ['--dry-run' => true])
            ->expectsOutput('   Would delete: 1 expired locks')
            ->expectsOutput('🔍 Dry run completed - no changes were made')
            ->assertExitCode(0);

        // Lock should still exist
        $this->assertCount(1, Lock::all());
    }

    public function test_cleanup_command_with_all_flag(): void
    {
        config()->set('collab.history.retention_days', 30);

        $this->artisan('collab:cleanup', ['--all' => true])
            ->assertExitCode(0);
    }

    /**
     * @throws RandomException
     */
    public function test_cleanup_command_shows_summary_table(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $this->artisan('collab:cleanup')
            ->expectsOutput('📊 Summary:')
            ->expectsTable(
                ['Category', 'Count'],
                [
                    ['Expired locks', 1],
                    ['Stale sessions', 0],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_cleanup_command_handles_empty_database(): void
    {
        $this->artisan('collab:cleanup')
            ->expectsOutput('   ✓ Deleted: 0 expired locks')
            ->expectsOutput('   ✓ Deleted: 0 stale sessions')
            ->assertExitCode(0);
    }

    /**
     * @throws RandomException
     */
    public function test_cleanup_command_with_multiple_expired_locks(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        // Create 5 expired locks
        for ($i = 0; $i < 5; $i++) {
            Lock::create([
                'lockable_type' => TestPost::class,
                'lockable_id' => $post->id,
                'user_id' => $user->id,
                'locked_at' => now()->subHours(2 + $i),
                'expires_at' => now()->subHours(1 + $i),
                'lock_token' => Lock::generateToken(),
            ]);
        }

        $this->artisan('collab:cleanup')
            ->expectsOutput('   ✓ Deleted: 5 expired locks')
            ->assertExitCode(0);

        $this->assertCount(0, Lock::all());
    }

    public function test_cleanup_command_output_formatting(): void
    {
        $this->artisan('collab:cleanup')
            ->expectsOutput('🧹 Starting Laravel Collab cleanup...')
            ->expectsOutput('🔒 Cleaning up expired locks...')
            ->expectsOutput('👥 Cleaning up stale sessions...')
            ->assertExitCode(0);
    }
}