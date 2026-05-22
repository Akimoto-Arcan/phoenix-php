<?php \Phoenix\View::extends('auth'); $pageTitle = 'Register'; ?>
<div class="login-card">
    <div class="login-logo">
        <span class="logo-icon">&#x1F525;</span>
        <h1>Phoenix<span>PHP</span></h1>
        <p>Create Account</p>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
    <div style="background:rgba(239,68,68,0.15);border:1px solid var(--danger);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:20px;color:var(--danger);font-size:13px">
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); endif; ?>

    <form method="POST" action="/register">
        <?= $csrfField ?? '' ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required autofocus>
        </div>
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Your full name" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirm Password</label>
            <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Repeat password" required>
        </div>
        <button type="submit" class="btn btn-primary">Create Account</button>
    </form>

    <p style="text-align:center;margin-top:24px;font-size:13px;color:var(--text-muted)">
        Already have an account? <a href="/login" style="color:var(--accent)">Sign In</a>
    </p>
</div>
