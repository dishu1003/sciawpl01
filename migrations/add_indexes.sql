-- Migration: Add Performance Indexes
-- Run this on existing databases to add indexes

USE u782093275_awpl;

-- Check and add indexes for leads table
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_email');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_email ON leads(email)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_phone');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_phone ON leads(phone)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_ref_id');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_ref_id ON leads(ref_id)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_assigned_to');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_assigned_to ON leads(assigned_to)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_status');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_status ON leads(status)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_lead_score');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_lead_score ON leads(lead_score)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_current_step');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_current_step ON leads(current_step)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_created_at');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_created_at ON leads(created_at)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_status_score');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_status_score ON leads(status, lead_score)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leads' AND index_name = 'idx_leads_assigned_status');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_leads_assigned_status ON leads(assigned_to, status)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Users table indexes
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_email');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_users_email ON users(email)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_username');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_users_username ON users(username)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_unique_ref');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_users_unique_ref ON users(unique_ref)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_role_status');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_users_role_status ON users(role, status)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Logs table indexes
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'logs' AND index_name = 'idx_logs_lead_id');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_logs_lead_id ON logs(lead_id)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'logs' AND index_name = 'idx_logs_user_id');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_logs_user_id ON logs(user_id)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'logs' AND index_name = 'idx_logs_timestamp');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_logs_timestamp ON logs(timestamp)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'logs' AND index_name = 'idx_logs_lead_timestamp');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_logs_lead_timestamp ON logs(lead_id, timestamp)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Scripts table indexes
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'scripts' AND index_name = 'idx_scripts_type');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_scripts_type ON scripts(type)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'scripts' AND index_name = 'idx_scripts_visibility');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_scripts_visibility ON scripts(visibility)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'scripts' AND index_name = 'idx_scripts_created_by');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_scripts_created_by ON scripts(created_by)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Templates table indexes
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'templates' AND index_name = 'idx_templates_type');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_templates_type ON templates(type)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'templates' AND index_name = 'idx_templates_name');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_templates_name ON templates(name)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SELECT 'Migration completed successfully!' AS status;
