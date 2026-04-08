<?php

namespace Phoenix;

/**
 * Security Configuration
 * Centralized security settings
 */

class SecurityConfig {
    /**
     * Session configuration
     */
    public static function getSessionConfig() {
        return [
            'cookie_httponly' => true,
            'cookie_secure' => false, // Set to true in production with HTTPS
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'cache_expire' => 5, // 5 minutes
            'gc_maxlifetime' => 300, // 5 minutes
        ];
    }

    /**
     * Error reporting configuration
     */
    public static function getErrorConfig() {
        return [
            'error_reporting' => E_ALL,
            'display_errors' => false, // CRITICAL: Never display errors
            'log_errors' => true,
            'error_log' => '/opt/lampp/htdocs/logs/php_errors.log',
        ];
    }

    /**
     * Logging configuration
     */
    public static function getLoggingConfig() {
        return [
            'log_path' => '/opt/lampp/htdocs/logs',
            'max_file_size' => 10485760, // 10MB
            'max_rotated_files' => 10,
            'log_level' => Logger::INFO, // Minimum level to log
        ];
    }

    /**
     * CORS configuration
     */
    public static function getCorsConfig() {
        return [
            'allowed_origins' => [
                'http://localhost',
                'http://localhost:80',
                'http://127.0.0.1',
                'http://localhost',
                // Add production domains here
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
        ];
    }

    /**
     * Rate limiting configuration
     */
    public static function getRateLimitConfig() {
        return [
            'login_limit' => 5,  // attempts
            'login_window' => 300,  // 5 minutes
            'api_limit' => 100,  // requests
            'api_window' => 3600,  // 1 hour
            'password_reset_limit' => 3,  // requests
            'password_reset_window' => 3600,  // 1 hour
        ];
    }

    /**
     * Content Security Policy headers
     */
    public static function getCSPHeaders() {
        return [
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];
    }

    /**
     * Sensitive paths (should not be directly accessible)
     */
    public static function getSensitivePaths() {
        return [
            '/logs/',
            '/.git/',
            '/config/',
            '/vendor/',
            '/database/',
        ];
    }

    /**
     * SQL injection prevention
     */
    public static function usePreparedStatements() {
        return true;
    }

    /**
     * HTTPS enforcement
     */
    public static function enforceHttps() {
        return false; // Set to true in production
    }

    /**
     * Password requirements
     */
    public static function getPasswordRequirements() {
        return [
            'min_length' => 8,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_special' => false,
            'expiry_days' => 90, // 0 = no expiry
        ];
    }

    /**
     * Two-factor authentication
     */
    public static function isTwoFactorEnabled() {
        return false; // Set to true when implemented
    }

    /**
     * API key configuration
     */
    public static function getApiKeyConfig() {
        return [
            'header_name' => 'X-API-Key',
            'min_length' => 32,
            'max_length' => 128,
        ];
    }

    /**
     * Apply security headers to response
     */
    public static function applySecurityHeaders() {
        $headers = self::getCSPHeaders();
        foreach ($headers as $header => $value) {
            header("{$header}: {$value}");
        }
    }

    /**
     * Apply session configuration
     */
    public static function applySessionConfig() {
        $config = self::getSessionConfig();
        foreach ($config as $key => $value) {
            if (strpos($key, 'cookie_') === 0) {
                ini_set("session.{$key}", $value);
            } elseif ($key === 'cache_expire' || $key === 'gc_maxlifetime') {
                ini_set("session.{$key}", $value);
            } elseif ($key === 'use_strict_mode') {
                ini_set("session.use_strict_mode", $value ? 1 : 0);
            }
        }
    }

    /**
     * Apply error configuration
     */
    public static function applyErrorConfig() {
        $config = self::getErrorConfig();
        error_reporting($config['error_reporting']);
        ini_set('display_errors', $config['display_errors'] ? 1 : 0);
        ini_set('log_errors', $config['log_errors'] ? 1 : 0);
        ini_set('error_log', $config['error_log']);
    }

    /**
     * Validate security configuration
     */
    public static function validate() {
        $errors = [];

        // Check logs directory
        $logPath = self::getLoggingConfig()['log_path'];
        if (!is_dir($logPath)) {
            $errors[] = "Logs directory does not exist: {$logPath}";
        } elseif (!is_writable($logPath)) {
            $errors[] = "Logs directory is not writable: {$logPath}";
        }

        // Check display_errors is disabled
        if (ini_get('display_errors')) {
            $errors[] = "display_errors is enabled - this is a security risk!";
        }

        // Check HTTPS in production
        if (self::enforceHttps() && $_SERVER['REQUEST_SCHEME'] !== 'https') {
            $errors[] = "HTTPS is required but request is HTTP";
        }

        return $errors;
    }

    /**
     * Report configuration status
     */
    public static function report() {
        Logger::info('Security Configuration Report', [
            'session_config' => self::getSessionConfig(),
            'error_config' => self::getErrorConfig(),
            'rate_limits' => self::getRateLimitConfig(),
        ]);
    }
}
