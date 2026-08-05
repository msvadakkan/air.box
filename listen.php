<?php
require __DIR__ . '/includes/config.php';

$track = music_db()->find($_GET['id'] ?? '');
if (!$track) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<div class="player-page"><div class="no-file"><h2>Track not found</h2></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $track['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="player-page">
  <div class="audio-page">
    <img class="audio-cover protected fade-in" src="<?= h(image_url('music', $track['id'])) ?>" alt="<?= h($track['title']) ?>">
    <div class="audio-details">
      <div class="hero-badge">Now Playing</div>
      <h1><?= h($track['title']) ?></h1>
      <div class="meta"><?= h($track['artist']) ?><?= !empty($track['album']) ? ' · ' . h($track['album']) : '' ?><?= !empty($track['genre']) ? ' · ' . h($track['genre']) : '' ?></div>

      <?php if ($track['audio']): ?>
        <audio controls autoplay controlsList="nodownload">
          <source src="<?= h(base_url('stream.php?type=music&id=' . urlencode($track['id']))) ?>">
        </audio>
      <?php else: ?>
        <div class="no-file">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="17" r="3"/><path d="M11 17V5l9-2v12"/><circle cx="17" cy="15" r="3"/></svg>
          <h2>No audio uploaded yet</h2>
          <p>This track is in the catalog, but the audio file hasn't been added from the admin panel.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="<?= base_url('assets/js/player.js') ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
