<?php
require __DIR__ . '/includes/config.php';

$movie = movies_db()->find($_GET['id'] ?? '');
if (!$movie) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<div class="player-page"><div class="no-file"><h2>Movie not found</h2></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $movie['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="player-page">
  <?php if ($movie['video']): ?>
    <div class="player-frame">
      <video controls autoplay controlsList="nodownload noremoteplayback" disablePictureInPicture
             poster="<?= h(image_url('movie', $movie['id'])) ?>">
        <source src="<?= h(base_url('stream.php?type=movie&id=' . urlencode($movie['id']))) ?>">
        Your browser does not support video playback.
      </video>
    </div>
  <?php else: ?>
    <div class="no-file">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9l5 3-5 3z"/></svg>
      <h2>No video uploaded yet</h2>
      <p>This title is in the catalog, but the video file hasn't been added from the admin panel.</p>
    </div>
  <?php endif; ?>
  <div class="player-info">
    <h1><?= h($movie['title']) ?></h1>
    <div class="meta"><?= h(trim(($movie['year'] ?? '') . ' · ' . ($movie['genre'] ?? '') . ' · ' . format_duration((int)($movie['duration'] ?? 0)))) ?></div>
    <p><?= h($movie['description']) ?></p>
  </div>
</div>
<script src="<?= base_url('assets/js/player.js') ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
