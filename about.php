<?php
require __DIR__ . '/includes/config.php';

// Live library counts. Empty libraries are skipped so the page never
// advertises "0 Books" before anything has been uploaded.
$stats = [];
foreach ([
    [count(movies_db()->all()), 'Movie', 'Movies'],
    [count(books_db()->all()), 'Book', 'Books'],
    [count(music_db()->all()), 'Track', 'Tracks'],
] as $stat) {
    list($n, $one, $many) = $stat;
    if ($n > 0) {
        $stats[] = [number_format($n), $n === 1 ? $one : $many];
    }
}

$pageTitle = 'About';
$activeNav = 'about';
require __DIR__ . '/includes/header.php';
?>

<div class="about-page">
  <section class="about-hero">
    <div class="about-hero-inner">
      <div class="hero-badge">About</div>
      <h1 class="about-title"><?= h(SITE_NAME) ?></h1>
      <p class="about-lede">
        Movies, books, and music in one place. Watch, read, and listen right
        in your browser, free, with no account or sign-up needed.
      </p>
      <a href="<?= base_url('index.php') ?>" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        Start browsing
      </a>
    </div>
  </section>

  <section class="container about-section">
    <div class="stat-grid">
      <?php foreach ($stats as $stat): ?>
        <div class="stat-card"><div class="num"><?= h($stat[0]) ?></div><div class="label"><?= h($stat[1]) ?></div></div>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <h2>About Rotana Jet</h2>
      <p>
        Rotana Jet Aviation provides worldwide executive private jet charter
        solutions for private individuals, corporate groups, sports teams,
        business executives and senior government officials. Its long-range
        fleet is privately owned, registered in the United Arab Emirates and
        operates under GCAA regulatory oversight.
      </p>
      <div class="about-fleet">
        <div class="about-fleet-item">
          <h3>Founded</h3>
          <p>Established in late 2010 by His Highness Dr. Sheikh Ahmed bin Saif Al-Nahyan.</p>
        </div>
        <div class="about-fleet-item">
          <h3>Base</h3>
          <p>Al Bateen Airport, Abu Dhabi, 10 km from the city centre — convenient for clients across Dubai, Abu Dhabi, the GCC and MENA region.</p>
        </div>
        <div class="about-fleet-item">
          <h3>Fleet</h3>
          <p>The Gulfstream G450 carries up to 14 passengers with true intercontinental range. In 2012 it was joined by a bespoke 50-seat Airbus A319 Corporate Jet, able to fly non-stop from Abu Dhabi to London, Singapore, Johannesburg or Casablanca.</p>
        </div>
        <div class="about-fleet-item">
          <h3>Technical operations</h3>
          <p>Aircraft maintenance and component support for the G450 and A319-CJ from 2,500 m² facilities at Al Bateen Airport.</p>
        </div>
      </div>
      <p>
        In the air and on the ground, Rotana Jet continues to set service
        benchmarks in safety, quality and customer service, supported by a
        management team with executive experience at airlines worldwide.
      </p>
    </div>

    <div class="panel">
      <h2>What's on <?= h(SITE_NAME) ?></h2>
      <div class="about-fleet">
        <div class="about-fleet-item">
          <h3>Movies</h3>
          <p>Stream films straight from the library and skip to any point without waiting for the whole file to load.</p>
        </div>
        <div class="about-fleet-item">
          <h3>Books</h3>
          <p>Read books page by page in the built-in reader, with nothing to install.</p>
        </div>
        <div class="about-fleet-item">
          <h3>Music</h3>
          <p>Play tracks from the music collection directly in your browser.</p>
        </div>
        <div class="about-fleet-item">
          <h3>Search</h3>
          <p>Find any title, author, or artist from the search bar at the top of every page.</p>
        </div>
      </div>
    </div>

    <div class="panel">
      <h2>Made for streaming</h2>
      <p>
        Everything on <?= h(SITE_NAME) ?> plays and opens in the browser.
        Movies, books, and music are streamed from the server as you use them
        and aren't offered as downloads, so there's nothing to save or manage
        on your device.
      </p>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
