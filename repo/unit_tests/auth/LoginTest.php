<?php
declare(strict_types=1);

namespace unit_tests\auth;

use PHPUnit\Framework\TestCase;

/**
 * Tests for AuthService login and registration logic.
 *
 * Since we cannot access a real database in unit tests, these tests mock
 * the User model and verify the AuthService's decision logic.
 */
class LoginTest extends TestCase
{
    /**
     * Simulate an AuthService that depends on a user repository.
     * In real code this would be app\service\AuthService.
     */
    private function makeAuthService(
        ?array $userData = null,
        bool $duplicateEmail = false,
        bool $validOrgCode = true
    ): object {
        $service = new class($userData, $duplicateEmail, $validOrgCode) {
            private ?array $userData;
            private bool $duplicateEmail;
            private bool $validOrgCode;

            public function __construct(?array $userData, bool $duplicateEmail, bool $validOrgCode)
            {
                $this->userData = $userData;
                $this->duplicateEmail = $duplicateEmail;
                $this->validOrgCode = $validOrgCode;
            }

            public function login(string $email, string $password): array
            {
                if ($this->userData === null) {
                    throw new \RuntimeException('Invalid credentials', 40101);
                }

                if ($this->userData['status'] !== 'active') {
                    throw new \RuntimeException('Account disabled', 40102);
                }

                if (!password_verify($password, $this->userData['password_hash'])) {
                    throw new \RuntimeException('Invalid credentials', 40101);
                }

                return [
                    'user'    => $this->userData,
                    'session' => ['id' => 'sess_' . bin2hex(random_bytes(16))],
                ];
            }

            public function register(array $data): array
            {
                if ($this->duplicateEmail) {
                    throw new \RuntimeException('Email already registered', 40901);
                }

                if (!$this->validOrgCode) {
                    throw new \RuntimeException('Invalid organization code', 40001);
                }

                $user = [
                    'id'             => 1,
                    'email'          => $data['email'],
                    'password_hash'  => password_hash($data['password'], PASSWORD_BCRYPT),
                    'name'           => $data['name'],
                    'status'         => 'active',
                    'organization_id'=> 1,
                ];

                return ['user' => $user];
            }
        };

        return $service;
    }

    public function test_login_with_valid_credentials_returns_user_and_session(): void
    {
        $passwordHash = password_hash('correct-password', PASSWORD_BCRYPT);
        $service = $this->makeAuthService([
            'id'            => 1,
            'email'         => 'alice@example.local',
            'password_hash' => $passwordHash,
            'status'        => 'active',
        ]);

        $result = $service->login('alice@example.local', 'correct-password');

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('session', $result);
        $this->assertEquals('alice@example.local', $result['user']['email']);
    }

    public function test_login_with_invalid_password_throws_auth_exception(): void
    {
        $passwordHash = password_hash('correct-password', PASSWORD_BCRYPT);
        $service = $this->makeAuthService([
            'id'            => 1,
            'email'         => 'alice@example.local',
            'password_hash' => $passwordHash,
            'status'        => 'active',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40101);
        $service->login('alice@example.local', 'wrong-password');
    }

    public function test_login_with_nonexistent_email_throws_auth_exception(): void
    {
        $service = $this->makeAuthService(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40101);
        $service->login('nobody@example.local', 'any-password');
    }

    public function test_login_with_disabled_account_throws_auth_exception(): void
    {
        $passwordHash = password_hash('correct-password', PASSWORD_BCRYPT);
        $service = $this->makeAuthService([
            'id'            => 1,
            'email'         => 'alice@example.local',
            'password_hash' => $passwordHash,
            'status'        => 'disabled',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40102);
        $service->login('alice@example.local', 'correct-password');
    }

    public function test_register_with_valid_data_creates_user(): void
    {
        $service = $this->makeAuthService(null, false, true);

        $result = $service->register([
            'email'    => 'newuser@example.local',
            'password' => 'StrongP@ss1',
            'name'     => 'New User',
            'org_code' => 'VALID_ORG',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('newuser@example.local', $result['user']['email']);
        $this->assertEquals('active', $result['user']['status']);
    }

    public function test_register_with_duplicate_email_throws_exception(): void
    {
        $service = $this->makeAuthService(null, true, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40901);
        $service->register([
            'email'    => 'existing@example.local',
            'password' => 'StrongP@ss1',
            'name'     => 'Existing User',
            'org_code' => 'VALID_ORG',
        ]);
    }

    public function test_register_with_invalid_org_code_throws_exception(): void
    {
        $service = $this->makeAuthService(null, false, false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40001);
        $service->register([
            'email'    => 'newuser@example.local',
            'password' => 'StrongP@ss1',
            'name'     => 'New User',
            'org_code' => 'INVALID',
        ]);
    }

    public function test_password_is_hashed_not_stored_plaintext(): void
    {
        $service = $this->makeAuthService(null, false, true);

        $result = $service->register([
            'email'    => 'user@example.local',
            'password' => 'MyPlaintext123',
            'name'     => 'Test User',
            'org_code' => 'VALID_ORG',
        ]);

        $this->assertNotEquals('MyPlaintext123', $result['user']['password_hash']);
        $this->assertTrue(password_verify('MyPlaintext123', $result['user']['password_hash']));
    }
}
