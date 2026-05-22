-- Phoenix POS Module Schema

CREATE TABLE IF NOT EXISTS `pos_registers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `register_id` INT UNSIGNED NOT NULL,
    `operator` VARCHAR(100) NOT NULL,
    `starting_cash` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `ending_cash` DECIMAL(10,2) DEFAULT NULL,
    `cash_sales` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `card_sales` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `total_sales` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `order_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
    `opened_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    INDEX `idx_register` (`register_id`),
    INDEX `idx_operator` (`operator`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`register_id`) REFERENCES `pos_registers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_customers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `company` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(50) DEFAULT NULL,
    `zip` VARCHAR(20) DEFAULT NULL,
    `tax_exempt` TINYINT(1) NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_tax_rates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `rate` DECIMAL(5,3) NOT NULL COMMENT 'Percentage, e.g. 7.000 = 7%',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) NOT NULL,
    `register_id` INT UNSIGNED DEFAULT NULL,
    `session_id` INT UNSIGNED DEFAULT NULL,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `operator` VARCHAR(100) NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `tax_rate` DECIMAL(5,3) NOT NULL DEFAULT 0,
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status` ENUM('open','paid','refunded','voided') NOT NULL DEFAULT 'open',
    `payment_method` VARCHAR(30) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_order_number` (`order_number`),
    INDEX `idx_register` (`register_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_operator` (`operator`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK to inventory_items',
    `sku` VARCHAR(50) DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `discount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `line_total` DECIMAL(12,2) GENERATED ALWAYS AS ((`quantity` * `unit_price`) - `discount`) STORED,
    INDEX `idx_order` (`order_id`),
    INDEX `idx_item` (`item_id`),
    FOREIGN KEY (`order_id`) REFERENCES `pos_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `method` VARCHAR(30) NOT NULL COMMENT 'cash, card, invoice, manual_card, stripe, square',
    `amount` DECIMAL(12,2) NOT NULL,
    `amount_tendered` DECIMAL(12,2) DEFAULT NULL,
    `change_due` DECIMAL(12,2) DEFAULT NULL,
    `transaction_id` VARCHAR(100) DEFAULT NULL COMMENT 'Gateway transaction reference',
    `gateway_response` JSON DEFAULT NULL,
    `status` ENUM('completed','pending','failed','refunded') NOT NULL DEFAULT 'completed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order` (`order_id`),
    INDEX `idx_method` (`method`),
    INDEX `idx_transaction` (`transaction_id`),
    FOREIGN KEY (`order_id`) REFERENCES `pos_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_refunds` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `payment_id` INT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `reason` TEXT DEFAULT NULL,
    `transaction_id` VARCHAR(100) DEFAULT NULL,
    `processed_by` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order` (`order_id`),
    FOREIGN KEY (`order_id`) REFERENCES `pos_orders`(`id`),
    FOREIGN KEY (`payment_id`) REFERENCES `pos_payments`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_invoices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(30) NOT NULL,
    `order_id` INT UNSIGNED DEFAULT NULL,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `issue_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `amount_due` DECIMAL(12,2) GENERATED ALWAYS AS (`total` - `amount_paid`) STORED,
    `status` ENUM('draft','sent','paid','partial','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `notes` TEXT DEFAULT NULL,
    `terms` TEXT DEFAULT NULL,
    `created_by` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_invoice_number` (`invoice_number`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_due_date` (`due_date`),
    FOREIGN KEY (`order_id`) REFERENCES `pos_orders`(`id`),
    FOREIGN KEY (`customer_id`) REFERENCES `pos_customers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_invoice_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED DEFAULT NULL,
    `description` VARCHAR(255) NOT NULL,
    `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `tax_rate` DECIMAL(5,3) NOT NULL DEFAULT 0,
    `line_total` DECIMAL(12,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
    INDEX `idx_invoice` (`invoice_id`),
    FOREIGN KEY (`invoice_id`) REFERENCES `pos_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default tax rate
INSERT IGNORE INTO `pos_tax_rates` (`name`, `rate`, `is_default`) VALUES ('Standard', 7.000, 1);

-- Default register
INSERT IGNORE INTO `pos_registers` (`name`, `location`) VALUES ('Register 1', 'Main');
