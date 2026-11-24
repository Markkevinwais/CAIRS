<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('resident');

$r = fetchReport((int) ($_GET['id'] ?? 0));
if (!$r || (int) $r['resident_id'] !== (int) $user['id']) {
    setFlash('Report not found.', 'error');
    redirectTo('resident/my_reports.php');
}

$title = refNo($r['id']);
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1><?= e($r['title']) ?></h1>
    <p class="page-head-sub"><?= refNo($r['id']) ?> &middot; Filed <?= e(fmtDate($r['created_at'])) ?></p>
  </div>
  <div class="page-actions">
    <?= badge($r['status']) ?>
    <button type="button" class="btn ghost small noprint" data-print>Print / Save as PDF</button>
  </div>
</div>
<div class="two">
  <div class="card">
    <dl class="info">
      <dt>Reference</dt><dd><?= refNo($r['id']) ?></dd>
      <dt>Category</dt><dd><?= e($r['category']) ?></dd>
      <dt>Location</dt><dd><?= e($r['location']) ?></dd>
      <dt>Priority</dt><dd><?= prio($r['priority']) ?></dd>
      <dt>Assigned to</dt><dd><?= !empty($r['volunteer_name']) ? e($r['volunteer_name']) : '<span class="muted">Not assigned yet</span>' ?></dd>
      <dt>Filed</dt><dd><?= e(fmtDate($r['created_at'])) ?></dd>
    </dl>
    <h3 style="margin-top:20px">Description</h3>
    <p><?= nl2br_safe($r['description']) ?></p>
    <?php if (!empty($r['photo'])): ?><img class="photo" src="<?= BASE_URL ?>/assets/uploads/<?= e($r['photo']) ?>" alt="Photo attached to the report"><?php endif; ?>
    <?= renderMapView($r) ?>
  </div>
  <div class="card"><h2>Progress</h2><?= renderTimeline((int) $r['id']) ?></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
