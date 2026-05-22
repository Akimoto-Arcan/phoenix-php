-- Phoenix-PHP Core Schema
-- Run during installation to create all core framework tables

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL,
    `full_name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(32) NOT NULL DEFAULT 'Operator',
    `groups` VARCHAR(500) DEFAULT NULL COMMENT 'Comma-separated group names',
    `shift` VARCHAR(10) DEFAULT NULL,
    `mobile_whitelisted` TINYINT(1) NOT NULL DEFAULT 0,
    `approved` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `reset` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Force password reset on next login',
    `session_timeout` INT UNSIGNED DEFAULT NULL COMMENT 'Custom timeout in minutes',
    `theme_preference` ENUM('light','dark','system') DEFAULT 'system',
    `last_login` DATETIME DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    `failed_login_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_username` (`username`),
    INDEX `idx_role` (`role`),
    INDEX `idx_approved` (`approved`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT DEFAULT NULL,
    `type` ENUM('string','integer','boolean','json') NOT NULL DEFAULT 'string',
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `description` VARCHAR(255) DEFAULT NULL,
    `is_public` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Readable without auth',
    `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL=global, N=per-user override',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_key_user` (`key`, `user_id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL,
    `display_name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_key` (`key`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_role_perm` (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_role_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `role_name` VARCHAR(50) NOT NULL,
    `action` ENUM('assigned','removed') NOT NULL,
    `changed_by` VARCHAR(100) NOT NULL,
    `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
