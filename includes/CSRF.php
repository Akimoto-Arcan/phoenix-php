<?php

namespace Phoenix;

/**
 * CSRF (Cross-Site Request Forgery) Protection
 *
 * Provides token generation and validation to prevent CSRF attacks
 */

class CSRF {
    private const TOKEN_NAME = '_csrf_token';
    private const TOKEN_EXPIRE = 3600; // 1 hour

    /**
     * Generate a new CSRF token
     *
     * @return string
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate token
        $token = bin2hex(random_bytes(32));
        $timestamp = time();

        // Store in session
        $_SESSION[self::TOKEN_NAME] = [
            'token' => $token,
            'timestamp' => $timestamp
        ];

        return $token;
    }

    /**
     * Get current CSRF token (or generate new one)
     *
     * @return string
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if token exists and is not expired
        if (isset($_SESSION[self::TOKEN_NAME])) {
            $data = $_SESSION[self::TOKEN_NAME];
            $age = time() - $data['timestamp'];

            if ($age < self::TOKEN_EXPIRE) {
                return $data['token'];
            }
        }

        // Generate new token
        return self::generateToken();
    }

    /**
     * Validate CSRF token
     *
     * @param string|null $token Token to validate
     * @return bool
     */
    public static function validateToken($token = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Get token from request if not provided
        if ($token === null) {
            $token = self::getTokenFromRequest();
        }

        if (!$token) {
            return false;
        }

        // Check if session token exists
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        $data = $_SESSION[self::TOKEN_NAME];

        // Check if expired
        $age = time() - $data['timestamp'];
        if ($age >= self::TOKEN_EXPIRE) {
            return false;
        }

        // Constant-time comparison to prevent timing attacks
        return hash_equals($data['token'], $token);
    }

    /**
     * Get token from current request
     *
     * @return string|null
     */
    private static function getTokenFromRequest() {
        // Check POST data
        if (isset($_POST[self::TOKEN_NAME])) {
            return $_POST[self::TOKEN_NAME];
        }

        // Check headers (for AJAX requests)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // Check JSON body
        if (isset($_SERVER['CONTENT_TYPE']) &&
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (isset($data[self::TOKEN_NAME])) {
                return $data[self::TOKEN_NAME];
            }
        }

        return null;
    }

    /**
     * Require valid CSRF token or die
     *
     * @param string|null $token
     * @return void
     */
    public static function requireToken($token = null) {
        if (!self::validateToken($token)) {
            error_log("CSRF token validation failed from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            // Check if AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => false,
                    'error' => 'CSRF token validation failed'
                ]);
                exit;
            }

            // Regular request
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }

    /**
     * Generate HTML hidden input field with token
     *
     * @return string
     */
    public static function field() {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Generate HTML meta tag with token (for JavaScript)
     *
     * @return string
     */
    public static function meta() {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    /**
     * Get token name (for JavaScript)
     *
     * @return string
     */
    public static function getTokenName() {
        return self::TOKEN_NAME;
    }

    /**
     * Validate CSRF token from current request
     *
     * @return bool
     */
    public static function validateRequest() {
        $token = self::getTokenFromRequest();
        return self::validateToken($token);
    }

    /**
     * Regenerate token (call after successful form submission)
     *
     * @return string
     */
    public static function regenerateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::TOKEN_NAME]);
        return self::generateToken();
    }
}
