<?php
require __DIR__ . '/../includes/config.php';
$admin = require_admin();

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = change_password(
        $admin['id'],
        $_POST['current_password'] ?? '',
        $_POST['new_password'] ?? '',
        $_POST['confirm_password'] ?? ''
    );
    if ($result === true) {
        $success = true;
    } else {
        $error = $result;
    }
}

$pageTitle = 'Settings';
$activeAdminNav = 'settings';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="panel" style="max-width:480px;">
  <h2>Change password</h2>
  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success">Password updated.</div><?php endif; ?>
  <form method="post">
    <div class="field">
      <label for="current_password">Current password</label>
      <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
    </div>
    <div class="field">
      <label for="new_password">New password</label>
      <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
    </div>
    <div class="field">
      <label for="confirm_password">Confirm new password</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-accent">Update Password</button>
  </form>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
