<?php

namespace Phoenix;

/**
 * Global Error Handler
 * Catches all PHP errors and exceptions with safe user feedback
 * Never displays sensitive error details to users
 */

if (!class_exists('Logger')) {
    require_once __DIR__ . '/Logger.php';
}

class ErrorHandler {
    /**
     * Register error and exception handlers
     */
    public static function register() {
        // Custom error handler
        set_error_handler([__CLASS__, 'handleError']);

        // Custom exception handler
        set_exception_handler([__CLASS__, 'handleException']);

        // Shutdown handler for fatal errors
        register_shutdown_function([__CLASS__, 'handleFatalError']);

        // Initialize logger
        Logger::initialize();

        // Configure PHP error handling
        error_reporting(E_ALL);
        ini_set('display_errors', 0); // CRITICAL: Never display errors to users
        ini_set('log_errors', 1);
        ini_set('error_log', '/opt/lampp/htdocs/logs/php_errors.log');
    }

    /**
     * Handle PHP errors (E_WARNING, E_NOTICE, etc.)
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        // Don't handle errors suppressed with @
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // Determine severity level
        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                $level = Logger::CRITICAL;
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_ERROR:
                $level = Logger::ERROR;
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $level = Logger::WARNING;
                break;
            default:
                $level = Logger::NOTICE;
        }

        // Log error with context
        $errorType = self::getErrorName($errno);
        $message = "{$errorType}: {$errstr}";

        Logger::log($level, $message, [
            'type' => 'php_error',
            'errno' => $errno,
            'file' => self::sanitizePath($errfile),
            'line' => $errline
        ]);

        // For critical errors, show user-friendly message
        if ($level <= Logger::ERROR) {
            self::showUserError();
        }

        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception) {
        $message = sprintf(
            "Uncaught %s: %s",
            get_class($exception),
            $exception->getMessage()
        );

        Logger::critical($message, [
            'type' => 'exception',
            'exception_class' => get_class($exception),
            'file' => self::sanitizePath($exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => self::sanitizeTrace($exception->getTraceAsString())
        ]);

        self::showUserError();
    }

    /**
     * Handle fatal errors and fatal exceptions
     */
    public static function handleFatalError() {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorType = self::getErrorName($error['type']);
            $message = "{$errorType}: {$error['message']}";

            Logger::emergency($message, [
                'type' => 'fatal_error',
                'error_type' => $error['type'],
                'file' => self::sanitizePath($error['file']),
                'line' => $error['line']
            ]);

            self::showUserError();
        }
    }

    /**
     * Show user-friendly error page
     * Never exposes technical details or system information
     */
    private static function showUserError() {
        // Prevent output buffering issues
        while (@ob_get_level() > 0) {
            @ob_end_clean();
        }

        // Check if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'error' => 'An error occurred while processing your request. Please try again.'
            ]);
            exit;
        }

        // Regular request - show error page
        http_response_code(500);
        require __DIR__ . '/../errors/500.php';
        exit;
    }

    /**
     * Get human-readable error name from errno
     */
    private static function getErrorName($errno) {
        $errorNames = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $errorNames[$errno] ?? 'E_UNKNOWN';
    }

    /**
     * Sanitize file paths to avoid exposing system structure
     */
    private static function sanitizePath($path) {
        // Remove any sensitive prefixes
        $path = str_replace('/opt/lampp/htdocs/', '', $path);
        return $path;
    }

    /**
     * Sanitize stack trace to remove sensitive info
     */
    private static function sanitizeTrace($trace) {
        // Remove full paths but keep relative file structure
        return str_replace('/opt/lampp/htdocs/', '', $trace);
    }

    /**
     * Public method to manually log with level
     */
    public static function log($level, $message, $context = []) {
        Logger::log($level, $message, $context);
    }
}
