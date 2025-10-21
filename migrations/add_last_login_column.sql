-- Add last_login column to users table
-- This migration adds a timestamp column to track the last time a user logged in.

ALTER TABLE `users`
ADD COLUMN `last_login` TIMESTAMP NULL DEFAULT NULL AFTER `status`;
