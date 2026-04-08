<?php

namespace Phoenix;

/**
 * Multi-layer caching system
 * Supports APCu, Redis, and file-based caching
 *
 * Automatically detects available cache drivers and uses the best one:
 * 1. Redis (fastest, requires redis extension)
 * 2. APCu (fast, in-memory, requires apcu extension)
 * 3. File cache (fallback, always available)
 *
 * Usage:
 *   Cache::set('key', $value, 3600);
 *   $value = Cache::get('key', 'default');
 *   Cache::forget('key');
 *   $value = Cache::remember('key', 3600, function() { return expensive_operation(); });
 */

class Cache {
    /**
     * Current cache driver (redis, apcu, file)
     */
    private static $driver = 'file';

    /**
     * Redis connection instance
     */
    private static $redis = null;

    /**
     * Cache statistics
     */
    private static $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];

    /**
     * Initialize cache system and detect best available driver
     */
    public static function init() {
        // Try Redis first
        if (extension_loaded('redis') && Config::get('app.cache.redis.enabled', false)) {
            try {
                self::$redis = new \Redis();
                $host = Config::get('app.cache.redis.host', '127.0.0.1');
                $port = Config::get('app.cache.redis.port', 6379);
                $timeout = Config::get('app.cache.redis.timeout', 2.5);

                if (self::$redis->connect($host, $port, $timeout)) {
                    self::$driver = 'redis';
                    Logger::info('Cache driver: Redis');
                    return;
                }
            } catch (Exception $e) {
                Logger::warning('Redis connection failed, falling back', ['error' => $e->getMessage()]);
            }
        }

        // Try APCu second
        if (extension_loaded('apcu') && ini_get('apc.enabled')) {
            self::$driver = 'apcu';
            Logger::info('Cache driver: APCu');
            return;
        }

        // Fall back to file cache
        self::$driver = 'file';
        Logger::info('Cache driver: File');
    }

    /**
     * Get value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function get($key, $default = null) {
        try {
            switch (self::$driver) {
                case 'redis':
                    $value = self::$redis->get($key);
                    if ($value === false) {
                        self::$stats['misses']++;
                        return $default;
                    }
                    self::$stats['hits']++;
                    return unserialize($value);

                case 'apcu':
                    $success = false;
                    $value = apcu_fetch($key, $success);
                    if (!$success) {
                        self::$stats['misses']++;
                        return $default;
                    }
                    self::$stats['hits']++;
                    return $value;

                default:
                    $value = self::fileGet($key, $default);
                    if ($value === $default) {
                        self::$stats['misses']++;
                    } else {
                        self::$stats['hits']++;
                    }
                    return $value;
            }
        } catch (Exception $e) {
            Logger::error('Cache get failed', ['key' => $key, 'error' => $e->getMessage()]);
            self::$stats['misses']++;
            return $default;
        }
    }

    /**
     * Set value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool Success
     */
    public static function set($key, $value, $ttl = 3600) {
        try {
            self::$stats['sets']++;

            switch (self::$driver) {
                case 'redis':
                    return self::$redis->setex($key, $ttl, serialize($value));

                case 'apcu':
                    return apcu_store($key, $value, $ttl);

                default:
                    return self::fileSet($key, $value, $ttl);
            }
        } catch (Exception $e) {
            Logger::error('Cache set failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete value from cache
     *
     * @param string $key Cache key
     * @return bool Success
     */
    public static function forget($key) {
        try {
            self::$stats['deletes']++;

            switch (self::$driver) {
                case 'redis':
                    return self::$redis->del($key) > 0;

                case 'apcu':
                    return apcu_delete($key);

                default:
                    return self::fileForget($key);
            }
        } catch (Exception $e) {
            Logger::error('Cache delete failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get value from cache, or execute callback and cache result
     *
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to execute if cache miss
     * @return mixed
     */
    public static function remember($key, $ttl, $callback) {
        $value = self::get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttl);

        return $value;
    }

    /**
     * Flush all cache entries
     *
     * @return bool Success
     */
    public static function flush() {
        try {
            switch (self::$driver) {
                case 'redis':
                    return self::$redis->flushDB();

                case 'apcu':
                    return apcu_clear_cache();

                default:
                    return self::fileFlush();
            }
        } catch (Exception $e) {
            Logger::error('Cache flush failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get cache statistics
     *
     * @return array Statistics
     */
    public static function stats() {
        $total = self::$stats['hits'] + self::$stats['misses'];
        $hitRate = $total > 0 ? (self::$stats['hits'] / $total) * 100 : 0;

        return array_merge(self::$stats, [
            'hit_rate' => round($hitRate, 2),
            'driver' => self::$driver
        ]);
    }

    /**
     * Get current cache driver
     *
     * @return string Driver name
     */
    public static function driver() {
        return self::$driver;
    }

    // ========== File Cache Implementation ==========

    /**
     * Get value from file cache
     */
    private static function fileGet($key, $default) {
        $file = self::getCacheFile($key);

        if (!file_exists($file)) {
            return $default;
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return $default;
        }

        $data = @unserialize($contents);
        if ($data === false) {
            return $default;
        }

        if ($data['expires'] < time()) {
            @unlink($file);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Set value in file cache
     */
    private static function fileSet($key, $value, $ttl) {
        $file = self::getCacheFile($key);
        $dir = dirname($file);

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true)) {
                return false;
            }
        }

        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        return @file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    /**
     * Delete value from file cache
     */
    private static function fileForget($key) {
        $file = self::getCacheFile($key);
        return file_exists($file) ? @unlink($file) : true;
    }

    /**
     * Flush all file cache
     */
    private static function fileFlush() {
        $cachePath = Config::get('app.cache.path');
        if (!is_dir($cachePath)) {
            return true;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cachePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                @unlink($file->getPathname());
            } elseif ($file->isDir()) {
                @rmdir($file->getPathname());
            }
        }

        return true;
    }

    /**
     * Get cache file path for key
     */
    private static function getCacheFile($key) {
        $hash = md5($key);
        $cachePath = Config::get('app.cache.path');
        return $cachePath . '/' . substr($hash, 0, 2) . '/' . $hash;
    }
}

// Auto-initialize cache system
Cache::init();
