<?php
// Core paths & app-wide setup. Loaded first by every entry point.

define('ROOT_DIR', dirname(__DIR__));
define('DATA_DIR', ROOT_DIR . '/data');
define('STORAGE_DIR', ROOT_DIR . '/storage');

define('MOVIE_VIDEO_DIR', STORAGE_DIR . '/movies/videos');
define('MOVIE_THUMB_DIR', STORAGE_DIR . '/movies/thumbs');
define('BOOK_PDF_DIR', STORAGE_DIR . '/books/pdfs');
define('BOOK_COVER_DIR', STORAGE_DIR . '/books/covers');
define('MUSIC_AUDIO_DIR', STORAGE_DIR . '/music/audio');
define('MUSIC_COVER_DIR', STORAGE_DIR . '/music/covers');
define('BOT_IMAGE_DIR', STORAGE_DIR . '/bot/images');     // Rotana Bot photos

define('SITE_NAME', 'air.box');

// Upload limits
define('MAX_VIDEO_BYTES', 2000 * 1024 * 1024); // ~2GB (stays inside 32-bit int)
define('MAX_AUDIO_BYTES', 50 * 1024 * 1024);  // 50MB
define('MAX_PDF_BYTES', 50 * 1024 * 1024);    // 50MB
define('MAX_IMAGE_BYTES', 8 * 1024 * 1024);   // 8MB

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

foreach ([
    DATA_DIR, MOVIE_VIDEO_DIR, MOVIE_THUMB_DIR, BOOK_PDF_DIR,
    BOOK_COVER_DIR, MUSIC_AUDIO_DIR, MUSIC_COVER_DIR, BOT_IMAGE_DIR,
] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bot.php';
require_once __DIR__ . '/seed.php';

seed_demo_data();
