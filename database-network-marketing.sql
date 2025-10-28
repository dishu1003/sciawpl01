-- Network Marketing CRM Database Schema
-- Enhanced database structure for network marketing business

-- Users table with network marketing features
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL UNIQUE,
    `email` varchar(100) NOT NULL UNIQUE,
    `password_hash` varchar(255) NOT NULL,
    `full_name` varchar(100) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `role` enum('admin','team') NOT NULL DEFAULT 'team',
    `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `level` int(11) NOT NULL DEFAULT 1,
    `upline_id` int(11) DEFAULT NULL,
    `sponsor_id` int(11) DEFAULT NULL,
    `position` enum('left','right') DEFAULT NULL,
    `join_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_upline` (`upline_id`),
    KEY `idx_sponsor` (`sponsor_id`),
    KEY `idx_level` (`level`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_users_upline` FOREIGN KEY (`upline_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_users_sponsor` FOREIGN KEY (`sponsor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leads table for prospect management
CREATE TABLE IF NOT EXISTS `leads` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `city` varchar(50) DEFAULT NULL,
    `state` varchar(50) DEFAULT NULL,
    `pincode` varchar(10) DEFAULT NULL,
    `source` varchar(50) DEFAULT 'Direct',
    `lead_score` enum('HOT','WARM','COLD') NOT NULL DEFAULT 'COLD',
    `status` enum('active','converted','lost','inactive') NOT NULL DEFAULT 'active',
    `assigned_to` int(11) DEFAULT NULL,
    `referred_by` int(11) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `follow_up_date` datetime DEFAULT NULL,
    `conversion_date` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_assigned_to` (`assigned_to`),
    KEY `idx_referred_by` (`referred_by`),
    KEY `idx_lead_score` (`lead_score`),
    KEY `idx_status` (`status`),
    KEY `idx_source` (`source`),
    CONSTRAINT `fk_leads_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leads_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commissions table for tracking payments
CREATE TABLE IF NOT EXISTS `commissions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
    `rate` decimal(5,4) NOT NULL DEFAULT 0.0000,
    `type` enum('direct','binary','matching','leadership','monthly','bonus') NOT NULL DEFAULT 'direct',
    `description` text DEFAULT NULL,
    `status` enum('pending','approved','paid','cancelled') NOT NULL DEFAULT 'pending',
    `period_start` date DEFAULT NULL,
    `period_end` date DEFAULT NULL,
    `approved_by` int(11) DEFAULT NULL,
    `approved_at` datetime DEFAULT NULL,
    `paid_at` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_type` (`type`),
    KEY `idx_period` (`period_start`, `period_end`),
    CONSTRAINT `fk_commissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_commissions_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table for network marketing products
CREATE TABLE IF NOT EXISTS `products` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `price` decimal(10,2) NOT NULL DEFAULT 0.00,
    `pv` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Point Value for commission calculation',
    `bv` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Business Volume',
    `category` varchar(50) DEFAULT NULL,
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table for product purchases
CREATE TABLE IF NOT EXISTS `orders` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `order_number` varchar(20) NOT NULL UNIQUE,
    `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
    `total_pv` decimal(10,2) NOT NULL DEFAULT 0.00,
    `total_bv` decimal(10,2) NOT NULL DEFAULT 0.00,
    `status` enum('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `shipping_address` text DEFAULT NULL,
    `order_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `delivery_date` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_order_date` (`order_date`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `product_id` int(11) NOT NULL,
    `quantity` int(11) NOT NULL DEFAULT 1,
    `price` decimal(10,2) NOT NULL DEFAULT 0.00,
    `pv` decimal(10,2) NOT NULL DEFAULT 0.00,
    `bv` decimal(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_product_id` (`product_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User goals table for tracking targets
CREATE TABLE IF NOT EXISTS `user_goals` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `title` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `type` enum('sales','recruitment','team_building','personal') NOT NULL DEFAULT 'sales',
    `target_value` decimal(10,2) NOT NULL DEFAULT 0.00,
    `current_value` decimal(10,2) NOT NULL DEFAULT 0.00,
    `unit` varchar(20) DEFAULT 'units',
    `period` enum('daily','weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `status` enum('active','completed','failed','cancelled') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_period` (`period`),
    CONSTRAINT `fk_user_goals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training materials table
CREATE TABLE IF NOT EXISTS `training_materials` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `type` enum('video','document','presentation','audio') NOT NULL DEFAULT 'video',
    `file_path` varchar(255) DEFAULT NULL,
    `url` varchar(255) DEFAULT NULL,
    `duration` int(11) DEFAULT NULL COMMENT 'Duration in minutes for videos/audio',
    `level` enum('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    `category` varchar(50) DEFAULT NULL,
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_level` (`level`),
    KEY `idx_category` (`category`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_training_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User training progress table
CREATE TABLE IF NOT EXISTS `user_training_progress` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `training_id` int(11) NOT NULL,
    `status` enum('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
    `progress_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
    `time_spent` int(11) NOT NULL DEFAULT 0 COMMENT 'Time spent in minutes',
    `completed_at` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_training` (`user_id`, `training_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_training_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_training_progress_training` FOREIGN KEY (`training_id`) REFERENCES `training_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `title` varchar(100) NOT NULL,
    `message` text NOT NULL,
    `type` enum('info','success','warning','error') NOT NULL DEFAULT 'info',
    `is_read` tinyint(1) NOT NULL DEFAULT 0,
    `action_url` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_read` (`is_read`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commission settings table
CREATE TABLE IF NOT EXISTS `commission_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `type` varchar(50) NOT NULL,
    `level` int(11) NOT NULL DEFAULT 1,
    `rate` decimal(5,4) NOT NULL DEFAULT 0.0000,
    `min_amount` decimal(10,2) DEFAULT NULL,
    `max_amount` decimal(10,2) DEFAULT NULL,
    `description` text DEFAULT NULL,
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_type_level` (`type`, `level`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `status`, `level`) 
VALUES ('admin', 'admin@networkcrm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'active', 1)
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- Insert sample products
INSERT INTO `products` (`name`, `description`, `price`, `pv`, `bv`, `category`) VALUES
('Health Supplement - Basic', 'Basic health supplement package', 2999.00, 100.00, 2000.00, 'Health'),
('Health Supplement - Premium', 'Premium health supplement package', 5999.00, 200.00, 4000.00, 'Health'),
('Wellness Kit', 'Complete wellness kit', 9999.00, 300.00, 7000.00, 'Wellness'),
('Starter Pack', 'New distributor starter pack', 4999.00, 150.00, 3500.00, 'Starter')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Insert default commission settings
INSERT INTO `commission_settings` (`type`, `level`, `rate`, `description`) VALUES
('direct', 1, 0.1000, 'Direct sales commission - 10%'),
('direct', 2, 0.1200, 'Direct sales commission - 12%'),
('direct', 3, 0.1500, 'Direct sales commission - 15%'),
('binary', 1, 0.0500, 'Binary commission - 5%'),
('binary', 2, 0.0800, 'Binary commission - 8%'),
('matching', 1, 0.0300, 'Matching bonus - 3%'),
('leadership', 1, 0.0200, 'Leadership bonus - 2%'),
('leadership', 2, 0.0400, 'Leadership bonus - 4%')
ON DUPLICATE KEY UPDATE `rate` = VALUES(`rate`);

-- Insert sample training materials
INSERT INTO `training_materials` (`title`, `description`, `type`, `level`, `category`) VALUES
('Getting Started Guide', 'Complete guide for new distributors', 'document', 'beginner', 'Basic Training'),
('Sales Techniques', 'Effective sales techniques and scripts', 'video', 'intermediate', 'Sales Training'),
('Network Building', 'How to build and manage your network', 'video', 'intermediate', 'Network Building'),
('Product Knowledge', 'Detailed product information and benefits', 'presentation', 'beginner', 'Product Training'),
('Advanced Leadership', 'Advanced leadership and team management', 'video', 'advanced', 'Leadership')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Create indexes for better performance
CREATE INDEX `idx_users_network` ON `users` (`upline_id`, `level`, `status`);
CREATE INDEX `idx_leads_assigned_status` ON `leads` (`assigned_to`, `status`, `lead_score`);
CREATE INDEX `idx_commissions_user_status` ON `commissions` (`user_id`, `status`, `type`);
CREATE INDEX `idx_orders_user_date` ON `orders` (`user_id`, `order_date`, `status`);

-- Create views for common queries
CREATE OR REPLACE VIEW `user_network_stats` AS
SELECT 
    u.id,
    u.username,
    u.full_name,
    u.level,
    u.upline_id,
    COUNT(d.id) as direct_downlines,
    COUNT(l.id) as total_leads,
    COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
    COALESCE(SUM(c.amount), 0) as total_commissions,
    COALESCE(SUM(CASE WHEN c.status = 'paid' THEN c.amount ELSE 0 END), 0) as paid_commissions
FROM users u
LEFT JOIN users d ON d.upline_id = u.id
LEFT JOIN leads l ON l.assigned_to = u.id
LEFT JOIN commissions c ON c.user_id = u.id
WHERE u.role = 'team'
GROUP BY u.id;

CREATE OR REPLACE VIEW `monthly_performance` AS
SELECT 
    u.id,
    u.username,
    u.full_name,
    DATE_FORMAT(c.created_at, '%Y-%m') as month,
    COUNT(l.id) as leads_generated,
    COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
    COALESCE(SUM(c.amount), 0) as commission_earned,
    COUNT(d.id) as new_recruits
FROM users u
LEFT JOIN leads l ON l.assigned_to = u.id AND DATE_FORMAT(l.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
LEFT JOIN commissions c ON c.user_id = u.id AND DATE_FORMAT(c.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
LEFT JOIN users d ON d.upline_id = u.id AND DATE_FORMAT(d.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
WHERE u.role = 'team'
GROUP BY u.id, DATE_FORMAT(c.created_at, '%Y-%m');
