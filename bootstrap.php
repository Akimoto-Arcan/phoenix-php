<?php
/**
 * Application Bootstrap
 *
 * This file initializes the PhoenixPHP Application Framework.
 * Include this file at the beginning of every PHP script to:
 *   - Load environment configuration
 *   - Set up error handling
 *   - Initialize sessions
 *   - Check maintenance mode
 *
 * Usage:
 *   require_once __DIR__ . '/bootstrap.php';
 *   // Or from subdirectory:
 *   require_once __DIR__ . '/../bootstrap.php';
 */

// Prevent direct access and multiple includes
if (defined('BOOTSTRAPPED')) {
    return;
}
define('BOOTSTRAPPED', true);

// Load Composer autoloader
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('Error: Composer dependencies not installed. Run: composer install');
}
require_once $autoloadPath;

// Import Phoenix classes
use Phoenix\Config;
use Phoenix\Database;

// Create class aliases for backward compatibility
class_alias('Phoenix\Config', 'Config');
class_alias('Phoenix\Database', 'Database');
class_alias('Phoenix\CSRF', 'CSRF');
class_alias('Phoenix\Auth', 'Auth');
class_alias('Phoenix\Logger', 'Logger');
class_alias('Phoenix\ErrorHandler', 'ErrorHandler');
class_alias('Phoenix\SecurityHelper', 'SecurityHelper');
class_alias('Phoenix\Validator', 'Validator');
class_alias('Phoenix\Cache', 'Cache');
class_alias('Phoenix\Performance', 'Performance');

try {
    Config::load();
} catch (Exception $e) {
    error_log("Bootstrap config error: " . $e->getMessage());
    die('Configuration Error: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Error Handling
|--------------------------------------------------------------------------
|
| Set up error reporting based on environment
|
*/

if (Config::isDebug()) {
    // Development: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    // Production: Log errors, don't display
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', Config::get('app.logging.error_log'));
}

/**
 * Custom error handler
 */
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorType = '';
    switch ($errno) {
        case E_ERROR:
        case E_USER_ERROR:
            $errorType = 'ERROR';
            break;
        case E_WARNING:
        case E_USER_WARNING:
            $errorType = 'WARNING';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $errorType = 'NOTICE';
            break;
        default:
            $errorType = 'UNKNOWN';
    }

    $message = sprintf(
        "[%s] %s in %s on line %d",
        $errorType,
        $errstr,
        $errfile,
        $errline
    );

    error_log($message);

    if (Config::isDebug()) {
        echo "<pre>{$message}</pre>";
    }

    return true;
});

/**
 * Custom exception handler
 */
set_exception_handler(function($exception) {
    $message = sprintf(
        "[EXCEPTION] %s in %s on line %d\nStack trace:\n%s",
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    error_log($message);

    if (Config::isDebug()) {
        echo "<pre>{$message}</pre>";
    } else {
        http_response_code(500);
        echo "An error occurred. Please contact support if this persists.";
    }
});

/*
|--------------------------------------------------------------------------
| Session Configuration
|--------------------------------------------------------------------------
|
| Start session with secure settings
|
*/

if (session_status() === PHP_SESSION_NONE) {
    $sessionConfig = Config::get('app.session');

    // Use longer lifetime for session.gc_maxlifetime (covers all users)
    ini_set('session.cookie_httponly', $sessionConfig['httponly'] ? '1' : '0');
    ini_set('session.cookie_secure', $sessionConfig['secure'] ? '1' : '0');
    ini_set('session.cookie_samesite', $sessionConfig['samesite']);
    ini_set('session.gc_maxlifetime', (string)$sessionConfig['lifetime']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start();

    // Role-based session timeout check
    // Only enforce timeout for privileged roles (admins/supervisors)
    // Regular operators never timeout to allow long data entry sessions
    if (isset($_SESSION['last_activity']) && isset($_SESSION['role'])) {
        $elapsed = time() - $_SESSION['last_activity'];

        // Determine if user is privileged (subject to timeout)
        $privilegedRoles = $sessionConfig['privileged_roles'] ?? ['SuperAdmin', 'Supervisor'];
        $isPrivileged = in_array($_SESSION['role'], $privilegedRoles, true);

        // Only apply timeout to privileged users
        if ($isPrivileged) {
            // Check for user-specific timeout (in minutes), otherwise use system default
            if (isset($_SESSION['session_timeout']) && $_SESSION['session_timeout'] > 0) {
                $timeout = $_SESSION['session_timeout'] * 60; // Convert minutes to seconds
            } else {
                $timeout = $sessionConfig['lifetime_admin'];
            }

            if ($elapsed > $timeout) {
                $role = $_SESSION['role'] ?? 'unknown';
                session_unset();
                session_destroy();
                session_start();

                // Log the timeout for security monitoring
                if (function_exists('log_activity')) {
                    log_activity("Session timeout: role={$role}, elapsed={$elapsed}s, limit={$timeout}s", 'info');
                }

                // Redirect to login page
                if (!headers_sent()) {
                    header('Location: /login.php');
                    exit();
                } else {
                    // If headers already sent, use JavaScript redirect
                    echo '<script>window.location.href = "/login.php";</script>';
                    exit();
                }
            }
        }
        // Regular users: no timeout check - sessions never expire due to inactivity
    }
    $_SESSION['last_activity'] = time();
    $_SESSION['LAST_ACTIVITY'] = time(); // Uppercase for session API compatibility

    // Track version information and debug data for session monitoring
    if (!isset($_SESSION['session_start'])) {
        $_SESSION['session_start'] = time();
    }
    $_SESSION['created_at'] = $_SESSION['session_start']; // Alias for compatibility

    // Load version info
    $versionFile = __DIR__ . '/dashboard/version.json';
    if (file_exists($versionFile)) {
        $versionData = json_decode(file_get_contents($versionFile), true);
        $_SESSION['system_version'] = $versionData['version'] ?? 'Unknown';
        $_SESSION['system_build'] = $versionData['build'] ?? 'Unknown';
    } else {
        $_SESSION['system_version'] = 'Unknown';
        $_SESSION['system_build'] = 'Unknown';
    }

    // Track user info for debugging (updated on every page load)
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // Only update current_page if this is not a background/ping endpoint
    $currentUri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
    $isBackgroundRequest = (
        strpos($currentUri, 'session_ping.php') !== false ||
        strpos($currentUri, '/api/') !== false ||
        strpos($currentUri, 'keepalive') !== false
    );

    if (!$isBackgroundRequest) {
        $_SESSION['current_page'] = $currentUri;
    }

    $_SESSION['last_page_load'] = microtime(true); // Force session write on every page

    // Parse browser and OS from user agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['browser'] = 'Unknown';
    $_SESSION['os'] = 'Unknown';

    if (preg_match('/MSIE|Trident/i', $userAgent)) {
        $_SESSION['browser'] = 'Internet Explorer';
    } elseif (preg_match('/Edge/i', $userAgent)) {
        $_SESSION['browser'] = 'Microsoft Edge';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $_SESSION['browser'] = 'Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $_SESSION['browser'] = 'Safari';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $_SESSION['browser'] = 'Firefox';
    } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
        $_SESSION['browser'] = 'Opera';
    }

    if (preg_match('/Windows/i', $userAgent)) {
        $_SESSION['os'] = 'Windows';
    } elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
        $_SESSION['os'] = 'macOS';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $_SESSION['os'] = 'Linux';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $_SESSION['os'] = 'Android';
    } elseif (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
        $_SESSION['os'] = 'iOS';
    }
}

/*
|--------------------------------------------------------------------------
| Maintenance Mode Check
|--------------------------------------------------------------------------
|
| Check if maintenance mode is enabled (except for allowed IPs)
|
*/

if (Config::isMaintenanceMode()) {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowedIps = Config::allowedIps();

    if (!in_array($clientIp, $allowedIps)) {
        http_response_code(503);

        // Check if maintenance page exists
        $maintenancePage = __DIR__ . '/maintenance.html';
        if (file_exists($maintenancePage)) {
            require $maintenancePage;
        } else {
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Mode</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #FF0000;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>System Maintenance</h1>
        <p>The PhoenixPHP Application Framework is currently undergoing maintenance.</p>
        <p>We apologize for the inconvenience. Please check back shortly.</p>
    </div>
</body>
</html>';
        }
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Timezone
|--------------------------------------------------------------------------
|
| Set default timezone
|
*/

date_default_timezone_set('America/New_York');

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
|
| Global helper functions available throughout the application
|
*/

/**
 * Get configuration value
 */
function config($key, $default = null) {
    return Config::get($key, $default);
}

/**
 * Get database connection
 */
function db($name = 'users', $database = null) {
    return Database::connection($name, $database);
}

/**
 * Log activity to activity log
 */
function log_activity($message, $level = 'info') {
    $logFile = Config::get('app.logging.activity_log');
    $timestamp = date('Y-m-d H:i:s');
    $username = $_SESSION['username'] ?? 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $logMessage = sprintf(
        "[%s] [%s] [%s] [%s] %s\n",
        $timestamp,
        strtoupper($level),
        $username,
        $ip,
        $message
    );

    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is authenticated
 */
function is_authenticated() {
    return isset($_SESSION['username']) && isset($_SESSION['role']);
}

/**
 * Require authentication (redirect if not authenticated)
 */
function require_auth($redirectUrl = '/index.html') {
    if (!is_authenticated()) {
        header("Location: {$redirectUrl}");
        exit;
    }
}

/**
 * Redirect helper
 */
function redirect($url, $statusCode = 302) {
    header("Location: {$url}", true, $statusCode);
    exit;
}

/**
 * JSON response helper
 */
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Debug helper (only works in debug mode)
 */
function dd($var, $die = true) {
    if (Config::isDebug()) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
        if ($die) {
            die();
        }
    }
}
