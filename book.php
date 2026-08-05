<?php
require __DIR__ . '/includes/config.php';

$book = books_db()->find($_GET['id'] ?? '');
if (!$book) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<div class="player-page"><div class="no-file"><h2>Book not found</h2></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $book['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="player-page">
  <div class="player-info" style="padding-top:24px;">
    <h1><?= h($book['title']) ?></h1>
    <div class="meta">by <?= h($book['author']) ?> · <?= h($book['genre'] ?? '') ?></div>
  </div>
  <?php if ($book['pdf']): ?>
    <iframe class="pdf-frame" src="<?= h(base_url('book_stream.php?id=' . urlencode($book['id']))) ?>#toolbar=0&navpanes=0"></iframe>
  <?php else: ?>
    <div class="no-file">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h11a3 3 0 013 3v13H7a3 3 0 01-3-3V4z"/><path d="M8 8h7M8 12h7"/></svg>
      <h2>No PDF uploaded yet</h2>
      <p>This title is in the catalog, but the manuscript hasn't been added from the admin panel.</p>
    </div>
  <?php endif; ?>
  <div class="player-info">
    <p><?= h($book['description']) ?></p>
  </div>
</div>
<script src="<?= base_url('assets/js/player.js') ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
