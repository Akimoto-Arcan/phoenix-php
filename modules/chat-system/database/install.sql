-- PhoenixPHP Internal Chat Module
-- Database Schema v1.0.0
-- CDAC Programming

CREATE TABLE IF NOT EXISTS `chat_channels` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `type` ENUM('public','private','direct') DEFAULT 'public',
    `created_by` VARCHAR(50) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`),
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_channel_members` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `channel_id` INT UNSIGNED NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `role` ENUM('owner','admin','member') DEFAULT 'member',
    `last_read_at` TIMESTAMP NULL DEFAULT NULL,
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_channel_user` (`channel_id`, `username`),
    FOREIGN KEY (`channel_id`) REFERENCES `chat_channels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `channel_id` INT UNSIGNED NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('text','file','image','system') DEFAULT 'text',
    `attachment_url` VARCHAR(500) DEFAULT NULL,
    `attachment_name` VARCHAR(255) DEFAULT NULL,
    `is_edited` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_channel` (`channel_id`),
    INDEX `idx_user` (`username`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`channel_id`) REFERENCES `chat_channels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_user_presence` (
    `username` VARCHAR(50) PRIMARY KEY,
    `status` ENUM('online','away','busy','offline') DEFAULT 'offline',
    `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status_message` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_mentions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `message_id` INT UNSIGNED NOT NULL,
    `mentioned_user` VARCHAR(50) NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_unread` (`mentioned_user`, `is_read`),
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default channels
INSERT IGNORE INTO `chat_channels` (`id`, `name`, `slug`, `description`, `type`, `created_by`) VALUES
(1, 'General', 'general', 'Company-wide announcements and discussion', 'public', 'system'),
(2, 'IT Support', 'it-support', 'Technical support and IT requests', 'public', 'system');
