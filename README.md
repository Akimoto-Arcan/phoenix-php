# PhoenixPHP Application Framework

**By CDAC Programming**

A production-ready PHP framework built from 4+ years of real-world enterprise application development. PhoenixPHP provides everything you need to build secure, scalable web applications with multi-database support, REST APIs, role-based access control, and a modular plugin architecture.

**This is not a toy framework.** Every component was battle-tested in a production environment serving 100+ daily users at 99.9% uptime before being extracted and generalized.

---

## Features

### Authentication & Security
- Session-based authentication with bcrypt password hashing
- Role-based access control (RBAC) with configurable permission groups
- CSRF token protection with constant-time comparison
- Rate limiting per IP (login, API, password reset)
- Input validation and sanitization
- SQL injection prevention (prepared statements throughout)
- Field-level audit logging (user, field, old/new values, timestamp, IP)
- Security hardening configuration

### Database
- Multi-database connection management with automatic pooling
- MySQLi and PDO support with lazy initialization
- Query optimization utilities and execution plan analysis
- Query result caching with tag-based invalidation
- Atomic write patterns with transaction support
- Dynamic module database routing

### REST API Framework
- Standardized JSON response format (`{ok: true/false, data: {}, error: ""}`)
- CORS support with preflight handling
- Authentication enforcement
- Permission checking per endpoint
- Pagination helpers
- Input validation helpers
- OpenAPI 3.0 compatible design

### Caching
- Multi-driver support: Redis, APCu, File-based
- Automatic driver detection and fallback
- TTL support with `Cache::remember()` pattern
- Hit/miss statistics
- Query-level caching with `QueryCache`

### Error Handling & Logging
- PSR-3 compatible logger with file rotation
- Custom error and exception handlers
- Safe error output (no internals exposed in production)
- Request context injection (user, IP, URI, method)
- Performance monitoring with slow operation detection

### Configuration
- Environment-based configuration via `.env`
- Dot-notation access (`Config::get('database.connections.primary.host')`)
- Runtime configuration override
- Centralized security settings

### Modular Architecture
- Pluggable module system — add new modules by creating a directory
- Each module can have its own database
- Zero core framework changes required for extension
- Configuration-driven module registration

---

## Quick Start

### 1. Install

```bash
git clone https://github.com/Akimoto-Arcan/phoenix-php.git
cd phoenix-php
composer install
cp .env.example .env
```

### 2. Configure

Edit `.env` with your database credentials and application settings.

### 3. Use

```php
<?php
require_once __DIR__ . '/bootstrap.php';

// Authentication
Auth::check();           // Is user logged in?
Auth::user();            // Get current user
Auth::can('admin.read'); // Check permission

// Database
$conn = Database::connection('primary');  // Get MySQLi connection
$pdo = Database::pdo('users');            // Get PDO connection

// Configuration
$debug = Config::get('app.debug', false);
$dbHost = Config::get('database.connections.primary.host');

// Caching
$data = Cache::remember('key', 300, function() {
    return expensive_query();
});

// Validation
$validator = Validator::make($input, [
    'email' => 'required|email',
    'name' => 'required|min:2|max:100',
    'age' => 'numeric|min:18'
]);

if (!$validator->validate()) {
    $errors = $validator->errors();
}

// API Response
json_response(['ok' => true, 'data' => $results]);
```

---

## Directory Structure

```
phoenix-php/
├── bootstrap.php           # Framework initialization
├── composer.json            # Dependencies
├── .env.example             # Environment template
├── config/
│   ├── app.php              # Application settings
│   ├── database.php         # Database connections
│   └── mail.php             # Email configuration
├── includes/
│   ├── Auth.php             # Authentication & RBAC
│   ├── Cache.php            # Multi-driver caching
│   ├── Config.php           # Configuration manager
│   ├── CSRF.php             # CSRF protection
│   ├── Database.php         # Connection management
│   ├── ErrorHandler.php     # Error/exception handling
│   ├── Logger.php           # PSR-3 logging
│   ├── Performance.php      # APM & monitoring
│   ├── QueryCache.php       # Query result caching
│   ├── SecurityConfig.php   # Security settings
│   ├── SecurityHelper.php   # Security utilities
│   └── Validator.php        # Input validation
├── api/
│   ├── _bootstrap.php       # API framework
│   └── v1/                  # Versioned endpoints
├── public/                  # Web-accessible files
├── logs/                    # Application logs
├── cache/                   # File cache storage
├── database/
│   └── migrations/          # Database migrations
├── tests/                   # PHPUnit tests
└── docs/                    # Documentation
```

---

## Requirements

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- Apache with mod_rewrite (or Nginx)

---

## License

MIT License — Copyright (c) 2026 CDAC Programming (Chris Carpenter)

---

## Author

**Chris Carpenter** — CDAC Programming
- GitHub: [@Akimoto-Arcan](https://github.com/Akimoto-Arcan)
- LinkedIn: [chris-carpenter-bs-cs](https://linkedin.com/in/chris-carpenter-bs-cs)
