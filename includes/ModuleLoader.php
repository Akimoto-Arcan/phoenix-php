<?php

namespace Phoenix;

/**
 * Module Loader — PhoenixPHP by CDAC Programming
 *
 * Discovers, loads, and manages pluggable modules.
 * Drop a module directory into /modules/ and it auto-registers.
 *
 * Usage:
 *   ModuleLoader::discover();           // Scan for modules
 *   ModuleLoader::installed();          // Get installed modules
 *   ModuleLoader::isInstalled('cmms');  // Check if module exists
 *   ModuleLoader::get('inventory');     // Get module metadata
 *   ModuleLoader::routes('inventory');  // Get API route prefix
 */

class ModuleLoader {
    private static $modules = [];
    private static $discovered = false;

    /**
     * Discover all installed modules by scanning /modules/ directory
     */
    public static function discover() {
        if (self::$discovered) return self::$modules;

        $modulesPath = dirname(__DIR__) . '/modules';
        if (!is_dir($modulesPath)) return [];

        $dirs = array_filter(glob($modulesPath . '/*'), 'is_dir');

        foreach ($dirs as $dir) {
            $manifestFile = $dir . '/module.json';
            if (!file_exists($manifestFile)) continue;

            $manifest = json_decode(file_get_contents($manifestFile), true);
            if (!$manifest || empty($manifest['id'])) continue;

            $manifest['path'] = $dir;
            $manifest['installed'] = file_exists($dir . '/.installed');
            self::$modules[$manifest['id']] = $manifest;
        }

        self::$discovered = true;
        return self::$modules;
    }

    /**
     * Get all discovered modules
     */
    public static function all() {
        self::discover();
        return self::$modules;
    }

    /**
     * Get only installed (activated) modules
     */
    public static function installed() {
        return array_filter(self::all(), fn($m) => $m['installed']);
    }

    /**
     * Check if a module is installed
     */
    public static function isInstalled($id) {
        $modules = self::all();
        return isset($modules[$id]) && $modules[$id]['installed'];
    }

    /**
     * Get module metadata
     */
    public static function get($id) {
        $modules = self::all();
        return $modules[$id] ?? null;
    }

    /**
     * Get module path
     */
    public static function path($id) {
        $module = self::get($id);
        return $module ? $module['path'] : null;
    }

    /**
     * Get API routes file for a module
     */
    public static function apiRoutes($id) {
        $path = self::path($id);
        if (!$path) return null;
        $routeFile = $path . '/api/routes.php';
        return file_exists($routeFile) ? $routeFile : null;
    }

    /**
     * Get module view path
     */
    public static function viewPath($id) {
        $path = self::path($id);
        return $path ? $path . '/views' : null;
    }

    /**
     * Install a module (run database migrations, mark as installed)
     */
    public static function install($id) {
        $module = self::get($id);
        if (!$module) return false;

        // Check dependencies
        if (!empty($module['dependencies'])) {
            foreach ($module['dependencies'] as $dep) {
                if (!self::isInstalled($dep)) {
                    throw new \RuntimeException("Module '{$id}' requires '{$dep}' to be installed first.");
                }
            }
        }

        // Run database setup
        $setupFile = $module['path'] . '/database/install.sql';
        if (file_exists($setupFile)) {
            $sql = file_get_contents($setupFile);
            $conn = Database::connection();
            if (!$conn->multi_query($sql)) {
                throw new \RuntimeException("Failed to install module '{$id}': " . $conn->error);
            }
            while ($conn->next_result()) { /* flush */ }
        }

        // Run PHP installer if exists
        $phpInstaller = $module['path'] . '/database/install.php';
        if (file_exists($phpInstaller)) {
            require $phpInstaller;
        }

        // Mark as installed
        file_put_contents($module['path'] . '/.installed', date('Y-m-d H:i:s'));
        self::$modules[$id]['installed'] = true;

        return true;
    }

    /**
     * Uninstall a module
     */
    public static function uninstall($id) {
        $module = self::get($id);
        if (!$module) return false;

        $installedFile = $module['path'] . '/.installed';
        if (file_exists($installedFile)) {
            unlink($installedFile);
        }

        self::$modules[$id]['installed'] = false;
        return true;
    }

    /**
     * Get navigation items from all installed modules
     */
    public static function navItems() {
        $items = [];
        foreach (self::installed() as $module) {
            if (!empty($module['nav'])) {
                $items = array_merge($items, $module['nav']);
            }
        }
        return $items;
    }
}
