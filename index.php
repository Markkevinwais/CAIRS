<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

if ($u = syncActiveUser()) {
    redirectTo(homeFor($u['role']));
}

$site = setting('site_name', 'CAIRS');
$barangay = setting('barangay_name', 'your barangay');
$resolved = (int) pdo()->query("SELECT COUNT(*) FROM reports WHERE status = 'resolved'")->fetchColumn();
$total = (int) pdo()->query('SELECT COUNT(*) FROM reports')->fetchColumn();

$title = 'Community incident reporting';
require __DIR__ . '/includes/header.php';
?>
<div class="landing">
  <div class="hero-shapes" aria-hidden="true"><span></span><span></span></div>

  <header class="landing-nav">
    <a class="brand" href="<?= BASE_URL ?>/index.php"><span class="brand-mark"><?= icon('shield', 20) ?></span><span><?= e($site) ?></span></a>
    <nav>
      <a class="btn ghost small" href="<?= BASE_URL ?>/login.php">Log in</a>
      <a class="btn small" href="<?= BASE_URL ?>/register.php">Register</a>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-copy">
      <span class="eyebrow-pill">Serving <?= e($barangay) ?></span>
      <h1>Community Assistance <span>and Incident Reporting</span> System</h1>
      <p class="lead">Report community concerns, track incidents, and help build a safer and better community.</p>
      <div class="actions">
        <a class="btn btn-lg" href="<?= BASE_URL ?>/resident/report.php">Report an incident</a>
        <a class="btn ghost btn-lg" href="<?= BASE_URL ?>/login.php">Login</a>
      </div>
      <ul class="hero-stats">
        <li><b><?= $resolved ?></b>incidents resolved</li>
        <li><b><?= $total ?></b>reports received</li>
      </ul>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="mock-stack">
        <div class="mock mock-1"><span class="rc-ref">CAIRS-00128</span><strong>Broken streetlight on Rizal St.</strong><span class="badge s-pending">Pending</span></div>
        <div class="mock mock-2"><span class="rc-ref">CAIRS-00127</span><strong>Flooding near the covered court</strong><span class="badge s-in_progress">In Progress</span></div>
        <div class="mock mock-3"><span class="rc-ref">CAIRS-00126</span><strong>Garbage pickup missed</strong><span class="badge s-resolved">Resolved</span></div>
      </div>
    </div>
  </section>

  <?php $landingAdvisories = renderAnnouncements(2); ?>
  <?php if ($landingAdvisories): ?>
  <section class="landing-sec landing-advisories" aria-label="Community advisories">
    <div class="landing-sec-head">
      <h2 class="sec-title">Community advisories</h2>
      <span class="muted">Latest barangay notices</span>
    </div>
    <?= $landingAdvisories ?>
  </section>
  <?php endif; ?>

  <section class="landing-sec landing-process" aria-label="How it works">
    <div class="landing-sec-head">
      <h2 class="sec-title">How it works</h2>
      <span class="muted">From report submission to verified resolution</span>
    </div>
    <div class="steps">
      <div class="card"><div class="step-no">01</div><h3>Report</h3><p class="muted">Pick a category, describe what happened, and pin the exact location on the map.</p></div>
      <div class="card"><div class="step-no">02</div><h3>Assigned</h3><p class="muted">The barangay admin reviews it and assigns a volunteer to respond.</p></div>
      <div class="card"><div class="step-no">03</div><h3>Resolved</h3><p class="muted">Follow every update until the incident is closed, with notifications along the way.</p></div>
    </div>
  </section>

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
