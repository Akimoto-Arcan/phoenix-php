<?php
require_once __DIR__ . '/../bootstrap.php';

/**
 * PhoenixPHP Application Framework - Unified API Bootstrap
 *
 * Centralized configuration, authentication, helpers, and database
 * connections for all REST API endpoints.
 *
 * Pattern inspired by CMMS API at /dashboard/maintenance/atlas/api/_bootstrap.php
 * Extended to support multi-database production system architecture.
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load CSRF protection
require_once __DIR__ . '/../../includes/CSRF.php';

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS headers (for future frontend apps)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// Validate CSRF token for state-changing methods
$method = $_SERVER['REQUEST_METHOD'];
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
    CSRF::requireToken();
}

// Error handling - log everything, display nothing
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_errors.log');

set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    error_log("API Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Internal server error'
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

/* ==================== DATABASE CONNECTIONS ==================== */

/**
 * Get PDO connection to Users database
 * Used for authentication, user management, activity logging
 */
function getUserDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = Database::pdo('users');
        } catch (Exception $e) {
            error_log("Users DB connection failed: " . $e->getMessage());
            fail('Database connection failed', 500);
        }
    }
    return $db;
}

/**
 * Get PDO connection to Inspection database
 */
function getInspectionDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = Database::pdo('users', 'Phoenix_Inspection');
        } catch (Exception $e) {
            error_log("Inspection DB connection failed: " . $e->getMessage());
            fail('Database connection failed', 500);
        }
    }
    return $db;
}

/**
 * Get mysqli connection to a production database by type and machine
 *
 * @param string $type Machine type (e.g. Sales, Inventory, Production)
 * @param string $machine Specific machine (e.g., UD1, LM2) or machine number
 * @return mysqli
 */
function getProductionDB($type, $machine = null) {
    try {
        // Extract numeric suffix if machine name provided (e.g., UD1 -> 1)
        $machineNumber = null;
        if ($machine) {
            if (preg_match('/\d+$/', $machine, $matches)) {
                $machineNumber = $matches[0];
            } else {
                $machineNumber = $machine;
            }
        }

        return Database::production($type, $machineNumber);
    } catch (Exception $e) {
        error_log("Production DB connection failed ($type" . ($machine ? $machine : '') . "): " . $e->getMessage());
        fail("Failed to connect to production database: $type" . ($machine ? $machine : ''), 500);
    }
}

/**
 * Get all production databases matching a type pattern
 *
 * @param string $type Machine type
 * @return array Array of database names
 */
function getProductionDatabases($type) {
    $patterns = [
        'Module1' => '/^Phoenix_Module1/i',
        'Module2' => '/^Phoenix_Module2/i',
        'Module3' => '/^Phoenix_Module3/i',
        // Add module patterns as needed
        
    ];

    $pattern = $patterns[$type] ?? null;
    if (!$pattern) {
        return [];
    }

    try {
        // Connect without database name to list databases
        $conn = Database::connection('production', null);

        $result = $conn->query("SHOW DATABASES");
        $databases = [];

        while ($row = $result->fetch_row()) {
            $dbName = $row[0];
            if (preg_match($pattern, $dbName)) {
                $databases[] = $dbName;
            }
        }

        return $databases;
    } catch (Exception $e) {
        error_log("Failed to list databases: " . $e->getMessage());
        return [];
    }
}

/* ==================== AUTHENTICATION & AUTHORIZATION ==================== */

/**
 * Get current user session information
 *
 * @return array ['valid' => bool, 'username' => string, 'role' => string, 'groups' => array]
 */
function getSessionInfo() {
    $username = $_SESSION['username'] ?? null;
    $role = $_SESSION['role'] ?? null;
    $groups = [];

    if (isset($_SESSION['groups'])) {
        if (is_array($_SESSION['groups'])) {
            $groups = $_SESSION['groups'];
        } elseif (is_string($_SESSION['groups'])) {
            $groups = array_map('trim', explode(',', $_SESSION['groups']));
        }
    }

    return [
        'valid' => ($username && $role),
        'username' => $username,
        'role' => $role,
        'groups' => $groups
    ];
}

/**
 * Require authentication - fail with 401 if not authenticated
 *
 * @return array Session info
 */
function require_auth() {
    $session = getSessionInfo();
    if (!$session['valid']) {
        fail('Authentication required', 401);
    }
    return $session;
}

/**
 * Check if current user has permission for an action
 *
 * @param string $permission Permission string (e.g., 'production.read')
 * @return bool
 */
function can($permission) {
    $session = getSessionInfo();
    if (!$session['valid']) {
        return false;
    }

    $groups = $session['groups'];

    // SuperAdmin can do everything
    if (in_array('SuperAdmin', $groups)) {
        return true;
    }

    // Permission map - defines which groups can perform which actions
    $permissionMap = [
        // Production
        'production.read' => ['Admin', 'Supervisor', 'Operator', 'Lab'],
        'production.write' => ['Admin', 'Supervisor'],
        'production.delete' => ['Admin', 'SuperAdmin'],

        // Inspection
        'inspection.read' => ['Admin', 'Supervisor', 'Inspection', 'Lab'],
        'inspection.write' => ['Admin', 'Supervisor', 'Inspection'],
        'inspection.delete' => ['Admin', 'Supervisor'],

        // Operator tools
        'operator.read' => ['Admin', 'Supervisor', 'Operator'],
        'operator.write' => ['Admin', 'Supervisor', 'Operator'],

        // Users
        'users.read' => ['Admin', 'SuperAdmin'],
        'users.write' => ['Admin', 'SuperAdmin'],
        'users.delete' => ['SuperAdmin'],

        // Maintenance (delegated to CMMS API)
        'maintenance.read' => ['Admin', 'Supervisor', 'Maintenance'],
        'maintenance.write' => ['Admin', 'Maintenance'],

        // Machines
        'machines.read' => ['Admin', 'Supervisor', 'Operator', 'Maintenance'],
        'machines.write' => ['Admin', 'Supervisor'],
    ];

    $allowedGroups = $permissionMap[$permission] ?? [];
    return !empty(array_intersect($groups, $allowedGroups));
}

/* ==================== RESPONSE HELPERS ==================== */

/**
 * Send success response and exit
 *
 * @param mixed $data Data to include in response
 * @param int $status HTTP status code
 */
function ok($data = null, $status = 200) {
    http_response_code($status);
    $response = ['ok' => true];

    if ($data !== null) {
        if (is_array($data) && isset($data['data'])) {
            // Data already has 'data' key, merge entire array
            $response = array_merge($response, $data);
        } else {
            // Wrap data
            $response['data'] = $data;
        }
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send error response and exit
 *
 * @param string $message Error message
 * @param int $status HTTP status code
 */
function fail($message, $status = 400) {
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ==================== REQUEST HELPERS ==================== */

/**
 * Get HTTP request method
 *
 * @return string
 */
function getMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Get parsed JSON or form input from request body
 *
 * @return array
 */
function getInput() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            fail('Invalid JSON: ' . json_last_error_msg(), 400);
        }

        return $data ?? [];
    }

    return $_POST;
}

/**
 * Get query parameter with optional default
 *
 * @param string $key Parameter name
 * @param mixed $default Default value if not present
 * @return mixed
 */
function getParam($key, $default = null) {
    return $_GET[$key] ?? $default;
}

/* ==================== VALIDATION HELPERS ==================== */

/**
 * Validate that required fields exist and are non-empty
 *
 * @param array $data Data to validate
 * @param array $fields Required field names
 */
function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            fail("Field '$field' is required", 400);
        }
    }
}

/**
 * Validate email address format
 *
 * @param string $email
 */
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Invalid email address', 400);
    }
}

/**
 * Sanitize input data (recursive for arrays)
 *
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate machine type
 *
 * @param string $type
 * @return bool
 */
function validateMachineType($type) {
    $validTypes = ['Module1', 'Module2', 'Module3'];
    return in_array($type, $validTypes);
}

/* ==================== ACTIVITY LOGGING ==================== */

/**
 * Log user activity to database
 *
 * @param string $action Action performed
 * @param array $details Additional details (will be JSON encoded)
 */
function logActivity($action, $details = []) {
    try {
        $db = getUserDB();

        // Check if activity_log table exists, create if not
        $tableExists = $db->query("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = 'Users'
            AND table_name = 'activity_log'
        ")->fetchColumn();

        if (!$tableExists) {
            $db->exec("
                CREATE TABLE activity_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) NOT NULL,
                    action VARCHAR(255) NOT NULL,
                    details TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_username (username),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                )
            ");
        }

        $stmt = $db->prepare("
            INSERT INTO activity_log (username, action, details, ip_address, user_agent, created_at)
            VALUES (:username, :action, :details, :ip, :user_agent, NOW())
        ");

        $stmt->execute([
            'username' => $_SESSION['username'] ?? 'system',
            'action' => $action,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        // Don't fail the request if logging fails
    }
}

/* ==================== PAGINATION HELPER ==================== */

/**
 * Execute a query with pagination
 *
 * @param PDO $db Database connection
 * @param string $query Base query (without LIMIT)
 * @param string $countQuery Query to get total count
 * @param array $params Query parameters
 * @param int $page Page number (1-indexed)
 * @param int $perPage Items per page
 * @return array ['data' => array, 'pagination' => array]
 */
function paginate($db, $query, $countQuery, $params = [], $page = 1, $perPage = 20) {
    $page = max(1, (int)$page);
    $perPage = min(100, max(1, (int)$perPage));
    $offset = ($page - 1) * $perPage;

    // Get total count
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // Get paginated data
    $stmt = $db->prepare($query . " LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return [
        'data' => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ];
}

/* ==================== CACHE HELPERS ==================== */

/**
 * Get cached data or execute callback to generate it
 *
 * @param string $key Cache key
 * @param int $ttl Time to live in seconds
 * @param callable $callback Function to generate data if cache miss
 * @return mixed
 */
function cache($key, $ttl, $callback) {
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $cacheFile = $cacheDir . '/' . md5($key) . '.json';

    // Check if cache exists and is fresh
    if (file_exists($cacheFile)) {
        $age = time() - filemtime($cacheFile);
        if ($age < $ttl) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data !== null) {
                return $data;
            }
        }
    }

    // Cache miss or expired - generate new data
    $data = $callback();

    // Save to cache
    file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));

    return $data;
}

/**
 * Clear cache by key or pattern
 *
 * @param string $pattern Cache key or glob pattern
 */
function clearCache($pattern = '*') {
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) {
        return;
    }

    $files = glob($cacheDir . '/' . $pattern . '.json');
    foreach ($files as $file) {
        unlink($file);
    }
}
