<?php
/**
 * Custom Error Handler and Logger
 */

class Logger {
    private static $logDir = __DIR__ . '/../logs';
    private static $logFile = 'app.log';
    private static $errorFile = 'error.log';
    private static $securityFile = 'security.log';

    /**
     * Initialize logger
     */
    public static function init() {
        // Create logs directory if not exists
        if (!file_exists(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }

        // Set custom error handler
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);
        register_shutdown_function([self::class, 'shutdownHandler']);
    }

    /**
     * Log general message
     */
    public static function log($message, $level = 'INFO', $context = []) {
        $logEntry = self::formatLogEntry($message, $level, $context);
        self::writeToFile(self::$logFile, $logEntry);
    }

    /**
     * Log error
     */
    public static function error($message, $context = []) {
        $logEntry = self::formatLogEntry($message, 'ERROR', $context);
        self::writeToFile(self::$errorFile, $logEntry);
    }

    /**
     * Log security event
     */
    public static function security($message, $context = []) {
        $logEntry = self::formatLogEntry($message, 'SECURITY', $context);
        self::writeToFile(self::$securityFile, $logEntry);
    }

    /**
     * Log warning
     */
    public static function warning($message, $context = []) {
        $logEntry = self::formatLogEntry($message, 'WARNING', $context);
        self::writeToFile(self::$logFile, $logEntry);
    }

    /**
     * Log info
     */
    public static function info($message, $context = []) {
        $logEntry = self::formatLogEntry($message, 'INFO', $context);
        self::writeToFile(self::$logFile, $logEntry);
    }

    /**
     * Log debug (only in debug mode)
     */
    public static function debug($message, $context = []) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            $logEntry = self::formatLogEntry($message, 'DEBUG', $context);
            self::writeToFile(self::$logFile, $logEntry);
        }
    }

    /**
     * Format log entry
     */
    private static function formatLogEntry($message, $level, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'N/A';
        
        $entry = "[$timestamp] [$level] [$ip] [$method $uri] $message";
        
        if (!empty($context)) {
            $entry .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        return $entry . PHP_EOL;
    }

    /**
     * Write to log file
     */
    private static function writeToFile($filename, $content) {
        $filepath = self::$logDir . '/' . $filename;
        
        // Rotate log if too large (> 10MB)
        if (file_exists($filepath) && filesize($filepath) > 10 * 1024 * 1024) {
            $rotatedFile = $filepath . '.' . date('Y-m-d-His') . '.old';
            rename($filepath, $rotatedFile);
        }
        
        file_put_contents($filepath, $content, FILE_APPEND | LOCK_EX);
    }

    /**
     * Custom error handler
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE ERROR',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE ERROR',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_ERROR => 'USER ERROR',
            E_USER_WARNING => 'USER WARNING',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER DEPRECATED'
        ];

        $errorType = $errorTypes[$errno] ?? 'UNKNOWN';
        $message = "$errorType: $errstr in $errfile on line $errline";
        
        self::error($message);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Custom exception handler
     */
    public static function exceptionHandler($exception) {
        $message = "Uncaught Exception: " . $exception->getMessage() . 
                   " in " . $exception->getFile() . 
                   " on line " . $exception->getLine();
        
        self::error($message, [
            'trace' => $exception->getTraceAsString()
        ]);

        // Show user-friendly error page
        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo "<h1>Exception</h1>";
            echo "<p>" . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        } else {
            echo "<h1>An error occurred</h1>";
            echo "<p>We're sorry, but something went wrong. Please try again later.</p>";
        }
        
        exit(1);
    }

    /**
     * Shutdown handler for fatal errors
     */
    public static function shutdownHandler() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $message = "FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}";
            self::error($message);
        }
    }

    /**
     * Clear old logs (older than 30 days)
     */
    public static function clearOldLogs($days = 30) {
        $files = glob(self::$logDir . '/*.old');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }
}

/**
 * Custom Exception Classes
 */

class ValidationException extends Exception {
    private $errors;

    public function __construct($errors, $message = "Validation failed", $code = 422) {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors() {
        return $this->errors;
    }
}

class DatabaseException extends Exception {
    public function __construct($message = "Database error occurred", $code = 500) {
        parent::__construct($message, $code);
    }
}

class AuthenticationException extends Exception {
    public function __construct($message = "Authentication failed", $code = 401) {
        parent::__construct($message, $code);
    }
}

class AuthorizationException extends Exception {
    public function __construct($message = "Access denied", $code = 403) {
        parent::__construct($message, $code);
    }
}

class RateLimitException extends Exception {
    public function __construct($message = "Too many requests", $code = 429) {
        parent::__construct($message, $code);
    }
}

// Initialize logger
Logger::init();
?>
