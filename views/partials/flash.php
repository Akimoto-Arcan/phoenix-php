<?php if (!empty($_SESSION['flash_error'])): ?>
<div style="background:rgba(239,68,68,0.15);border:1px solid var(--danger);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;color:var(--danger);font-size:14px">
    <?= htmlspecialchars($_SESSION['flash_error']) ?>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
<div style="background:rgba(16,185,129,0.15);border:1px solid var(--success);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;color:var(--success);font-size:14px">
    <?= htmlspecialchars($_SESSION['flash_success']) ?>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>
