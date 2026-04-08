<?php

namespace Phoenix;

/**
 * Security Helper Functions
 * Utility functions for error handling and security
 */

class SecurityHelper {
    /**
     * Check if user has permission to access resource
     */
    public static function checkAccess($requiredRoles = []) {
        if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
            self::deny('Unauthorized access');
            return false;
        }

        if (!empty($requiredRoles) && !in_array($_SESSION['role'], $requiredRoles, true)) {
            Logger::warning('Unauthorized access attempt', [
                'user' => $_SESSION['username'],
                'required_roles' => $requiredRoles,
                'user_role' => $_SESSION['role']
            ]);
            self::deny('Access denied');
            return false;
        }

        return true;
    }

    /**
     * Handle access denial
     */
    public static function deny($message = 'Access denied') {
        http_response_code(403);

        if (self::isJson()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $message]);
        } else {
            require __DIR__ . '/../errors/403.php';
        }

        exit;
    }

    /**
     * Sanitize user input
     */
    public static function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT);

            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);

            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL);

            case 'string':
            default:
                return htmlspecialchars((string)$input, ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Check if request is AJAX/JSON
     */
    public static function isJson() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Return JSON response
     */
    public static function jsonResponse($success, $data = [], $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => $success], $data), JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Log access attempt
     */
    public static function logAccess($action, $details = []) {
        Logger::info("Access: {$action}", array_merge([
            'user' => $_SESSION['username'] ?? 'guest',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], $details));
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $severity = Logger::WARNING, $details = []) {
        Logger::log($severity, "Security Event: {$event}", array_merge([
            'user' => $_SESSION['username'] ?? 'guest',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], $details));
    }

    /**
     * Get safe error message for users
     */
    public static function getSafeErrorMessage($exceptionOrError) {
        if ($exceptionOrError instanceof Exception) {
            $code = $exceptionOrError->getCode();
        } else {
            $code = 500;
        }

        $messages = [
            400 => 'Invalid request format.',
            401 => 'Authentication required.',
            403 => 'Access denied.',
            404 => 'Resource not found.',
            422 => 'Validation failed. Please check your input.',
            429 => 'Too many requests. Please try again later.',
            500 => 'An error occurred. Please try again or contact support.',
            503 => 'Service temporarily unavailable. Please try again later.'
        ];

        return $messages[$code] ?? $messages[500];
    }

    /**
     * Generate a random token
     *
     * @param int $length Length in bytes (will be doubled in hex)
     * @return string
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Hash a password
     *
     * @param string $password
     * @return string
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify a password against a hash
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Rate limiting check
     */
    public static function checkRateLimit($key, $limit = 100, $window = 3600) {
        $cacheKey = "ratelimit_{$key}";
        $file = '/opt/lampp/htdocs/logs/ratelimit_' . md5($key) . '.json';

        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? [];
        }

        $now = time();
        $data = array_filter($data, function($ts) use ($now, $window) {
            return $ts > ($now - $window);
        });

        $data[] = $now;

        if (count($data) > $limit) {
            Logger::warning("Rate limit exceeded for key: {$key}", [
                'limit' => $limit,
                'window' => $window,
                'attempts' => count($data)
            ]);
            return false;
        }

        @file_put_contents($file, json_encode($data));
        return true;
    }
}
