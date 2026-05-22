<?php

namespace Phoenix;

class Settings
{
    private static $cache = [];
    private static $connectionName = 'users';

    public static function get(string $key, $default = null, ?int $userId = null)
    {
        $cacheKey = $key . ':' . ($userId ?? 'global');
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        try {
            $db = Database::pdo(self::$connectionName);

            // Try user-specific first
            if ($userId !== null) {
                $stmt = $db->prepare("SELECT `value`, `type` FROM `settings` WHERE `key` = ? AND `user_id` = ? LIMIT 1");
                $stmt->execute([$key, $userId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $val = self::cast($row['value'], $row['type']);
                    self::$cache[$cacheKey] = $val;
                    return $val;
                }
            }

            // Global setting
            $stmt = $db->prepare("SELECT `value`, `type` FROM `settings` WHERE `key` = ? AND `user_id` IS NULL LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $val = self::cast($row['value'], $row['type']);
                self::$cache[$cacheKey] = $val;
                return $val;
            }
        } catch (\Exception $e) {
            error_log("Settings::get error: " . $e->getMessage());
        }

        return $default;
    }

    public static function set(string $key, $value, ?int $userId = null, string $type = 'string', string $category = 'general'): void
    {
        try {
            $db = Database::pdo(self::$connectionName);

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
                $type = 'boolean';
            } elseif (is_array($value)) {
                $value = json_encode($value);
                $type = 'json';
            } elseif (is_int($value)) {
                $value = (string)$value;
                $type = 'integer';
            }

            if ($userId !== null) {
                $stmt = $db->prepare("
                    INSERT INTO `settings` (`key`, `value`, `type`, `category`, `user_id`)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)
                ");
                $stmt->execute([$key, $value, $type, $category, $userId]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO `settings` (`key`, `value`, `type`, `category`, `user_id`)
                    VALUES (?, ?, ?, ?, NULL)
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)
                ");
                $stmt->execute([$key, $value, $type, $category]);
            }

            // Invalidate cache
            $cacheKey = $key . ':' . ($userId ?? 'global');
            unset(self::$cache[$cacheKey]);
        } catch (\Exception $e) {
            error_log("Settings::set error: " . $e->getMessage());
        }
    }

    public static function delete(string $key, ?int $userId = null): void
    {
        try {
            $db = Database::pdo(self::$connectionName);

            if ($userId !== null) {
                $stmt = $db->prepare("DELETE FROM `settings` WHERE `key` = ? AND `user_id` = ?");
                $stmt->execute([$key, $userId]);
            } else {
                $stmt = $db->prepare("DELETE FROM `settings` WHERE `key` = ? AND `user_id` IS NULL");
                $stmt->execute([$key]);
            }

            $cacheKey = $key . ':' . ($userId ?? 'global');
            unset(self::$cache[$cacheKey]);
        } catch (\Exception $e) {
            error_log("Settings::delete error: " . $e->getMessage());
        }
    }

    public static function all(string $category = null): array
    {
        try {
            $db = Database::pdo(self::$connectionName);

            if ($category) {
                $stmt = $db->prepare("SELECT `key`, `value`, `type`, `category` FROM `settings` WHERE `category` = ? AND `user_id` IS NULL ORDER BY `key`");
                $stmt->execute([$category]);
            } else {
                $stmt = $db->query("SELECT `key`, `value`, `type`, `category` FROM `settings` WHERE `user_id` IS NULL ORDER BY `category`, `key`");
            }

            $settings = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $settings[$row['key']] = self::cast($row['value'], $row['type']);
            }
            return $settings;
        } catch (\Exception $e) {
            error_log("Settings::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private static function cast($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'integer':
                return (int)$value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                $decoded = json_decode($value, true);
                return $decoded !== null ? $decoded : $value;
            default:
                return $value;
        }
    }
}
