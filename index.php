<?php
/**
 * Phoenix-PHP Front Controller
 */

// Install check — must come before bootstrap
$installLock = __DIR__ . '/storage/.installed';
if (!file_exists($installLock)) {
    require __DIR__ . '/install.php';
    exit;
}

// Bootstrap the application
require_once __DIR__ . '/bootstrap.php';

// Class aliases for new classes
class_alias('Phoenix\Router', 'Router');
class_alias('Phoenix\View', 'View');
class_alias('Phoenix\Settings', 'Settings');
class_alias('Phoenix\ModuleLoader', 'ModuleLoader');

// Initialize view engine
\Phoenix\View::init(__DIR__);

// Share common data with all views
if (\Phoenix\Auth::check()) {
    $user = \Phoenix\Auth::user();
    \Phoenix\View::share('user', $user);
    \Phoenix\View::share('username', $user['username'] ?? '');
    \Phoenix\View::share('userRole', $user['role'] ?? '');
    \Phoenix\View::share('userGroups', $user['groups'] ?? []);

    \Phoenix\ModuleLoader::discover();
    \Phoenix\View::share('moduleNavItems', \Phoenix\ModuleLoader::navItems());
    \Phoenix\View::share('installedModules', \Phoenix\ModuleLoader::installed());
}

\Phoenix\View::share('csrfToken', \Phoenix\CSRF::getToken());
\Phoenix\View::share('csrfMeta', \Phoenix\CSRF::meta());
\Phoenix\View::share('csrfField', \Phoenix\CSRF::field());
\Phoenix\View::share('appName', config('app.name', 'Phoenix ERP'));
\Phoenix\View::share('theme', $_COOKIE['phoenix_theme'] ?? 'dark');

// Load routes
require __DIR__ . '/routes/web.php';
require __DIR__ . '/routes/api.php';

// Dispatch
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
\Phoenix\Router::dispatch($method, $uri);
