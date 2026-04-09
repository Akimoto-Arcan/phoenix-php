-- PhoenixPHP Shift Scheduling Module
-- Database Schema v1.0.0
-- CDAC Programming

CREATE TABLE IF NOT EXISTS `schedule_shifts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `color` VARCHAR(7) DEFAULT '#3b82f6',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_departments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `manager` VARCHAR(50) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_assignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `shift_id` INT UNSIGNED NOT NULL,
    `department_id` INT UNSIGNED DEFAULT NULL,
    `schedule_date` DATE NOT NULL,
    `start_override` TIME DEFAULT NULL,
    `end_override` TIME DEFAULT NULL,
    `status` ENUM('scheduled','confirmed','swapped','called_off','no_show') DEFAULT 'scheduled',
    `overtime` TINYINT(1) DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_date` (`username`, `schedule_date`),
    INDEX `idx_date` (`schedule_date`),
    INDEX `idx_shift` (`shift_id`),
    INDEX `idx_department` (`department_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`shift_id`) REFERENCES `schedule_shifts`(`id`),
    FOREIGN KEY (`department_id`) REFERENCES `schedule_departments`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_availability` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `day_of_week` TINYINT NOT NULL COMMENT '0=Sun, 6=Sat',
    `available` TINYINT(1) DEFAULT 1,
    `preferred_shift_id` INT UNSIGNED DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY `uk_user_day` (`username`, `day_of_week`),
    FOREIGN KEY (`preferred_shift_id`) REFERENCES `schedule_shifts`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_swap_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `requester` VARCHAR(50) NOT NULL,
    `assignment_id` INT UNSIGNED NOT NULL,
    `target_user` VARCHAR(50) DEFAULT NULL,
    `target_assignment_id` INT UNSIGNED DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `status` ENUM('pending','approved','denied','cancelled') DEFAULT 'pending',
    `reviewed_by` VARCHAR(50) DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_requester` (`requester`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`assignment_id`) REFERENCES `schedule_assignments`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_time_off` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `type` ENUM('vacation','sick','personal','bereavement','jury_duty','other') NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `hours` DECIMAL(5,2) DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `status` ENUM('pending','approved','denied','cancelled') DEFAULT 'pending',
    `reviewed_by` VARCHAR(50) DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`username`),
    INDEX `idx_dates` (`start_date`, `end_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedule_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `pattern` JSON NOT NULL COMMENT 'Array of {day, shift_id, department_id}',
    `repeat_weeks` INT DEFAULT 1,
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default shifts
INSERT IGNORE INTO `schedule_shifts` (`id`, `name`, `start_time`, `end_time`, `color`) VALUES
(1, '1st Shift (Day)', '06:00:00', '14:00:00', '#10b981'),
(2, '2nd Shift (Swing)', '14:00:00', '22:00:00', '#3b82f6'),
(3, '3rd Shift (Night)', '22:00:00', '06:00:00', '#8b5cf6');
