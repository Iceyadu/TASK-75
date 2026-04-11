<?php

/**
 * Idempotent fixture: second organization + user + listing for cross-tenant tests.
 *
 * Safe to run when DB was seeded with an older single-org DatabaseSeeder.
 * Usage: php database/seeds/EnsureSecondOrganization.php | mysql ...
 */

$passwords = [
    'Carol123!' => password_hash('Carol123!', PASSWORD_BCRYPT, ['cost' => 10]),
];

$now = gmdate('Y-m-d H:i:s');
$h   = $passwords['Carol123!'];

$sql = <<<SQL
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT IGNORE INTO `organizations` (`id`, `name`, `code`, `settings`, `created_at`, `updated_at`) VALUES
(2, 'Eastside Co-op', 'RC2026B', '{"max_listings_per_user": 50, "require_review_moderation": false}', '{$now}', '{$now}');

INSERT IGNORE INTO `roles` (`id`, `organization_id`, `name`, `slug`, `description`) VALUES
(4, 2, 'Administrator', 'admin',     'Full system administrator with all permissions'),
(5, 2, 'Moderator',     'moderator', 'Content moderator with review and moderation capabilities'),
(6, 2, 'User',          'user',      'Standard user with basic listing and order permissions');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, p.`permission_id` FROM `role_permissions` p WHERE p.`role_id` = 1
AND NOT EXISTS (SELECT 1 FROM `role_permissions` x WHERE x.`role_id` = 4 AND x.`permission_id` = p.`permission_id`);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, p.`permission_id` FROM `role_permissions` p WHERE p.`role_id` = 2
AND NOT EXISTS (SELECT 1 FROM `role_permissions` x WHERE x.`role_id` = 5 AND x.`permission_id` = p.`permission_id`);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, p.`permission_id` FROM `role_permissions` p WHERE p.`role_id` = 3
AND NOT EXISTS (SELECT 1 FROM `role_permissions` x WHERE x.`role_id` = 6 AND x.`permission_id` = p.`permission_id`);

INSERT IGNORE INTO `users` (`id`, `organization_id`, `name`, `email`, `password_hash`, `status`, `created_at`, `updated_at`) VALUES
(5, 2, 'Carol Other', 'carol@otherorg.local', '{$h}', 'active', '{$now}', '{$now}');

INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (5, 6);

INSERT IGNORE INTO `listings` (`id`, `organization_id`, `user_id`, `title`, `description`, `pickup_address`, `dropoff_address`, `rider_count`, `vehicle_type`, `baggage_notes`, `time_window_start`, `time_window_end`, `tags`, `status`, `version`, `view_count`, `favorite_count`, `comment_count`, `last_activity_at`, `data_quality_score`, `created_at`, `updated_at`) VALUES
(4, 2, 5, 'Cross-tenant fixture listing', 'Used by API tests for organization isolation.', '100 East Ave', '200 West St', 2, 'sedan', NULL, '{$now}', DATE_ADD('{$now}', INTERVAL 2 HOUR), '["fixture", "isolation"]', 'active', 1, 0, 0, 0, NULL, NULL, '{$now}', '{$now}');

SET FOREIGN_KEY_CHECKS = 1;
SQL;

echo $sql . "\n";
