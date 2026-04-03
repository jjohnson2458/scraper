-- Migration: Create scan_items table
-- Date: 2026-04-03

CREATE TABLE IF NOT EXISTS `scan_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scan_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `price` DECIMAL(10, 2) NULL,
    `category` VARCHAR(100) NULL,
    `image_url` TEXT NULL COMMENT 'Original image URL from source',
    `image_path` VARCHAR(500) NULL COMMENT 'Local stored image path',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_selected` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether item is selected for import',
    `raw_text` TEXT NULL COMMENT 'Original scraped text before parsing',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`scan_id`) REFERENCES `scans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_scan_items_scan_id` ON `scan_items` (`scan_id`);
CREATE INDEX `idx_scan_items_category` ON `scan_items` (`category`);
