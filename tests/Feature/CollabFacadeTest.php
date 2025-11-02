<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests\Feature;

use Random\RandomException;
use Kevjo\LaravelCollab\Tests\{TestCase, TestPost};
use Kevjo\LaravelCollab\Facades\Collab;
use Kevjo\LaravelCollab\Models\Lock;

class CollabFacadeTest extends TestCase
{
    /**
     * @throws RandomException
     */
    public function test_gets_all_active_locks(): void
    {
        $user = $this->createUser();
        $post1 = $this->createPost(['title' => 'Post 1']);
        $post2 = $this->createPost(['title' => 'Post 2']);

        // Create active locks
        $post1->acquireLock($user);
        $post2->acquireLock($user);

        // Create expired lock
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post1->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $activeLocks = Collab::activeLocks();

        $this->assertCount(2, $activeLocks);
    }

    /**
     * @throws RandomException
     */
    public function test_gets_expired_locks(): void
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

        $expiredLocks = Collab::expiredLocks();

        $this->assertCount(1, $expiredLocks);
    }

    /**
     * @throws RandomException
     */
    public function test_cleanups_expired_locks(): void
    {
        $user = $this->createUser();
        $post1 = $this->createPost();
        $post2 = $this->createPost();

        // Create active lock
        $post1->acquireLock($user);

        // Create expired locks
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post2->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post2->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(3),
            'expires_at' => now()->subHours(2),
            'lock_token' => Lock::generateToken(),
        ]);

        $deletedCount = Collab::cleanupExpiredLocks();

        $this->assertEquals(2, $deletedCount);
        $this->assertCount(1, Lock::all()); // Only active lock remains
    }

    /**
     * @throws RandomException
     */
    public function test_gets_locks_for_specific_model(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);

        // Add expired lock for history
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $locks = Collab::getLocksFor($post);

        $this->assertCount(2, $locks); // Both active and expired
    }

    /**
     * @throws RandomException
     */
    public function test_gets_active_lock_for_specific_model(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);

        $activeLock = Collab::getActiveLockFor($post);

        $this->assertNotNull($activeLock);
        $this->assertEquals($user->id, $activeLock->user_id);
        $this->assertEquals($post->id, $activeLock->lockable_id);
    }

    public function test_gets_null_when_no_active_lock(): void
    {
        $post = $this->createPost();

        $activeLock = Collab::getActiveLockFor($post);

        $this->assertNull($activeLock);
    }

    /**
     * @throws RandomException
     */
    public function test_releases_all_locks_for_user(): void
    {
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post1 = $this->createPost();
        $post2 = $this->createPost();
        $post3 = $this->createPost();

        // User 1 has 2 locks
        $post1->acquireLock($user1);
        $post2->acquireLock($user1);

        // User 2 has 1 lock
        $post3->acquireLock($user2);

        $deletedCount = Collab::releaseAllLocksForUser($user1->id);

        $this->assertEquals(2, $deletedCount);
        $this->assertCount(1, Lock::all()); // Only user2's lock remains
    }

    /**
     * @throws RandomException
     */
    public function test_releases_all_locks(): void
    {
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post1 = $this->createPost();
        $post2 = $this->createPost();

        $post1->acquireLock($user1);
        $post2->acquireLock($user2);

        $deletedCount = Collab::releaseAllLocks();

        $this->assertEquals(2, $deletedCount);
        $this->assertCount(0, Lock::all());
    }

    /**
     * @throws RandomException
     */
    public function test_gets_locks_for_specific_model_type(): void
    {
        $user = $this->createUser();
        $post1 = $this->createPost();
        $post2 = $this->createPost();

        $post1->acquireLock($user);
        $post2->acquireLock($user);

        $postLocks = Collab::getLocksForModelType(TestPost::class);

        $this->assertCount(2, $postLocks);
        $this->assertTrue($postLocks->every(
            fn($lock) => $lock->lockable_type === TestPost::class
        ));
    }

    /**
     * @throws RandomException
     */
    public function test_gets_package_statistics(): void
    {
        $user = $this->createUser();
        $post1 = $this->createPost();
        $post2 = $this->createPost();

        $post1->acquireLock($user);
        $post2->acquireLock($user, ['strategy' => 'optimistic']);

        // Create expired lock
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post1->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $stats = Collab::getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_active_locks', $stats);
        $this->assertArrayHasKey('total_expired_locks', $stats);
        $this->assertArrayHasKey('locks_by_strategy', $stats);
        $this->assertArrayHasKey('locks_by_model_type', $stats);
        $this->assertArrayHasKey('most_active_users', $stats);

        $this->assertEquals(2, $stats['total_active_locks']);
        $this->assertEquals(1, $stats['total_expired_locks']);
    }

    /**
     * @throws RandomException
     */
    public function test_checks_if_model_is_locked(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $this->assertFalse(
            Collab::isModelLocked(TestPost::class, $post->id)
        );

        $post->acquireLock($user);

        $this->assertTrue(
            Collab::isModelLocked(TestPost::class, $post->id)
        );
    }

    public function test_gets_package_version(): void
    {
        $version = Collab::version();

        $this->assertIsString($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    public function test_gets_package_configuration(): void
    {
        $config = Collab::config();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('default_strategy', $config);
        $this->assertArrayHasKey('lock_duration', $config);
    }

    /**
     * @throws RandomException
     */
    public function test_runs_all_cleanup_tasks(): void
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

        $results = Collab::runCleanup();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('expired_locks_deleted', $results);
        $this->assertArrayHasKey('stale_sessions_deleted', $results);
        $this->assertArrayHasKey('old_history_deleted', $results);
    }
}