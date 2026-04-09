-- PhoenixPHP Production Tracker Module
-- Database Schema v1.0.0
-- CDAC Programming

CREATE TABLE IF NOT EXISTS `production_lines` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `type` VARCHAR(50) DEFAULT NULL,
    `location` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('running','idle','maintenance','offline') DEFAULT 'idle',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `production_shifts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `production_runs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `line_id` INT UNSIGNED NOT NULL,
    `shift_id` INT UNSIGNED DEFAULT NULL,
    `operator` VARCHAR(50) NOT NULL,
    `product_name` VARCHAR(255) DEFAULT NULL,
    `work_order` VARCHAR(50) DEFAULT NULL,
    `target_qty` INT DEFAULT 0,
    `good_qty` INT DEFAULT 0,
    `reject_qty` INT DEFAULT 0,
    `scrap_qty` INT DEFAULT 0,
    `total_qty` INT GENERATED ALWAYS AS (`good_qty` + `reject_qty` + `scrap_qty`) STORED,
    `efficiency` DECIMAL(5,2) GENERATED ALWAYS AS (CASE WHEN `target_qty` > 0 THEN ROUND((`good_qty` / `target_qty`) * 100, 2) ELSE 0 END) STORED,
    `quality_rate` DECIMAL(5,2) GENERATED ALWAYS AS (CASE WHEN (`good_qty` + `reject_qty` + `scrap_qty`) > 0 THEN ROUND((`good_qty` / (`good_qty` + `reject_qty` + `scrap_qty`)) * 100, 2) ELSE 0 END) STORED,
    `run_date` DATE NOT NULL,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `status` ENUM('setup','running','paused','completed','cancelled') DEFAULT 'setup',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_line` (`line_id`),
    INDEX `idx_date` (`run_date`),
    INDEX `idx_operator` (`operator`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`line_id`) REFERENCES `production_lines`(`id`),
    FOREIGN KEY (`shift_id`) REFERENCES `production_shifts`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `production_downtime` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `run_id` INT UNSIGNED NOT NULL,
    `line_id` INT UNSIGNED NOT NULL,
    `reason_code` VARCHAR(50) NOT NULL,
    `reason_detail` TEXT DEFAULT NULL,
    `category` ENUM('mechanical','electrical','material','operator','quality','changeover','planned','other') DEFAULT 'other',
    `duration_minutes` INT NOT NULL,
    `started_at` TIMESTAMP NOT NULL,
    `ended_at` TIMESTAMP NULL DEFAULT NULL,
    `reported_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_run` (`run_id`),
    INDEX `idx_line` (`line_id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_date` (`started_at`),
    FOREIGN KEY (`run_id`) REFERENCES `production_runs`(`id`),
    FOREIGN KEY (`line_id`) REFERENCES `production_lines`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `production_defects` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `run_id` INT UNSIGNED NOT NULL,
    `defect_type` VARCHAR(100) NOT NULL,
    `severity` ENUM('critical','major','minor','cosmetic') DEFAULT 'minor',
    `quantity` INT DEFAULT 1,
    `description` TEXT DEFAULT NULL,
    `disposition` ENUM('scrap','rework','accept','hold') DEFAULT 'hold',
    `reported_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_run` (`run_id`),
    INDEX `idx_type` (`defect_type`),
    FOREIGN KEY (`run_id`) REFERENCES `production_runs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default shifts
INSERT IGNORE INTO `production_shifts` (`id`, `name`, `start_time`, `end_time`) VALUES
(1, '1st Shift', '06:00:00', '14:00:00'),
(2, '2nd Shift', '14:00:00', '22:00:00'),
(3, '3rd Shift', '22:00:00', '06:00:00');
