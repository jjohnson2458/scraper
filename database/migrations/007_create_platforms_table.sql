-- Migration: Create platforms table
-- Date: 2026-04-03

CREATE TABLE IF NOT EXISTS `platforms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `category` ENUM('ordering_pos', 'delivery_marketplace', 'website_builder', 'data_aggregator') NOT NULL,
    `tier` ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'low',
    `scrape_method` VARCHAR(50) NOT NULL DEFAULT 'dom' COMMENT 'dom, api, api_dom, selenium',
    `url_pattern` VARCHAR(500) NULL COMMENT 'Regex to auto-detect platform from URL',
    `engine_class` VARCHAR(255) NULL COMMENT 'Fully qualified PHP class name',
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `health_status` ENUM('green', 'yellow', 'red', 'unknown') NOT NULL DEFAULT 'unknown',
    `last_health_check` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
