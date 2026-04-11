<?php

/**
 * RideCircle Database Seeder
 *
 * Run via: php database/seeds/DatabaseSeeder.php | mysql -u root ridecircle
 * Or source the output SQL directly.
 *
 * Passwords are pre-hashed with bcrypt (cost 10).
 */

// Pre-computed bcrypt hashes (cost=10)
$passwords = [
    'Admin123!'  => password_hash('Admin123!', PASSWORD_BCRYPT, ['cost' => 10]),
    'Mod12345!'  => password_hash('Mod12345!', PASSWORD_BCRYPT, ['cost' => 10]),
    'Alice123!'  => password_hash('Alice123!', PASSWORD_BCRYPT, ['cost' => 10]),
    'Bob12345!'  => password_hash('Bob12345!', PASSWORD_BCRYPT, ['cost' => 10]),
    'Carol123!'  => password_hash('Carol123!', PASSWORD_BCRYPT, ['cost' => 10]),
];

$now = gmdate('Y-m-d H:i:s');

$sql = <<<SQL
-- ============================================================
-- RideCircle Database Seed Data
-- Generated: {$now}
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- Organization
-- -----------------------------------------------------------
INSERT INTO `organizations` (`id`, `name`, `code`, `settings`, `created_at`, `updated_at`) VALUES
(1, 'RideCircle Community', 'RC2026', '{"max_listings_per_user": 50, "require_review_moderation": false}', '{$now}', '{$now}'),
(2, 'Eastside Co-op', 'RC2026B', '{"max_listings_per_user": 50, "require_review_moderation": false}', '{$now}', '{$now}');

-- -----------------------------------------------------------
-- Roles
-- -----------------------------------------------------------
INSERT INTO `roles` (`id`, `organization_id`, `name`, `slug`, `description`) VALUES
(1, 1, 'Administrator', 'admin',     'Full system administrator with all permissions'),
(2, 1, 'Moderator',     'moderator', 'Content moderator with review and moderation capabilities'),
(3, 1, 'User',          'user',      'Standard user with basic listing and order permissions'),
(4, 2, 'Administrator', 'admin',     'Full system administrator with all permissions'),
(5, 2, 'Moderator',     'moderator', 'Content moderator with review and moderation capabilities'),
(6, 2, 'User',          'user',      'Standard user with basic listing and order permissions');

-- -----------------------------------------------------------
-- Permissions
-- -----------------------------------------------------------
INSERT INTO `permissions` (`id`, `resource`, `action`) VALUES
( 1, 'listing',        'create'),
( 2, 'listing',        'read'),
( 3, 'listing',        'update'),
( 4, 'listing',        'delete'),
( 5, 'listing',        'moderate'),
( 6, 'listing',        'admin'),
( 7, 'order',          'create'),
( 8, 'order',          'read'),
( 9, 'order',          'update'),
(10, 'review',         'create'),
(11, 'review',         'read'),
(12, 'review',         'moderate'),
(13, 'media',          'upload'),
(14, 'moderation',     'read'),
(15, 'moderation',     'update'),
(16, 'governance',     'view_dashboard'),
(17, 'audit',          'read'),
(18, 'audit',          'read_unmasked'),
(19, 'org_settings',   'read'),
(20, 'org_settings',   'update'),
(21, 'role',           'manage'),
(22, 'dispute',        'resolve'),
(23, 'user',           'manage'),
(24, 'api_token',      'create'),
(25, 'api_token',      'read'),
(26, 'api_token',      'revoke');

-- -----------------------------------------------------------
-- Role-Permission Mappings
-- -----------------------------------------------------------
-- Admin: ALL permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1,  1), (1,  2), (1,  3), (1,  4), (1,  5), (1,  6),
(1,  7), (1,  8), (1,  9), (1, 10), (1, 11), (1, 12),
(1, 13), (1, 14), (1, 15), (1, 16), (1, 17), (1, 18),
(1, 19), (1, 20), (1, 21), (1, 22), (1, 23), (1, 24),
(1, 25), (1, 26);

-- Moderator: listing CRUD + moderate, order read, review read + moderate,
--            media upload, moderation read + update, governance dashboard,
--            audit read (masked), dispute resolve
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2,  1), (2,  2), (2,  3), (2,  5),
(2,  8), (2, 10), (2, 11), (2, 12),
(2, 13), (2, 14), (2, 15), (2, 16),
(2, 17), (2, 22), (2, 24), (2, 25), (2, 26);

-- User: listing create/read/update, order create/read/update,
--       review create/read, media upload, api_token create/read/revoke
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3,  1), (3,  2), (3,  3),
(3,  7), (3,  8), (3,  9),
(3, 10), (3, 11),
(3, 13), (3, 24), (3, 25), (3, 26);

-- Org 2 roles mirror org 1 (admin / moderator / user)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `permission_id` FROM `role_permissions` WHERE `role_id` = 1;
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, `permission_id` FROM `role_permissions` WHERE `role_id` = 2;
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, `permission_id` FROM `role_permissions` WHERE `role_id` = 3;

-- -----------------------------------------------------------
-- Users
-- -----------------------------------------------------------
INSERT INTO `users` (`id`, `organization_id`, `name`, `email`, `password_hash`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'System Admin',     'admin@ridecircle.local',     '{$passwords['Admin123!']}',  'active', '{$now}', '{$now}'),
(2, 1, 'Content Moderator','moderator@ridecircle.local', '{$passwords['Mod12345!']}',  'active', '{$now}', '{$now}'),
(3, 1, 'Alice Johnson',    'alice@ridecircle.local',     '{$passwords['Alice123!']}',  'active', '{$now}', '{$now}'),
(4, 1, 'Bob Williams',     'bob@ridecircle.local',       '{$passwords['Bob12345!']}',  'active', '{$now}', '{$now}'),
(5, 2, 'Carol Other',      'carol@otherorg.local',       '{$passwords['Carol123!']}',  'active', '{$now}', '{$now}');

-- -----------------------------------------------------------
-- User-Role Assignments
-- -----------------------------------------------------------
INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 3),
(5, 6);

-- -----------------------------------------------------------
-- Sample Listings (by Alice, user_id=3)
-- -----------------------------------------------------------
INSERT INTO `listings` (`id`, `organization_id`, `user_id`, `title`, `description`, `pickup_address`, `dropoff_address`, `rider_count`, `vehicle_type`, `baggage_notes`, `time_window_start`, `time_window_end`, `tags`, `status`, `version`, `view_count`, `favorite_count`, `comment_count`, `last_activity_at`, `data_quality_score`, `created_at`, `updated_at`) VALUES
(1, 1, 3, 'Morning Commute Downtown', 'Daily morning commute from suburbs to downtown financial district. Comfortable sedan, usually depart around 7:30 AM.', '42 Oak Lane, Suburbia', '100 Finance Blvd, Downtown', 2, 'sedan', 'Small backpack or briefcase only', '{$now}', DATE_ADD('{$now}', INTERVAL 2 HOUR), '["commute", "daily", "morning"]', 'active', 1, 15, 3, 1, '{$now}', 0.9200, '{$now}', '{$now}'),
(2, 1, 3, 'Weekend Airport Shuttle', 'Weekend trip to the airport. Can accommodate larger luggage. SUV with plenty of trunk space.', '42 Oak Lane, Suburbia', 'International Airport Terminal 2', 3, 'suv', 'Two large suitcases OK', DATE_ADD('{$now}', INTERVAL 3 DAY), DATE_ADD(DATE_ADD('{$now}', INTERVAL 3 DAY), INTERVAL 4 HOUR), '["airport", "weekend", "luggage"]', 'draft', 1, 0, 0, 0, NULL, NULL, '{$now}', '{$now}'),
(3, 1, 3, 'Evening Return from Campus', 'Returning from university campus after evening classes. Van available for group rides.', 'State University Main Gate', '42 Oak Lane, Suburbia', 4, 'van', NULL, DATE_ADD('{$now}', INTERVAL 1 DAY), DATE_ADD(DATE_ADD('{$now}', INTERVAL 1 DAY), INTERVAL 3 HOUR), '["university", "evening", "group"]', 'completed', 1, 42, 7, 5, '{$now}', 0.8750, '{$now}', '{$now}'),
(4, 2, 5, 'Cross-tenant fixture listing', 'Used by API tests for organization isolation.', '100 East Ave', '200 West St', 2, 'sedan', NULL, '{$now}', DATE_ADD('{$now}', INTERVAL 2 HOUR), '["fixture", "isolation"]', 'active', 1, 0, 0, 0, NULL, NULL, '{$now}', '{$now}');

-- -----------------------------------------------------------
-- Sample Order (Bob as passenger on listing 1, Alice as driver)
-- -----------------------------------------------------------
INSERT INTO `orders` (`id`, `organization_id`, `listing_id`, `passenger_id`, `driver_id`, `status`, `driver_notes`, `created_at`, `accepted_at`, `expires_at`) VALUES
(1, 1, 1, 4, 3, 'accepted', 'Will pick up at the corner of Oak and Main.', '{$now}', '{$now}', DATE_ADD('{$now}', INTERVAL 24 HOUR));

SET FOREIGN_KEY_CHECKS = 1;
SQL;

echo $sql . "\n";
