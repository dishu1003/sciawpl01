-- Direct Selling Business Support System - Database Schema
-- Clean database focused on lead management and referral tracking

-- Drop existing tables if they exist
DROP TABLE IF EXISTS `commissions`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `user_goals`;
DROP TABLE IF EXISTS `leads`;
DROP TABLE IF EXISTS `users`;

-- Users table for team members and admin
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','team') NOT NULL DEFAULT 'team',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `referral_code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_referral_code` (`referral_code`),
  KEY `idx_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leads table for managing prospects
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `source` varchar(50) DEFAULT 'Website',
  `referral_code` varchar(20) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `lead_score` enum('HOT','WARM','COLD') NOT NULL DEFAULT 'COLD',
  `status` enum('active','converted','lost','follow_up') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_referral_code` (`referral_code`),
  KEY `idx_lead_score` (`lead_score`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`referral_code`) REFERENCES `users`(`referral_code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead activities table for tracking interactions
CREATE TABLE `lead_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('call','email','meeting','note','status_change') NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead_id` (`lead_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead categories for better organization
CREATE TABLE `lead_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead category assignments
CREATE TABLE `lead_category_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_lead_category` (`lead_id`, `category_id`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `lead_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings table
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
INSERT INTO `users` (`username`, `email`, `full_name`, `password_hash`, `role`, `referral_code`) VALUES
('admin', 'admin@example.com', 'System Administrator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ADMIN001');

-- Insert default lead categories
INSERT INTO `lead_categories` (`name`, `description`, `color`) VALUES
('Interested', 'Shows genuine interest in the business opportunity', '#00d2d3'),
('Potential Customer', 'Interested in products/services', '#54a0ff'),
('Network Builder', 'Good at recruiting and building teams', '#ff9ff3'),
('High Performer', 'Consistently performs well', '#feca57'),
('New Recruit', 'Recently joined the team', '#667eea'),
('VIP Client', 'High-value customer or team member', '#ff6b6b');

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_name', 'Direct Selling Business Support', 'Website name'),
('site_description', 'Support system for direct selling business growth', 'Website description'),
('default_lead_score', 'COLD', 'Default lead score for new leads'),
('auto_assign_leads', '1', 'Automatically assign leads to team members'),
('lead_follow_up_days', '7', 'Default follow-up days for leads'),
('max_leads_per_user', '100', 'Maximum leads per team member'),
('referral_bonus_enabled', '0', 'Enable referral bonus system'),
('email_notifications', '1', 'Enable email notifications');

-- Create indexes for better performance
CREATE INDEX `idx_leads_composite` ON `leads` (`assigned_to`, `status`, `lead_score`);
CREATE INDEX `idx_leads_date_status` ON `leads` (`created_at`, `status`);
CREATE INDEX `idx_users_role_status` ON `users` (`role`, `status`);
CREATE INDEX `idx_lead_activities_composite` ON `lead_activities` (`lead_id`, `created_at`);

-- Create views for common queries
CREATE VIEW `team_performance` AS
SELECT 
    u.id,
    u.username,
    u.full_name,
    u.email,
    u.referral_code,
    COUNT(l.id) as total_leads,
    COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
    COUNT(CASE WHEN l.lead_score = 'HOT' THEN 1 END) as hot_leads,
    COUNT(CASE WHEN l.lead_score = 'WARM' THEN 1 END) as warm_leads,
    COUNT(CASE WHEN l.lead_score = 'COLD' THEN 1 END) as cold_leads,
    COUNT(CASE WHEN l.referral_code = u.referral_code THEN 1 END) as referral_leads,
    ROUND(
        CASE 
            WHEN COUNT(l.id) > 0 THEN (COUNT(CASE WHEN l.status = 'converted' THEN 1 END) / COUNT(l.id)) * 100 
            ELSE 0 
        END, 2
    ) as conversion_rate,
    MAX(l.created_at) as last_lead_date
FROM users u
LEFT JOIN leads l ON u.id = l.assigned_to
WHERE u.role = 'team'
GROUP BY u.id, u.username, u.full_name, u.email, u.referral_code;

CREATE VIEW `lead_summary` AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_leads,
    COUNT(CASE WHEN lead_score = 'HOT' THEN 1 END) as hot_leads,
    COUNT(CASE WHEN lead_score = 'WARM' THEN 1 END) as warm_leads,
    COUNT(CASE WHEN lead_score = 'COLD' THEN 1 END) as cold_leads,
    COUNT(CASE WHEN status = 'converted' THEN 1 END) as conversions,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_leads,
    COUNT(CASE WHEN referral_code IS NOT NULL THEN 1 END) as referral_leads
FROM leads
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Create stored procedures for common operations
DELIMITER //

-- Procedure to assign lead to team member
CREATE PROCEDURE AssignLeadToTeamMember(
    IN p_lead_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Update lead assignment
    UPDATE leads 
    SET assigned_to = p_user_id, updated_at = CURRENT_TIMESTAMP 
    WHERE id = p_lead_id;
    
    -- Log the activity
    INSERT INTO lead_activities (lead_id, user_id, activity_type, description)
    VALUES (p_lead_id, p_user_id, 'status_change', 'Lead assigned to team member');
    
    COMMIT;
END //

-- Procedure to update lead status
CREATE PROCEDURE UpdateLeadStatus(
    IN p_lead_id INT,
    IN p_user_id INT,
    IN p_status VARCHAR(20),
    IN p_notes TEXT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Update lead status and notes
    UPDATE leads 
    SET status = p_status, notes = p_notes, updated_at = CURRENT_TIMESTAMP 
    WHERE id = p_lead_id;
    
    -- Log the activity
    INSERT INTO lead_activities (lead_id, user_id, activity_type, description)
    VALUES (p_lead_id, p_user_id, 'status_change', CONCAT('Status changed to: ', p_status));
    
    COMMIT;
END //

DELIMITER ;

-- Grant permissions (adjust as needed for your setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON direct_selling_support.* TO 'your_app_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE direct_selling_support.* TO 'your_app_user'@'localhost';

-- Sample data for testing (optional)
INSERT INTO `users` (`username`, `email`, `full_name`, `phone`, `password_hash`, `role`, `referral_code`) VALUES
('john_doe', 'john@example.com', 'John Doe', '+1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'team', 'REF000001'),
('jane_smith', 'jane@example.com', 'Jane Smith', '+1234567891', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'team', 'REF000002');

INSERT INTO `leads` (`name`, `email`, `phone`, `source`, `referral_code`, `assigned_to`, `lead_score`, `status`, `notes`) VALUES
('Alice Johnson', 'alice@example.com', '+1234567892', 'Website', 'REF000001', 2, 'HOT', 'active', 'Very interested in the business opportunity'),
('Bob Wilson', 'bob@example.com', '+1234567893', 'Referral', 'REF000002', 3, 'WARM', 'active', 'Looking for additional income'),
('Carol Brown', 'carol@example.com', '+1234567894', 'Social Media', NULL, 2, 'COLD', 'active', 'Initial contact made'),
('David Lee', 'david@example.com', '+1234567895', 'Website', 'REF000001', 3, 'HOT', 'converted', 'Successfully converted to team member');

-- Database optimization
OPTIMIZE TABLE `users`;
OPTIMIZE TABLE `leads`;
OPTIMIZE TABLE `lead_activities`;
OPTIMIZE TABLE `lead_categories`;
OPTIMIZE TABLE `lead_category_assignments`;
OPTIMIZE TABLE `system_settings`;

-- Show table information
SHOW TABLE STATUS;

-- Show database size
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables 
WHERE table_schema = DATABASE()
GROUP BY table_schema;
