<?php
/**
 * Security Headers Class
 * Sets secure HTTP headers to protect against common attacks
 */

class SecurityHeaders {
    /**
     * Set all security headers
     */
    public static function setAll() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;");
        
        // Strict Transport Security (HSTS) - Force HTTPS
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        
        // Permissions Policy (formerly Feature Policy)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    /**
     * Set CORS headers for API endpoints
     */
    public static function setCORS($allowedOrigins = []) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            header("Access-Control-Allow-Origin: " . env('SITE_URL'));
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
}

/**
 * Rate Limiting Class
 * Prevents brute force and spam attacks
 */

class RateLimiter {
    private $pdo;
    private $identifier;
    private $action;
    
    public function __construct($pdo, $action = 'general') {
        $this->pdo = $pdo;
        $this->action = $action;
        $this->identifier = $this->getIdentifier();
        $this->createTableIfNotExists();
    }

    /**
     * Get unique identifier (IP + User Agent)
     */
    private function getIdentifier() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip . $userAgent);
    }

    /**
     * Create rate limit table if not exists
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(64) NOT NULL,
            action VARCHAR(50) NOT NULL,
            attempts INT DEFAULT 1,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            blocked_until TIMESTAMP NULL,
            INDEX idx_identifier_action (identifier, action),
            INDEX idx_blocked_until (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Rate limit table creation failed: " . $e->getMessage());
        }
    }

    /**
     * Check if rate limit exceeded
     * 
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @param int $blockDuration Block duration in seconds if exceeded
     * @return bool True if allowed, false if rate limited
     */
    public function check($maxAttempts = 5, $timeWindow = 300, $blockDuration = 900) {
        // Clean old records
        $this->cleanup($timeWindow);

        // Check if currently blocked
        $stmt = $this->pdo->prepare("
            SELECT blocked_until FROM rate_limits 
            WHERE identifier = ? AND action = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$this->identifier, $this->action]);
        
        if ($stmt->fetch()) {
            return false; // Still blocked
        }

        // Get attempts in time window
        $stmt = $this->pdo->prepare("
            SELECT attempts FROM rate_limits 
            WHERE identifier = ? AND action = ? 
            AND last_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$this->identifier, $this->action, $timeWindow]);
        $record = $stmt->fetch();

        if ($record) {
            $attempts = $record['attempts'];
            
            if ($attempts >= $maxAttempts) {
                // Block the user
                $this->block($blockDuration);
                return false;
            }
            
            // Increment attempts
            $this->increment();
        } else {
            // First attempt in this window
            $this->record();
        }

        return true;
    }

    /**
     * Record new attempt
     */
    private function record() {
        $stmt = $this->pdo->prepare("
            INSERT INTO rate_limits (identifier, action, attempts, last_attempt) 
            VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = 1, last_attempt = NOW()
        ");
        $stmt->execute([$this->identifier, $this->action]);
    }

    /**
     * Increment attempts
     */
    private function increment() {
        $stmt = $this->pdo->prepare("
            UPDATE rate_limits 
            SET attempts = attempts + 1, last_attempt = NOW() 
            WHERE identifier = ? AND action = ?
        ");
        $stmt->execute([$this->identifier, $this->action]);
    }

    /**
     * Block user
     */
    private function block($duration) {
        $stmt = $this->pdo->prepare("
            UPDATE rate_limits 
            SET blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) 
            WHERE identifier = ? AND action = ?
        ");
        $stmt->execute([$duration, $this->identifier, $this->action]);
    }

    /**
     * Clean old records
     */
    private function cleanup($timeWindow) {
        $stmt = $this->pdo->prepare("
            DELETE FROM rate_limits 
            WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND) 
            AND blocked_until IS NULL
        ");
        $stmt->execute([$timeWindow * 2]);
    }

    /**
     * Reset rate limit for current identifier
     */
    public function reset() {
        $stmt = $this->pdo->prepare("
            DELETE FROM rate_limits 
            WHERE identifier = ? AND action = ?
        ");
        $stmt->execute([$this->identifier, $this->action]);
    }

    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts($maxAttempts = 5, $timeWindow = 300) {
        $stmt = $this->pdo->prepare("
            SELECT attempts FROM rate_limits 
            WHERE identifier = ? AND action = ? 
            AND last_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$this->identifier, $this->action, $timeWindow]);
        $record = $stmt->fetch();

        if ($record) {
            return max(0, $maxAttempts - $record['attempts']);
        }

        return $maxAttempts;
    }
}
?>
