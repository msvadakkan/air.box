<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_featured'])) {
    $ok = (bool)movies_db()->update($_POST['toggle_featured'], ['featured' => !empty($_POST['value'])]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $title = trim($_POST['title'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $duration = (int)($_POST['duration'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $featured = isset($_POST['featured']);

    if ($title === '') $errors[] = 'Title is required.';

    if (!$errors) {
        $id = bin2hex(random_bytes(8));
        $thumb = handle_upload('thumbnail', ['jpg', 'jpeg', 'png', 'webp', 'svg'], MAX_IMAGE_BYTES, MOVIE_THUMB_DIR, $id, true);
        if (!$thumb['ok']) $errors[] = $thumb['error'];

        $video = handle_upload('video', ['mp4', 'webm'], MAX_VIDEO_BYTES, MOVIE_VIDEO_DIR, $id, false);
        if (!$video['ok']) $errors[] = $video['error'];

        if (!$errors) {
            movies_db()->insert([
                'id' => $id, 'title' => $title, 'description' => $description, 'genre' => $genre,
                'year' => $year, 'duration' => $duration,
                'thumbnail' => $thumb['filename'], 'video' => $video['filename'], 'featured' => $featured,
            ]);
            redirect(base_url('admin/movies.php?added=1'));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleted = delete_movie($_POST['delete_id']);
    redirect(base_url('admin/movies.php?deleted=' . $deleted));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $count = 0;
    foreach ((array)($_POST['ids'] ?? []) as $id) {
        $count += delete_movie($id);
    }
    redirect(base_url('admin/movies.php?deleted=' . $count));
}

function delete_movie(string $id): int
{
    $movie = movies_db()->find($id);
    if (!$movie) return 0;
    if ($movie['thumbnail']) @unlink(MOVIE_THUMB_DIR . '/' . basename($movie['thumbnail']));
    if ($movie['video']) @unlink(MOVIE_VIDEO_DIR . '/' . basename($movie['video']));
    movies_db()->delete($id);
    return 1;
}

$movies = movies_db()->all();
usort($movies, function ($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });

$pageTitle = 'Movies';
$activeAdminNav = 'movies';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if (isset($_GET['added'])): ?><div class="alert alert-success">Movie uploaded.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): $n = (int)$_GET['deleted']; ?>
  <div class="alert alert-success"><?= $n === 1 ? 'Movie deleted.' : "$n movies deleted." ?></div>
<?php endif; ?>

<div class="panel" id="upload">
  <h2>Upload a movie</h2>
  <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="upload-form">
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="field"><label>Title</label><input name="title" required value="<?= h($_POST['title'] ?? '') ?>"></div>
      <div class="field"><label>Genre</label><input name="genre" value="<?= h($_POST['genre'] ?? '') ?>"></div>
      <div class="field"><label>Year</label><input type="number" name="year" value="<?= h($_POST['year'] ?? '') ?>"></div>
      <div class="field"><label>Duration (minutes)</label><input type="number" name="duration" value="<?= h($_POST['duration'] ?? '') ?>"></div>
      <div class="field field-full"><label>Description</label><textarea name="description"><?= h($_POST['description'] ?? '') ?></textarea></div>
      <div class="field"><label>Thumbnail image</label><input type="file" name="thumbnail" accept="image/*" required></div>
      <div class="field"><label>Video file (mp4/webm, optional, up to 2GB)</label><input type="file" name="video" accept="video/mp4,video/webm"></div>
      <div class="field field-full"><label><input type="checkbox" name="featured" style="width:auto;"> Feature on homepage hero</label></div>
    </div>
    <div class="upload-progress" hidden>
      <div class="upload-progress-track"><div class="upload-progress-bar"></div></div>
      <span class="upload-progress-text">Uploading… 0%</span>
    </div>
    <button class="btn btn-accent" type="submit">Upload Movie</button>
  </form>
</div>

<div class="panel">
  <h2>All movies (<?= count($movies) ?>)</h2>
  <form method="post" class="bulk-form">
    <div class="table-toolbar">
      <button type="submit" name="bulk_delete" value="1" class="btn btn-danger btn-sm bulk-delete-btn" disabled
              onclick="return confirm('Delete the selected movies? This cannot be undone.');">
        Delete Selected (<span class="bulk-count">0</span>)
      </button>
    </div>
    <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th><input type="checkbox" class="select-all" <?= $movies ? '' : 'disabled' ?>></th><th>Thumb</th><th>Title</th><th>Genre</th><th>Year</th><th>Video</th><th>Featured</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($movies as $m): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= h($m['id']) ?>" class="row-check"></td>
          <td><img src="<?= h(image_url('movie', $m['id'])) ?>" alt=""></td>
          <td><?= h($m['title']) ?></td>
          <td><?= h($m['genre']) ?></td>
          <td><?= h($m['year']) ?></td>
          <td><?php if ($m['video']): ?><span class="file-status-yes">Uploaded</span><?php else: ?><span class="file-status-no">Missing</span><?php endif; ?></td>
          <td><input type="checkbox" class="featured-toggle" data-id="<?= h($m['id']) ?>" <?= !empty($m['featured']) ? 'checked' : '' ?>></td>
          <td>
            <button type="submit" name="delete_id" value="<?= h($m['id']) ?>" class="btn btn-danger btn-sm"
                    onclick="return confirm('Delete this movie?');">Delete</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
