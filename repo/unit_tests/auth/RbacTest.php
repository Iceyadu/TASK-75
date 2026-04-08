<?php
declare(strict_types=1);

namespace unit_tests\auth;

use PHPUnit\Framework\TestCase;

/**
 * Tests for RBAC permission logic.
 *
 * Simulates the role-permission model without a database.
 */
class RbacTest extends TestCase
{
    /**
     * Role-to-permission mapping matching the RideCircle RBAC model.
     */
    private const ROLE_PERMISSIONS = [
        'administrator' => ['*'], // All permissions
        'moderator' => [
            'moderation.read',
            'moderation.update',
            'listing.create',
            'listing.update',
            'listing.delete',
            'order.read',
            'review.read',
        ],
        'driver' => [
            'listing.create',
            'listing.update',
            'listing.delete',
            'order.read',
            'order.create',
            'review.create',
            'review.read',
        ],
        'regular_user' => [
            'listing.read',
            'order.read',
            'order.create',
            'review.create',
            'review.read',
        ],
    ];

    /**
     * Check if a user with given roles has a specific permission.
     */
    private function hasPermission(array $roles, string $permission): bool
    {
        foreach ($roles as $role) {
            $perms = self::ROLE_PERMISSIONS[$role] ?? [];
            if (in_array('*', $perms, true)) {
                return true;
            }
            if (in_array($permission, $perms, true)) {
                return true;
            }
        }
        return false;
    }

    public function test_admin_has_all_permissions(): void
    {
        $this->assertTrue($this->hasPermission(['administrator'], 'moderation.read'));
        $this->assertTrue($this->hasPermission(['administrator'], 'governance.view_dashboard'));
        $this->assertTrue($this->hasPermission(['administrator'], 'audit.read'));
        $this->assertTrue($this->hasPermission(['administrator'], 'user.manage'));
        $this->assertTrue($this->hasPermission(['administrator'], 'any.thing'));
    }

    public function test_moderator_has_moderation_permissions(): void
    {
        $this->assertTrue($this->hasPermission(['moderator'], 'moderation.read'));
        $this->assertTrue($this->hasPermission(['moderator'], 'moderation.update'));
    }

    public function test_regular_user_lacks_admin_permissions(): void
    {
        $this->assertFalse($this->hasPermission(['regular_user'], 'moderation.read'));
        $this->assertFalse($this->hasPermission(['regular_user'], 'governance.view_dashboard'));
        $this->assertFalse($this->hasPermission(['regular_user'], 'audit.read'));
        $this->assertFalse($this->hasPermission(['regular_user'], 'user.manage'));
        $this->assertFalse($this->hasPermission(['regular_user'], 'listing.create'));
    }

    public function test_user_with_multiple_roles_has_union_of_permissions(): void
    {
        // A user who is both a driver and moderator
        $roles = ['driver', 'moderator'];

        // From driver role
        $this->assertTrue($this->hasPermission($roles, 'order.create'));
        // From moderator role
        $this->assertTrue($this->hasPermission($roles, 'moderation.read'));
        $this->assertTrue($this->hasPermission($roles, 'moderation.update'));
        // Neither role has admin permissions
        $this->assertFalse($this->hasPermission($roles, 'governance.view_dashboard'));
        $this->assertFalse($this->hasPermission($roles, 'user.manage'));
    }
}
