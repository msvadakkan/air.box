<?php
/** @var string $pageTitle */
/** @var string $activeNav */
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?> · <?= h(SITE_NAME) ?></title>
<?php require __DIR__ . '/theme_init.php'; ?>
<?php require __DIR__ . '/webfont.php'; ?>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<nav class="navbar" id="navbar">
  <div class="nav-left">
    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <a href="<?= base_url('index.php') ?>" class="brand">air<span>.box</span></a>
    <ul class="nav-links" id="navLinks">
      <li><a href="<?= base_url('index.php') ?>" class="<?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">Home</a></li>
      <li><a href="<?= base_url('index.php?type=movie') ?>" class="<?= ($activeNav ?? '') === 'movie' ? 'active' : '' ?>">Movies</a></li>
      <li><a href="<?= base_url('index.php?type=book') ?>" class="<?= ($activeNav ?? '') === 'book' ? 'active' : '' ?>">Books</a></li>
      <li><a href="<?= base_url('index.php?type=music') ?>" class="<?= ($activeNav ?? '') === 'music' ? 'active' : '' ?>">Music</a></li>
      <li><a href="<?= base_url('about.php') ?>" class="<?= ($activeNav ?? '') === 'about' ? 'active' : '' ?>">About</a></li>
    </ul>
  </div>
  <div class="nav-right">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="siteSearch" placeholder="Titles, artists, books&hellip;" autocomplete="off">
    </div>
    <button class="icon-btn theme-toggle" id="themeToggle" aria-label="Toggle light/dark theme">
      <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
      <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
    </button>
  </div>
</nav>
