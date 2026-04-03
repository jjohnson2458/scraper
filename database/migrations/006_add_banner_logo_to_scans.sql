-- Migration: Add banner_url and logo_url to scans table
-- Date: 2026-04-03

ALTER TABLE `scans`
    ADD COLUMN `banner_url` TEXT NULL AFTER `error_message`,
    ADD COLUMN `logo_url` TEXT NULL AFTER `banner_url`;
