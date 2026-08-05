<?php
require __DIR__ . '/includes/config.php';

$pageTitle = 'About Rotana Jet';
$activeNav = 'about';
require __DIR__ . '/includes/header.php';
?>

<div class="about-page">
  <section class="about-hero">
    <div class="about-hero-inner">
      <div class="hero-badge">Powered By</div>
      <h1 class="about-title">Rotana Jet</h1>
      <p class="about-lede">
        air.box is brought to you by Rotana Jet — an Abu Dhabi-based executive
        aviation company delivering worldwide private jet charter solutions
        since 2010.
      </p>
      <a href="https://rotanajet.com/" target="_blank" rel="noopener" class="btn btn-primary">
        Visit rotanajet.com
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M7 7h10v10"/></svg>
      </a>
    </div>
  </section>

  <section class="container about-section">
    <div class="stat-grid">
      <div class="stat-card"><div class="num">2010</div><div class="label">Founded</div></div>
      <div class="stat-card"><div class="num">Abu Dhabi</div><div class="label">Headquarters</div></div>
      <div class="stat-card"><div class="num">2</div><div class="label">Aircraft in fleet</div></div>
      <div class="stat-card"><div class="num">2,500m²</div><div class="label">Maintenance facility</div></div>
    </div>

    <div class="panel">
      <h2>Our story</h2>
      <p>
        Rotana Jet Aviation was established in late 2010 by His Highness Dr. Sheikh
        Ahmed bin Saif Al Nahyan, operating out of Al Bateen Executive Airport —
        just 10km from central Abu Dhabi. The company delivers worldwide executive
        private jet charter solutions to private individuals, corporate groups,
        sports teams, business executives, and government officials, with safety,
        quality, and customer service as its core benchmarks.
      </p>
    </div>

    <div class="panel">
      <h2>Fleet</h2>
      <div class="about-fleet">
        <div class="about-fleet-item">
          <h3>Gulfstream G450</h3>
          <p>The primary aircraft in the fleet, accommodating up to 14 passengers with intercontinental range.</p>
        </div>
        <div class="about-fleet-item">
          <h3>Airbus A319 Corporate Jet</h3>
          <p>Added to the fleet in 2012 and configured for 50 passengers, capable of non-stop flights from Abu Dhabi to London, Singapore, Johannesburg, or Casablanca.</p>
        </div>
      </div>
    </div>

    <div class="panel">
      <h2>Technical operations</h2>
      <p>
        Beyond charter services, Rotana Jet runs a dedicated Technical Operations
        division providing maintenance and component support for its aircraft,
        based out of a 2,500m² facility at Al Bateen Airport. The fleet operates
        under regulatory oversight from the UAE's General Civil Aviation Authority
        (GCAA), and the company's growth is aligned with the Abu Dhabi government's
        vision, serving the broader GCC and MENA markets.
      </p>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
