-- Migration: Create scrape_jobs table
-- Date: 2026-04-03

CREATE TABLE IF NOT EXISTS `scrape_jobs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scan_id` INT UNSIGNED NULL,
    `platform_id` INT UNSIGNED NULL,
    `url` TEXT NOT NULL,
    `status` ENUM('pending', 'running', 'complete', 'failed') NOT NULL DEFAULT 'pending',
    `engine_used` VARCHAR(100) NULL COMMENT 'Engine class that processed this job',
    `items_found` INT UNSIGNED NOT NULL DEFAULT 0,
    `scrape_time_ms` INT UNSIGNED NULL COMMENT 'Time taken in milliseconds',
    `error_message` TEXT NULL,
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`scan_id`) REFERENCES `scans`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`platform_id`) REFERENCES `platforms`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_scrape_jobs_status` ON `scrape_jobs` (`status`);
CREATE INDEX `idx_scrape_jobs_platform` ON `scrape_jobs` (`platform_id`);
