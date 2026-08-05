<?php
/** @var string $pageTitle */
/** @var string $activeAdminNav */
$__admin = require_admin();
$pageTitle = $pageTitle ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?> · Admin · <?= h(SITE_NAME) ?></title>
<?php require __DIR__ . '/../../includes/theme_init.php'; ?>
<?php require __DIR__ . '/../../includes/webfont.php'; ?>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div class="admin-topbar">
  <button class="icon-btn" id="adminSidebarToggle" aria-label="Toggle menu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <a href="<?= base_url('admin/index.php') ?>" class="brand">air<span>.box</span></a>
  <button class="icon-btn theme-toggle" id="themeToggle" aria-label="Toggle light/dark theme">
    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
  </button>
</div>
<div class="admin-backdrop" id="adminBackdrop"></div>
<div class="admin-shell">
  <aside class="admin-sidebar" id="adminSidebar">
    <a href="<?= base_url('admin/index.php') ?>" class="brand">air<span>.box</span></a>
    <ul class="admin-nav">
      <li><a href="<?= base_url('admin/index.php') ?>" class="<?= ($activeAdminNav ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
      <li><a href="<?= base_url('admin/movies.php') ?>" class="<?= ($activeAdminNav ?? '') === 'movies' ? 'active' : '' ?>">Movies</a></li>
      <li><a href="<?= base_url('admin/books.php') ?>" class="<?= ($activeAdminNav ?? '') === 'books' ? 'active' : '' ?>">Books</a></li>
      <li><a href="<?= base_url('admin/music.php') ?>" class="<?= ($activeAdminNav ?? '') === 'music' ? 'active' : '' ?>">Music</a></li>
      <li><a href="<?= base_url('admin/settings.php') ?>" class="<?= ($activeAdminNav ?? '') === 'settings' ? 'active' : '' ?>">Settings</a></li>
      <li style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px;">
        <a href="<?= base_url('index.php') ?>">&larr; Back to site</a>
      </li>
      <li><a href="<?= base_url('admin/logout.php') ?>">Sign out</a></li>
    </ul>
  </aside>
  <main class="admin-main">
    <div class="admin-header">
      <h1><?= h($pageTitle) ?></h1>
      <div style="display:flex;align-items:center;gap:14px;">
        <div style="color:var(--text-dim);font-size:14px;">Signed in as <?= h($__admin['name']) ?></div>
        <button class="icon-btn theme-toggle theme-toggle-desktop" id="themeToggleDesktop" aria-label="Toggle light/dark theme">
          <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
          <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        </button>
      </div>
    </div>
