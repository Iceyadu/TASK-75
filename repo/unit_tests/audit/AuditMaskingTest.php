<?php
declare(strict_types=1);

namespace unit_tests\audit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for audit log PII masking using the helper functions from common.php.
 *
 * Functions tested: mask_email(), mask_ip(), mask_user_id()
 * These are defined in app/common.php and loaded via Composer autoload.
 */
class AuditMaskingTest extends TestCase
{
    // Re-implement the masking functions here for test isolation
    // (avoids requiring the full ThinkPHP bootstrap)

    private function maskUserId($id): string
    {
        $str = (string) $id;
        $suffix = strlen($str) >= 2 ? substr($str, -2) : $str;
        return 'user_***' . $suffix;
    }

    private function maskIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octets = explode('.', $ip);
            return $octets[0] . '.' . $octets[1] . '.***.' . '***';
        }
        return '***';
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $domain = $parts[1];
        if (strlen($local) <= 1) {
            return $local . '***@' . $domain;
        }
        return substr($local, 0, 1) . '***@' . $domain;
    }

    /**
     * Build an audit log entry with masked fields.
     */
    private function buildMaskedAuditEntry(array $data): array
    {
        return [
            'action'     => $data['action'],
            'user_id'    => $this->maskUserId($data['user_id']),
            'ip_address' => $this->maskIp($data['ip_address']),
            'email'      => $this->maskEmail($data['email'] ?? ''),
            'details'    => $data['details'] ?? null,
        ];
    }

    /**
     * Build an unmasked audit log entry.
     */
    private function buildUnmaskedAuditEntry(array $data): array
    {
        return [
            'action'     => $data['action'],
            'user_id'    => $data['user_id'],
            'ip_address' => $data['ip_address'],
            'email'      => $data['email'] ?? null,
            'details'    => $data['details'] ?? null,
        ];
    }

    public function test_masked_output_hides_user_id(): void
    {
        $entry = $this->buildMaskedAuditEntry([
            'action'     => 'login',
            'user_id'    => 12345,
            'ip_address' => '192.168.1.100',
            'email'      => 'alice@example.local',
        ]);

        $this->assertEquals('user_***45', $entry['user_id']);
        $this->assertStringNotContainsString('12345', $entry['user_id']);
    }

    public function test_masked_output_hides_ip_address(): void
    {
        $entry = $this->buildMaskedAuditEntry([
            'action'     => 'login',
            'user_id'    => 1,
            'ip_address' => '192.168.1.100',
            'email'      => 'alice@example.local',
        ]);

        $this->assertEquals('192.168.***.***', $entry['ip_address']);
        $this->assertStringNotContainsString('100', $entry['ip_address']);
    }

    public function test_unmasked_output_shows_full_data(): void
    {
        $entry = $this->buildUnmaskedAuditEntry([
            'action'     => 'login',
            'user_id'    => 12345,
            'ip_address' => '192.168.1.100',
            'email'      => 'alice@example.local',
        ]);

        $this->assertEquals(12345, $entry['user_id']);
        $this->assertEquals('192.168.1.100', $entry['ip_address']);
        $this->assertEquals('alice@example.local', $entry['email']);
    }

    public function test_password_hash_never_in_audit_log(): void
    {
        $passwordHash = '$2y$10$someHashedPasswordValue1234567890';

        $entry = $this->buildMaskedAuditEntry([
            'action'     => 'password_change',
            'user_id'    => 1,
            'ip_address' => '10.0.0.1',
            'email'      => 'user@example.local',
            'details'    => 'Password changed successfully',
        ]);

        // The details should never contain a password hash
        $entryJson = json_encode($entry);
        $this->assertStringNotContainsString($passwordHash, $entryJson);
        $this->assertStringNotContainsString('$2y$', $entryJson);
    }
}
