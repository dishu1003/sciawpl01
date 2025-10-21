<?php
/**
 * Input Validation and Sanitization Class
 */

class Validator {
    private $errors = [];
    private $data = [];

    /**
     * Validate required field
     */
    public function required($field, $value, $message = null) {
        if (empty(trim($value))) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' is required';
            return false;
        }
        return true;
    }

    /**
     * Validate email
     */
    public function email($field, $value, $message = null) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? 'Invalid email address';
            return false;
        }
        return true;
    }

    /**
     * Validate phone number (Indian format)
     */
    public function phone($field, $value, $message = null) {
        // Remove spaces, dashes, and parentheses
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $value);
        
        // Check if it's a valid Indian phone number (10 digits starting with 6-9)
        if (!preg_match('/^[6-9]\d{9}$/', $cleaned)) {
            $this->errors[$field] = $message ?? 'Invalid phone number. Must be 10 digits starting with 6-9';
            return false;
        }
        
        $this->data[$field] = $cleaned;
        return true;
    }

    /**
     * Validate minimum length
     */
    public function minLength($field, $value, $min, $message = null) {
        if (strlen(trim($value)) < $min) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must be at least $min characters";
            return false;
        }
        return true;
    }

    /**
     * Validate maximum length
     */
    public function maxLength($field, $value, $max, $message = null) {
        if (strlen(trim($value)) > $max) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must not exceed $max characters";
            return false;
        }
        return true;
    }

    /**
     * Validate string (alphanumeric with spaces)
     */
    public function string($field, $value, $message = null) {
        if (!preg_match('/^[a-zA-Z\s]+$/', $value)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must contain only letters and spaces';
            return false;
        }
        return true;
    }

    /**
     * Validate alphanumeric
     */
    public function alphanumeric($field, $value, $message = null) {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must contain only letters and numbers';
            return false;
        }
        return true;
    }

    /**
     * Validate URL
     */
    public function url($field, $value, $message = null) {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errors[$field] = $message ?? 'Invalid URL';
            return false;
        }
        return true;
    }

    /**
     * Validate enum (value must be in array)
     */
    public function enum($field, $value, $allowed, $message = null) {
        if (!in_array($value, $allowed, true)) {
            $this->errors[$field] = $message ?? 'Invalid value for ' . $field;
            return false;
        }
        return true;
    }

    /**
     * Sanitize string
     */
    public function sanitize($value) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize email
     */
    public function sanitizeEmail($value) {
        return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails() {
        return !$this->passes();
    }

    /**
     * Get first error message
     */
    public function getFirstError() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    /**
     * Get sanitized data
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Add custom error
     */
    public function addError($field, $message) {
        $this->errors[$field] = $message;
    }
}

/**
 * Sanitization Helper Functions
 */
class Sanitizer {
    /**
     * Clean string
     */
    public static function clean($value) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Clean email
     */
    public static function email($value) {
        return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Clean phone
     */
    public static function phone($value) {
        return preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Clean integer
     */
    public static function int($value) {
        return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Clean URL
     */
    public static function url($value) {
        return filter_var(trim($value), FILTER_SANITIZE_URL);
    }

    /**
     * Strip tags
     */
    public static function stripTags($value, $allowedTags = '') {
        return strip_tags($value, $allowedTags);
    }
}
?>
