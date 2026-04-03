-- Migration: Create scans table
-- Date: 2026-04-03

CREATE TABLE IF NOT EXISTS `scans` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `source_type` ENUM('url', 'photo') NOT NULL,
    `source_value` TEXT NOT NULL COMMENT 'URL or image file path',
    `title` VARCHAR(255) NULL COMMENT 'Restaurant or menu name',
    `status` ENUM('pending', 'processing', 'complete', 'failed', 'imported') NOT NULL DEFAULT 'pending',
    `item_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `error_message` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_scans_status` ON `scans` (`status`);
CREATE INDEX `idx_scans_user_id` ON `scans` (`user_id`);
CREATE INDEX `idx_scans_created_at` ON `scans` (`created_at`);
