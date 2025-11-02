<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests\Feature;

use Random\RandomException;
use Kevjo\LaravelCollab\Tests\{TestCase, TestPost};
use Kevjo\LaravelCollab\Models\Lock;

class LockModelTest extends TestCase
{
    /**
     * @throws RandomException
     */
    public function test_creates_lock_in_database(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $lock = Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $this->assertDatabaseHas('model_locks', [
            'id' => $lock->id,
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * @throws RandomException
     */
    public function test_deletes_lock_from_database(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $lock = Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $lockId = $lock->id;
        $lock->delete();

        $this->assertDatabaseMissing('model_locks', ['id' => $lockId]);
    }

    /**
     * @throws RandomException
     */
    public function test_checks_if_lock_is_expired(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $expiredLock = Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $this->assertTrue($expiredLock->isExpired());
        $this->assertFalse($expiredLock->isActive());
    }

    /**
     * @throws RandomException
     */
    public function test_checks_if_lock_is_active(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $activeLock = Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $this->assertFalse($activeLock->isExpired());
        $this->assertTrue($activeLock->isActive());
    }

    /**
     * @throws RandomException
     */
    public function test_calculates_lock_duration(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $lock = Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subMinutes(30),
            'expires_at' => now()->addMinutes(30),
            'lock_token' => Lock::generateToken(),
        ]);

        $duration = $lock->getDuration();
        $this->assertGreaterThanOrEqual(1790, $duration);
        $this->assertLessThanOrEqual(1810, $duration);
    }

    /**
     * @throws RandomException
     */
    public function test_calculates_remaining_time(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $lock = Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'lock_token' => Lock::generateToken(),
        ]);

        $remaining = $lock->getRemainingTime();
        $this->assertGreaterThanOrEqual(1790, $remaining);
        $this->assertLessThanOrEqual(1810, $remaining);
    }

    /**
     * @throws RandomException
     */
    public function test_extends_lock(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $lock = Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'lock_token' => Lock::generateToken(),
        ]);

        $originalExpiry = $lock->expires_at;

        $lock->extend(3600);
        $lock->refresh();

        $this->assertTrue($lock->expires_at->greaterThan($originalExpiry));
    }

    /**
     * @throws RandomException
     */
    public function test_has_user_relationship(): void
    {
        $user = $this->createUser(['name' => 'John Doe']);
        $post = $this->createPost();

        $lock = Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $this->assertEquals('John Doe', $lock->user->name);
        $this->assertEquals($user->id, $lock->user->id);
    }

    /**
     * @throws RandomException
     */
    public function test_has_lockable_polymorphic_relationship(): void
    {
        $user = $this->createUser();
        $post = $this->createPost(['title' => 'My Test Post']);

        $lock = Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $this->assertInstanceOf(TestPost::class, $lock->lockable);
        $this->assertEquals('My Test Post', $lock->lockable->title);
        $this->assertEquals($post->id, $lock->lockable->id);
    }

    /**
     * @throws RandomException
     */
    public function test_active_scope_returns_only_active_locks(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        // Create active lock
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        // Create expired lock
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $activeLocks = Lock::active()->get();

        $this->assertCount(1, $activeLocks);
    }

    /**
     * @throws RandomException
     */
    public function test_expired_scope_returns_only_expired_locks(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        // Create active lock
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        // Create expired lock
        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user->id,
            'locked_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $expiredLocks = Lock::expired()->get();

        $this->assertCount(1, $expiredLocks);
    }

    /**
     * @throws RandomException
     */
    public function test_for_user_scope_filters_by_user(): void
    {
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post = $this->createPost();

        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user1->id,
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        Lock::create([
            'lockable_type' => TestPost::class,
            'lockable_id' => $post->id,
            'user_id' => $user2->id,
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
            'lock_token' => Lock::generateToken(),
        ]);

        $user1Locks = Lock::forUser($user1->id)->get();

        $this->assertCount(1, $user1Locks);
        $this->assertEquals($user1->id, $user1Locks->first()->user_id);
    }
}