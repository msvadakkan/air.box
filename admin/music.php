<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $title = trim($_POST['title'] ?? '');
    $artist = trim($_POST['artist'] ?? '');
    $album = trim($_POST['album'] ?? '');
    $genre = trim($_POST['genre'] ?? '');

    if ($title === '') $errors[] = 'Title is required.';

    if (!$errors) {
        $id = bin2hex(random_bytes(8));
        $cover = handle_upload('cover', ['jpg', 'jpeg', 'png', 'webp', 'svg'], MAX_IMAGE_BYTES, MUSIC_COVER_DIR, $id, true);
        if (!$cover['ok']) $errors[] = $cover['error'];

        $audio = handle_upload('audio', ['mp3', 'wav', 'ogg'], MAX_AUDIO_BYTES, MUSIC_AUDIO_DIR, $id, false);
        if (!$audio['ok']) $errors[] = $audio['error'];

        if (!$errors) {
            music_db()->insert([
                'id' => $id, 'title' => $title, 'artist' => $artist, 'album' => $album, 'genre' => $genre,
                'cover' => $cover['filename'], 'audio' => $audio['filename'],
            ]);
            redirect(base_url('admin/music.php?added=1'));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleted = delete_track($_POST['delete_id']);
    redirect(base_url('admin/music.php?deleted=' . $deleted));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $count = 0;
    foreach ((array)($_POST['ids'] ?? []) as $id) {
        $count += delete_track($id);
    }
    redirect(base_url('admin/music.php?deleted=' . $count));
}

function delete_track(string $id): int
{
    $track = music_db()->find($id);
    if (!$track) return 0;
    if ($track['cover']) @unlink(MUSIC_COVER_DIR . '/' . basename($track['cover']));
    if ($track['audio']) @unlink(MUSIC_AUDIO_DIR . '/' . basename($track['audio']));
    music_db()->delete($id);
    return 1;
}

$music = music_db()->all();
usort($music, function ($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });

$pageTitle = 'Music';
$activeAdminNav = 'music';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if (isset($_GET['added'])): ?><div class="alert alert-success">Track uploaded.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): $n = (int)$_GET['deleted']; ?>
  <div class="alert alert-success"><?= $n === 1 ? 'Track deleted.' : "$n tracks deleted." ?></div>
<?php endif; ?>

<div class="panel" id="upload">
  <h2>Upload a track</h2>
  <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="upload-form">
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="field"><label>Title</label><input name="title" required value="<?= h($_POST['title'] ?? '') ?>"></div>
      <div class="field"><label>Artist</label><input name="artist" value="<?= h($_POST['artist'] ?? '') ?>"></div>
      <div class="field"><label>Album</label><input name="album" value="<?= h($_POST['album'] ?? '') ?>"></div>
      <div class="field"><label>Genre</label><input name="genre" value="<?= h($_POST['genre'] ?? '') ?>"></div>
      <div class="field"><label>Cover image</label><input type="file" name="cover" accept="image/*" required></div>
      <div class="field"><label>Audio file (mp3/wav/ogg, optional)</label><input type="file" name="audio" accept="audio/mpeg,audio/wav,audio/ogg"></div>
    </div>
    <div class="upload-progress" hidden>
      <div class="upload-progress-track"><div class="upload-progress-bar"></div></div>
      <span class="upload-progress-text">Uploading… 0%</span>
    </div>
    <button class="btn btn-accent" type="submit">Upload Track</button>
  </form>
</div>

<div class="panel">
  <h2>All tracks (<?= count($music) ?>)</h2>
  <form method="post" class="bulk-form">
    <div class="table-toolbar">
      <button type="submit" name="bulk_delete" value="1" class="btn btn-danger btn-sm bulk-delete-btn" disabled
              onclick="return confirm('Delete the selected tracks? This cannot be undone.');">
        Delete Selected (<span class="bulk-count">0</span>)
      </button>
    </div>
    <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th><input type="checkbox" class="select-all" <?= $music ? '' : 'disabled' ?>></th><th>Cover</th><th>Title</th><th>Artist</th><th>Album</th><th>Audio</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($music as $t): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= h($t['id']) ?>" class="row-check"></td>
          <td><img src="<?= h(image_url('music', $t['id'])) ?>" alt=""></td>
          <td><?= h($t['title']) ?></td>
          <td><?= h($t['artist']) ?></td>
          <td><?= h($t['album']) ?></td>
          <td><?php if ($t['audio']): ?><span class="file-status-yes">Uploaded</span><?php else: ?><span class="file-status-no">Missing</span><?php endif; ?></td>
          <td>
            <button type="submit" name="delete_id" value="<?= h($t['id']) ?>" class="btn btn-danger btn-sm"
                    onclick="return confirm('Delete this track?');">Delete</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
