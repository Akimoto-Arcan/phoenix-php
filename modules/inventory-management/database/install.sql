-- PhoenixPHP Inventory Management Module
-- Database Schema v1.0.0
-- CDAC Programming

CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `upc` VARCHAR(20) DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `unit_of_measure` VARCHAR(20) DEFAULT 'each',
    `cost_price` DECIMAL(10,2) DEFAULT 0.00,
    `sell_price` DECIMAL(10,2) DEFAULT 0.00,
    `reorder_point` INT DEFAULT 0,
    `reorder_qty` INT DEFAULT 0,
    `lead_time_days` INT DEFAULT 0,
    `supplier_id` INT UNSIGNED DEFAULT NULL,
    `location` VARCHAR(100) DEFAULT NULL,
    `bin_number` VARCHAR(50) DEFAULT NULL,
    `weight` DECIMAL(8,3) DEFAULT NULL,
    `weight_unit` ENUM('lb','kg','oz','g') DEFAULT 'lb',
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_supplier` (`supplier_id`),
    INDEX `idx_sku` (`sku`),
    INDEX `idx_upc` (`upc`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_stock` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT UNSIGNED NOT NULL,
    `location_id` INT UNSIGNED DEFAULT 1,
    `quantity` DECIMAL(12,3) DEFAULT 0,
    `reserved_qty` DECIMAL(12,3) DEFAULT 0,
    `available_qty` DECIMAL(12,3) GENERATED ALWAYS AS (`quantity` - `reserved_qty`) STORED,
    `last_counted` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_item_location` (`item_id`, `location_id`),
    INDEX `idx_low_stock` (`available_qty`),
    FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT UNSIGNED NOT NULL,
    `type` ENUM('receive','ship','adjust','transfer','return','scrap') NOT NULL,
    `quantity` DECIMAL(12,3) NOT NULL,
    `reference_number` VARCHAR(50) DEFAULT NULL,
    `po_number` VARCHAR(50) DEFAULT NULL,
    `supplier_id` INT UNSIGNED DEFAULT NULL,
    `cost_per_unit` DECIMAL(10,2) DEFAULT NULL,
    `location_from` INT UNSIGNED DEFAULT NULL,
    `location_to` INT UNSIGNED DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_item` (`item_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_date` (`created_at`),
    INDEX `idx_reference` (`reference_number`),
    INDEX `idx_po` (`po_number`),
    FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_suppliers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `contact_name` VARCHAR(100) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `lead_time_days` INT DEFAULT 0,
    `payment_terms` VARCHAR(50) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_locations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `type` ENUM('warehouse','shelf','bin','zone','dock') DEFAULT 'warehouse',
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_purchase_orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `po_number` VARCHAR(50) NOT NULL UNIQUE,
    `supplier_id` INT UNSIGNED NOT NULL,
    `status` ENUM('draft','submitted','partial','received','cancelled') DEFAULT 'draft',
    `order_date` DATE NOT NULL,
    `expected_date` DATE DEFAULT NULL,
    `received_date` DATE DEFAULT NULL,
    `total_amount` DECIMAL(12,2) DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_supplier` (`supplier_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`supplier_id`) REFERENCES `inventory_suppliers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_po_lines` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `quantity_ordered` DECIMAL(12,3) NOT NULL,
    `quantity_received` DECIMAL(12,3) DEFAULT 0,
    `unit_cost` DECIMAL(10,2) NOT NULL,
    `line_total` DECIMAL(12,2) GENERATED ALWAYS AS (`quantity_ordered` * `unit_cost`) STORED,
    FOREIGN KEY (`po_id`) REFERENCES `inventory_purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default location
INSERT IGNORE INTO `inventory_locations` (`id`, `name`, `code`, `type`) VALUES (1, 'Main Warehouse', 'WH-01', 'warehouse');
