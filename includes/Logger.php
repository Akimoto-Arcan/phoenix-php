<?php

namespace Phoenix;

/**
 * Application Logger
 * Centralized logging with multiple levels and handlers
 * Implements PSR-3 like logging levels
 */

class Logger {
    // PSR-3 Log Levels
    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;

    private static $logLevels = [
        0 => 'EMERGENCY',
        1 => 'ALERT',
        2 => 'CRITICAL',
        3 => 'ERROR',
        4 => 'WARNING',
        5 => 'NOTICE',
        6 => 'INFO',
        7 => 'DEBUG'
    ];

    private static $logPath = '/opt/lampp/htdocs/logs';
    private static $maxFileSize = 10485760; // 10MB
    private static $initialized = false;

    /**
     * Initialize logger (ensure logs directory exists)
     */
    public static function initialize() {
        if (self::$initialized) {
            return;
        }

        // Create logs directory if it doesn't exist
        if (!is_dir(self::$logPath)) {
            @mkdir(self::$logPath, 0775, true);
        }

        self::$initialized = true;
    }

    /**
     * Log emergency message - system is unusable
     */
    public static function emergency($message, $context = []) {
        self::log(self::EMERGENCY, $message, $context);
    }

    /**
     * Log alert message - action must be taken immediately
     */
    public static function alert($message, $context = []) {
        self::log(self::ALERT, $message, $context);
    }

    /**
     * Log critical message - critical conditions
     */
    public static function critical($message, $context = []) {
        self::log(self::CRITICAL, $message, $context);
    }

    /**
     * Log error message - error conditions
     */
    public static function error($message, $context = []) {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Log warning message - warning conditions
     */
    public static function warning($message, $context = []) {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Log notice message - normal but significant condition
     */
    public static function notice($message, $context = []) {
        self::log(self::NOTICE, $message, $context);
    }

    /**
     * Log info message - informational message
     */
    public static function info($message, $context = []) {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Log debug message - debug level message
     */
    public static function debug($message, $context = []) {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Main logging method
     */
    private static function log($level, $message, $context = []) {
        self::initialize();

        $levelName = self::$logLevels[$level] ?? 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');
        $username = $_SESSION['username'] ?? 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'cli';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'N/A';

        // Build context array with request info
        $fullContext = array_merge([
            'user' => $username,
            'ip' => $ip,
            'uri' => $requestUri,
            'method' => $requestMethod,
        ], $context);

        // Format message with context
        $contextStr = !empty($context) ? ' | ' . json_encode($fullContext, JSON_UNESCAPED_SLASHES) : '';
        $logMessage = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            $levelName,
            $message,
            $contextStr
        );

        // Write to file
        $logFile = self::$logPath . '/app.log';
        self::writeToFile($logFile, $logMessage);

        // Also write to PHP error log for critical errors
        if ($level <= self::ERROR) {
            error_log("[Phoenix] " . $message);
        }
    }

    /**
     * Write to log file with rotation
     */
    private static function writeToFile($file, $message) {
        // Check file size and rotate if needed
        if (file_exists($file) && @filesize($file) > self::$maxFileSize) {
            self::rotateLog($file);
        }

        // Write to file
        @error_log($message, 3, $file);
    }

    /**
     * Rotate log file when it reaches max size
     */
    private static function rotateLog($file) {
        $backup = $file . '.' . date('Y-m-d-H-i-s');
        @rename($file, $backup);

        // Keep only last 10 rotated logs
        $pattern = $file . '.*';
        $logs = @glob($pattern);
        if (is_array($logs) && count($logs) > 10) {
            rsort($logs);
            foreach (array_slice($logs, 10) as $oldLog) {
                @unlink($oldLog);
            }
        }
    }

    /**
     * Get log file path
     */
    public static function getLogPath() {
        return self::$logPath;
    }

    /**
     * Get log file contents (last N lines)
     */
    public static function getRecentLogs($limit = 50) {
        self::initialize();
        $logFile = self::$logPath . '/app.log';

        if (!file_exists($logFile)) {
            return [];
        }

        $lines = @file($logFile);
        if (!is_array($lines)) {
            return [];
        }

        return array_slice($lines, -$limit);
    }
}
