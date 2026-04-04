-- Migration: Add modifier support to scan_items and create modifier groups
-- Date: 2026-04-03

ALTER TABLE `scan_items`
    ADD COLUMN `platform_id` INT UNSIGNED NULL AFTER `scan_id`,
    ADD COLUMN `external_id` VARCHAR(255) NULL AFTER `platform_id`,
    ADD COLUMN `calories` INT NULL AFTER `image_path`,
    ADD COLUMN `dietary_tags` JSON NULL AFTER `calories`,
    ADD COLUMN `availability` VARCHAR(50) NULL DEFAULT 'always' AFTER `dietary_tags`;

CREATE TABLE IF NOT EXISTS `scan_item_modifiers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scan_item_id` INT UNSIGNED NOT NULL,
    `group_name` VARCHAR(255) NOT NULL,
    `option_name` VARCHAR(255) NOT NULL,
    `price_adjustment` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_required` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`scan_item_id`) REFERENCES `scan_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_scan_item_modifiers_item` ON `scan_item_modifiers` (`scan_item_id`);
