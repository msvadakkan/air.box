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

    if (!$errors) {
        $id = bin2hex(random_bytes(8));
        $cover = handle_upload('cover', ['jpg', 'jpeg', 'png', 'webp', 'svg'], MAX_IMAGE_BYTES, BOOK_COVER_DIR, $id, true);
        if (!$cover['ok']) $errors[] = $cover['error'];

        $pdf = handle_upload('pdf', ['pdf'], MAX_PDF_BYTES, BOOK_PDF_DIR, $id, false);
        if (!$pdf['ok']) $errors[] = $pdf['error'];

        if (!$errors) {
            books_db()->insert([
                'id' => $id, 'title' => $title, 'author' => $author, 'genre' => $genre, 'description' => $description,
                'cover' => $cover['filename'], 'pdf' => $pdf['filename'],
            ]);
            redirect(base_url('admin/books.php?added=1'));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = $_POST['id'] ?? '';
    $book = books_db()->find($id);
    if ($book) {
        if ($book['cover']) @unlink(BOOK_COVER_DIR . '/' . basename($book['cover']));
        if ($book['pdf']) @unlink(BOOK_PDF_DIR . '/' . basename($book['pdf']));
        books_db()->delete($id);
    }
    redirect(base_url('admin/books.php?deleted=1'));
}

$books = books_db()->all();
usort($books, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$pageTitle = 'Books';
$activeAdminNav = 'books';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if (isset($_GET['added'])): ?><div class="alert alert-success">Book uploaded.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Book deleted.</div><?php endif; ?>

<div class="panel" id="upload">
  <h2>Upload a book</h2>
  <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="upload-form">
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="field"><label>Title</label><input name="title" required value="<?= h($_POST['title'] ?? '') ?>"></div>
      <div class="field"><label>Author</label><input name="author" value="<?= h($_POST['author'] ?? '') ?>"></div>
      <div class="field"><label>Genre</label><input name="genre" value="<?= h($_POST['genre'] ?? '') ?>"></div>
      <div class="field field-full"><label>Description</label><textarea name="description"><?= h($_POST['description'] ?? '') ?></textarea></div>
      <div class="field"><label>Cover image</label><input type="file" name="cover" accept="image/*" required></div>
      <div class="field"><label>PDF file (optional)</label><input type="file" name="pdf" accept="application/pdf"></div>
    </div>
    <div class="upload-progress" hidden>
      <div class="upload-progress-track"><div class="upload-progress-bar"></div></div>
      <span class="upload-progress-text">Uploading… 0%</span>
    </div>
    <button class="btn btn-accent" type="submit">Upload Book</button>
  </form>
</div>

<div class="panel">
  <h2>All books (<?= count($books) ?>)</h2>
  <div class="table-scroll">
  <table class="data-table">
    <thead><tr><th>Cover</th><th>Title</th><th>Author</th><th>Genre</th><th>PDF</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($books as $b): ?>
      <tr>
        <td><img src="<?= h(image_url('book', $b['id'])) ?>" alt=""></td>
        <td><?= h($b['title']) ?></td>
        <td><?= h($b['author']) ?></td>
        <td><?= h($b['genre']) ?></td>
        <td><?php if ($b['pdf']): ?><span class="file-status-yes">Uploaded</span><?php else: ?><span class="file-status-no">Missing</span><?php endif; ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Delete this book?');" style="display:inline;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= h($b['id']) ?>">
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
