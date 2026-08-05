<?php
require __DIR__ . '/../includes/config.php';

$user = current_user();
if ($user && $user['role'] === 'admin') redirect(base_url('admin/index.php'));

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if (is_string($result)) {
        $error = $result;
    } elseif (($result['role'] ?? '') !== 'admin') {
        logout_user();
        $error = 'That account does not have admin access.';
    } else {
        redirect(base_url('admin/index.php'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Sign In · <?= h(SITE_NAME) ?></title>
<?php require __DIR__ . '/../includes/theme_init.php'; ?>
<?php require __DIR__ . '/../includes/webfont.php'; ?>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div class="auth-wrap">
  <button class="icon-btn theme-toggle" id="themeToggle" aria-label="Toggle light/dark theme" style="position:fixed;top:20px;right:20px;">
    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
  </button>
  <div class="auth-card">
    <a href="<?= base_url('index.php') ?>" class="brand" style="display:block;margin-bottom:24px;">air<span>.box</span></a>
    <h1>Admin Sign In</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="field">
        <label for="email">Username</label>
        <input type="text" id="email" name="email" required autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-accent btn-block">Sign In to Admin</button>
    </form>
    <div class="auth-alt"><a href="<?= base_url('index.php') ?>">&larr; Back to site</a></div>
  </div>
</div>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
