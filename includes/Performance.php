<?php

namespace Phoenix;

/**
 * Application Performance Monitoring (APM)
 *
 * Tracks performance metrics including:
 * - Operation timers
 * - Database query performance
 * - Memory usage
 * - Slow operation detection
 *
 * Usage:
 *   Performance::start('operation_name');
 *   // ... do work ...
 *   $duration = Performance::end('operation_name');
 */

class Performance {
    /**
     * Active timers
     */
    private static $timers = [];

    /**
     * Completed timers
     */
    private static $completed = [];

    /**
     * Query log
     */
    private static $queries = [];

    /**
     * Performance thresholds (in seconds)
     */
    private static $thresholds = [
        'slow_operation' => 1.0,
        'slow_query' => 0.1,
        'very_slow_query' => 1.0
    ];

    /**
     * Start performance timer
     *
     * @param string $name Timer name
     * @param array $context Additional context
     */
    public static function start($name, $context = []) {
        self::$timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'context' => $context
        ];
    }

    /**
     * End performance timer and return duration
     *
     * @param string $name Timer name
     * @return float Duration in seconds
     */
    public static function end($name) {
        if (!isset(self::$timers[$name])) {
            Logger::warning("Performance timer '$name' was not started");
            return 0;
        }

        $timer = self::$timers[$name];
        $duration = microtime(true) - $timer['start'];
        $memoryUsed = memory_get_usage(true) - $timer['memory_start'];

        // Store completed timer
        self::$completed[$name] = [
            'duration' => $duration,
            'memory' => $memoryUsed,
            'context' => $timer['context']
        ];

        // Log slow operations
        if ($duration > self::$thresholds['slow_operation']) {
            Logger::warning("Slow operation detected", [
                'operation' => $name,
                'duration' => round($duration, 4),
                'memory_mb' => round($memoryUsed / 1024 / 1024, 2),
                'context' => $timer['context']
            ]);
        }

        // Remove from active timers
        unset(self::$timers[$name]);

        return $duration;
    }

    /**
     * Log database query performance
     *
     * @param string $sql SQL query
     * @param float $duration Query duration in seconds
     * @param array $context Additional context
     */
    public static function logQuery($sql, $duration, $context = []) {
        $query = [
            'sql' => $sql,
            'duration' => $duration,
            'timestamp' => microtime(true),
            'context' => $context,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];

        self::$queries[] = $query;

        // Log slow queries
        if ($duration > self::$thresholds['very_slow_query']) {
            Logger::error("Very slow query detected", [
                'sql' => self::sanitizeSql($sql),
                'duration' => round($duration, 4)
            ]);
        } elseif ($duration > self::$thresholds['slow_query']) {
            Logger::warning("Slow query detected", [
                'sql' => self::sanitizeSql($sql),
                'duration' => round($duration, 4)
            ]);
        }
    }

    /**
     * Measure execution time of a callback
     *
     * @param string $name Operation name
     * @param callable $callback Function to measure
     * @return mixed Callback return value
     */
    public static function measure($name, $callback) {
        self::start($name);
        try {
            return $callback();
        } finally {
            self::end($name);
        }
    }

    /**
     * Get performance metrics
     *
     * @return array Metrics
     */
    public static function getMetrics() {
        return [
            'memory' => [
                'current' => memory_get_usage(true),
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak' => memory_get_peak_usage(true),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit' => ini_get('memory_limit')
            ],
            'queries' => [
                'total' => count(self::$queries),
                'slow' => self::getSlowQueryCount(),
                'very_slow' => self::getVerySlowQueryCount(),
                'total_time' => self::getTotalQueryTime(),
                'avg_time' => self::getAverageQueryTime()
            ],
            'timers' => [
                'active' => count(self::$timers),
                'completed' => count(self::$completed),
                'slowest' => self::getSlowestOperations(5)
            ],
            'runtime' => [
                'elapsed' => self::getElapsedTime(),
                'elapsed_ms' => round(self::getElapsedTime() * 1000, 2)
            ]
        ];
    }

    /**
     * Get slow queries
     *
     * @param int $limit Maximum queries to return
     * @return array Slow queries
     */
    public static function getSlowQueries($limit = 20) {
        $slowQueries = array_filter(self::$queries, function($q) {
            return $q['duration'] > self::$thresholds['slow_query'];
        });

        usort($slowQueries, function($a, $b) {
            return $b['duration'] <=> $a['duration'];
        });

        return array_slice($slowQueries, 0, $limit);
    }

    /**
     * Get all queries
     *
     * @return array All queries
     */
    public static function getQueries() {
        return self::$queries;
    }

    /**
     * Get completed timers
     *
     * @return array Completed timers
     */
    public static function getTimers() {
        return self::$completed;
    }

    /**
     * Set performance threshold
     *
     * @param string $type Threshold type
     * @param float $value Threshold value in seconds
     */
    public static function setThreshold($type, $value) {
        if (isset(self::$thresholds[$type])) {
            self::$thresholds[$type] = $value;
        }
    }

    /**
     * Reset all metrics
     */
    public static function reset() {
        self::$timers = [];
        self::$completed = [];
        self::$queries = [];
    }

    // ========== Private Helper Methods ==========

    /**
     * Get slow query count
     */
    private static function getSlowQueryCount() {
        return count(array_filter(self::$queries, function($q) {
            return $q['duration'] > self::$thresholds['slow_query'];
        }));
    }

    /**
     * Get very slow query count
     */
    private static function getVerySlowQueryCount() {
        return count(array_filter(self::$queries, function($q) {
            return $q['duration'] > self::$thresholds['very_slow_query'];
        }));
    }

    /**
     * Get total query time
     */
    private static function getTotalQueryTime() {
        $total = 0;
        foreach (self::$queries as $query) {
            $total += $query['duration'];
        }
        return round($total, 4);
    }

    /**
     * Get average query time
     */
    private static function getAverageQueryTime() {
        if (empty(self::$queries)) {
            return 0;
        }
        return round(self::getTotalQueryTime() / count(self::$queries), 4);
    }

    /**
     * Get slowest operations
     */
    private static function getSlowestOperations($limit = 5) {
        $operations = self::$completed;

        uasort($operations, function($a, $b) {
            return $b['duration'] <=> $a['duration'];
        });

        return array_slice($operations, 0, $limit, true);
    }

    /**
     * Get elapsed time since script start
     */
    private static function getElapsedTime() {
        if (!defined('APP_START_TIME')) {
            return 0;
        }
        return microtime(true) - APP_START_TIME;
    }

    /**
     * Sanitize SQL for logging (truncate long queries)
     */
    private static function sanitizeSql($sql) {
        $maxLength = 200;
        if (strlen($sql) > $maxLength) {
            return substr($sql, 0, $maxLength) . '...';
        }
        return $sql;
    }

    /**
     * Generate performance report
     *
     * @return string HTML performance report
     */
    public static function generateReport() {
        if (!Config::isDebug()) {
            return '';
        }

        $metrics = self::getMetrics();
        $slowQueries = self::getSlowQueries(10);

        ob_start();
        ?>
        <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; margin: 20px 0; font-family: monospace; font-size: 12px;">
            <h3 style="margin-top: 0;">Performance Report</h3>

            <h4>Memory Usage</h4>
            <p>Current: <?php echo $metrics['memory']['current_mb']; ?> MB | Peak: <?php echo $metrics['memory']['peak_mb']; ?> MB | Limit: <?php echo $metrics['memory']['limit']; ?></p>

            <h4>Database Queries</h4>
            <p>Total: <?php echo $metrics['queries']['total']; ?> | Slow: <?php echo $metrics['queries']['slow']; ?> | Very Slow: <?php echo $metrics['queries']['very_slow']; ?></p>
            <p>Total Time: <?php echo $metrics['queries']['total_time']; ?>s | Average: <?php echo $metrics['queries']['avg_time']; ?>s</p>

            <?php if (!empty($slowQueries)): ?>
            <h4>Slow Queries</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #ddd;">
                        <th style="text-align: left; padding: 5px;">Duration</th>
                        <th style="text-align: left; padding: 5px;">Query</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slowQueries as $query): ?>
                    <tr>
                        <td style="padding: 5px;"><?php echo round($query['duration'], 4); ?>s</td>
                        <td style="padding: 5px;"><?php echo htmlspecialchars(substr($query['sql'], 0, 100)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <h4>Slowest Operations</h4>
            <?php foreach ($metrics['timers']['slowest'] as $name => $timer): ?>
            <p><?php echo htmlspecialchars($name); ?>: <?php echo round($timer['duration'], 4); ?>s (<?php echo round($timer['memory'] / 1024 / 1024, 2); ?> MB)</p>
            <?php endforeach; ?>

            <h4>Runtime</h4>
            <p>Elapsed: <?php echo $metrics['runtime']['elapsed_ms']; ?>ms</p>
        </div>
        <?php
        return ob_get_clean();
    }
}
