<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests\Feature;

use Kevjo\LaravelCollab\Events\{LockAcquired, LockReleased, LockExpired, LockForceReleased, LockRequested};
use Kevjo\LaravelCollab\Facades\Collab;
use Kevjo\LaravelCollab\Models\Lock;
use Kevjo\LaravelCollab\Tests\{TestCase, TestPost};
use Illuminate\Support\Facades\Event;
use Random\RandomException;

class EventsTest extends TestCase
{
    /**
     * @throws RandomException
     */
    public function test_lock_acquired_event_is_fired_when_lock_is_acquired(): void
    {
        Event::fake([LockAcquired::class]);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);

        Event::assertDispatched(LockAcquired::class, function (LockAcquired $event) use ($user, $post) {
            return $event->model->is($post)
                && $event->user->id === $user->id
                && $event->lock->user_id === $user->id;
        });
    }

    /**
     * @throws RandomException
     */
    public function test_lock_acquired_event_is_not_fired_when_lock_fails(): void
    {
        Event::fake([LockAcquired::class]);

        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post = $this->createPost();

        $post->acquireLock($user1);

        Event::assertDispatchedTimes(LockAcquired::class, 1);

        // Second user tries to acquire — should fail, no additional event
        $post->acquireLock($user2);

        Event::assertDispatchedTimes(LockAcquired::class, 1);
    }

    /**
     * @throws RandomException
     */
    public function test_lock_released_event_is_fired_when_lock_is_released(): void
    {
        Event::fake([LockAcquired::class, LockReleased::class]);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $post->releaseLock($user);

        Event::assertDispatched(LockReleased::class, function (LockReleased $event) use ($user, $post) {
            return $event->model->is($post)
                && $event->user->id === $user->id;
        });
    }

    /**
     * @throws RandomException
     */
    public function test_lock_released_event_is_not_fired_when_release_fails(): void
    {
        Event::fake([LockAcquired::class, LockReleased::class]);

        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post = $this->createPost();

        $post->acquireLock($user1);

        // User2 tries to release User1's lock — should fail
        $post->releaseLock($user2);

        Event::assertNotDispatched(LockReleased::class);
    }

    /**
     * @throws RandomException
     */
    public function test_lock_force_released_event_is_fired(): void
    {
        Event::fake([LockAcquired::class, LockForceReleased::class]);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $post->forceReleaseLock();

        Event::assertDispatched(LockForceReleased::class, function (LockForceReleased $event) use ($user, $post) {
            return $event->model->is($post)
                && $event->lockOwner->id === $user->id;
        });
    }

    /**
     * @throws RandomException
     */
    public function test_lock_requested_event_is_fired(): void
    {
        Event::fake([LockAcquired::class, LockRequested::class]);

        $owner = $this->createUser(['email' => 'owner@test.com']);
        $requester = $this->createUser(['email' => 'requester@test.com']);
        $post = $this->createPost();

        $post->acquireLock($owner);
        $result = $post->requestLock($requester);

        $this->assertTrue($result);

        Event::assertDispatched(LockRequested::class, function (LockRequested $event) use ($owner, $requester, $post) {
            return $event->model->is($post)
                && $event->requester->id === $requester->id
                && $event->lockOwner->id === $owner->id;
        });
    }

    /**
     * @throws RandomException
     */
    public function test_lock_requested_event_is_not_fired_when_requesting_own_lock(): void
    {
        Event::fake([LockAcquired::class, LockRequested::class]);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $result = $post->requestLock($user);

        $this->assertFalse($result);
        Event::assertNotDispatched(LockRequested::class);
    }

    /**
     * @throws RandomException
     */
    public function test_lock_expired_event_is_fired_during_cleanup(): void
    {
        Event::fake([LockExpired::class]);

        $user = $this->createUser();
        $post = $this->createPost();

        // Create an expired lock directly
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        Collab::cleanupExpiredLocks();

        Event::assertDispatched(LockExpired::class, function (LockExpired $event) use ($post) {
            return $event->model->is($post)
                && $event->lock instanceof Lock;
        });
    }

    /**
     * @throws RandomException
     */
    public function test_lock_expired_event_is_not_fired_for_active_locks(): void
    {
        Event::fake([LockAcquired::class, LockExpired::class]);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);

        Collab::cleanupExpiredLocks();

        Event::assertNotDispatched(LockExpired::class);
    }
}
