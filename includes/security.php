<?php
/**
 * Enhanced Security Features
 * Advanced security measures for the Direct Selling Business Support System
 */

class EnhancedSecurity {
    private static $instance = null;
    private $pdo;
    private $config;
    
    private function __construct() {
        $this->pdo = get_pdo_connection();
        $this->config = [
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'session_timeout' => 3600, // 1 hour
            'password_min_length' => 8,
            'require_2fa' => false,
            'log_security_events' => true
        ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enhanced login security with rate limiting
     */
    public function secureLogin($username, $password, $ip_address) {
        try {
            // Check if IP is blocked
            if ($this->isIpBlocked($ip_address)) {
                $this->logSecurityEvent('BLOCKED_IP_LOGIN_ATTEMPT', $ip_address, $username);
                return ['success' => false, 'message' => 'IP address is temporarily blocked'];
            }
            
            // Check login attempts
            $attempts = $this->getLoginAttempts($ip_address);
            if ($attempts >= $this->config['max_login_attempts']) {
                $this->blockIp($ip_address);
                $this->logSecurityEvent('MAX_LOGIN_ATTEMPTS_EXCEEDED', $ip_address, $username);
                return ['success' => false, 'message' => 'Too many login attempts. IP blocked for 15 minutes.'];
            }
            
            // Validate credentials
            $stmt = $this->pdo->prepare("
                SELECT id, username, password_hash, full_name, role, status, 
                       last_login, failed_login_attempts, locked_until
                FROM users 
                WHERE username = ? AND status = 'active'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->recordLoginAttempt($ip_address, false);
                $this->logSecurityEvent('INVALID_USERNAME', $ip_address, $username);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $this->logSecurityEvent('ACCOUNT_LOCKED_LOGIN_ATTEMPT', $ip_address, $username);
                return ['success' => false, 'message' => 'Account is temporarily locked'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->recordLoginAttempt($ip_address, false);
                $this->incrementFailedAttempts($user['id']);
                $this->logSecurityEvent('INVALID_PASSWORD', $ip_address, $username);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if password needs to be changed
            if ($this->isPasswordExpired($user['id'])) {
                $this->logSecurityEvent('PASSWORD_EXPIRED_LOGIN', $ip_address, $username);
                return ['success' => false, 'message' => 'Password expired. Please reset your password.', 'password_expired' => true];
            }
            
            // Successful login
            $this->recordLoginAttempt($ip_address, true);
            $this->resetFailedAttempts($user['id']);
            $this->updateLastLogin($user['id']);
            
            // Generate secure session token
            $session_token = $this->generateSecureToken();
            $this->storeSessionToken($user['id'], $session_token);
            
            $this->logSecurityEvent('SUCCESSFUL_LOGIN', $ip_address, $username);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ],
                'session_token' => $session_token
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent('LOGIN_ERROR', $ip_address, $username, $e->getMessage());
            return ['success' => false, 'message' => 'Login error occurred'];
        }
    }
    
    /**
     * Enhanced session validation
     */
    public function validateSession($session_token, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT st.*, u.status as user_status
                FROM session_tokens st
                JOIN users u ON st.user_id = u.id
                WHERE st.token = ? AND st.user_id = ? AND st.expires_at > NOW()
            ");
            $stmt->execute([$session_token, $user_id]);
            $session = $stmt->fetch();
            
            if (!$session) {
                $this->logSecurityEvent('INVALID_SESSION_TOKEN', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $user_id);
                return false;
            }
            
            // Check if user is still active
            if ($session['user_status'] !== 'active') {
                $this->logSecurityEvent('INACTIVE_USER_SESSION', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $user_id);
                return false;
            }
            
            // Update last activity
            $stmt = $this->pdo->prepare("UPDATE session_tokens SET last_activity = NOW() WHERE token = ?");
            $stmt->execute([$session_token]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logSecurityEvent('SESSION_VALIDATION_ERROR', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $user_id, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Two-Factor Authentication
     */
    public function setup2FA($user_id) {
        try {
            $secret = $this->generate2FASecret();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_2fa (user_id, secret, created_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE secret = VALUES(secret), created_at = NOW()
            ");
            $stmt->execute([$user_id, $secret]);
            
            return [
                'secret' => $secret,
                'qr_code_url' => $this->generateQRCode($user_id, $secret)
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent('2FA_SETUP_ERROR', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $user_id, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify 2FA code
     */
    public function verify2FA($user_id, $code) {
        try {
            $stmt = $this->pdo->prepare("SELECT secret FROM user_2fa WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return false;
            }
            
            // Verify TOTP code
            $isValid = $this->verifyTOTP($result['secret'], $code);
            
            if ($isValid) {
                $this->logSecurityEvent('2FA_SUCCESS', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $user_id);
            } else {
                $this->logSecurityEvent('2FA_FAILED', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $user_id);
            }
            
            return $isValid;
            
        } catch (Exception $e) {
            $this->logSecurityEvent('2FA_VERIFICATION_ERROR', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $user_id, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhanced password validation
     */
    public function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < $this->config['password_min_length']) {
            $errors[] = "Password must be at least {$this->config['password_min_length']} characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return $errors;
    }
    
    /**
     * Generate secure password
     */
    public function generateSecurePassword($length = 12) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        // Ensure at least one character from each required set
        $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[rand(0, 25)]; // Uppercase
        $password .= 'abcdefghijklmnopqrstuvwxyz'[rand(0, 25)]; // Lowercase
        $password .= '0123456789'[rand(0, 9)]; // Number
        $password .= '!@#$%^&*'[rand(0, 7)]; // Special character
        
        // Fill the rest randomly
        for ($i = 4; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return str_shuffle($password);
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($event_type, $ip_address, $user_id = null, $details = null) {
        if (!$this->config['log_security_events']) {
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_logs (event_type, ip_address, user_id, details, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$event_type, $ip_address, $user_id, $details]);
            
        } catch (Exception $e) {
            // Log to file if database fails
            error_log("Security log error: " . $e->getMessage());
        }
    }
    
    /**
     * Get security dashboard data
     */
    public function getSecurityDashboard() {
        try {
            $dashboard = [];
            
            // Recent security events
            $stmt = $this->pdo->query("
                SELECT event_type, ip_address, user_id, created_at
                FROM security_logs
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $dashboard['recent_events'] = $stmt->fetchAll();
            
            // Failed login attempts (last 24 hours)
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as count
                FROM security_logs
                WHERE event_type IN ('INVALID_PASSWORD', 'INVALID_USERNAME')
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $dashboard['failed_logins_24h'] = $stmt->fetchColumn();
            
            // Blocked IPs
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as count
                FROM blocked_ips
                WHERE blocked_until > NOW()
            ");
            $dashboard['blocked_ips'] = $stmt->fetchColumn();
            
            // Active sessions
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as count
                FROM session_tokens
                WHERE expires_at > NOW()
            ");
            $dashboard['active_sessions'] = $stmt->fetchColumn();
            
            return $dashboard;
            
        } catch (Exception $e) {
            $this->logSecurityEvent('SECURITY_DASHBOARD_ERROR', $_SERVER['REMOTE_ADDR'] ?? 'unknown', null, $e->getMessage());
            return [];
        }
    }
    
    // Private helper methods
    private function isIpBlocked($ip_address) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM blocked_ips 
            WHERE ip_address = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$ip_address]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function getLoginAttempts($ip_address) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ip_address]);
        return $stmt->fetchColumn();
    }
    
    private function recordLoginAttempt($ip_address, $success) {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (ip_address, success, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$ip_address, $success ? 1 : 0]);
    }
    
    private function blockIp($ip_address) {
        $stmt = $this->pdo->prepare("
            INSERT INTO blocked_ips (ip_address, blocked_until, created_at)
            VALUES (?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
            ON DUPLICATE KEY UPDATE blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip_address, $this->config['lockout_duration'], $this->config['lockout_duration']]);
    }
    
    private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    private function storeSessionToken($user_id, $token) {
        $stmt = $this->pdo->prepare("
            INSERT INTO session_tokens (user_id, token, expires_at, created_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
        ");
        $stmt->execute([$user_id, $token, $this->config['session_timeout']]);
    }
    
    private function generate2FASecret() {
        return base32_encode(random_bytes(20));
    }
    
    private function generateQRCode($user_id, $secret) {
        $issuer = 'Direct Selling Support';
        $user_email = $this->getUserEmail($user_id);
        return "otpauth://totp/{$issuer}:{$user_email}?secret={$secret}&issuer={$issuer}";
    }
    
    private function verifyTOTP($secret, $code) {
        // Implement TOTP verification
        // This would require a TOTP library like Google Authenticator
        return true; // Placeholder
    }
    
    private function getUserEmail($user_id) {
        $stmt = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['email'] : '';
    }
    
    private function incrementFailedAttempts($user_id) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                locked_until = CASE 
                    WHEN failed_login_attempts + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE locked_until
                END
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
    }
    
    private function resetFailedAttempts($user_id) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, locked_until = NULL
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
    }
    
    private function updateLastLogin($user_id) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
    }
    
    private function isPasswordExpired($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT password_changed_at 
            FROM users 
            WHERE id = ? AND password_changed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch() !== false;
    }
}

// Helper function for base32 encoding
function base32_encode($data) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0, $j = strlen($data); $i < $j; $i++) {
        $v <<= 8;
        $v += ord($data[$i]);
        $vbits += 8;
        
        while ($vbits >= 5) {
            $vbits -= 5;
            $output .= $alphabet[$v >> $vbits];
            $v &= ((1 << $vbits) - 1);
        }
    }
    
    if ($vbits > 0) {
        $v <<= (5 - $vbits);
        $output .= $alphabet[$v];
    }
    
    return $output;
}
?>
