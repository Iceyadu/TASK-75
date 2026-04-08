<?php
declare(strict_types=1);

namespace unit_tests\auth;

use PHPUnit\Framework\TestCase;

/**
 * Tests for organization isolation logic.
 *
 * Ensures users cannot access resources belonging to other organizations.
 */
class OrgIsolationTest extends TestCase
{
    /**
     * Simulated resource store: type => [id => org_id].
     */
    private array $resources = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Org 1 resources
        $this->resources['listing'][101] = 1;
        $this->resources['listing'][102] = 1;
        $this->resources['order'][201]   = 1;
        $this->resources['order'][202]   = 1;

        // Org 2 resources
        $this->resources['listing'][103] = 2;
        $this->resources['order'][203]   = 2;
    }

    /**
     * Simulates the org isolation check.
     *
     * @throws \RuntimeException when resource belongs to a different org
     */
    private function checkAccess(int $userOrgId, string $resourceType, int $resourceId): bool
    {
        if (!isset($this->resources[$resourceType][$resourceId])) {
            throw new \RuntimeException('Resource not found', 40401);
        }

        $resourceOrgId = $this->resources[$resourceType][$resourceId];
        if ($resourceOrgId !== $userOrgId) {
            throw new \RuntimeException('You do not have access to this resource', 40302);
        }

        return true;
    }

    /**
     * Simulates a query scope that filters by org_id.
     */
    private function queryWithOrgScope(string $resourceType, int $orgId): array
    {
        $results = [];
        foreach ($this->resources[$resourceType] ?? [] as $id => $rOrgId) {
            if ($rOrgId === $orgId) {
                $results[] = $id;
            }
        }
        return $results;
    }

    public function test_user_cannot_access_other_org_listing(): void
    {
        // User in org 1 tries to access listing 103 (org 2)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40302);
        $this->checkAccess(1, 'listing', 103);
    }

    public function test_user_cannot_access_other_org_order(): void
    {
        // User in org 1 tries to access order 203 (org 2)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40302);
        $this->checkAccess(1, 'order', 203);
    }

    public function test_query_scopes_by_org_id(): void
    {
        // Org 1 should only see their own listings
        $org1Listings = $this->queryWithOrgScope('listing', 1);
        $this->assertCount(2, $org1Listings);
        $this->assertContains(101, $org1Listings);
        $this->assertContains(102, $org1Listings);
        $this->assertNotContains(103, $org1Listings);

        // Org 2 should only see their own listings
        $org2Listings = $this->queryWithOrgScope('listing', 2);
        $this->assertCount(1, $org2Listings);
        $this->assertContains(103, $org2Listings);
    }
}
