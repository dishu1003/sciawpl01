CREATE DATABASE IF NOT EXISTS lead_management;
USE lead_management;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'team') DEFAULT 'team',
    unique_ref VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leads Table
CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),

    -- Form A Data
    form_a_data JSON,
    form_a_submitted_at TIMESTAMP NULL,

    -- Form B Data
    form_b_data JSON,
    form_b_submitted_at TIMESTAMP NULL,

    -- Form C Data
    form_c_data JSON,
    form_c_submitted_at TIMESTAMP NULL,

    -- Form D Data
    form_d_data JSON,
    form_d_submitted_at TIMESTAMP NULL,

    current_step INT DEFAULT 1,
    ref_id VARCHAR(50),
    assigned_to INT,
    status ENUM('new', 'contacted', 'qualified', 'converted', 'lost') DEFAULT 'new',
    lead_score ENUM('HOT', 'WARM', 'COLD') DEFAULT 'COLD',
    notes TEXT,
    tags VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Scripts Table
CREATE TABLE scripts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('followup', 'sales', 'closing', 'objection') NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_by INT,
    visibility ENUM('all', 'admin_only') DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Activity Logs Table
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Templates Table
CREATE TABLE templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('whatsapp', 'email', 'sms') NOT NULL,
    subject VARCHAR(200),
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Default Admin
INSERT INTO users (name, email, username, password, role, unique_ref)
VALUES ('Admin', 'admin@example.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'admin123');
-- Default password: password

-- Insert Sample Scripts
INSERT INTO scripts (type, title, content, created_by, visibility) VALUES
('followup', 'Initial Follow-up Script', 'Namaste [Name], Main [Your Name] bol raha hoon. Aapne hamare form mein interest dikhaya tha. Kya aap 5 minute baat kar sakte hain?', 1, 'all'),
('sales', 'Product Pitch Script', 'Humara program aapko [benefit 1], [benefit 2], aur [benefit 3] provide karta hai. Kya aap iske baare mein aur jaanna chahenge?', 1, 'all'),
('objection', 'Price Objection Handler', 'Main samajh sakta hoon. Lekin soचiye, agar yeh investment aapko [ROI] de sakta hai, toh kya yeh worth it nahi hoga?', 1, 'all');

-- Performance Indexes
-- Leads table indexes
CREATE INDEX idx_leads_email ON leads(email);
CREATE INDEX idx_leads_phone ON leads(phone);
CREATE INDEX idx_leads_ref_id ON leads(ref_id);
CREATE INDEX idx_leads_assigned_to ON leads(assigned_to);
CREATE INDEX idx_leads_status ON leads(status);
CREATE INDEX idx_leads_lead_score ON leads(lead_score);
CREATE INDEX idx_leads_current_step ON leads(current_step);
CREATE INDEX idx_leads_created_at ON leads(created_at);
CREATE INDEX idx_leads_status_score ON leads(status, lead_score);
CREATE INDEX idx_leads_assigned_status ON leads(assigned_to, status);

-- Users table indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_unique_ref ON users(unique_ref);
CREATE INDEX idx_users_role_status ON users(role, status);

-- Logs table indexes
CREATE INDEX idx_logs_lead_id ON logs(lead_id);
CREATE INDEX idx_logs_user_id ON logs(user_id);
CREATE INDEX idx_logs_timestamp ON logs(timestamp);
CREATE INDEX idx_logs_lead_timestamp ON logs(lead_id, timestamp);

-- Scripts table indexes
CREATE INDEX idx_scripts_type ON scripts(type);
CREATE INDEX idx_scripts_visibility ON scripts(visibility);
CREATE INDEX idx_scripts_created_by ON scripts(created_by);

-- Templates table indexes
CREATE INDEX idx_templates_type ON templates(type);
CREATE INDEX idx_templates_name ON templates(name);

-- Rate Limits table for security
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(64) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    INDEX idx_identifier_action (identifier, action),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;