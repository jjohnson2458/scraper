-- Migration: Activate Phase 3 engines
-- Date: 2026-04-03

UPDATE `platforms` SET `is_active` = 1 WHERE `slug` IN ('clover', 'olo', 'bentobox', 'popmenu', 'singleplatform', 'menufy', 'yelp', 'google');
