<?php
/**
 * Environment Variable Loader
 * Loads configuration from .env file
 */

class EnvLoader {
    private static $loaded = false;
    private static $vars = [];

    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = __DIR__ . '/../.env';
        }

        if (!file_exists($path)) {
            // If .env file is missing, just return.
            // The application will use default values from config.php
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                // Store in static array and set as environment variable
                self::$vars[$key] = $value;
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        return self::$vars[$key] ?? getenv($key) ?: $default;
    }

    public static function required($key) {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new Exception("Required environment variable '$key' is not set");
        }
        return $value;
    }
}

// Helper function
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}
?>
