<?php
/**
 * Phoenix-PHP Install Wizard
 * Self-contained — works before .env or bootstrap exist.
 */

// Already installed?
if (file_exists(__DIR__ . '/storage/.installed')) {
    header('Location: /login');
    exit;
}

// Handle AJAX steps
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    switch ($action) {
        case 'check_requirements':
            echo json_encode(checkRequirements());
            break;

        case 'test_db':
            echo json_encode(testDatabase($_POST));
            break;

        case 'create_db':
            echo json_encode(createDatabase($_POST));
            break;

        case 'install_core':
            echo json_encode(installCoreTables($_POST));
            break;

        case 'create_admin':
            echo json_encode(createAdmin($_POST));
            break;

        case 'discover_modules':
            echo json_encode(discoverModules());
            break;

        case 'install_modules':
            echo json_encode(installModules($_POST));
            break;

        case 'finalize':
            echo json_encode(finalize($_POST));
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
    exit;
}

// --- Step handler functions ---

function checkRequirements(): array {
    $checks = [];

    $checks[] = [
        'name' => 'PHP Version',
        'required' => '>= 7.4',
        'found' => PHP_VERSION,
        'pass' => version_compare(PHP_VERSION, '7.4.0', '>='),
    ];

    $extensions = ['mysqli', 'pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
    foreach ($extensions as $ext) {
        $checks[] = [
            'name' => "ext-{$ext}",
            'required' => 'Required',
            'found' => extension_loaded($ext) ? 'Installed' : 'Missing',
            'pass' => extension_loaded($ext),
        ];
    }

    $checks[] = [
        'name' => 'ext-gd',
        'required' => 'Recommended',
        'found' => extension_loaded('gd') ? 'Installed' : 'Missing',
        'pass' => true, // Not strictly required
    ];

    $dirs = ['cache', 'logs', 'storage'];
    foreach ($dirs as $dir) {
        $path = __DIR__ . '/' . $dir;
        $writable = is_dir($path) && is_writable($path);
        $checks[] = [
            'name' => "{$dir}/ writable",
            'required' => 'Writable',
            'found' => $writable ? 'Writable' : (is_dir($path) ? 'Not writable' : 'Missing'),
            'pass' => $writable,
        ];
    }

    $composerOk = file_exists(__DIR__ . '/vendor/autoload.php');
    $checks[] = [
        'name' => 'Composer dependencies',
        'required' => 'Installed',
        'found' => $composerOk ? 'Installed' : 'Missing — run: composer install',
        'pass' => $composerOk,
    ];

    $allPass = true;
    foreach ($checks as $c) {
        if (!$c['pass'] && $c['required'] === 'Required') {
            $allPass = false;
        }
    }

    return ['ok' => true, 'checks' => $checks, 'all_pass' => $allPass];
}

function testDatabase(array $data): array {
    $host = $data['db_host'] ?? '127.0.0.1';
    $port = (int)($data['db_port'] ?? 3306);
    $user = $data['db_user'] ?? '';
    $pass = $data['db_pass'] ?? '';
    $name = $data['db_name'] ?? 'phoenix';

    $conn = @new \mysqli($host, $user, $pass, '', $port);
    if ($conn->connect_error) {
        return ['ok' => false, 'error' => 'Connection failed: ' . $conn->connect_error];
    }

    $dbExists = false;
    $stmt = $conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $r = $stmt->get_result();
    $dbExists = $r && $r->num_rows > 0;
    $stmt->close();

    $conn->close();

    return [
        'ok' => true,
        'message' => 'Connection successful',
        'db_exists' => $dbExists,
    ];
}

function createDatabase(array $data): array {
    $host = $data['db_host'] ?? '127.0.0.1';
    $port = (int)($data['db_port'] ?? 3306);
    $user = $data['db_user'] ?? '';
    $pass = $data['db_pass'] ?? '';
    $name = $data['db_name'] ?? 'phoenix';

    $conn = @new \mysqli($host, $user, $pass, '', $port);
    if ($conn->connect_error) {
        return ['ok' => false, 'error' => $conn->connect_error];
    }

    $safeName = $conn->real_escape_string($name);
    if (!$conn->query("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        $err = $conn->error;
        $conn->close();
        return ['ok' => false, 'error' => $err];
    }

    $conn->close();
    return ['ok' => true, 'message' => "Database '{$name}' created"];
}

function installCoreTables(array $data): array {
    $conn = getInstallConnection($data);
    if (!$conn) {
        return ['ok' => false, 'error' => 'Database connection failed'];
    }

    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        return ['ok' => false, 'error' => 'schema.sql not found'];
    }

    $sql = file_get_contents($schemaFile);
    if (!$conn->multi_query($sql)) {
        $err = $conn->error;
        $conn->close();
        return ['ok' => false, 'error' => $err];
    }
    while ($conn->next_result()) { /* flush */ }

    // Run seed data
    $seedFile = __DIR__ . '/database/seed.sql';
    if (file_exists($seedFile)) {
        $seed = file_get_contents($seedFile);
        if (!$conn->multi_query($seed)) {
            $err = $conn->error;
            $conn->close();
            return ['ok' => false, 'error' => 'Seed error: ' . $err];
        }
        while ($conn->next_result()) { /* flush */ }
    }

    $conn->close();
    return ['ok' => true, 'message' => 'Core tables and seed data installed'];
}

function createAdmin(array $data): array {
    $conn = getInstallConnection($data);
    if (!$conn) {
        return ['ok' => false, 'error' => 'Database connection failed'];
    }

    $username = trim($data['admin_user'] ?? '');
    $password = $data['admin_pass'] ?? '';
    $fullName = trim($data['admin_name'] ?? '');
    $email = trim($data['admin_email'] ?? '');

    if (empty($username) || empty($password) || strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Username required, password min 8 chars'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password, role, `groups`, approved, is_active) VALUES (?, ?, ?, ?, 'SuperAdmin', 'SuperAdmin', 1, 1)");
    if (!$stmt) {
        $err = $conn->error;
        $conn->close();
        return ['ok' => false, 'error' => $err];
    }

    $stmt->bind_param('ssss', $username, $fullName, $email, $hash);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        $conn->close();
        return ['ok' => false, 'error' => $err];
    }

    $userId = $stmt->insert_id;
    $stmt->close();
    $conn->close();

    return ['ok' => true, 'user_id' => $userId, 'message' => 'Admin account created'];
}

function discoverModules(): array {
    $modulesPath = __DIR__ . '/modules';
    $modules = [];

    if (!is_dir($modulesPath)) {
        return ['ok' => true, 'modules' => []];
    }

    $dirs = glob($modulesPath . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $manifest = $dir . '/module.json';
        if (!file_exists($manifest)) continue;

        $data = json_decode(file_get_contents($manifest), true);
        if (!$data || empty($data['id'])) continue;

        $modules[] = [
            'id' => $data['id'],
            'name' => $data['name'] ?? $data['id'],
            'description' => $data['description'] ?? '',
            'version' => $data['version'] ?? '1.0.0',
            'has_sql' => file_exists($dir . '/database/install.sql'),
        ];
    }

    return ['ok' => true, 'modules' => $modules];
}

function installModules(array $data): array {
    $selected = json_decode($data['modules'] ?? '[]', true);
    if (empty($selected)) {
        return ['ok' => true, 'message' => 'No modules selected', 'installed' => []];
    }

    $conn = getInstallConnection($data);
    if (!$conn) {
        return ['ok' => false, 'error' => 'Database connection failed'];
    }

    $installed = [];
    $errors = [];

    foreach ($selected as $moduleId) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $moduleId)) {
            $errors[] = "{$moduleId}: invalid module ID";
            continue;
        }
        $sqlFile = __DIR__ . '/modules/' . $moduleId . '/database/install.sql';
        if (!file_exists($sqlFile)) {
            $errors[] = "{$moduleId}: install.sql not found";
            continue;
        }

        $sql = file_get_contents($sqlFile);
        if ($conn->multi_query($sql)) {
            while ($conn->next_result()) { /* flush */ }
            // Mark as installed
            file_put_contents(__DIR__ . '/modules/' . $moduleId . '/.installed', date('Y-m-d H:i:s'));
            $installed[] = $moduleId;
        } else {
            $errors[] = "{$moduleId}: " . $conn->error;
            // Flush any remaining results
            while ($conn->more_results() && $conn->next_result()) { /* flush */ }
        }
    }

    $conn->close();

    return [
        'ok' => empty($errors),
        'installed' => $installed,
        'errors' => $errors,
        'message' => count($installed) . ' module(s) installed',
    ];
}

function finalize(array $data): array {
    // Generate .env file
    $envContent = generateEnv($data);
    $envPath = __DIR__ . '/.env';

    if (file_put_contents($envPath, $envContent) === false) {
        return ['ok' => false, 'error' => 'Failed to write .env file'];
    }

    // Create install lock
    $lockData = json_encode([
        'installed_at' => date('c'),
        'version' => '1.0.0',
        'php_version' => PHP_VERSION,
    ], JSON_PRETTY_PRINT);

    $lockPath = __DIR__ . '/storage/.installed';
    if (file_put_contents($lockPath, $lockData) === false) {
        return ['ok' => false, 'error' => 'Failed to create install lock file'];
    }

    return ['ok' => true, 'message' => 'Installation complete'];
}

function generateEnv(array $data): string {
    $host = $data['db_host'] ?? '127.0.0.1';
    $port = $data['db_port'] ?? '3306';
    $dbName = $data['db_name'] ?? 'phoenix';
    $dbUser = $data['db_user'] ?? 'root';
    $dbPass = $data['db_pass'] ?? '';
    $env = $data['environment'] ?? 'development';
    $debug = $env === 'development' ? 'true' : 'false';
    $appUrl = $data['app_url'] ?? 'http://localhost';
    $now = date('Y-m-d H:i:s');

    return <<<ENV
# Phoenix-PHP Configuration
# Generated by install wizard on {$now}

# Application
APP_NAME="Phoenix ERP"
APP_ENV={$env}
APP_DEBUG={$debug}
APP_URL={$appUrl}

# Database
DB_HOST={$host}
DB_PORT={$port}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD="{$dbPass}"

# Users Database (same as primary for single-DB setup)
USERS_DATABASE={$dbName}

# Mail Configuration
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_EMAIL=noreply@example.com
MAIL_FROM_NAME="Phoenix System"

# Cache
CACHE_DRIVER=file
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Security
SESSION_LIFETIME=720
CSRF_TOKEN_EXPIRY=3600
RATE_LIMIT_LOGIN=5
RATE_LIMIT_API=100
ENV;
}

function getInstallConnection(array $data): ?\mysqli {
    $host = $data['db_host'] ?? '127.0.0.1';
    $port = (int)($data['db_port'] ?? 3306);
    $user = $data['db_user'] ?? '';
    $pass = $data['db_pass'] ?? '';
    $name = $data['db_name'] ?? 'phoenix';

    $conn = @new \mysqli($host, $user, $pass, $name, $port);
    if ($conn->connect_error) {
        return null;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// --- Render the wizard HTML ---
$date = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phoenix-PHP Installer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/public/css/phoenix.css">
    <style>
    .installer { max-width: 720px; margin: 0 auto; padding: 40px 20px; }
    .installer-header { text-align: center; margin-bottom: 40px; }
    .installer-header h1 { font-size: 32px; font-weight: 800; }
    .installer-header h1 span { color: var(--accent); }
    .installer-header p { color: var(--text-muted); margin-top: 4px; }
    .step { display: none; }
    .step.active { display: block; }
    .step-indicators { display: flex; justify-content: center; gap: 8px; margin-bottom: 32px; }
    .step-dot { width: 12px; height: 12px; border-radius: 50%; background: var(--border); transition: all 0.3s; }
    .step-dot.complete { background: var(--success); }
    .step-dot.current { background: var(--accent); box-shadow: 0 0 8px var(--accent); }
    .check-list { list-style: none; padding: 0; }
    .check-list li { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
    .check-list li:last-child { border: none; }
    .check-pass { color: var(--success); font-weight: 700; }
    .check-fail { color: var(--danger); font-weight: 700; }
    .check-name { flex: 1; }
    .check-value { color: var(--text-muted); font-size: 13px; }
    .step-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
    .step-desc { color: var(--text-muted); font-size: 14px; margin-bottom: 24px; }
    .step-actions { display: flex; justify-content: space-between; margin-top: 24px; }
    .env-toggle { display: flex; gap: 12px; margin-bottom: 24px; }
    .env-btn { flex: 1; padding: 16px; border: 2px solid var(--border); border-radius: var(--radius-sm); background: transparent; color: var(--text-primary); cursor: pointer; text-align: center; transition: all 0.3s; }
    .env-btn.selected { border-color: var(--accent); background: var(--accent-glow); }
    .env-btn strong { display: block; margin-bottom: 4px; }
    .env-btn small { color: var(--text-muted); font-size: 12px; }
    .module-list { display: grid; gap: 12px; }
    .module-item { display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-sm); cursor: pointer; transition: all 0.3s; }
    .module-item:hover { border-color: var(--accent); }
    .module-item.selected { border-color: var(--accent); background: var(--accent-glow); }
    .module-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--accent); }
    .module-info { flex: 1; }
    .module-info strong { font-size: 15px; display: block; margin-bottom: 2px; }
    .module-info small { color: var(--text-muted); font-size: 12px; }
    .progress-log { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px; font-family: monospace; font-size: 13px; max-height: 200px; overflow-y: auto; color: var(--text-secondary); }
    .progress-log .log-ok { color: var(--success); }
    .progress-log .log-err { color: var(--danger); }
    .success-card { text-align: center; padding: 40px; }
    .success-card h2 { font-size: 28px; color: var(--success); margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="installer">
    <div class="installer-header">
        <div style="font-size:48px;margin-bottom:12px">&#x1F525;</div>
        <h1>Phoenix<span>PHP</span> Installer</h1>
        <p>Set up your ERP &amp; CMMS platform</p>
    </div>

    <div class="step-indicators" id="stepDots"></div>

    <!-- Step 1: Requirements -->
    <div class="step active" id="step-1">
        <div class="step-title">System Requirements</div>
        <div class="step-desc">Checking your server meets the requirements.</div>
        <ul class="check-list" id="reqList"><li>Checking...</li></ul>
        <div class="step-actions">
            <div></div>
            <button class="btn btn-primary" id="btnReqNext" disabled onclick="goStep(2)" style="width:auto">Next &rarr;</button>
        </div>
    </div>

    <!-- Step 2: Environment -->
    <div class="step" id="step-2">
        <div class="step-title">Environment</div>
        <div class="step-desc">Choose your deployment type.</div>
        <div class="env-toggle">
            <button class="env-btn selected" onclick="selectEnv('local', this)">
                <strong>&#x1F4BB; Local / Development</strong>
                <small>localhost, debug enabled</small>
            </button>
            <button class="env-btn" onclick="selectEnv('cloud', this)">
                <strong>&#x2601; Cloud / Production</strong>
                <small>Remote host, debug off</small>
            </button>
        </div>
        <div class="step-actions">
            <button class="btn btn-outline" onclick="goStep(1)" style="width:auto">&larr; Back</button>
            <button class="btn btn-primary" onclick="goStep(3)" style="width:auto">Next &rarr;</button>
        </div>
    </div>

    <!-- Step 3: Database -->
    <div class="step" id="step-3">
        <div class="step-title">Database Configuration</div>
        <div class="step-desc">Enter your MySQL database credentials.</div>
        <div class="form-group">
            <label>Host</label>
            <input type="text" id="db_host" class="form-control" value="127.0.0.1">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label>Port</label>
                <input type="number" id="db_port" class="form-control" value="3306">
            </div>
            <div class="form-group">
                <label>Database Name</label>
                <input type="text" id="db_name" class="form-control" value="phoenix">
            </div>
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" id="db_user" class="form-control" value="root">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" id="db_pass" class="form-control">
        </div>
        <div id="dbStatus" style="margin-bottom:16px"></div>
        <div class="step-actions">
            <button class="btn btn-outline" onclick="goStep(2)" style="width:auto">&larr; Back</button>
            <div style="display:flex;gap:8px">
                <button class="btn btn-outline" onclick="testDb()" style="width:auto">Test Connection</button>
                <button class="btn btn-primary" id="btnDbNext" disabled onclick="goStep(4)" style="width:auto">Next &rarr;</button>
            </div>
        </div>
    </div>

    <!-- Step 4: Install Tables -->
    <div class="step" id="step-4">
        <div class="step-title">Install Core Tables</div>
        <div class="step-desc">Creating database tables and seed data.</div>
        <div class="progress-log" id="installLog">Ready to install...</div>
        <div class="step-actions">
            <button class="btn btn-outline" onclick="goStep(3)" style="width:auto">&larr; Back</button>
            <button class="btn btn-primary" id="btnInstallCore" onclick="installCore()" style="width:auto">Install Tables</button>
        </div>
    </div>

    <!-- Step 5: Admin Account -->
    <div class="step" id="step-5">
        <div class="step-title">Admin Account</div>
        <div class="step-desc">Create the first administrator account.</div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" id="admin_user" class="form-control" value="admin">
        </div>
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" id="admin_name" class="form-control">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" id="admin_email" class="form-control">
        </div>
        <div class="form-group">
            <label>Password (min 8 characters)</label>
            <input type="password" id="admin_pass" class="form-control" minlength="8">
        </div>
        <div id="adminStatus" style="margin-bottom:16px"></div>
        <div class="step-actions">
            <div></div>
            <button class="btn btn-primary" onclick="createAdmin()" style="width:auto">Create Admin &rarr;</button>
        </div>
    </div>

    <!-- Step 6: Modules -->
    <div class="step" id="step-6">
        <div class="step-title">Select Modules</div>
        <div class="step-desc">Choose which modules to install.</div>
        <div class="module-list" id="moduleList">Loading modules...</div>
        <div class="step-actions">
            <div></div>
            <button class="btn btn-primary" onclick="installSelectedModules()" style="width:auto">Install Selected &rarr;</button>
        </div>
    </div>

    <!-- Step 7: Complete -->
    <div class="step" id="step-7">
        <div class="success-card">
            <div style="font-size:64px;margin-bottom:16px">&#x2705;</div>
            <h2>Installation Complete!</h2>
            <p style="color:var(--text-muted);margin-bottom:24px">Your Phoenix ERP system is ready to use.</p>
            <a href="/login" class="btn btn-primary" style="width:auto;display:inline-flex">Go to Login &rarr;</a>
        </div>
    </div>
</div>

<script>
var totalSteps = 7;
var currentStep = 1;
var selectedEnv = 'local';

// Init step dots
(function() {
    var dots = document.getElementById('stepDots');
    for (var i = 1; i <= totalSteps; i++) {
        var d = document.createElement('div');
        d.className = 'step-dot' + (i === 1 ? ' current' : '');
        d.id = 'dot-' + i;
        dots.appendChild(d);
    }
    checkRequirements();
})();

function goStep(n) {
    document.getElementById('step-' + currentStep).classList.remove('active');
    document.getElementById('step-' + n).classList.add('active');
    for (var i = 1; i <= totalSteps; i++) {
        var d = document.getElementById('dot-' + i);
        d.className = 'step-dot';
        if (i < n) d.className += ' complete';
        if (i === n) d.className += ' current';
    }
    currentStep = n;

    if (n === 6) loadModules();
}

function selectEnv(env, btn) {
    selectedEnv = env;
    document.querySelectorAll('.env-btn').forEach(function(b) { b.classList.remove('selected'); });
    btn.classList.add('selected');
    if (env === 'local') {
        document.getElementById('db_host').value = '127.0.0.1';
    } else {
        document.getElementById('db_host').value = '';
        document.getElementById('db_host').focus();
    }
}

function getDbData() {
    return {
        db_host: document.getElementById('db_host').value,
        db_port: document.getElementById('db_port').value,
        db_name: document.getElementById('db_name').value,
        db_user: document.getElementById('db_user').value,
        db_pass: document.getElementById('db_pass').value,
        environment: selectedEnv === 'local' ? 'development' : 'production',
        app_url: window.location.origin
    };
}

function post(action, extra) {
    var data = Object.assign({action: action}, getDbData(), extra || {});
    var form = new FormData();
    for (var k in data) form.append(k, data[k]);
    return fetch('/install.php', {method: 'POST', body: form}).then(function(r) { return r.json(); });
}

function checkRequirements() {
    post('check_requirements').then(function(r) {
        var list = document.getElementById('reqList');
        list.innerHTML = '';
        r.checks.forEach(function(c) {
            var li = document.createElement('li');
            li.innerHTML = '<span class="' + (c.pass ? 'check-pass' : 'check-fail') + '">' + (c.pass ? '&#x2714;' : '&#x2718;') + '</span>'
                + '<span class="check-name">' + c.name + '</span>'
                + '<span class="check-value">' + c.found + '</span>';
            list.appendChild(li);
        });
        document.getElementById('btnReqNext').disabled = !r.all_pass;
    });
}

function testDb() {
    var status = document.getElementById('dbStatus');
    status.innerHTML = '<span style="color:var(--text-muted)">Testing...</span>';
    post('test_db').then(function(r) {
        if (r.ok) {
            if (r.db_exists) {
                status.innerHTML = '<span class="check-pass">&#x2714; Connected — database exists</span>';
                document.getElementById('btnDbNext').disabled = false;
            } else {
                status.innerHTML = '<span style="color:var(--warning)">&#x26A0; Connected but database does not exist</span> <button class="btn btn-sm btn-outline" onclick="createDb()" style="margin-left:8px">Create Database</button>';
            }
        } else {
            status.innerHTML = '<span class="check-fail">&#x2718; ' + r.error + '</span>';
        }
    });
}

function createDb() {
    post('create_db').then(function(r) {
        var status = document.getElementById('dbStatus');
        if (r.ok) {
            status.innerHTML = '<span class="check-pass">&#x2714; Database created</span>';
            document.getElementById('btnDbNext').disabled = false;
        } else {
            status.innerHTML = '<span class="check-fail">&#x2718; ' + r.error + '</span>';
        }
    });
}

function installCore() {
    var log = document.getElementById('installLog');
    var btn = document.getElementById('btnInstallCore');
    btn.disabled = true;
    log.innerHTML = '<div>Installing core tables...</div>';
    post('install_core').then(function(r) {
        if (r.ok) {
            log.innerHTML += '<div class="log-ok">&#x2714; ' + r.message + '</div>';
            setTimeout(function() { goStep(5); }, 800);
        } else {
            log.innerHTML += '<div class="log-err">&#x2718; ' + r.error + '</div>';
            btn.disabled = false;
        }
    });
}

function createAdmin() {
    var status = document.getElementById('adminStatus');
    var pass = document.getElementById('admin_pass').value;
    if (pass.length < 8) {
        status.innerHTML = '<span class="check-fail">Password must be at least 8 characters</span>';
        return;
    }
    status.innerHTML = '<span style="color:var(--text-muted)">Creating account...</span>';
    post('create_admin', {
        admin_user: document.getElementById('admin_user').value,
        admin_name: document.getElementById('admin_name').value,
        admin_email: document.getElementById('admin_email').value,
        admin_pass: pass
    }).then(function(r) {
        if (r.ok) {
            status.innerHTML = '<span class="check-pass">&#x2714; ' + r.message + '</span>';
            setTimeout(function() { goStep(6); }, 600);
        } else {
            status.innerHTML = '<span class="check-fail">&#x2718; ' + r.error + '</span>';
        }
    });
}

function loadModules() {
    post('discover_modules').then(function(r) {
        var list = document.getElementById('moduleList');
        if (!r.modules || r.modules.length === 0) {
            list.innerHTML = '<p style="color:var(--text-muted)">No modules found. You can install them later.</p>';
            return;
        }
        list.innerHTML = '';
        r.modules.forEach(function(m) {
            var div = document.createElement('div');
            div.className = 'module-item';
            div.innerHTML = '<input type="checkbox" checked value="' + m.id + '">'
                + '<div class="module-info"><strong>' + m.name + '</strong><small>' + m.description + '</small></div>'
                + '<span class="badge badge-info">v' + m.version + '</span>';
            div.onclick = function(e) {
                if (e.target.tagName !== 'INPUT') {
                    var cb = div.querySelector('input');
                    cb.checked = !cb.checked;
                }
                div.classList.toggle('selected', div.querySelector('input').checked);
            };
            div.classList.add('selected');
            list.appendChild(div);
        });
    });
}

function installSelectedModules() {
    var checkboxes = document.querySelectorAll('#moduleList input[type="checkbox"]:checked');
    var selected = [];
    checkboxes.forEach(function(cb) { selected.push(cb.value); });

    post('install_modules', {modules: JSON.stringify(selected)}).then(function(r) {
        // Finalize
        post('finalize').then(function(f) {
            if (f.ok) {
                goStep(7);
            } else {
                alert('Finalization error: ' + f.error);
            }
        });
    });
}
</script>
</body>
</html>
