<?php

namespace Phoenix;

/**
 * Database Configuration — PhoenixPHP by CDAC Programming
 *
 * Define your database connections here. Phoenix supports multiple
 * simultaneous connections with automatic pooling and lazy initialization.
 * All credentials should be set in your .env file.
 */

return [
    'default' => 'primary',

    'connections' => [
        'primary' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => (int) env('DB_PORT', 3306),
            'database'  => env('DB_DATABASE', 'phoenix_app'),
            'username'  => env('DB_USERNAME', ''),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
            'options'   => []
        ],

        'users' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => (int) env('DB_PORT', 3306),
            'database'  => env('USERS_DATABASE', 'phoenix_users'),
            'username'  => env('DB_USERNAME', ''),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
            'options'   => []
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Database Mapping (Optional)
    |--------------------------------------------------------------------------
    |
    | Phoenix supports a pluggable module system where each module can have
    | its own database. Define module prefixes and ranges here.
    |
    | Example: Module 'Sales' with range(1, 4) creates databases:
    |   Phoenix_Sales_1, Phoenix_Sales_2, Phoenix_Sales_3, Phoenix_Sales_4
    |
    */
    'module_databases' => [
        // 'Sales'      => env('DB_SALES_PREFIX', 'Phoenix_Sales'),
        // 'Inventory'  => env('DB_INV_PREFIX', 'Phoenix_Inventory'),
    ],

    'module_ranges' => [
        // 'Sales'     => range(1, 4),
        // 'Inventory' => [1],
    ]
];
