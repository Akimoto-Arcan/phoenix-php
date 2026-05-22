<?php \Phoenix\View::extends('auth'); $pageTitle = 'Sign In'; ?>
<div class="login-card">
    <div class="login-logo">
        <span class="logo-icon">&#x1F525;</span>
        <h1>Phoenix<span>PHP</span></h1>
        <p>ERP &amp; CMMS Platform</p>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
    <div style="background:rgba(239,68,68,0.15);border:1px solid var(--danger);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:20px;color:var(--danger);font-size:13px">
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div style="background:rgba(16,185,129,0.15);border:1px solid var(--success);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:20px;color:var(--success);font-size:13px">
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); endif; ?>

    <form method="POST" action="/login">
        <?= $csrfField ?? '' ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary">Sign In</button>
    </form>

    <p style="text-align:center;margin-top:24px;font-size:13px;color:var(--text-muted)">
        Don't have an account? <a href="/register" style="color:var(--accent)">Register</a>
    </p>
</div>
