-- PhoenixPHP CMMS Module
-- Database Schema v1.0.0
-- CDAC Programming

CREATE TABLE IF NOT EXISTS `cmms_equipment` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `asset_tag` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `manufacturer` VARCHAR(100) DEFAULT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `serial_number` VARCHAR(100) DEFAULT NULL,
    `location` VARCHAR(100) DEFAULT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `install_date` DATE DEFAULT NULL,
    `warranty_expiry` DATE DEFAULT NULL,
    `status` ENUM('operational','maintenance','down','retired') DEFAULT 'operational',
    `criticality` ENUM('critical','high','medium','low') DEFAULT 'medium',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_location` (`location`),
    INDEX `idx_criticality` (`criticality`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cmms_work_orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `wo_number` VARCHAR(20) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `equipment_id` INT UNSIGNED DEFAULT NULL,
    `type` ENUM('corrective','preventive','inspection','emergency','project') DEFAULT 'corrective',
    `priority` ENUM('emergency','high','medium','low') DEFAULT 'medium',
    `status` ENUM('open','assigned','in_progress','on_hold','completed','cancelled') DEFAULT 'open',
    `requested_by` VARCHAR(50) NOT NULL,
    `assigned_to` VARCHAR(50) DEFAULT NULL,
    `estimated_hours` DECIMAL(6,2) DEFAULT NULL,
    `actual_hours` DECIMAL(6,2) DEFAULT NULL,
    `labor_cost` DECIMAL(10,2) DEFAULT NULL,
    `parts_cost` DECIMAL(10,2) DEFAULT NULL,
    `total_cost` DECIMAL(10,2) GENERATED ALWAYS AS (COALESCE(`labor_cost`, 0) + COALESCE(`parts_cost`, 0)) STORED,
    `downtime_hours` DECIMAL(6,2) DEFAULT NULL,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_equipment` (`equipment_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_assigned` (`assigned_to`),
    INDEX `idx_type` (`type`),
    INDEX `idx_due` (`due_date`),
    FOREIGN KEY (`equipment_id`) REFERENCES `cmms_equipment`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cmms_pm_schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `equipment_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `frequency` ENUM('daily','weekly','biweekly','monthly','quarterly','semiannual','annual') NOT NULL,
    `last_performed` DATE DEFAULT NULL,
    `next_due` DATE NOT NULL,
    `assigned_to` VARCHAR(50) DEFAULT NULL,
    `estimated_hours` DECIMAL(6,2) DEFAULT NULL,
    `checklist` JSON DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_equipment` (`equipment_id`),
    INDEX `idx_next_due` (`next_due`),
    INDEX `idx_active` (`is_active`),
    FOREIGN KEY (`equipment_id`) REFERENCES `cmms_equipment`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cmms_parts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `part_number` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `quantity_on_hand` INT DEFAULT 0,
    `reorder_point` INT DEFAULT 0,
    `unit_cost` DECIMAL(10,2) DEFAULT 0.00,
    `supplier` VARCHAR(255) DEFAULT NULL,
    `location` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cmms_wo_parts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `work_order_id` INT UNSIGNED NOT NULL,
    `part_id` INT UNSIGNED NOT NULL,
    `quantity_used` INT NOT NULL DEFAULT 1,
    `unit_cost` DECIMAL(10,2) DEFAULT 0.00,
    `line_total` DECIMAL(10,2) GENERATED ALWAYS AS (`quantity_used` * `unit_cost`) STORED,
    FOREIGN KEY (`work_order_id`) REFERENCES `cmms_work_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`part_id`) REFERENCES `cmms_parts`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cmms_wo_comments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `work_order_id` INT UNSIGNED NOT NULL,
    `user` VARCHAR(50) NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`work_order_id`) REFERENCES `cmms_work_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
