<?php
require __DIR__ . '/includes/config.php';

$type = $_GET['type'] ?? '';
if (!in_array($type, ['movie', 'book', 'music'], true)) $type = '';

$movies = movies_db()->all();
$books = books_db()->all();
$music = music_db()->all();

switch ($type) {
    case 'movie': $pageTitle = 'Movies'; break;
    case 'book': $pageTitle = 'Books'; break;
    case 'music': $pageTitle = 'Music'; break;
    default: $pageTitle = 'Home';
}
$activeNav = $type ?: 'home';

require __DIR__ . '/includes/header.php';
?>

<?php if ($type === ''):
    $featuredPool = array_values(array_filter($movies, function ($m) { return !empty($m['featured']); })) ?: $movies;
    $slides = array_map(function ($m) {
        return [
            'title' => $m['title'],
            'badge' => 'Featured Film',
            'desc' => $m['description'],
            'meta' => trim(($m['year'] ?? '') . ' · ' . ($m['genre'] ?? '')),
            'image' => image_url('movie', $m['id']),
            'link' => item_link('movie', $m['id']),
        ];
    }, array_slice($featuredPool, 0, 5));
    $hero = $slides[0] ?? null;
    ?>

    <?php if ($hero): ?>
    <section class="hero" data-rotate='<?= h(json_encode($slides)) ?>' style="background-image:url('<?= h($hero['image']) ?>')">
      <div class="hero-content">
        <div class="hero-badge"><?= h($hero['badge']) ?></div>
        <h1 class="hero-title"><?= h($hero['title']) ?></h1>
        <div class="hero-meta"><span class="tag"><?= h($hero['meta']) ?></span></div>
        <p class="hero-desc"><?= h($hero['desc']) ?></p>
        <div class="hero-actions">
          <a href="<?= h($hero['link']) ?>" class="btn btn-primary hero-play">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#000"><path d="M8 5v14l11-7z"/></svg> Play
          </a>
          <a href="#" class="btn btn-secondary hero-info" data-modal
             data-title="<?= h($hero['title']) ?>" data-meta="<?= h($hero['meta']) ?>" data-desc="<?= h($hero['desc']) ?>"
             data-image="<?= h($hero['image']) ?>" data-link="<?= h($hero['link']) ?>" data-action="Play">
            More Info
          </a>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php
      $rows = [
        ['label' => 'Movies', 'type' => 'movie', 'items' => $movies],
        ['label' => 'Books', 'type' => 'book', 'items' => $books],
        ['label' => 'Music', 'type' => 'music', 'items' => $music],
      ];
      foreach ($rows as $row):
    ?>
      <section class="row-section">
        <div class="row-header">
          <h2><?= h($row['label']) ?></h2>
          <a href="<?= base_url('index.php?type=' . $row['type']) ?>">See all</a>
        </div>
        <?php if ($row['items']): ?>
        <div class="row-wrap">
          <button class="row-arrow left" aria-label="Scroll left">&#8249;</button>
          <div class="row-track">
            <?php foreach ($row['items'] as $item) render_card($item, $row['type']); ?>
          </div>
          <button class="row-arrow right" aria-label="Scroll right">&#8250;</button>
        </div>
        <?php else: ?>
          <div class="empty-row">Nothing here yet.</div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

<?php else:
    switch ($type) {
        case 'movie': $items = $movies; break;
        case 'book': $items = $books; break;
        default: $items = $music;
    }
    ?>
    <div class="tabs">
      <a href="<?= base_url('index.php') ?>" class="tab">All</a>
      <a href="<?= base_url('index.php?type=movie') ?>" class="tab <?= $type === 'movie' ? 'active' : '' ?>">Movies</a>
      <a href="<?= base_url('index.php?type=book') ?>" class="tab <?= $type === 'book' ? 'active' : '' ?>">Books</a>
      <a href="<?= base_url('index.php?type=music') ?>" class="tab <?= $type === 'music' ? 'active' : '' ?>">Music</a>
    </div>
    <section class="row-section">
      <div class="row-header"><h2><?= h($pageTitle) ?></h2></div>
      <?php if ($items): ?>
        <div class="row-track" style="flex-wrap:wrap;overflow:visible;">
          <?php foreach ($items as $item) render_card($item, $type); ?>
        </div>
      <?php else: ?>
        <div class="empty-row">Nothing here yet. Check back soon.</div>
      <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
