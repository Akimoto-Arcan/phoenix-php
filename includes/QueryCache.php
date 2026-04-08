<?php

namespace Phoenix;

/**
 * Database query result caching
 *
 * Provides automatic caching of database query results with tag-based invalidation.
 * Helps reduce database load for frequently-run queries.
 *
 * Usage:
 *   $users = QueryCache::query('SELECT * FROM Users WHERE active = ?', [1], 300);
 *   QueryCache::invalidate('users'); // Clear all user-related caches
 */

class QueryCache {
    /**
     * Default TTL for cached queries
     */
    const DEFAULT_TTL = 300; // 5 minutes

    /**
     * Query statistics
     */
    private static $stats = [
        'queries' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0
    ];

    /**
     * Execute query with caching
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param int $ttl Cache TTL in seconds
     * @param string|null $connection Database connection name
     * @param array $tags Cache tags for invalidation
     * @return array Query results
     */
    public static function query($sql, $params = [], $ttl = self::DEFAULT_TTL, $connection = 'users', $tags = []) {
        self::$stats['queries']++;

        $cacheKey = self::getCacheKey($sql, $params, $connection);

        // Try to get from cache
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            self::$stats['cache_hits']++;
            return $cached;
        }

        self::$stats['cache_misses']++;

        // Execute query
        Performance::start("query:$cacheKey");
        try {
            $db = Database::pdo($connection);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $duration = Performance::end("query:$cacheKey");
            Performance::logQuery($sql, $duration);

            // Cache results
            Cache::set($cacheKey, $results, $ttl);

            // Register tags
            if (!empty($tags)) {
                self::registerTags($cacheKey, $tags);
            }

            return $results;

        } catch (PDOException $e) {
            Performance::end("query:$cacheKey");
            Logger::error('Query cache execution failed', [
                'sql' => $sql,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute query and return single row
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param int $ttl Cache TTL in seconds
     * @param string|null $connection Database connection name
     * @param array $tags Cache tags for invalidation
     * @return array|null Query result or null
     */
    public static function queryOne($sql, $params = [], $ttl = self::DEFAULT_TTL, $connection = 'users', $tags = []) {
        $results = self::query($sql, $params, $ttl, $connection, $tags);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Execute query and return single column value
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param int $ttl Cache TTL in seconds
     * @param string|null $connection Database connection name
     * @param array $tags Cache tags for invalidation
     * @return mixed Query result or null
     */
    public static function queryValue($sql, $params = [], $ttl = self::DEFAULT_TTL, $connection = 'users', $tags = []) {
        $result = self::queryOne($sql, $params, $ttl, $connection, $tags);
        return $result ? array_values($result)[0] : null;
    }

    /**
     * Invalidate cache by tags
     *
     * @param string|array $tags Tag or array of tags
     * @return int Number of cache entries invalidated
     */
    public static function invalidate($tags) {
        $tags = (array)$tags;
        $count = 0;

        foreach ($tags as $tag) {
            $tagKey = "querycache:tag:$tag";
            $cacheKeys = Cache::get($tagKey, []);

            foreach ($cacheKeys as $cacheKey) {
                if (Cache::forget($cacheKey)) {
                    $count++;
                }
            }

            // Remove tag index
            Cache::forget($tagKey);
        }

        Logger::info('Query cache invalidated', ['tags' => $tags, 'count' => $count]);
        return $count;
    }

    /**
     * Invalidate specific query cache
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param string|null $connection Database connection name
     * @return bool Success
     */
    public static function invalidateQuery($sql, $params = [], $connection = 'users') {
        $cacheKey = self::getCacheKey($sql, $params, $connection);
        return Cache::forget($cacheKey);
    }

    /**
     * Get query cache statistics
     *
     * @return array Statistics
     */
    public static function stats() {
        $total = self::$stats['cache_hits'] + self::$stats['cache_misses'];
        $hitRate = $total > 0 ? (self::$stats['cache_hits'] / $total) * 100 : 0;

        return array_merge(self::$stats, [
            'hit_rate' => round($hitRate, 2)
        ]);
    }

    /**
     * Get cache key for query
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param string $connection Database connection name
     * @return string Cache key
     */
    private static function getCacheKey($sql, $params, $connection) {
        return 'querycache:' . md5($connection . ':' . $sql . ':' . serialize($params));
    }

    /**
     * Register cache key with tags for later invalidation
     *
     * @param string $cacheKey Cache key
     * @param array $tags Tags to register
     */
    private static function registerTags($cacheKey, $tags) {
        foreach ($tags as $tag) {
            $tagKey = "querycache:tag:$tag";
            $cacheKeys = Cache::get($tagKey, []);

            if (!in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::set($tagKey, $cacheKeys, 86400); // Tag index TTL: 24 hours
            }
        }
    }

    /**
     * Flush all query cache
     *
     * @return bool Success
     */
    public static function flush() {
        Logger::info('Query cache flushed');
        return Cache::flush();
    }
}
