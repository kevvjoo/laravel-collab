<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Kevjo\LaravelCollab\Models\Lock;
use Random\RandomException;

class TokenGenerationTest extends TestCase
{
    /**
     * @throws RandomException
     */
    public function test_generates_string_token(): void
    {
        $token = Lock::generateToken();

        $this->assertIsString($token);
    }

    /**
     * @throws RandomException
     */
    public function test_generates_64_character_token(): void
    {
        $token = Lock::generateToken();

        $this->assertEquals(64, strlen($token));
    }

    /**
     * @throws RandomException
     */
    public function test_generates_unique_tokens(): void
    {
        $token1 = Lock::generateToken();
        $token2 = Lock::generateToken();
        $token3 = Lock::generateToken();

        $this->assertNotEquals($token1, $token2);
        $this->assertNotEquals($token2, $token3);
        $this->assertNotEquals($token1, $token3);
    }

    /**
     * @throws RandomException
     */
    public function test_generates_hexadecimal_tokens(): void
    {
        $token = Lock::generateToken();

        // Should only contain hexadecimal characters (0-9, a-f)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * @throws RandomException
     */
    public function test_generates_lowercase_hex(): void
    {
        $token = Lock::generateToken();

        // Should be lowercase
        $this->assertEquals($token, strtolower($token));
    }
}