<?php

use Phoenix\Router;
use Phoenix\Auth;
use Phoenix\CSRF;
use Phoenix\Database;

// Public routes
Router::get('/login', 'auth.login');

Router::post('/login', function () {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['flash_error'] = 'Username and password are required.';
        redirect('/login');
    }

    $result = Auth::login($username, $password);
    if ($result === true) {
        redirect('/dashboard');
    }

    $_SESSION['flash_error'] = $result;
    redirect('/login');
});

Router::get('/register', 'auth.register');

Router::post('/register', function () {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($username) || empty($password) || empty($fullName)) {
        $_SESSION['flash_error'] = 'Username, full name, and password are required.';
        redirect('/register');
    }

    if ($password !== $confirm) {
        $_SESSION['flash_error'] = 'Passwords do not match.';
        redirect('/register');
    }

    if (strlen($password) < 8) {
        $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
        redirect('/register');
    }

    try {
        $db = Database::pdo('users');

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $_SESSION['flash_error'] = 'Username already taken.';
            redirect('/register');
        }

        $stmt = $db->prepare("INSERT INTO users (username, full_name, email, password, role, `groups`, approved) VALUES (?, ?, ?, ?, 'Operator', 'Operator', 0)");
        $stmt->execute([$username, $fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);

        $_SESSION['flash_success'] = 'Account created successfully. Awaiting admin approval.';
        redirect('/login');
    } catch (\Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        $_SESSION['flash_error'] = 'Registration failed. Please try again.';
        redirect('/register');
    }
});

Router::get('/logout', function () {
    Auth::logout();
    redirect('/login');
});

// Authenticated routes
Router::group(['middleware' => ['auth']], function () {
    Router::get('/', function () { redirect('/dashboard'); });
    Router::get('/dashboard', 'dashboard.index');
    Router::get('/settings', 'settings.index');
    Router::get('/docs/{page...}', function ($params) {
        $page = $params['page'] ?? 'getting-started';
        echo \Phoenix\View::render('docs.index', ['page' => $page]);
    });
    Router::get('/docs', function () {
        echo \Phoenix\View::render('docs.index', ['page' => 'getting-started']);
    });
});
