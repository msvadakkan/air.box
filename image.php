<?php
// Public poster/cover art endpoint. Posters aren't sensitive, so no login is
// required — but routing through PHP means storage/ never has to be
// web-accessible directly, keeping the actual media/PDF files locked down.
require __DIR__ . '/includes/config.php';

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

$map = [
    'movie' => ['db' => movies_db(), 'field' => 'thumbnail', 'dir' => MOVIE_THUMB_DIR],
    'book' => ['db' => books_db(), 'field' => 'cover', 'dir' => BOOK_COVER_DIR],
    'music' => ['db' => music_db(), 'field' => 'cover', 'dir' => MUSIC_COVER_DIR],
];

if (!isset($map[$type])) {
    http_response_code(404);
    exit;
}

$conf = $map[$type];
$doc = $conf['db']->find($id);
$filename = $doc[$conf['field']] ?? null;

if (!$doc || !$filename) {
    header('Content-Type: image/svg+xml');
    readfile(__DIR__ . '/assets/img/placeholder.svg');
    exit;
}

$path = $conf['dir'] . '/' . basename($filename);
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . mime_for_ext(file_ext($path)));
header('Cache-Control: public, max-age=86400');
readfile($path);
