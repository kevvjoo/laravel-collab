<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Kevjo\LaravelCollab\Exceptions\ModelLockedException;
use Kevjo\LaravelCollab\Tests\{TestCase, TestPost, TestUser};
use Random\RandomException;

class CheckLockMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register test routes for middleware testing
        Route::middleware(['web', 'collab.lock:post'])->group(function () {
            Route::put('/test-posts/{post}', function (TestPost $post) {
                return response()->json(['status' => 'ok']);
            });
        });

        // Route without specifying model key — auto-detects lockable models
        Route::middleware(['web', 'collab.lock'])->group(function () {
            Route::put('/auto-posts/{post}', function (TestPost $post) {
                return response()->json(['status' => 'ok']);
            });
        });
    }

    /**
     * @throws RandomException
     */
    public function test_middleware_allows_request_when_model_is_not_locked(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $this->actingAs($user)
            ->putJson("/test-posts/{$post->id}")
            ->assertOk();
    }

    /**
     * @throws RandomException
     */
    public function test_middleware_allows_request_when_locked_by_same_user(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);

        $this->actingAs($user)
            ->putJson("/test-posts/{$post->id}")
            ->assertOk();
    }

    /**
     * @throws RandomException
     */
    public function test_middleware_blocks_request_when_locked_by_another_user(): void
    {
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post = $this->createPost();

        $post->acquireLock($user1);

        $this->actingAs($user2)
            ->putJson("/test-posts/{$post->id}")
            ->assertStatus(423);
    }

    /**
     * @throws RandomException
     */
    public function test_middleware_passes_through_for_unauthenticated_requests(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $post->acquireLock($user);

        // Without actingAs — no authenticated user, middleware skips check
        $this->putJson("/test-posts/{$post->id}")
            ->assertOk();
    }

    /**
     * @throws RandomException
     */
    public function test_middleware_auto_detects_lockable_models_without_key(): void
    {
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);
        $post = $this->createPost();

        $post->acquireLock($user1);

        $this->actingAs($user2)
            ->putJson("/auto-posts/{$post->id}")
            ->assertStatus(423);
    }

    /**
     * @throws RandomException
     */
    public function test_middleware_auto_detect_allows_when_not_locked(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();

        $this->actingAs($user)
            ->putJson("/auto-posts/{$post->id}")
            ->assertOk();
    }

    /**
     * @throws RandomException
     */
    public function test_middleware_returns_423_with_lock_info(): void
    {
        $owner = $this->createUser(['email' => 'owner@test.com', 'name' => 'Lock Owner']);
        $requester = $this->createUser(['email' => 'requester@test.com']);
        $post = $this->createPost();

        $post->acquireLock($owner);

        $response = $this->actingAs($requester)
            ->putJson("/test-posts/{$post->id}");

        $response->assertStatus(423)
            ->assertJsonPath('message', 'This resource is currently locked by Lock Owner');
    }
}
