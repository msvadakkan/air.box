<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') $errors[] = 'Title is required.';
    if (!empty($_POST['ajax']) && $errors) json_respond(['ok' => false, 'error' => implode(' ', $errors)]);

    if (!$errors) {
        $id = bin2hex(random_bytes(8));
        $cover = handle_upload('cover', ['jpg', 'jpeg', 'jfif', 'png', 'webp', 'avif', 'svg'], MAX_IMAGE_BYTES, BOOK_COVER_DIR, $id, false);
        if (!$cover['ok']) $errors[] = 'Cover image: ' . $cover['error'];

        $pdf = handle_upload('pdf', ['pdf'], MAX_PDF_BYTES, BOOK_PDF_DIR, $id, false);
        if (!$pdf['ok']) $errors[] = 'PDF file: ' . $pdf['error'];

        if ($errors) {
            if (!empty($cover['filename'])) @unlink(BOOK_COVER_DIR . '/' . $cover['filename']);
            if (!empty($pdf['filename'])) @unlink(BOOK_PDF_DIR . '/' . $pdf['filename']);
        }

        if (!$errors) {
            books_db()->insert([
                'id' => $id, 'title' => $title, 'author' => $author, 'genre' => $genre, 'description' => $description,
                'cover' => $cover['filename'], 'pdf' => $pdf['filename'],
            ]);
            if (!empty($_POST['ajax'])) json_respond(['ok' => true, 'id' => $id, 'title' => $title]);
            redirect(base_url('admin/books.php?added=1'));
        }
    }

    if (!empty($_POST['ajax']) && $errors) json_respond(['ok' => false, 'error' => implode(' ', $errors)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleted = delete_book($_POST['delete_id']);
    redirect(base_url('admin/books.php?deleted=' . $deleted));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $count = 0;
    foreach ((array)($_POST['ids'] ?? []) as $id) {
        $count += delete_book($id);
    }
    redirect(base_url('admin/books.php?deleted=' . $count));
}

function delete_book(string $id): int
{
    $book = books_db()->find($id);
    if (!$book) return 0;
    if ($book['cover']) @unlink(BOOK_COVER_DIR . '/' . basename($book['cover']));
    if ($book['pdf']) @unlink(BOOK_PDF_DIR . '/' . basename($book['pdf']));
    books_db()->delete($id);
    return 1;
}

$books = books_db()->all();
usort($books, function ($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });

$pageTitle = 'Books';
$activeAdminNav = 'books';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if (isset($_GET['added'])): ?>
<div class="alert alert-success" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <strong>Book uploaded.</strong>
  <span style="display:flex;gap:8px;flex-wrap:wrap;">
    <a class="btn btn-sm btn-accent" href="#upload">+ Upload another book</a>
    <a class="btn btn-sm" href="<?= base_url('admin/movies.php#upload') ?>">+ Add movie</a>
    <a class="btn btn-sm" href="<?= base_url('admin/music.php#upload') ?>">+ Add music</a>
  </span>
</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): $n = (int)$_GET['deleted']; ?>
  <div class="alert alert-success"><?= $n === 1 ? 'Book deleted.' : "$n books deleted." ?></div>
<?php endif; ?>

<div class="panel" id="upload">
  <h2>Upload books</h2>
  <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="upload-form">
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="field"><label>Title</label><input name="title" required value="<?= h($_POST['title'] ?? '') ?>"></div>
      <div class="field"><label>Author</label><input name="author" value="<?= h($_POST['author'] ?? '') ?>"></div>
      <div class="field"><label>Genre</label><input name="genre" value="<?= h($_POST['genre'] ?? '') ?>"></div>
      <div class="field field-full"><label>Description</label><textarea name="description"><?= h($_POST['description'] ?? '') ?></textarea></div>
      <div class="field"><label>Cover image (optional)</label><input type="file" name="cover" accept="image/*"></div>
      <div class="field"><label>PDF files — select several to queue (optional)</label><input type="file" name="pdf" accept="application/pdf" multiple data-queue-field="pdf"></div>
    </div>
    <div class="upload-progress" hidden>
      <div class="upload-progress-track"><div class="upload-progress-bar"></div></div>
      <span class="upload-progress-text">Uploading… 0%</span>
    </div>
    <div class="batch-queue" id="batchQueue" hidden></div>
    <div class="batch-summary" id="batchSummary"></div>
    <button class="btn btn-accent" type="submit">Upload Book</button>
  </form>
</div>

<div class="panel">
  <h2>All books (<?= count($books) ?>)</h2>
  <form method="post" class="bulk-form">
    <div class="table-toolbar">
      <button type="submit" name="bulk_delete" value="1" class="btn btn-danger btn-sm bulk-delete-btn" disabled
              onclick="return confirm('Delete the selected books? This cannot be undone.');">
        Delete Selected (<span class="bulk-count">0</span>)
      </button>
    </div>
    <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th><input type="checkbox" class="select-all" <?= $books ? '' : 'disabled' ?>></th><th>Cover</th><th>Title</th><th>Author</th><th>Genre</th><th>PDF</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($books as $b): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= h($b['id']) ?>" class="row-check"></td>
          <td><img src="<?= h(image_url('book', $b['id'])) ?>" alt=""></td>
          <td><?= h($b['title']) ?></td>
          <td><?= h($b['author']) ?></td>
          <td><?= h($b['genre']) ?></td>
          <td><?php if ($b['pdf']): ?><span class="file-status-yes">Uploaded</span><?php else: ?><span class="file-status-no">Missing</span><?php endif; ?></td>
          <td>
            <button type="submit" name="delete_id" value="<?= h($b['id']) ?>" class="btn btn-danger btn-sm"
                    onclick="return confirm('Delete this book?');">Delete</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
