-- PhoenixPHP Report Builder Module
-- Database Schema v1.0.0
-- CDAC Programming

CREATE TABLE IF NOT EXISTS `report_definitions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(50) DEFAULT 'General',
    `type` ENUM('table','chart','summary','custom') DEFAULT 'table',
    `query` TEXT NOT NULL COMMENT 'SQL query or query builder JSON',
    `query_type` ENUM('sql','builder') DEFAULT 'builder',
    `columns` JSON DEFAULT NULL COMMENT 'Column definitions for display',
    `filters` JSON DEFAULT NULL COMMENT 'Available filter parameters',
    `chart_config` JSON DEFAULT NULL COMMENT 'Chart.js configuration',
    `default_sort` VARCHAR(100) DEFAULT NULL,
    `default_limit` INT DEFAULT 100,
    `is_public` TINYINT(1) DEFAULT 0,
    `allowed_roles` JSON DEFAULT NULL COMMENT 'Roles that can view this report',
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`),
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` INT UNSIGNED NOT NULL,
    `frequency` ENUM('daily','weekly','monthly','quarterly') NOT NULL,
    `day_of_week` TINYINT DEFAULT NULL COMMENT '0=Sun for weekly',
    `day_of_month` TINYINT DEFAULT NULL COMMENT 'For monthly',
    `time` TIME DEFAULT '08:00:00',
    `format` ENUM('pdf','xlsx','csv','html') DEFAULT 'pdf',
    `recipients` JSON NOT NULL COMMENT 'Array of email addresses',
    `filters` JSON DEFAULT NULL COMMENT 'Preset filter values',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_run` TIMESTAMP NULL DEFAULT NULL,
    `next_run` TIMESTAMP NULL DEFAULT NULL,
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_report` (`report_id`),
    INDEX `idx_next_run` (`next_run`),
    INDEX `idx_active` (`is_active`),
    FOREIGN KEY (`report_id`) REFERENCES `report_definitions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_execution_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` INT UNSIGNED NOT NULL,
    `schedule_id` INT UNSIGNED DEFAULT NULL,
    `triggered_by` ENUM('manual','scheduled') DEFAULT 'manual',
    `executed_by` VARCHAR(50) NOT NULL,
    `format` ENUM('screen','pdf','xlsx','csv','html') DEFAULT 'screen',
    `row_count` INT DEFAULT 0,
    `execution_time_ms` INT DEFAULT 0,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `filters_used` JSON DEFAULT NULL,
    `status` ENUM('success','error') DEFAULT 'success',
    `error_message` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_report` (`report_id`),
    INDEX `idx_date` (`created_at`),
    FOREIGN KEY (`report_id`) REFERENCES `report_definitions`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_favorites` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` INT UNSIGNED NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_report` (`username`, `report_id`),
    FOREIGN KEY (`report_id`) REFERENCES `report_definitions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_dashboards` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `layout` JSON NOT NULL COMMENT 'Grid layout with widget positions',
    `widgets` JSON NOT NULL COMMENT 'Array of {report_id, chart_type, size, position}',
    `is_default` TINYINT(1) DEFAULT 0,
    `allowed_roles` JSON DEFAULT NULL,
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
