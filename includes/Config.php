<?php

namespace Phoenix;

use Dotenv\Dotenv;

/**
 * Configuration Manager
 *
 * Provides centralized access to application configuration.
 * Loads .env file and config files, exposes values via dot notation.
 *
 * Usage:
 *   Config::get('database.connections.users.host')
 *   Config::get('app.debug', false)
 *   Config::isDebug()
 *   Config::database('users')
 */

class Config {
    /**
     * Configuration array
     */
    private static $config = [];

    /**
     * Whether configuration has been loaded
     */
    private static $loaded = false;

    /**
     * Load environment and configuration files
     *
     * @throws RuntimeException if .env file doesn't exist
     */
    public static function load() {
        if (self::$loaded) {
            return;
        }

        // Check if vendor autoload exists
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            throw new \RuntimeException('Composer dependencies not installed. Run: composer install');
        }

        require_once $autoloadPath;

        // Load .env file
        $envPath = __DIR__ . '/..';
        if (!file_exists($envPath . '/.env')) {
            throw new \RuntimeException('.env file not found. Copy .env.example to .env and configure.');
        }

        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->load();

        // Load config files
        $configPath = __DIR__ . '/../config';
        $files = ['app', 'database'];

        foreach ($files as $file) {
            $path = $configPath . '/' . $file . '.php';
            if (file_exists($path)) {
                self::$config[$file] = require $path;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get configuration value using dot notation
     *
     * Examples:
     *   Config::get('database.connections.users.host')
     *   Config::get('app.debug', false)
     *
     * @param string $key Configuration key in dot notation
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Check if a configuration key exists
     *
     * @param string $key Configuration key in dot notation
     * @return bool
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * Set a configuration value at runtime
     *
     * @param string $key Configuration key in dot notation
     * @param mixed $value Value to set
     */
    public static function set($key, $value) {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * Get all configuration
     *
     * @return array
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        return self::$config;
    }

    /**
     * Check if app is in debug mode
     *
     * @return bool
     */
    public static function isDebug() {
        return (bool) self::get('app.debug', false);
    }

    /**
     * Get current environment
     *
     * @return string
     */
    public static function env() {
        return self::get('app.env', 'production');
    }

    /**
     * Check if running in production
     *
     * @return bool
     */
    public static function isProduction() {
        return self::env() === 'production';
    }

    /**
     * Get database connection configuration
     *
     * @param string|null $connection Connection name (users, production, creel)
     * @return array|null
     */
    public static function database($connection = null) {
        $connection = $connection ?? self::get('database.default');
        return self::get("database.connections.{$connection}");
    }

    /**
     * Get production database prefix for machine type
     *
     * @param string $type Machine type (UD, LM, CP, etc.)
     * @return string|null
     */
    public static function productionDatabase($type) {
        return self::get("database.production_databases.{$type}");
    }

    /**
     * Check if maintenance mode is enabled
     *
     * @return bool
     */
    public static function isMaintenanceMode() {
        return (bool) self::get('app.maintenance_mode', false);
    }

    /**
     * Get allowed IPs for maintenance mode bypass
     *
     * @return array
     */
    public static function allowedIps() {
        return self::get('app.allowed_ips', ['127.0.0.1', '::1']);
    }

    /**
     * Reload configuration (useful for testing)
     */
    public static function reload() {
        self::$config = [];
        self::$loaded = false;
        self::load();
    }
}

/**
 * Helper function to get environment variable
 *
 * This function retrieves environment variables and handles type casting.
 * String values like 'true', 'false', 'null' are converted to their
 * respective types.
 *
 * @param string $key Environment variable key
 * @param mixed $default Default value if not found
 * @return mixed
 */
function env($key, $default = null) {
    // Check $_ENV first (where Dotenv loads variables)
    if (isset($_ENV[$key])) {
        $value = $_ENV[$key];
    }
    // Then check $_SERVER
    elseif (isset($_SERVER[$key])) {
        $value = $_SERVER[$key];
    }
    // Finally try getenv()
    else {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
    }

    // Convert string booleans and null
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }

    // Handle quoted strings
    if (strlen($value) > 1 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
        return substr($value, 1, -1);
    }

    return $value;
}
