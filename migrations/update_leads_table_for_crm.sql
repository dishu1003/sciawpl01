-- Phase 1: CRM Enhancement - Update Leads Table
-- This migration adds a 'source' column to track lead origin and
-- updates the 'status' enum to better suit a network marketing workflow.

-- Add 'source' column to track where the lead came from (e.g., organic, social_media_ad)
ALTER TABLE `leads`
ADD COLUMN `source` VARCHAR(50) NULL DEFAULT 'organic' AFTER `ref_id`;

-- Modify 'status' column to include more relevant stages for network marketing
ALTER TABLE `leads`
MODIFY COLUMN `status` ENUM('New', 'Contacted', 'Plan Shown', 'Follow-up', 'Joined', 'Not Interested', 'converted', 'lost', 'qualified') NOT NULL DEFAULT 'New';
