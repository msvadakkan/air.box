<?php
function h(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function base_url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function format_duration(int $minutes): string
{
    if ($minutes <= 0) return '';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
}

function file_ext(string $filename): string
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/** Stream a local file to the browser with HTTP Range support (seeking) without exposing its real path. */
function stream_file_with_range(string $path, string $mime): void
{
    if (!is_file($path)) {
        http_response_code(404);
        exit('File not found');
    }

    $size = filesize($path);
    $start = 0;
    $end = $size - 1;

    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    header('X-Content-Type-Options: nosniff');

    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') $start = (int)$m[1];
            if ($m[2] !== '') $end = (int)$m[2];
            $end = min($end, $size - 1);

            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
    }

    header('Content-Length: ' . ($end - $start + 1));

    $fh = fopen($path, 'rb');
    fseek($fh, $start);
    $bytesLeft = $end - $start + 1;
    $chunk = 8192;
    while ($bytesLeft > 0 && !feof($fh)) {
        $read = min($chunk, $bytesLeft);
        echo fread($fh, $read);
        flush();
        $bytesLeft -= $read;
    }
    fclose($fh);
    exit;
}

function mime_for_ext(string $ext): string
{
    return match ($ext) {
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'pdf' => 'application/pdf',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };
}

function movies_db(): JsonDB
{
    static $db = null;
    if ($db === null) $db = new JsonDB('movies');
    return $db;
}

function books_db(): JsonDB
{
    static $db = null;
    if ($db === null) $db = new JsonDB('books');
    return $db;
}

function music_db(): JsonDB
{
    static $db = null;
    if ($db === null) $db = new JsonDB('music');
    return $db;
}

/** Public URL for a piece of poster/cover art, served through image.php so storage/ can stay locked down. */
function image_url(string $type, string $id): string
{
    return base_url('image.php?type=' . urlencode($type) . '&id=' . urlencode($id));
}

function item_link(string $type, string $id): string
{
    $page = match ($type) {
        'movie' => 'watch.php',
        'book' => 'book.php',
        'music' => 'listen.php',
        default => 'index.php',
    };
    return base_url($page . '?id=' . urlencode($id));
}

function item_action_label(string $type): string
{
    return match ($type) {
        'movie' => 'Play',
        'book' => 'Read',
        'music' => 'Listen',
        default => 'Open',
    };
}

/**
 * Validates and moves an uploaded file into $destDir as "$idPrefix.$ext".
 * Returns ['ok' => true, 'filename' => ...] or ['ok' => false, 'error' => ...].
 */
function handle_upload(string $fieldName, array $allowedExt, int $maxBytes, string $destDir, string $idPrefix, bool $required = true): array
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $required ? ['ok' => false, 'error' => 'Please choose a file to upload.'] : ['ok' => true, 'filename' => null];
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (error code ' . $file['error'] . ').'];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File is too large. Max ' . round($maxBytes / 1024 / 1024) . 'MB.'];
    }

    $ext = file_ext($file['name']);
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'error' => 'Unsupported file type ".' . $ext . '". Allowed: ' . implode(', ', $allowedExt)];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    $filename = $idPrefix . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], rtrim($destDir, '/') . '/' . $filename)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
    }

    return ['ok' => true, 'filename' => $filename];
}

function render_card(array $item, string $type): void
{
    $title = $item['title'];
    $meta = match ($type) {
        'movie' => trim(($item['year'] ?? '') . ' · ' . ($item['genre'] ?? '')),
        'book' => $item['author'] ?? '',
        'music' => $item['artist'] ?? '',
        default => '',
    };
    $desc = $item['description'] ?? '';
    $img = image_url($type, $item['id']);
    $link = item_link($type, $item['id']);
    $action = item_action_label($type);
    $square = $type === 'music';
    ?>
    <a href="<?= h($link) ?>" class="card<?= $square ? ' card-square' : '' ?>" data-modal
       data-title="<?= h($title) ?>" data-meta="<?= h($meta) ?>" data-desc="<?= h($desc) ?>"
       data-image="<?= h($img) ?>" data-link="<?= h($link) ?>" data-action="<?= h($action) ?>">
        <img class="card-media protected fade-in" src="<?= h($img) ?>" alt="<?= h($title) ?>" loading="lazy">
        <div class="card-play-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </div>
        <div class="card-overlay">
            <div class="t"><?= h($title) ?></div>
            <div class="m"><?= h($meta) ?></div>
        </div>
    </a>
    <?php
}
