<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Kevjo\LaravelCollab\Exceptions\ModelLockedException;
use Kevjo\LaravelCollab\Models\Lock;

class ModelLockedExceptionTest extends TestCase
{
    public function test_creates_exception_with_default_message(): void
    {
        $exception = new ModelLockedException();

        $this->assertEquals(
            'This resource is currently locked by another user',
            $exception->getMessage()
        );
    }

    public function test_creates_exception_with_custom_message(): void
    {
        $exception = new ModelLockedException('Custom error message');

        $this->assertEquals('Custom error message', $exception->getMessage());
    }

    public function test_can_set_lock(): void
    {
        $lock = $this->createMock(Lock::class);
        $exception = new ModelLockedException();

        $result = $exception->setLock($lock);

        $this->assertSame($exception, $result); // Returns self for chaining
        $this->assertSame($lock, $exception->getLock());
    }

    public function test_can_get_lock(): void
    {
        $lock = $this->createMock(Lock::class);
        $exception = new ModelLockedException(null, $lock);

        $this->assertSame($lock, $exception->getLock());
    }

    public function test_get_lock_returns_null_when_not_set(): void
    {
        $exception = new ModelLockedException();

        $this->assertNull($exception->getLock());
    }
}