<?php \Phoenix\View::extends('auth'); $pageTitle = '403 Forbidden'; ?>
<div class="login-card" style="text-align:center">
    <div style="font-size:72px;margin-bottom:16px;opacity:0.5">403</div>
    <h2 style="margin-bottom:8px">Access Denied</h2>
    <p style="color:var(--text-muted);margin-bottom:24px;font-size:14px">You don't have permission to access this resource.</p>
    <a href="/dashboard" class="btn btn-primary" style="width:auto;display:inline-flex">Go to Dashboard</a>
</div>
