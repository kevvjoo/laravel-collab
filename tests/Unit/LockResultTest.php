<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests\Unit;

use Kevjo\LaravelCollab\Support\LockResult;
use PHPUnit\Framework\TestCase;
use Kevjo\LaravelCollab\Models\Lock;

class LockResultTest extends TestCase
{
    public function test_creates_successful_result(): void
    {
        $lock = $this->createMock(Lock::class);

        $result = LockResult::success($lock);

        $this->assertTrue($result->success);
        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->isFailed());
        $this->assertEquals('Lock acquired successfully', $result->message);
        $this->assertSame($lock, $result->lock);
    }

    public function test_creates_failed_result(): void
    {
        $result = LockResult::failed('Test failure message');

        $this->assertFalse($result->success);
        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->isFailed());
        $this->assertEquals('Test failure message', $result->message);
        $this->assertNull($result->lock);
    }

    public function test_failed_result_with_existing_lock(): void
    {
        $lock = $this->createMock(Lock::class);

        $result = LockResult::failed('Already locked', $lock);

        $this->assertFalse($result->success);
        $this->assertSame($lock, $result->lock);
    }

    public function test_converts_to_array(): void
    {
        $result = LockResult::failed('Test message');
        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertFalse($array['success']);
        $this->assertEquals('Test message', $array['message']);
    }

    public function test_converts_to_json(): void
    {
        $result = LockResult::failed('Test message');
        $json = $result->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertFalse($decoded['success']);
        $this->assertEquals('Test message', $decoded['message']);
    }

    public function test_converts_to_string(): void
    {
        $result = LockResult::failed('Error message');

        $this->assertEquals('Error message', (string) $result);
    }

    public function test_get_remaining_time_without_lock(): void
    {
        $result = LockResult::failed('No lock');

        $this->assertEquals(0, $result->getRemainingTime());
    }
}