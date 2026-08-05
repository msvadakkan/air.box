<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$movies = movies_db()->all();
$books = books_db()->all();
$music = music_db()->all();
$users = users_db()->all();

$moviesWithFile = count(array_filter($movies, fn($m) => !empty($m['video'])));
$booksWithFile = count(array_filter($books, fn($b) => !empty($b['pdf'])));
$musicWithFile = count(array_filter($music, fn($m) => !empty($m['audio'])));

$pageTitle = 'Dashboard';
$activeAdminNav = 'dashboard';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="stat-grid">
  <div class="stat-card"><div class="num"><?= count($movies) ?></div><div class="label">Movies (<?= $moviesWithFile ?> with video)</div></div>
  <div class="stat-card"><div class="num"><?= count($books) ?></div><div class="label">Books (<?= $booksWithFile ?> with PDF)</div></div>
  <div class="stat-card"><div class="num"><?= count($music) ?></div><div class="label">Tracks (<?= $musicWithFile ?> with audio)</div></div>
  <div class="stat-card"><div class="num"><?= count($users) ?></div><div class="label">Registered users</div></div>
</div>

<div class="panel">
  <h2>Quick actions</h2>
  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <a href="<?= base_url('admin/movies.php') ?>#upload" class="btn btn-accent btn-sm">+ Upload Movie</a>
    <a href="<?= base_url('admin/books.php') ?>#upload" class="btn btn-accent btn-sm">+ Upload Book</a>
    <a href="<?= base_url('admin/music.php') ?>#upload" class="btn btn-accent btn-sm">+ Upload Track</a>
  </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
