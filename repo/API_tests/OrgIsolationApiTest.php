<?php

namespace API_tests;

use PHPUnit\Framework\TestCase;

/**
 * Defence-in-depth: authenticated users must not read other organizations' resources.
 *
 * Requires listing id {@see OTHER_ORG_LISTING_ID} in a second tenant (seed: RC2026B / EnsureSecondOrganization).
 */
class OrgIsolationApiTest extends TestCase
{
    protected static string $baseUrl;
    protected static ?string $org1UserToken = null;
    protected static string $cookieFile;

    /** Listing owned by organization 2 (fixture). */
    private const OTHER_ORG_LISTING_ID = 4;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl    = rtrim(getenv('API_BASE_URL') ?: 'http://localhost:8081', '/');
        self::$cookieFile = tempnam(sys_get_temp_dir(), 'rc_orgiso_');
        self::$org1UserToken = self::loginOrg1User();
    }

    protected static function loginOrg1User(): string
    {
        $ch = curl_init(self::$baseUrl . '/api/auth/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS     => json_encode([
                'email'               => getenv('TEST_USER_EMAIL') ?: 'alice@ridecircle.local',
                'password'            => getenv('TEST_USER_PASSWORD') ?: 'Alice123!',
                'organization_code'   => getenv('TEST_ORG_CODE') ?: 'RC2026',
            ]),
            CURLOPT_COOKIEFILE     => self::$cookieFile,
            CURLOPT_COOKIEJAR      => self::$cookieFile,
        ]);
        $body = json_decode((string) curl_exec($ch), true);
        curl_close($ch);

        return $body['data']['token'] ?? '';
    }

    protected function apiGet(string $path, ?string $token = null): array
    {
        $url = self::$baseUrl . $path;
        $ch  = curl_init();
        $headers = ['Accept: application/json'];
        $t = $token ?? self::$org1UserToken;
        if ($t !== '') {
            $headers[] = 'Authorization: Bearer ' . $t;
        }
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_COOKIEFILE     => self::$cookieFile,
            CURLOPT_COOKIEJAR      => self::$cookieFile,
        ]);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body'   => json_decode((string) $raw, true) ?? [],
        ];
    }

    public function test_listing_from_other_org_returns_403(): void
    {
        $this->assertNotEmpty(self::$org1UserToken, 'Login as org1 user must return data.token');

        $res = $this->apiGet('/api/listings/' . self::OTHER_ORG_LISTING_ID);

        $this->assertSame(
            403,
            $res['status'],
            'GET listing in another organization must return 403 (OrgIsolationMiddleware / controller check).'
        );
        $this->assertNotEmpty($res['body']['message'] ?? $res['body']['msg'] ?? '');
    }

    public function test_order_index_excludes_other_organization(): void
    {
        $res = $this->apiGet('/api/orders');
        $this->assertEquals(200, $res['status']);
        $orders = $res['body']['data'] ?? [];
        $this->assertIsArray($orders);
        foreach ($orders as $row) {
            if (isset($row['organization_id'])) {
                $this->assertSame(1, (int) $row['organization_id'], 'Orders list must stay within the user org');
            }
        }
    }
}
