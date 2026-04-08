<?php

namespace Phoenix;

/**
 * Application Configuration
 *
 * This file returns application-level configuration settings.
 * All values are pulled from environment variables via the env() helper.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | The name of your application
    |
    */
    'name' => env('APP_NAME', 'PhoenixPHP Application Framework'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is running in.
    | Options: production, development, testing
    |
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When debug mode is enabled, detailed error messages with stack traces
    | will be shown. NEVER enable this in production!
    |
    */
    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the application
    |
    */
    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configure session behavior and security settings
    |
    */
    'session' => [
        'lifetime' => (int) env('SESSION_LIFETIME', 43200), // 12 hours default
        'lifetime_admin' => (int) env('SESSION_LIFETIME_ADMIN', 600), // 10 minutes for supervisors and above
        'privileged_roles' => array_map('trim', explode(',', env('SESSION_PRIVILEGED_ROLES', 'SuperAdmin,Supervisor'))),
        'secure' => env('SESSION_SECURE', false),
        'httponly' => env('SESSION_HTTPONLY', true),
        'samesite' => env('SESSION_SAMESITE', 'Lax')
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    |
    | Cross-Site Request Forgery protection settings
    |
    */
    'csrf' => [
        'token_name' => env('CSRF_TOKEN_NAME', '_csrf_token'),
        'token_expire' => (int) env('CSRF_TOKEN_EXPIRE', 3600)
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | General security settings for the application
    |
    */
    'security' => [
        'csrf_protection' => env('SECURITY_CSRF_PROTECTION', true),
        'xss_protection' => env('SECURITY_XSS_PROTECTION', true),
        'content_security_policy' => env('SECURITY_CSP', false),
        'rate_limiting' => env('SECURITY_RATE_LIMITING', true)
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure application logging behavior
    |
    */
    'logging' => [
        'level' => env('LOG_LEVEL', 'error'),
        'path' => env('LOG_PATH', __DIR__ . '/../logs'),
        'error_log' => env('ERROR_LOG', __DIR__ . '/../logs/error.log'),
        'activity_log' => env('ACTIVITY_LOG', __DIR__ . '/../logs/activity.log')
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for production data
    |
    */
    'cache' => [
        'enabled' => env('CACHE_ENABLED', true),
        'ttl' => (int) env('CACHE_TTL', 600),
        'path' => env('CACHE_PATH', __DIR__ . '/../dashboard/cache'),
        'redis' => [
            'enabled' => env('CACHE_REDIS_ENABLED', false),
            'host' => env('CACHE_REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('CACHE_REDIS_PORT', 6379),
            'timeout' => (float) env('CACHE_REDIS_TIMEOUT', 2.5)
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Tracking
    |--------------------------------------------------------------------------
    |
    | Configure error tracking service (Sentry, etc.)
    |
    */
    'error_tracking' => [
        'dsn' => env('ERROR_TRACKING_DSN', '')
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure alerting channels for critical events
    |
    */
    'alerts' => [
        'email' => env('ALERT_EMAIL', ''),
        'webhook' => env('ALERT_WEBHOOK', ''),
        'webhook_type' => env('ALERT_WEBHOOK_TYPE', 'generic') // slack, discord, generic
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the application will show a maintenance page
    | Allowed IPs can still access the application
    |
    */
    'maintenance_mode' => env('MAINTENANCE_MODE', false),
    'allowed_ips' => array_filter(explode(',', env('ALLOWED_IPS', '127.0.0.1,::1')))
];
