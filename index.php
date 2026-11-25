<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

if ($u = syncActiveUser()) {
    redirectTo(homeFor($u['role']));
}

$site = setting('site_name', 'CAIRS');
$barangay = setting('barangay_name', 'your barangay');
$resolved = (int) pdo()->query("SELECT COUNT(*) FROM reports WHERE status = 'resolved'")->fetchColumn();
$active = (int) pdo()->query("SELECT COUNT(*) FROM reports WHERE status IN ('pending', 'assigned', 'in_progress')")->fetchColumn();
$total = (int) pdo()->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$landingAdvisories = renderAnnouncements(2);
$landingHotlines = renderHotlines();

$title = 'Community incident reporting';
require __DIR__ . '/includes/header.php';
?>
<div class="landing">
  <div class="hero-shapes" aria-hidden="true">
    <div class="landing-grid-layer"></div>
    <span class="mesh-blob blob-1"></span>
    <span class="mesh-blob blob-2"></span>
    <span class="mesh-blob blob-3"></span>
  </div>

  <header class="landing-nav">
    <div class="landing-nav-inner">
      <a class="brand" href="<?= BASE_URL ?>/index.php">
        <span class="brand-mark"><?= icon('shield', 20) ?></span>
        <span class="brand-name"><?= e($site) ?></span>
      </a>
      <nav class="landing-links" aria-label="Page sections">
        <?php if ($landingAdvisories): ?><a href="#advisories">Advisories</a><?php endif; ?>
        <a href="#how-it-works">How it works</a>
        <?php if ($landingHotlines): ?><a href="#hotlines">Hotlines</a><?php endif; ?>
      </nav>
      <div class="landing-nav-actions">
        <a class="landing-login-link" href="<?= BASE_URL ?>/login.php">Log in</a>
        <a class="btn small" href="<?= BASE_URL ?>/register.php">Register</a>
      </div>
    </div>
  </header>

  <section class="hero">
    <div class="hero-copy">
      <p class="hero-kicker">Serving <?= e($barangay) ?></p>
      <h1>Report concerns fast. <span>Track every response.</span></h1>
      <p class="lead">Community Assistance and Incident Reporting System for <?= e($barangay) ?>&mdash;pin exact locations on a map, follow responder updates, and reach emergency help without delay.</p>
      <div class="actions">
        <a class="btn btn-lg" href="<?= BASE_URL ?>/resident/report.php">Report an incident</a>
        <a class="btn ghost btn-lg" href="<?= $landingHotlines ? '#hotlines' : '#how-it-works' ?>"><?= $landingHotlines ? 'Emergency hotlines' : 'See how it works' ?></a>
      </div>
      <ul class="hero-stats">
        <li><b><?= $resolved ?></b><span><?= $resolved === 1 ? 'incident resolved' : 'incidents resolved' ?></span></li>
        <li><b><?= $active ?></b><span><?= $active === 1 ? 'active response' : 'active responses' ?></span></li>
        <li><b><?= $total ?></b><span><?= $total === 1 ? 'total report' : 'total reports' ?></span></li>
      </ul>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="dispatch-card">
        <div class="dispatch-head">
          <strong>Dispatch overview</strong>
          <span class="muted">Example workflow</span>
        </div>
        <div class="dispatch-list">
          <div class="dispatch-row">
            <div class="dispatch-main">
              <span class="dispatch-meta">CAIRS-00128 &middot; Purok 5</span>
              <strong>Broken streetlight on Rizal St.</strong>
            </div>
            <span class="badge s-pending">Pending</span>
          </div>
          <div class="dispatch-row">
            <div class="dispatch-main">
              <span class="dispatch-meta">CAIRS-00127 &middot; Purok 3</span>
              <strong>Drainage clearing near covered court</strong>
            </div>
            <span class="badge s-in_progress">In Progress</span>
          </div>
          <div class="dispatch-row">
            <div class="dispatch-main">
              <span class="dispatch-meta">CAIRS-00126 &middot; Alley 4</span>
              <strong>Missed garbage pickup</strong>
            </div>
            <span class="badge s-resolved">Resolved</span>
          </div>
        </div>
        <div class="dispatch-foot">
          <span><?= icon('pin', 15) ?><span>GPS pin mapping</span></span>
          <span><?= icon('bell', 15) ?><span>Status alerts</span></span>
        </div>
      </div>
    </div>
  </section>

  <?php if ($landingAdvisories): ?>
  <section class="landing-sec landing-advisories" id="advisories" aria-label="Community advisories">
    <div class="landing-sec-head">
      <h2 class="sec-title">Community advisories</h2>
      <span class="muted">Latest barangay notices</span>
    </div>
    <?= $landingAdvisories ?>
  </section>
  <?php endif; ?>

  <section class="landing-sec landing-process" id="how-it-works" aria-label="How it works">
    <div class="landing-sec-head">
      <h2 class="sec-title">How it works</h2>
      <span class="muted">From report submission to verified resolution</span>
    </div>
    <div class="steps">
      <article class="card step-card">
        <div class="step-no">01</div>
        <div class="step-body">
          <h3>Submit a report</h3>
          <p class="muted">Pick a category, describe what happened, and drop a pin at the exact street or purok.</p>
        </div>
      </article>
      <article class="card step-card">
        <div class="step-no">02</div>
        <div class="step-body">
          <h3>Assign a responder</h3>
          <p class="muted">Barangay administrators review the concern, set priority, and dispatch a volunteer.</p>
        </div>
      </article>
      <article class="card step-card">
        <div class="step-no">03</div>
        <div class="step-body">
          <h3>Track until resolved</h3>
          <p class="muted">Follow every timeline update and receive status notifications until the case is closed.</p>
        </div>
      </article>
    </div>
  </section>

  <?php if ($landingHotlines): ?>
  <section class="landing-sec landing-hotlines" id="hotlines" aria-label="Emergency hotlines">
    <div class="landing-sec-head">
      <h2 class="sec-title">Emergency hotlines</h2>
      <span class="muted">Direct lines for <?= e($barangay) ?></span>
    </div>
    <?= $landingHotlines ?>
  </section>
  <?php endif; ?>

  <footer class="landing-foot muted">
    <span>&copy; <?= date('Y') ?> <?= e($site) ?> &middot; <?= e($barangay) ?></span>
    <nav class="landing-foot-links" aria-label="Footer links">
      <a href="<?= BASE_URL ?>/resident/report.php">Report an incident</a>
      <a href="<?= BASE_URL ?>/login.php">Log in</a>
      <a href="<?= BASE_URL ?>/register.php">Register</a>
    </nav>
  </footer>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
