-- Migration: Activate Phase 4 engines — all 25 platforms now active
-- Date: 2026-04-03

UPDATE `platforms` SET `is_active` = 1 WHERE `is_active` = 0;
