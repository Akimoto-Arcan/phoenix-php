<?php

namespace Phoenix;

/**
 * Authentication & Authorization Manager
 * Centralized authentication and permission handling
 */

require_once __DIR__ . '/Database.php';

class Auth {
    private const SESSION_TIMEOUT = 1800; // 30 minutes
    private const REMEMBER_ME_DURATION = 2592000; // 30 days

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];

            if ($elapsed > self::SESSION_TIMEOUT) {
                self::logout();
                return false;
            }
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Get authenticated user
     *
     * @return array|null
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'groups' => self::groups(),
            'mobile_whitelisted' => $_SESSION['mobile_whitelisted'] ?? false
        ];
    }

    /**
     * Get username
     *
     * @return string|null
     */
    public static function username() {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Get user role
     *
     * @return string|null
     */
    public static function role() {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Get user groups
     *
     * @return array
     */
    public static function groups() {
        $groups = $_SESSION['groups'] ?? '';

        if (is_array($groups)) {
            return $groups;
        }

        return array_filter(array_map('trim', explode(',', $groups)));
    }

    /**
     * Require authentication or redirect
     * For API requests, returns JSON error instead of redirecting
     *
     * @param string $redirectTo
     * @return void
     */
    public static function require($redirectTo = '/login.php') {
        if (!self::check()) {
            self::logActivity('auth.required_failed', [
                'requested_url' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);

            // Check if this is an API request (expecting JSON)
            $isApiRequest = (
                strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
                strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
                isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
                $_SERVER['CONTENT_TYPE'] ?? '' === 'application/json'
            );

            if ($isApiRequest) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => false,
                    'error' => 'Authentication required',
                    'code' => 'AUTH_REQUIRED'
                ]);
                exit;
            }

            header("Location: $redirectTo");
            exit;
        }
    }

    /**
     * Require specific permission
     *
     * @param string|array $permissions
     * @param bool $requireAll
     * @return void
     */
    public static function requirePermission($permissions, $requireAll = false) {
        if (!self::check()) {
            self::require();
        }

        $permissions = (array) $permissions;

        if ($requireAll) {
            foreach ($permissions as $permission) {
                if (!self::can($permission)) {
                    self::forbidden();
                }
            }
        } else {
            $hasPermission = false;
            foreach ($permissions as $permission) {
                if (self::can($permission)) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                self::forbidden();
            }
        }
    }

    /**
     * Check if user has permission
     *
     * @param string $permission
     * @return bool
     */
    public static function can($permission) {
        if (!self::check()) {
            return false;
        }

        $userGroups = self::groups();

        // SuperAdmin can do everything
        if (in_array('SuperAdmin', $userGroups)) {
            return true;
        }

        // Get permission map
        $permissionMap = self::getPermissionMap();

        if (!isset($permissionMap[$permission])) {
            return false;
        }

        $allowedGroups = $permissionMap[$permission];

        return !empty(array_intersect($userGroups, $allowedGroups));
    }

    /**
     * Check if user is in group(s)
     *
     * @param string|array $groups
     * @param bool $requireAll
     * @return bool
     */
    public static function inGroup($groups, $requireAll = false) {
        if (!self::check()) {
            return false;
        }

        $userGroups = self::groups();
        $groups = (array) $groups;

        if ($requireAll) {
            return count(array_intersect($groups, $userGroups)) === count($groups);
        }

        return !empty(array_intersect($groups, $userGroups));
    }

    /**
     * Login user
     *
     * @param string $username
     * @param string $password
     * @return bool|string True on success, error message on failure
     */
    public static function login($username, $password) {
        try {
            $db = Database::pdo('users');

            $stmt = $db->prepare("
                SELECT id, username, full_name, password, role, groups, shift, mobile_whitelisted, approved, reset, session_timeout
                FROM users
                WHERE username = :username
                LIMIT 1
            ");

            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if (!$user) {
                self::logActivity('auth.login_failed', [
                    'username' => $username,
                    'reason' => 'user_not_found',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);

                return 'Invalid username or password';
            }

            // Check if user is approved
            if (isset($user['approved']) && $user['approved'] == 0) {
                self::logActivity('auth.login_failed', [
                    'username' => $username,
                    'reason' => 'user_not_approved',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);

                return 'Your account is awaiting admin approval';
            }

            // Note: 'active' column doesn't exist in users table - skip this check
            // if (isset($user['active']) && !$user['active']) {
            //     return 'Account is inactive';
            // }

            // Verify password
            if (!password_verify($password, $user['password'])) {
                self::logActivity('auth.login_failed', [
                    'username' => $username,
                    'reason' => 'invalid_password',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);

                return 'Invalid username or password';
            }

            // Check mobile device restriction
            if (self::isMobileDevice() && (!isset($user['mobile_whitelisted']) || $user['mobile_whitelisted'] != 1)) {
                self::logActivity('auth.login_failed', [
                    'username' => $username,
                    'reason' => 'mobile_not_whitelisted',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);

                return 'Mobile access is restricted. Contact administrator';
            }

            // Start session if not started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
            $_SESSION['role'] = $user['role'];
            // Convert groups string to array for API compatibility
            $_SESSION['groups'] = !empty($user['groups'])
                ? array_map('trim', explode(',', $user['groups']))
                : [];
            $_SESSION['mobile_whitelisted'] = $user['mobile_whitelisted'] ?? 0;
            $_SESSION['shift'] = $user['shift'] ?? null;
            $_SESSION['session_timeout'] = $user['session_timeout'] ?? null; // Custom timeout in minutes
            $_SESSION['last_activity'] = time();
            $_SESSION['LAST_ACTIVITY'] = time(); // Uppercase for session API compatibility
            $_SESSION['login_time'] = time();
            $_SESSION['session_start'] = time();
            $_SESSION['created_at'] = time(); // Alias for compatibility
            $_SESSION['password_reset_required'] = isset($user['reset']) && $user['reset'] == 1;

            // Track user info for session monitoring (set on login)
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $_SESSION['current_page'] = $_SERVER['REQUEST_URI'] ?? 'Unknown';
            $_SESSION['last_page_load'] = microtime(true);

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

            // Load version info for session tracking
            $versionFile = __DIR__ . '/../dashboard/version.json';
            if (file_exists($versionFile)) {
                $versionData = json_decode(file_get_contents($versionFile), true);
                $_SESSION['system_version'] = $versionData['version'] ?? 'Unknown';
                $_SESSION['system_build'] = $versionData['build'] ?? 'Unknown';
            } else {
                $_SESSION['system_version'] = 'Unknown';
                $_SESSION['system_build'] = 'Unknown';
            }

            // Note: last_login columns don't exist in users table - skip this update
            // $stmt = $db->prepare("
            //     UPDATE users
            //     SET last_login = NOW(), last_login_ip = :ip
            //     WHERE id = :id
            // ");
            // $stmt->execute([
            //     'id' => $user['id'],
            //     'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            // ]);

            self::logActivity('auth.login_success', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);

            return true;

        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());

            self::logActivity('auth.login_error', [
                'username' => $username,
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);

            return 'Login failed. Please try again.';
        }
    }

    /**
     * Logout user
     *
     * @return void
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = $_SESSION['username'] ?? 'unknown';

        self::logActivity('auth.logout', [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        // Clear all session variables
        $_SESSION = [];

        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy session
        session_destroy();
    }

    /**
     * Check if device is mobile
     *
     * @return bool
     */
    private static function isMobileDevice() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'];
        return preg_match('/iPhone|iPad|iPod|Android|webOS|BlackBerry|Windows Phone/i', $ua);
    }

    /**
     * Get permission map
     *
     * @return array
     */
    private static function getPermissionMap() {
        return [
            // Production permissions
            'production.read' => ['Admin', 'Supervisor', 'Operator'],
            'production.write' => ['Admin', 'Supervisor'],
            'production.delete' => ['Admin'],

            // Inspection permissions
            'inspection.read' => ['Admin', 'Supervisor', 'Inspection', 'Lab'],
            'inspection.write' => ['Admin', 'Supervisor', 'Inspection'],
            'inspection.delete' => ['Admin'],

            // Operator permissions
            'operator.read' => ['Admin', 'Supervisor', 'Operator'],
            'operator.write' => ['Admin', 'Supervisor', 'Operator'],

            // Maintenance permissions
            'maintenance.read' => ['Admin', 'Supervisor', 'Maintenance'],
            'maintenance.write' => ['Admin', 'Maintenance'],
            'maintenance.delete' => ['Admin'],

            // User management permissions
            'users.read' => ['Admin'],
            'users.write' => ['Admin'],
            'users.delete' => ['Admin'],

            // Dashboard access
            'dashboard.production' => ['Admin', 'Supervisor', 'Operator'],
            'dashboard.qc' => ['Admin', 'Supervisor', 'Inspection', 'Lab'],
            'dashboard.maintenance' => ['Admin', 'Supervisor', 'Maintenance'],
            'dashboard.shipping' => ['Admin', 'Supervisor', 'Shipping'],
            'dashboard.users' => ['Admin', 'Supervisor'],
            'dashboard.paperwork' => ['Admin', 'Supervisor', 'Inspection', 'Lab'],
            'dashboard.analytics' => ['Admin', 'Supervisor'],
            'dashboard.welcome' => ['Admin', 'Supervisor', 'Operator', 'Inspection', 'Lab', 'Maintenance', 'Shipping'],
            'dashboard.options' => ['Admin', 'Supervisor', 'Operator', 'Inspection', 'Lab', 'Maintenance', 'Shipping'],
        ];
    }

    /**
     * Handle forbidden access
     *
     * @return void
     */
    private static function forbidden() {
        self::logActivity('auth.forbidden', [
            'username' => self::username(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        http_response_code(403);

        // Check if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'error' => 'You do not have permission to access this resource'
            ]);
            exit;
        }

        // Regular request
        if (file_exists(__DIR__ . '/../errors/403.php')) {
            require __DIR__ . '/../errors/403.php';
        } else {
            echo '<h1>403 Forbidden</h1><p>You do not have permission to access this resource.</p>';
        }
        exit;
    }

    /**
     * Log activity
     *
     * @param string $action
     * @param array $details
     * @return void
     */
    private static function logActivity($action, $details = []) {
        try {
            $db = Database::pdo('users');

            // Create activity_log table if it doesn't exist
            $db->exec("
                CREATE TABLE IF NOT EXISTS activity_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100),
                    action VARCHAR(100),
                    details TEXT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_username (username),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $stmt = $db->prepare("
                INSERT INTO activity_log (username, action, details, ip_address)
                VALUES (:username, :action, :details, :ip)
            ");

            $stmt->execute([
                'username' => $details['username'] ?? self::username() ?? 'guest',
                'action' => $action,
                'details' => json_encode($details),
                'ip' => $details['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

        } catch (Exception $e) {
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }
}

// Helper functions for convenience
function auth_check() {
    return Auth::check();
}

function auth_user() {
    return Auth::user();
}

function auth_require($redirectTo = '/dashboard/index.html') {
    Auth::require($redirectTo);
}

function auth_can($permission) {
    return Auth::can($permission);
}

function auth_in_group($groups, $requireAll = false) {
    return Auth::inGroup($groups, $requireAll);
}
