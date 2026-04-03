-- Migration: Create imports table
-- Date: 2026-04-03

CREATE TABLE IF NOT EXISTS `imports` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scan_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `target_platform` VARCHAR(100) NOT NULL COMMENT 'e.g., claude_takeout, claude_toolrental',
    `target_store_slug` VARCHAR(100) NULL COMMENT 'Store identifier within the platform',
    `status` ENUM('pending', 'processing', 'complete', 'partial', 'failed') NOT NULL DEFAULT 'pending',
    `total_items` INT UNSIGNED NOT NULL DEFAULT 0,
    `imported_items` INT UNSIGNED NOT NULL DEFAULT 0,
    `failed_items` INT UNSIGNED NOT NULL DEFAULT 0,
    `error_log` JSON NULL COMMENT 'Per-item error details',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`scan_id`) REFERENCES `scans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_imports_scan_id` ON `imports` (`scan_id`);
CREATE INDEX `idx_imports_status` ON `imports` (`status`);
