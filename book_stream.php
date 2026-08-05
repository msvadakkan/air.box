<?php
// PDF endpoint. Open to everyone (no login needed to read) but serves
// inline (not as attachment) so the browser's built-in viewer opens it
// instead of prompting a download.
require __DIR__ . '/includes/config.php';

$id = $_GET['id'] ?? '';
$book = books_db()->find($id);
$filename = $book['pdf'] ?? null;

if (!$book || !$filename) {
    http_response_code(404);
    exit('No file uploaded for this book yet.');
}

$path = BOOK_PDF_DIR . '/' . basename($filename);
stream_file_with_range($path, 'application/pdf');
