<?php
// Media endpoint for movies & music. Open to everyone (no login needed to
// watch/listen) but never reveals the real filesystem path — the browser
// only ever sees stream.php?type=...&id=..., not a direct link to storage/.
require __DIR__ . '/includes/config.php';

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

$map = [
    'movie' => ['db' => movies_db(), 'field' => 'video', 'dir' => MOVIE_VIDEO_DIR],
    'music' => ['db' => music_db(), 'field' => 'audio', 'dir' => MUSIC_AUDIO_DIR],
];

if (!isset($map[$type])) {
    http_response_code(404);
    exit('Not found');
}

$conf = $map[$type];
$doc = $conf['db']->find($id);
$filename = $doc[$conf['field']] ?? null;

if (!$doc || !$filename) {
    http_response_code(404);
    exit('No file uploaded for this item yet.');
}

$path = $conf['dir'] . '/' . basename($filename);
stream_file_with_range($path, mime_for_ext(file_ext($path)));
