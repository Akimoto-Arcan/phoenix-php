<?php

namespace Phoenix;

/**
 * Database Connection Manager
 *
 * Provides centralized database connection management using configuration.
 * Replaces hardcoded credentials throughout the application.
 *
 * Usage:
 *   // Get Users database connection
 *   $conn = Database::connection('users');
 *
 *   // Get production machine database
 *   $conn = Database::production('UD', 1); // Phoenix_UD1
 *
 *   // Get PDO connection for prepared statements
 *   $pdo = Database::pdo('users');
 *
 *   // Get Module connection
 *   $conn = Database::connection('creel');
 */

require_once __DIR__ . '/Config.php';

class Database {
    /**
     * Connection cache
     */
    private static $connections = [];

    /**
     * PDO connection cache
     */
    private static $pdoConnections = [];

    /**
     * Get mysqli database connection
     *
     * @param string $name Connection name (users, production, creel, cmms)
     * @param string|null $database Optional database name (overrides config)
     * @return mysqli
     * @throws RuntimeException on connection failure
     */
    public static function connection($name = 'users', $database = null) {
        $cacheKey = $name . ($database ? ":{$database}" : '');

        // Return cached connection if alive
        if (isset(self::$connections[$cacheKey])) {
            if (@self::$connections[$cacheKey]->ping()) {
                return self::$connections[$cacheKey];
            }
            // Connection died, remove from cache
            unset(self::$connections[$cacheKey]);
        }

        // Get configuration
        $config = Config::database($name);

        if (!$config) {
            throw new \RuntimeException("Database connection '{$name}' not configured");
        }

        // Use provided database name or config default
        $dbName = $database ?? ($config['database'] ?? '');

        // Create connection
        $conn = @new \mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $dbName,
            $config['port']
        );

        if ($conn->connect_error) {
            error_log("Database connection failed [{$name}]: " . $conn->connect_error);
            throw new \RuntimeException("Database connection failed");
        }

        // Set charset
        if (!@mysqli_set_charset($conn, $config['charset'])) {
            error_log("Failed to set charset [{$name}]: " . $conn->error);
        }

        // Cache and return
        self::$connections[$cacheKey] = $conn;

        return $conn;
    }

    /**
     * Get production machine database connection
     *
     * @param string $type Machine type (e.g. Sales, Inventory, Production)
     * @param int|string|null $machine Machine number (1-6) or null for base database
     * @return mysqli
     * @throws RuntimeException on invalid type or connection failure
     */
    public static function production($type, $machine = null) {
        $dbPrefix = Config::productionDatabase($type);

        if (!$dbPrefix) {
            throw new \RuntimeException("Invalid machine type: {$type}");
        }

        // Build database name
        $dbName = $machine ? "{$dbPrefix}{$machine}" : $dbPrefix;

        // Validate machine number if provided
        if ($machine !== null) {
            $validMachines = Config::get("database.machine_ranges.{$type}", []);
            if (!in_array((int)$machine, $validMachines)) {
                throw new \RuntimeException("Invalid machine number {$machine} for type {$type}");
            }
        }

        return self::connection('production', $dbName);
    }

    /**
     * Get PDO connection for prepared statements
     *
     * @param string $name Connection name (users, production, creel, cmms)
     * @param string|null $database Optional database name (overrides config)
     * @return PDO
     * @throws RuntimeException on connection failure
     */
    public static function pdo($name = 'users', $database = null) {
        $cacheKey = $name . ($database ? ":{$database}" : '');

        // Return cached connection if exists
        if (isset(self::$pdoConnections[$cacheKey])) {
            try {
                // Test connection
                self::$pdoConnections[$cacheKey]->query('SELECT 1');
                return self::$pdoConnections[$cacheKey];
            } catch (PDOException $e) {
                // Connection died, remove from cache
                unset(self::$pdoConnections[$cacheKey]);
            }
        }

        // Get configuration
        $config = Config::database($name);

        if (!$config) {
            throw new \RuntimeException("Database connection '{$name}' not configured");
        }

        // Use provided database name or config default
        $dbName = $database ?? ($config['database'] ?? '');

        // Build DSN
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['host'],
            $config['port'],
            $config['charset']
        );

        if ($dbName) {
            $dsn .= ";dbname={$dbName}";
        }

        try {
            // Build PDO options
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false
            ];

            $pdo = new \PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $options
            );

            // Cache and return
            self::$pdoConnections[$cacheKey] = $pdo;

            return $pdo;
        } catch (PDOException $e) {
            error_log("PDO connection failed [{$name}]: " . $e->getMessage());
            throw new \RuntimeException("Database connection failed");
        }
    }

    /**
     * Get production machine PDO connection
     *
     * @param string $type Machine type (e.g. Sales, Inventory, Production)
     * @param int|string|null $machine Machine number (1-6) or null for base database
     * @return PDO
     * @throws RuntimeException on invalid type or connection failure
     */
    public static function productionPdo($type, $machine = null) {
        $dbPrefix = Config::productionDatabase($type);

        if (!$dbPrefix) {
            throw new \RuntimeException("Invalid machine type: {$type}");
        }

        // Build database name
        $dbName = $machine ? "{$dbPrefix}{$machine}" : $dbPrefix;

        // Validate machine number if provided
        if ($machine !== null) {
            $validMachines = Config::get("database.machine_ranges.{$type}", []);
            if (!in_array((int)$machine, $validMachines)) {
                throw new \RuntimeException("Invalid machine number {$machine} for type {$type}");
            }
        }

        return self::pdo('production', $dbName);
    }

    /**
     * Get all production databases for a machine type
     *
     * @param string $type Machine type (e.g. Sales, Inventory, Production)
     * @return array Array of mysqli connections
     */
    public static function allProductionConnections($type) {
        $connections = [];
        $machines = Config::get("database.machine_ranges.{$type}", []);

        foreach ($machines as $machine) {
            try {
                $connections[$machine] = self::production($type, $machine);
            } catch (Exception $e) {
                error_log("Failed to connect to {$type}{$machine}: " . $e->getMessage());
            }
        }

        return $connections;
    }

    /**
     * Get Module connection (convenience method)
     *
     * @return mysqli
     */
    public static function creel() {
        return self::connection('creel');
    }

    /**
     * Get CMMS (Atlas) connection (convenience method)
     *
     * @return mysqli
     */
    public static function cmms() {
        return self::connection('cmms');
    }

    /**
     * Test a connection
     *
     * @param string $name Connection name
     * @param string|null $database Optional database
     * @return array ['success' => bool, 'message' => string]
     */
    public static function test($name, $database = null) {
        try {
            $conn = self::connection($name, $database);
            $result = $conn->query('SELECT 1 as test');
            $row = $result->fetch_assoc();

            if ($row['test'] === 1) {
                return [
                    'success' => true,
                    'message' => "Connection to {$name}" . ($database ? ":{$database}" : '') . " successful"
                ];
            }

            return [
                'success' => false,
                'message' => "Connection test query failed"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Close a specific connection
     *
     * @param string $name Connection name
     * @param string|null $database Optional database
     */
    public static function close($name, $database = null) {
        $cacheKey = $name . ($database ? ":{$database}" : '');

        if (isset(self::$connections[$cacheKey])) {
            self::$connections[$cacheKey]->close();
            unset(self::$connections[$cacheKey]);
        }

        if (isset(self::$pdoConnections[$cacheKey])) {
            unset(self::$pdoConnections[$cacheKey]);
        }
    }

    /**
     * Close all connections
     */
    public static function closeAll() {
        foreach (self::$connections as $conn) {
            if ($conn instanceof mysqli) {
                @$conn->close();
            }
        }
        self::$connections = [];
        self::$pdoConnections = [];
    }

    /**
     * Get connection statistics
     *
     * @return array
     */
    public static function stats() {
        return [
            'mysqli_connections' => count(self::$connections),
            'pdo_connections' => count(self::$pdoConnections),
            'total_connections' => count(self::$connections) + count(self::$pdoConnections)
        ];
    }
}
