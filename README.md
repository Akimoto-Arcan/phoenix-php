# Phoenix PHP — ERP & CMMS Framework

A plug-and-play PHP framework for building ERP systems, CMMS platforms, and internal business tools. Clone it, run the installer, and you have a working application with authentication, role-based access control, and 6 production-ready modules.

## Quick Start

```bash
git clone https://github.com/akimoto-arcan/phoenix-php.git
cd phoenix-php
composer install
```

Then visit `http://your-server/` in a browser. The install wizard handles everything:

1. **System check** — verifies PHP version, extensions, writable directories
2. **Environment** — choose Local (development) or Cloud (production)
3. **Database** — enter MySQL credentials, create database
4. **Core tables** — users, roles, permissions, settings
5. **Admin account** — create your first SuperAdmin
6. **Modules** — select which modules to install
7. **Done** — `.env` generated, redirected to login

## What You Get

### Core Framework
- **Authentication** — session-based login with brute-force protection, account lockout, mobile device whitelisting
- **RBAC** — 8 system roles, 23 permissions, group-based access control with permission inheritance
- **Router** — segment-based URL routing with middleware (auth, permission, CSRF, guest)
- **View Engine** — PHP templates with layout inheritance, sections, and partials
- **Settings** — key-value store with per-user overrides and type casting
- **Caching** — multi-layer (Redis > APCu > file) with query caching
- **Security** — CSRF protection, input validation, rate limiting, security headers
- **Theming** — light/dark theme toggle with user preference persistence

### Modules (Plug & Play)

| Module | Tables | API Endpoints | Description |
|--------|--------|---------------|-------------|
| **CMMS** | 6 | 14 | Work orders, PM schedules, equipment, parts inventory |
| **Inventory** | 8 | 5+ | Items, stock levels, receiving, shipping, low-stock alerts |
| **Production** | 5 | 9 | Lines, runs, downtime tracking, defect logging, OEE |
| **Chat** | 5 | 12 | Channels, messages, presence, mentions, direct messages |
| **Scheduling** | 7 | 12 | Shift assignments, time-off, swaps, availability |
| **Reporting** | 5 | 11 | Report builder, scheduled reports, export (PDF/CSV) |

Each module is self-contained: drop the directory into `/modules/`, run the install SQL, and it auto-registers with navigation and API routes.

## Architecture

```
phoenix-php/
├── index.php              # Front controller
├── bootstrap.php          # App initialization
├── install.php            # Web-based installer
├── .htaccess              # URL rewriting
├── config/
│   ├── app.php            # Application config
│   └── database.php       # Database connections
├── includes/              # Core PHP classes (PSR-4: Phoenix\)
│   ├── Auth.php           # Authentication & authorization
│   ├── Router.php         # URL routing
│   ├── View.php           # Template engine
│   ├── Database.php       # Connection pooling (mysqli + PDO)
│   ├── Config.php         # Environment configuration
│   ├── CSRF.php           # CSRF protection
│   ├── Settings.php       # Key-value settings
│   ├── ModuleLoader.php   # Module discovery & management
│   ├── Cache.php          # Multi-layer caching
│   ├── Validator.php      # Input validation
│   └── ...
├── views/                 # PHP view templates
│   ├── layouts/           # app.php, auth.php
│   ├── partials/          # sidebar, topbar, flash
│   ├── auth/              # login, register
│   └── dashboard/         # main dashboard
├── routes/                # Route definitions
│   ├── web.php            # Web routes
│   └── api.php            # API routes
├── modules/               # Pluggable modules
│   ├── cmms/
│   ├── inventory-management/
│   ├── production-tracker/
│   ├── chat-system/
│   ├── scheduling/
│   └── reporting/
├── public/
│   ├── css/phoenix.css    # Styles (dark + light themes)
│   └── js/                # Shared UI components
├── database/
│   ├── schema.sql         # Core table definitions
│   └── seed.sql           # Roles, permissions, defaults
└── storage/               # Runtime files (.installed lock)
```

## Requirements

- PHP >= 7.4
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite (or nginx equivalent)
- Composer

**PHP Extensions:** mysqli, pdo_mysql, json, mbstring, openssl

## Creating a Module

1. Create a directory in `/modules/your-module/`
2. Add `module.json` with metadata:

```json
{
    "id": "your-module",
    "name": "Your Module",
    "version": "1.0.0",
    "description": "What it does",
    "nav": [
        {
            "label": "Your Module",
            "icon": "📦",
            "href": "/modules/your-module/views/",
            "permission": "production.read"
        }
    ]
}
```

3. Add `database/install.sql` with your CREATE TABLE statements
4. Add `api/routes.php` for REST endpoints
5. Add `views/index.php` for the frontend SPA

The module auto-registers when discovered by `ModuleLoader::discover()`.

## API

All API endpoints require authentication via session cookies. CSRF token must be included as `X-CSRF-Token` header on POST/PUT/DELETE requests.

```bash
# Get current user
curl -b cookies.txt http://localhost/api/v1/me

# List installed modules
curl -b cookies.txt http://localhost/api/v1/modules/installed

# Module APIs are proxied through the router
# Example: CMMS work orders
curl -b cookies.txt http://localhost/modules/cmms/api/routes.php?path=work-orders
```

## Theming

Phoenix ships with dark and light themes. Toggle via the button in the top bar. The theme preference persists in `localStorage` and syncs to the user's database record.

To customize: edit CSS custom properties in `/public/css/phoenix.css`. All components use `var(--bg-primary)`, `var(--text-primary)`, etc.

## Tech Stack

- PHP (no framework dependencies beyond Composer packages)
- MySQL with prepared statements
- Vanilla JavaScript (ES modules, no build step)
- Custom CSS with CSS custom properties
- Chart.js for data visualization

## License

MIT — use in personal or commercial projects. Modify freely.

---

Built by [CDAC Programming](https://github.com/Akimoto-Arcan)
