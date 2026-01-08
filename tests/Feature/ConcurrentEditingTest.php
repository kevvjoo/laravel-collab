<?php

namespace Kevjo\LaravelCollab\Tests\Feature;

use Kevjo\LaravelCollab\Models\Lock;
use Kevjo\LaravelCollab\Tests\TestCase;
use Kevjo\LaravelCollab\Exceptions\ModelLockedException;
use Random\RandomException;

class ConcurrentEditingTest extends TestCase
{
    /**
     * @throws RandomException
     */
    public function test_user_can_acquire_lock_on_model(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $result = $post->acquireLock($user);

        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($post->isLocked());
        $this->assertTrue($post->isLockedByUser($user));
        $this->assertFalse($post->isLockedByAnother($user));
    }

    /**
     * @throws RandomException
     */
    public function test_user_cannot_acquire_lock_if_already_locked_by_another(): void
    {
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post = $this->createPost();

        $post->acquireLock($user1);
        $result = $post->acquireLock($user2);

        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($post->isLockedByAnother($user2));
        $this->assertEquals($user1->id, $result->getLockedBy()->id);
    }

    /**
     * @throws RandomException
     */
    public function test_same_user_acquiring_again_extends_lock(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user, ['duration' => 600]);
        $firstExpiry = $post->lockExpiresAt();

        sleep(1);

        $post->acquireLock($user, ['duration' => 1800]);
        $secondExpiry = $post->lockExpiresAt();

        $this->assertGreaterThan($firstExpiry, $secondExpiry);
    }

    /**
     * @throws RandomException
     */
    public function test_user_can_release_lock(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $this->assertTrue($post->isLocked());

        $post->releaseLock($user);
        $this->assertFalse($post->isLocked());
    }

    /**
     * @throws RandomException
     */
    public function test_user_cannot_release_another_users_lock(): void
    {
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post = $this->createPost();

        $post->acquireLock($user1);
        $released = $post->releaseLock($user2);

        $this->assertFalse($released);
        $this->assertTrue($post->isLocked());
    }

    /**
     * @throws RandomException
     */
    public function test_lock_is_auto_released_after_update_if_configured(): void
    {
        config()->set('collab.auto_release_after_update', true);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $this->assertTrue($post->isLocked());

        $this->actingAs($user);
        $post->update(['title' => 'Updated Title']);

        $this->assertFalse($post->fresh()->isLocked());
    }

    /**
     * @throws RandomException
     */
    public function test_update_throws_exception_if_locked_by_another(): void
    {
        config()->set('collab.prevent_update_if_locked', true);

        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post = $this->createPost();

        $post->acquireLock($user1);

        $this->expectException(ModelLockedException::class);

        $this->actingAs($user2);
        $post->update(['title' => 'New Title']);
    }

    /**
     * @throws RandomException
     */
    public function test_user_can_extend_their_own_lock(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user, ['duration' => 600]);
        $originalExpiry = $post->lockExpiresAt();

        sleep(1);

        $extended = $post->extendLock(1800, $user);

        $this->assertTrue($extended);
        $this->assertGreaterThan($originalExpiry, $post->lockExpiresAt());
    }

    /**
     * @throws RandomException
     */
    public function test_user_cannot_extend_another_users_lock(): void
    {
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post = $this->createPost();

        $post->acquireLock($user1);
        $extended = $post->extendLock(1800, $user2);

        $this->assertFalse($extended);
    }

    /**
     * @throws RandomException
     */
    public function test_admin_can_force_release_lock(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $this->assertTrue($post->isLocked());

        $post->forceReleaseLock();

        $this->assertFalse($post->isLocked());
    }

    /**
     * @throws RandomException
     */
    public function test_expired_locks_are_automatically_cleaned(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        Lock::create([
            'lockable_type' => get_class($post),
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
            'lock_token' => Lock::generateToken(),
        ]);

        $this->assertFalse($post->isLocked());
    }

    /**
     * @throws RandomException
     */
    public function test_returns_lock_owner_information(): void
    {
        $user = $this->createUser(['name' => 'John Doe']);
        $post = $this->createPost();

        $post->acquireLock($user);

        $owner = $post->lockOwner();
        $this->assertEquals('John Doe', $owner->name);
        $this->assertEquals($user->id, $owner->id);
    }

    /**
     * @throws RandomException
     */
    public function test_returns_lock_info_array(): void
    {
        $user = $this->createUser(['name' => 'John Doe']);
        $post = $this->createPost();

        $post->acquireLock($user);

        $info = $post->getLockInfo();

        $this->assertIsArray($info);
        $this->assertTrue($info['is_locked']);
        $this->assertEquals('John Doe', $info['locked_by']['name']);
        $this->assertArrayHasKey('expires_at', $info);
        $this->assertArrayHasKey('remaining_seconds', $info);
    }

    public function test_returns_null_lock_info_when_not_locked(): void
    {
        $post = $this->createPost();

        $info = $post->getLockInfo();

        $this->assertNull($info);
    }

    /**
     * @throws RandomException
     */
    public function test_validates_lock_duration_limits(): void
    {
        config()->set('collab.lock_duration.min', 60);
        config()->set('collab.lock_duration.max', 3600);

        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user, ['duration' => 10]); // Too short

        $this->assertGreaterThanOrEqual(50, $post->lockRemainingTime());
    }

    /**
     * @throws RandomException
     */
    public function test_multiple_models_can_have_independent_locks(): void
    {
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post1 = $this->createPost(['title' => 'Post 1']);
        $post2 = $this->createPost(['title' => 'Post 2']);

        $post1->acquireLock($user1);
        $result = $post2->acquireLock($user2);

        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($post1->isLocked());
        $this->assertTrue($post2->isLocked());
    }

    /**
     * @throws RandomException
     */
    public function test_get_lock_status_for_locked_model(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);
        $status = $post->getLockStatus($user);

        $this->assertTrue($status['is_locked']);
        $this->assertTrue($status['can_edit']);
        $this->assertTrue($status['is_owner']);
    }

    public function test_get_lock_status_for_unlocked_model(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $status = $post->getLockStatus($user);

        $this->assertFalse($status['is_locked']);
        $this->assertTrue($status['can_edit']);
        $this->assertFalse($status['is_owner']);
    }
}