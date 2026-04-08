-- ============================================================
-- RideCircle Carpool Marketplace - Complete Database Schema
-- MySQL 8.0+  |  Charset: utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- Organizations
-- -----------------------------------------------------------
CREATE TABLE `organizations` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(200)    NOT NULL,
    `code`       VARCHAR(50)     NOT NULL,
    `settings`   JSON            NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_organizations_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Users
-- -----------------------------------------------------------
CREATE TABLE `users` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id` BIGINT UNSIGNED NOT NULL,
    `name`            VARCHAR(100)    NOT NULL,
    `email`           VARCHAR(255)    NOT NULL,
    `password_hash`   VARCHAR(255)    NOT NULL,
    `status`          ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_org_email` (`organization_id`, `email`),
    CONSTRAINT `fk_users_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Roles
-- -----------------------------------------------------------
CREATE TABLE `roles` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `organization_id`      BIGINT UNSIGNED NOT NULL,
    `name`        VARCHAR(50)     NOT NULL,
    `slug`        VARCHAR(50)     NOT NULL,
    `description` VARCHAR(255)    NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_roles_org_name` (`organization_id`, `name`),
    UNIQUE KEY `uk_roles_org_slug` (`organization_id`, `slug`),
    CONSTRAINT `fk_roles_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Permissions
-- -----------------------------------------------------------
CREATE TABLE `permissions` (
    `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `resource` VARCHAR(50)  NOT NULL,
    `action`   VARCHAR(50)  NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_permissions_resource_action` (`resource`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Role-Permission pivot
-- -----------------------------------------------------------
CREATE TABLE `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- User-Role pivot
-- -----------------------------------------------------------
CREATE TABLE `user_roles` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED    NOT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- User Sessions
-- -----------------------------------------------------------
CREATE TABLE `user_sessions` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `session_id`  VARCHAR(128)    NOT NULL,
    `ip_address`  VARCHAR(45)     NULL,
    `user_agent`  VARCHAR(512)    NULL,
    `last_active` DATETIME        NULL,
    `expires_at`  DATETIME        NOT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_sessions_session_id` (`session_id`),
    INDEX `idx_user_sessions_user_id` (`user_id`),
    CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- API Tokens
-- -----------------------------------------------------------
CREATE TABLE `api_tokens` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `name`         VARCHAR(100)    NOT NULL,
    `token_hash`   VARCHAR(64)     NOT NULL,
    `last_used_at` DATETIME        NULL,
    `expires_at`   DATETIME        NOT NULL,
    `revoked_at`   DATETIME        NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_api_tokens_token_hash` (`token_hash`),
    INDEX `idx_api_tokens_user_id` (`user_id`),
    CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Listings
-- -----------------------------------------------------------
CREATE TABLE `listings` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`             BIGINT UNSIGNED NOT NULL,
    `user_id`            BIGINT UNSIGNED NOT NULL,
    `title`              VARCHAR(200)    NOT NULL,
    `description`        TEXT            NULL,
    `pickup_address`     VARCHAR(500)    NOT NULL,
    `dropoff_address`    VARCHAR(500)    NOT NULL,
    `rider_count`        TINYINT UNSIGNED NOT NULL,
    `vehicle_type`       ENUM('sedan','suv','van') NOT NULL,
    `baggage_notes`      VARCHAR(500)    NULL,
    `time_window_start`  DATETIME        NOT NULL,
    `time_window_end`    DATETIME        NOT NULL,
    `tags`               JSON            NULL,
    `status`             ENUM('draft','active','matched','in_progress','completed','canceled','disputed','closed','resolved') NOT NULL DEFAULT 'draft',
    `version`            INT UNSIGNED    NOT NULL DEFAULT 1,
    `view_count`         INT UNSIGNED    NOT NULL DEFAULT 0,
    `favorite_count`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `comment_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `last_activity_at`   DATETIME        NULL,
    `data_quality_score` DECIMAL(5,4)    NULL,
    `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_listings_org_status` (`organization_id`, `status`),
    FULLTEXT INDEX `ft_listings_title_description` (`title`, `description`),
    CONSTRAINT `fk_listings_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_listings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Listing Versions
-- -----------------------------------------------------------
CREATE TABLE `listing_versions` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id`      BIGINT UNSIGNED NOT NULL,
    `version`         INT UNSIGNED    NOT NULL,
    `snapshot`        JSON            NOT NULL,
    `change_summary`  TEXT            NULL,
    `created_by`      BIGINT UNSIGNED NOT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_listing_versions_listing_version` (`listing_id`, `version`),
    CONSTRAINT `fk_listing_versions_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_listing_versions_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Orders
-- -----------------------------------------------------------
CREATE TABLE `orders` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`              BIGINT UNSIGNED NOT NULL,
    `listing_id`          BIGINT UNSIGNED NOT NULL,
    `passenger_id`        BIGINT UNSIGNED NOT NULL,
    `driver_id`           BIGINT UNSIGNED NOT NULL,
    `status`              ENUM('pending_match','accepted','in_progress','completed','canceled','expired','disputed','resolved') NOT NULL DEFAULT 'pending_match',
    `driver_notes`        VARCHAR(500)    NULL,
    `cancel_reason_code`  VARCHAR(50)     NULL,
    `cancel_reason_text`  TEXT            NULL,
    `dispute_reason`      TEXT            NULL,
    `resolution_notes`    TEXT            NULL,
    `resolution_outcome`  ENUM('passenger_favor','driver_favor','mutual','dismissed') NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `accepted_at`         DATETIME        NULL,
    `started_at`          DATETIME        NULL,
    `completed_at`        DATETIME        NULL,
    `canceled_at`         DATETIME        NULL,
    `disputed_at`         DATETIME        NULL,
    `resolved_at`         DATETIME        NULL,
    `expires_at`          DATETIME        NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_orders_org_status` (`organization_id`, `status`),
    INDEX `idx_orders_passenger` (`passenger_id`),
    INDEX `idx_orders_driver` (`driver_id`),
    INDEX `idx_orders_listing` (`listing_id`),
    CONSTRAINT `fk_orders_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_orders_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_orders_passenger` FOREIGN KEY (`passenger_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_orders_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Reviews
-- -----------------------------------------------------------
CREATE TABLE `reviews` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`            BIGINT UNSIGNED NOT NULL,
    `order_id`          BIGINT UNSIGNED NOT NULL,
    `listing_id`        BIGINT UNSIGNED NULL,
    `user_id`           BIGINT UNSIGNED NOT NULL,
    `rating`            TINYINT UNSIGNED NOT NULL,
    `text`              TEXT            NOT NULL,
    `credibility_score` DECIMAL(5,4)    NULL,
    `status`            ENUM('published','pending','hidden','rejected') NOT NULL DEFAULT 'published',
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_reviews_org` (`organization_id`),
    INDEX `idx_reviews_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_reviews_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Media
-- -----------------------------------------------------------
CREATE TABLE `media` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`      BIGINT UNSIGNED NOT NULL,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `parent_type` VARCHAR(30)     NOT NULL,
    `parent_id`   BIGINT UNSIGNED NOT NULL,
    `file_name`   VARCHAR(255)    NOT NULL,
    `file_path`   VARCHAR(512)    NOT NULL,
    `file_hash`   VARCHAR(64)     NOT NULL,
    `file_size`   BIGINT UNSIGNED NOT NULL,
    `mime_type`   VARCHAR(100)    NOT NULL,
    `file_type`   ENUM('photo','video') NOT NULL,
    `watermarked` TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_media_parent` (`parent_type`, `parent_id`),
    INDEX `idx_media_user_hash` (`user_id`, `file_hash`),
    CONSTRAINT `fk_media_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_media_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Moderation Queue
-- -----------------------------------------------------------
CREATE TABLE `moderation_queue` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`            BIGINT UNSIGNED NOT NULL,
    `item_type`         VARCHAR(30)     NOT NULL,
    `item_id`           BIGINT UNSIGNED NOT NULL,
    `flag_reason`       VARCHAR(50)     NOT NULL,
    `flag_details`      TEXT            NULL,
    `credibility_score` DECIMAL(5,4)    NULL,
    `status`            ENUM('pending','approved','rejected','escalated') NOT NULL DEFAULT 'pending',
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_moderation_queue_org_status` (`organization_id`, `status`),
    CONSTRAINT `fk_moderation_queue_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Moderation Actions
-- -----------------------------------------------------------
CREATE TABLE `moderation_actions` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_id`     BIGINT UNSIGNED NOT NULL,
    `moderator_id` BIGINT UNSIGNED NOT NULL,
    `action`       ENUM('approve','reject','escalate') NOT NULL,
    `reason`       TEXT            NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_moderation_actions_queue` FOREIGN KEY (`queue_id`) REFERENCES `moderation_queue` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_moderation_actions_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Behavior Events
-- -----------------------------------------------------------
CREATE TABLE `behavior_events` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`      BIGINT UNSIGNED NOT NULL,
    `user_id`     BIGINT UNSIGNED NULL,
    `event_type`  VARCHAR(30)     NOT NULL,
    `target_type` VARCHAR(30)     NOT NULL,
    `target_id`   BIGINT UNSIGNED NULL,
    `metadata`    JSON            NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_behavior_events_org_type_created` (`organization_id`, `event_type`, `created_at`),
    INDEX `idx_behavior_events_user_type_target_created` (`user_id`, `event_type`, `target_type`, `target_id`, `created_at`),
    CONSTRAINT `fk_behavior_events_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Audit Logs
-- -----------------------------------------------------------
CREATE TABLE `audit_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`        BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `action`        VARCHAR(50)     NOT NULL,
    `resource_type` VARCHAR(50)     NOT NULL,
    `resource_id`   BIGINT UNSIGNED NULL,
    `old_value`     JSON            NULL,
    `new_value`     JSON            NULL,
    `ip_address`    VARCHAR(45)     NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_logs_org_created` (`organization_id`, `created_at`),
    INDEX `idx_audit_logs_user` (`user_id`),
    CONSTRAINT `fk_audit_logs_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Data Lineage
-- -----------------------------------------------------------
CREATE TABLE `data_lineage` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`        BIGINT UNSIGNED NULL,
    `job_name`      VARCHAR(50)     NOT NULL,
    `run_id`        VARCHAR(36)     NOT NULL,
    `step`          VARCHAR(100)    NOT NULL,
    `input_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `output_count`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `removed_count` INT UNSIGNED    NOT NULL DEFAULT 0,
    `details`       JSON            NULL,
    `executed_at`   DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_data_lineage_org_job_executed` (`organization_id`, `job_name`, `executed_at`),
    CONSTRAINT `fk_data_lineage_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Data Quality Metrics
-- -----------------------------------------------------------
CREATE TABLE `data_quality_metrics` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`       BIGINT UNSIGNED NOT NULL,
    `metric_date`  DATE            NOT NULL,
    `metric_name`  VARCHAR(50)     NOT NULL,
    `metric_value` DECIMAL(10,4)   NOT NULL,
    `details`      JSON            NULL,
    `computed_at`  DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_data_quality_org_date_name` (`organization_id`, `metric_date`, `metric_name`),
    CONSTRAINT `fk_data_quality_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Search Dictionary
-- -----------------------------------------------------------
CREATE TABLE `search_dictionary` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`     BIGINT UNSIGNED NOT NULL,
    `word`       VARCHAR(100)    NOT NULL,
    `frequency`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_search_dictionary_org_word` (`organization_id`, `word`),
    CONSTRAINT `fk_search_dictionary_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
