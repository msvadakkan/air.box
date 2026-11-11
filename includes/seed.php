<?php
/**
 * First-run bootstrap: creates a default admin account and a handful of demo
 * catalog entries so the UI isn't empty. Runs at most once, guarded by a
 * marker file, so deleting demo content later doesn't bring it back.
 */
function seed_demo_data(): void
{
    $marker = DATA_DIR . '/.seeded';
    if (file_exists($marker)) return;

    seed_admin_user();
    seed_demo_movies();
    seed_demo_books();
    seed_demo_music();

    file_put_contents($marker, date('c'));
}

function seed_admin_user(): void
{
    $db = users_db();
    if ($db->where(function ($u) { return $u['role'] === 'admin'; })) return;

    $db->insert([
        'name' => 'Administrator',
        'email' => 'admin',
        'password' => password_hash('Admin123', PASSWORD_DEFAULT),
        'role' => 'admin',
    ]);
}

/**
 * Writes a placeholder cover image (SVG gradient, no embedded text) and
 * returns its filename. Deliberately textless: the same file gets reused
 * as both a portrait card thumbnail and a cropped/stretched wide hero
 * backdrop, and any baked-in title text would get distorted or duplicated
 * behind the real HTML title depending on how it's cropped. The title is
 * always rendered as real HTML wherever this image appears.
 */
function seed_placeholder_image(string $dir, string $id, string $colorA, string $colorB): string
{
    $filename = $id . '.svg';
    $path = $dir . '/' . $filename;
    if (file_exists($path)) return $filename;

    $gradientId = 'g' . substr(md5($id), 0, 8);

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="900" viewBox="0 0 600 900">
  <defs>
    <linearGradient id="{$gradientId}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$colorA}"/>
      <stop offset="100%" stop-color="{$colorB}"/>
    </linearGradient>
    <radialGradient id="{$gradientId}v" cx="50%" cy="35%" r="75%">
      <stop offset="0%" stop-color="#ffffff" stop-opacity="0.14"/>
      <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="600" height="900" fill="url(#{$gradientId})"/>
  <rect width="600" height="900" fill="url(#{$gradientId}v)"/>
</svg>
SVG;

    file_put_contents($path, $svg);
    return $filename;
}

/** Generates a short sine-wave tone as a valid WAV file (no external deps). */
function seed_placeholder_audio(string $dir, string $id, float $freq = 440.0, float $seconds = 6.0): string
{
    $filename = $id . '.wav';
    $path = $dir . '/' . $filename;
    if (file_exists($path)) return $filename;

    $sampleRate = 22050;
    $numSamples = (int)($sampleRate * $seconds);
    $amplitude = 8000;

    $dataBytes = $numSamples * 2; // 16-bit mono
    $header = 'RIFF' . pack('V', 36 + $dataBytes) . 'WAVE';
    $header .= 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1)
        . pack('V', $sampleRate) . pack('V', $sampleRate * 2)
        . pack('v', 2) . pack('v', 16);
    $header .= 'data' . pack('V', $dataBytes);

    $fh = fopen($path, 'wb');
    fwrite($fh, $header);
    for ($i = 0; $i < $numSamples; $i++) {
        // gentle fade in/out to avoid clicks
        $fade = min(1.0, min($i, $numSamples - $i) / ($sampleRate * 0.2));
        $sample = (int)($amplitude * $fade * sin(2 * M_PI * $freq * $i / $sampleRate));
        fwrite($fh, pack('v', $sample & 0xFFFF));
    }
    fclose($fh);
    return $filename;
}

/** Hand-writes a minimal single-page PDF containing a title & body text (no libraries). */
function seed_placeholder_pdf(string $dir, string $id, string $title, string $body): string
{
    $filename = $id . '.pdf';
    $path = $dir . '/' . $filename;
    if (file_exists($path)) return $filename;

    $escape = function ($s) { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s); };
    $titleEsc = $escape($title);

    $lines = explode("\n", wordwrap($body, 78));
    $bodyStream = "BT /F1 12 Tf 72 760 Td 16 TL\n";
    $bodyStream .= "(" . $escape($title) . ") Tj\n0 -30 TD\n";
    foreach ($lines as $line) {
        $bodyStream .= "(" . $escape($line) . ") Tj T*\n";
    }
    $bodyStream .= "ET";

    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /MediaBox [0 0 612 792] /Contents 4 0 R >>";
    $objects[4] = "<< /Length " . strlen($bodyStream) . " >>\nstream\n{$bodyStream}\nendstream";
    $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
    }
    $xrefStart = strlen($pdf);
    $count = count($objects) + 1;
    $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";

    file_put_contents($path, $pdf);
    return $filename;
}

function seed_demo_movies(): void
{
    $db = new JsonDB('movies');
    if ($db->all()) return;

    $demo = [
        ['title' => 'Crimson Horizon', 'genre' => 'Sci-Fi', 'year' => 2024, 'duration' => 128, 'colors' => ['#8e0e00', '#1f1c18'], 'featured' => true,
            'description' => 'A stranded crew races against a dying star to reach the last known human colony.'],
        ['title' => 'Neon Alley', 'genre' => 'Thriller', 'year' => 2023, 'duration' => 104, 'colors' => ['#0f2027', '#2c5364'], 'featured' => false,
            'description' => 'A detective chases a hacker through a city that never turns its lights off.'],
        ['title' => 'The Quiet Orchard', 'genre' => 'Drama', 'year' => 2022, 'duration' => 96, 'colors' => ['#3a1c71', '#d76d77'], 'featured' => false,
            'description' => 'Three siblings return to their childhood home to decide its fate — and their own.'],
        ['title' => 'Ridgeline', 'genre' => 'Adventure', 'year' => 2025, 'duration' => 118, 'colors' => ['#134e5e', '#71b280'], 'featured' => false,
            'description' => 'A mountain guide leads a doomed expedition through a range that doesn\'t want visitors.'],
    ];

    foreach ($demo as $m) {
        $id = bin2hex(random_bytes(8));
        $thumb = seed_placeholder_image(MOVIE_THUMB_DIR, $id, $m['colors'][0], $m['colors'][1]);
        $db->insert([
            'id' => $id,
            'title' => $m['title'],
            'description' => $m['description'],
            'genre' => $m['genre'],
            'year' => $m['year'],
            'duration' => $m['duration'],
            'thumbnail' => $thumb,
            'video' => null, // uploaded later via admin panel
            'featured' => $m['featured'],
        ]);
    }
}

function seed_demo_books(): void
{
    $db = new JsonDB('books');
    if ($db->all()) return;

    $demo = [
        ['title' => 'Letters from the Ionosphere', 'author' => 'M. Calder', 'genre' => 'Sci-Fi', 'colors' => ['#1a2980', '#26d0ce'],
            'description' => 'A satellite technician exchanges cryptic letters with something that shouldn\'t be listening.'],
        ['title' => 'The Cartographer\'s Debt', 'author' => 'R. Osei', 'genre' => 'Mystery', 'colors' => ['#232526', '#414345'],
            'description' => 'An old map leads a mapmaker\'s daughter to a debt her father never mentioned.'],
        ['title' => 'Small Gods of the Pantry', 'author' => 'J. Whitfield', 'genre' => 'Fantasy', 'colors' => ['#654ea3', '#eaafc8'],
            'description' => 'A cozy fantasy about the minor household spirits that keep a family\'s kitchen running.'],
    ];

    foreach ($demo as $b) {
        $id = bin2hex(random_bytes(8));
        $cover = seed_placeholder_image(BOOK_COVER_DIR, $id, $b['colors'][0], $b['colors'][1]);
        $pdf = seed_placeholder_pdf(BOOK_PDF_DIR, $id, $b['title'],
            "By {$b['author']}\n\n{$b['description']}\n\nThis is a placeholder sample generated automatically so the reader works out of the box. Replace it with the real manuscript from the admin panel.");
        $db->insert([
            'id' => $id,
            'title' => $b['title'],
            'author' => $b['author'],
            'description' => $b['description'],
            'genre' => $b['genre'],
            'cover' => $cover,
            'pdf' => $pdf,
        ]);
    }
}

function seed_demo_music(): void
{
    $db = new JsonDB('music');
    if ($db->all()) return;

    $demo = [
        ['title' => 'Midnight Drive', 'artist' => 'Aeon Loop', 'album' => 'Static Bloom', 'genre' => 'Synthwave', 'freq' => 329.63, 'colors' => ['#ee0979', '#ff6a00']],
        ['title' => 'Paper Boats', 'artist' => 'Wren & Co.', 'album' => 'Low Tide', 'genre' => 'Acoustic', 'freq' => 261.63, 'colors' => ['#43cea2', '#185a9d']],
        ['title' => 'Glass Static', 'artist' => 'Halide', 'album' => 'Halide', 'genre' => 'Electronic', 'freq' => 392.00, 'colors' => ['#485563', '#29323c']],
    ];

    foreach ($demo as $t) {
        $id = bin2hex(random_bytes(8));
        $cover = seed_placeholder_image(MUSIC_COVER_DIR, $id, $t['colors'][0], $t['colors'][1]);
        $audio = seed_placeholder_audio(MUSIC_AUDIO_DIR, $id, $t['freq']);
        $db->insert([
            'id' => $id,
            'title' => $t['title'],
            'artist' => $t['artist'],
            'album' => $t['album'],
            'genre' => $t['genre'],
            'cover' => $cover,
            'audio' => $audio,
        ]);
    }
}
