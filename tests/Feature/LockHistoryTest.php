<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests\Feature;

use Kevjo\LaravelCollab\Models\Lock;
use Random\RandomException;
use Kevjo\LaravelCollab\Tests\{TestCase, TestPost};
use Kevjo\LaravelCollab\Models\LockHistory;
use Kevjo\LaravelCollab\Facades\Collab;

class LockHistoryTest extends TestCase
{
    /**
     * @throws RandomException
     */
    public function test_creates_history_when_lock_acquired(): void
    {
        config()->set('collab.history.enabled', true);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);

        $this->assertDatabaseHas('model_lock_history', [
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'action' => 'acquired',
        ]);
    }

    /**
     * @throws RandomException
     */
    public function test_creates_history_when_lock_released(): void
    {
        config()->set('collab.history.enabled', true);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $post->releaseLock($user);

        $this->assertDatabaseHas('model_lock_history', [
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'action' => 'released',
        ]);
    }

    /**
     * @throws RandomException
     */
    public function test_creates_history_when_lock_expired(): void
    {
        config()->set('collab.history.enabled', true);

        $user = $this->createUser();
        $post = $this->createPost();

        // Create expired lock
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
            'lock_token' => Lock::generateToken(),
            'session_id' => session()->getId(),
        ]);

        // Trigger cleanup
        Collab::cleanupExpiredLocks();

        $this->assertDatabaseHas('model_lock_history', [
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'action' => 'expired',
        ]);
    }

    /**
     * @throws RandomException
     */
    public function test_creates_history_when_lock_force_released(): void
    {
        config()->set('collab.history.enabled', true);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $post->forceReleaseLock();

        $this->assertDatabaseHas('model_lock_history', [
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'action' => 'forced_release',
        ]);
    }

    /**
     * @throws RandomException
     */
    public function test_gets_history_for_model(): void
    {
        config()->set('collab.history.enabled', true);

        $user = $this->createUser();
        $post = $this->createPost();

        // Create some history
        $post->acquireLock($user);
        $post->releaseLock($user);
        $post->acquireLock($user);
        $post->releaseLock($user);

        $history = Collab::getHistoryFor($post, 10);

        $this->assertCount(4, $history); // 2 acquired + 2 released
    }

    /**
     * @throws RandomException
     */
    public function test_gets_user_history(): void
    {
        config()->set('collab.history.enabled', true);

        $user = $this->createUser();
        $post1 = $this->createPost();
        $post2 = $this->createPost();

        $post1->acquireLock($user);
        $post1->releaseLock($user);
        $post2->acquireLock($user);
        $post2->releaseLock($user);

        $history = Collab::getUserHistory($user->id, 10);

        $this->assertCount(4, $history);
    }

    /**
     * @throws RandomException
     */
    public function test_history_disabled_when_configured(): void
    {
        config()->set('collab.history.enabled', false);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $post->releaseLock($user);

        $this->assertCount(0, LockHistory::all());
    }

    /**
     * @throws RandomException
     */
    public function test_history_includes_duration(): void
    {
        config()->set('collab.history.enabled', true);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        sleep(2); // Hold for 2 seconds
        $post->releaseLock($user);

        $history = LockHistory::where('action', 'released')->first();

        $this->assertNotNull($history->duration);
        $this->assertGreaterThanOrEqual(2, $history->duration);
    }
}