-- Migration: Create error_log table
-- Date: 2026-04-03

CREATE TABLE IF NOT EXISTS `error_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `level` ENUM('error', 'warning', 'info', 'debug') NOT NULL DEFAULT 'error',
    `message` TEXT NOT NULL,
    `context` JSON NULL COMMENT 'Additional error context',
    `file` VARCHAR(500) NULL,
    `line` INT NULL,
    `trace` TEXT NULL,
    `user_id` INT UNSIGNED NULL,
    `url` VARCHAR(500) NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_error_log_level` ON `error_log` (`level`);
CREATE INDEX `idx_error_log_created_at` ON `error_log` (`created_at`);
