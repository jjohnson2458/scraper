-- Migration: Activate Phase 2 engines
-- Date: 2026-04-03

UPDATE `platforms` SET `is_active` = 1 WHERE `slug` IN ('doordash', 'ubereats', 'grubhub', 'chownow', 'square');
UPDATE `platforms` SET `is_active` = 1 WHERE `slug` IN ('postmates', 'seamless', 'caviar');
