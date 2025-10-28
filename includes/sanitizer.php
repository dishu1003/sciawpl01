<?php
/**
 * Sanitizer Class
 * Simple input cleaning for forms
 */
if (!class_exists('Sanitizer')) {
    class Sanitizer {

        /**
         * Clean string input
         */
        public static function clean($input) {
            $input = trim($input);
            $input = stripslashes($input);
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return $input;
        }

        /**
         * Validate and sanitize email
         */
        public static function email($email) {
            $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return '';
            }
            return $email;
        }

        /**
         * Validate and sanitize phone numbers
         * Accepts digits, +, -, and spaces
         */
        public static function phone($phone) {
            $phone = trim($phone);
            $phone = preg_replace('/[^\d\+\-\s]/', '', $phone);
            return $phone;
        }

        /**
         * Sanitize text area or long input
         */
        public static function text($input) {
            $input = strip_tags($input);
            $input = trim($input);
            return $input;
        }
    }
}
?>
