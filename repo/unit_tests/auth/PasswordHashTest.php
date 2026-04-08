<?php
declare(strict_types=1);

namespace unit_tests\auth;

use PHPUnit\Framework\TestCase;

/**
 * Tests for password hashing conventions.
 */
class PasswordHashTest extends TestCase
{
    public function test_password_is_hashed_with_bcrypt(): void
    {
        $password = 'SecureP@ssw0rd!';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Bcrypt hashes start with $2y$
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertEquals(60, strlen($hash));
    }

    public function test_password_verify_matches_hash(): void
    {
        $password = 'SecureP@ssw0rd!';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $this->assertTrue(password_verify($password, $hash));
    }

    public function test_different_passwords_produce_different_hashes(): void
    {
        $hash1 = password_hash('password1', PASSWORD_BCRYPT);
        $hash2 = password_hash('password2', PASSWORD_BCRYPT);

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_plaintext_password_never_equals_hash(): void
    {
        $password = 'MyPassword123';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $this->assertNotEquals($password, $hash);
        $this->assertStringNotContainsString($password, $hash);
    }
}
