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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = $_POST['id'] ?? '';
    $track = music_db()->find($id);
    if ($track) {
        if ($track['cover']) @unlink(MUSIC_COVER_DIR . '/' . basename($track['cover']));
        if ($track['audio']) @unlink(MUSIC_AUDIO_DIR . '/' . basename($track['audio']));
        music_db()->delete($id);
    }
    redirect(base_url('admin/music.php?deleted=1'));
}

$music = music_db()->all();
usort($music, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$pageTitle = 'Music';
$activeAdminNav = 'music';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if (isset($_GET['added'])): ?><div class="alert alert-success">Track uploaded.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Track deleted.</div><?php endif; ?>

<div class="panel" id="upload">
  <h2>Upload a track</h2>
  <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="field"><label>Title</label><input name="title" required value="<?= h($_POST['title'] ?? '') ?>"></div>
      <div class="field"><label>Artist</label><input name="artist" value="<?= h($_POST['artist'] ?? '') ?>"></div>
      <div class="field"><label>Album</label><input name="album" value="<?= h($_POST['album'] ?? '') ?>"></div>
      <div class="field"><label>Genre</label><input name="genre" value="<?= h($_POST['genre'] ?? '') ?>"></div>
      <div class="field"><label>Cover image</label><input type="file" name="cover" accept="image/*" required></div>
      <div class="field"><label>Audio file (mp3/wav/ogg, optional)</label><input type="file" name="audio" accept="audio/mpeg,audio/wav,audio/ogg"></div>
    </div>
    <button class="btn btn-accent" type="submit">Upload Track</button>
  </form>
</div>

<div class="panel">
  <h2>All tracks (<?= count($music) ?>)</h2>
  <div class="table-scroll">
  <table class="data-table">
    <thead><tr><th>Cover</th><th>Title</th><th>Artist</th><th>Album</th><th>Audio</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($music as $t): ?>
      <tr>
        <td><img src="<?= h(image_url('music', $t['id'])) ?>" alt=""></td>
        <td><?= h($t['title']) ?></td>
        <td><?= h($t['artist']) ?></td>
        <td><?= h($t['album']) ?></td>
        <td><?php if ($t['audio']): ?><span class="file-status-yes">Uploaded</span><?php else: ?><span class="file-status-no">Missing</span><?php endif; ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Delete this track?');" style="display:inline;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= h($t['id']) ?>">
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
