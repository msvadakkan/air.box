<?php
/**
 * Router for PHP's built-in dev server (`php -S localhost:8000 router.php`).
 *
 * Unlike Apache, the built-in server does NOT honor .htaccess, so without
 * this router a direct request to /data/users.json or /storage/movies/videos/x.mp4
 * would be served as a raw static file — leaking password hashes and bypassing
 * the login-gated stream.php/book_stream.php/image.php endpoints entirely.
 * This blocks both paths; everything else is served normally.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/(data|storage)(/|$)#', $path)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden');
}

return false;
