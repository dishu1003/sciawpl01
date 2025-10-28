-- Add Superadmin User to Database
-- Run this SQL to add superadmin user

INSERT INTO users (username, email, password_hash, full_name, phone, role, status, referral_code, joining_date, performance_rating) VALUES
('superadmin', 'superadmin@spartancommunity.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', '9876543210', 'admin', 'active', 'SUPER001', '2024-01-01', 5.00);

-- Update existing admin user password to 'admin123'
UPDATE users SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';

-- Update existing admin user password to 'admin'
UPDATE users SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';

