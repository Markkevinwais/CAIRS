<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('volunteer');
$uid = (int) $user['id'];

$id = (int) ($_GET['id'] ?? 0);
$r = fetchReport($id);
if (!$r || (int) $r['volunteer_id'] !== $uid) {
    setFlash('Report not found.', 'error');
    redirectTo('volunteer/assigned_reports.php');
}

$open = in_array($r['status'], ['assigned', 'in_progress'], true);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
    csrfCheckOrDie();
    $status = (string) ($_POST['status'] ?? '');
    $remark = trim((string) ($_POST['remark'] ?? ''));
    if (!in_array($status, ['in_progress', 'resolved'], true)) {
        $errors[] = 'Choose In progress or Resolved.';
    }
    if ($status === 'resolved' && $remark === '') {
        $errors[] = 'Describe what was done before marking it resolved.';
    }
    if (!$errors) {
        $upd = pdo()->prepare('UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$status, $id]);
        logUpdate($id, $uid, $status, $remark);
        notify((int) $r['resident_id'], $id, refNo($id) . ' is now ' . statusLabel($status) . '.');
        $admins = pdo()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
        foreach ($admins as $a) {
            notify((int) $a['id'], $id, $user['full_name'] . ' set ' . refNo($id) . ' to ' . statusLabel($status) . '.');
        }
        setFlash('Report updated.');
        redirectTo('volunteer/report_details.php?id=' . $id);
    }
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
  <div>
    <div class="card">
      <dl class="info">
        <dt>Reference</dt><dd><?= refNo($r['id']) ?></dd>
        <dt>Category</dt><dd><?= e($r['category']) ?></dd>
        <dt>Location</dt><dd><?= e($r['location']) ?></dd>
        <dt>Priority</dt><dd><?= prio($r['priority']) ?></dd>
        <dt>Reported by</dt><dd><?= e($r['resident_name']) ?> <span class="muted"><?= e($r['resident_phone']) ?></span></dd>
      </dl>
      <h3 style="margin-top:20px">Description</h3>
      <p><?= nl2br_safe($r['description']) ?></p>
      <?php if (!empty($r['photo'])): ?><img class="photo" src="<?= BASE_URL ?>/assets/uploads/<?= e($r['photo']) ?>" alt="Photo attached to the report"><?php endif; ?>
      <?= renderMapView($r) ?>
    </div>
    <div class="card"><h2>History</h2><?= renderTimeline($id) ?></div>
  </div>
  <div class="card">
    <h2>Update progress</h2>
    <?php if (!$open): ?>
      <p class="muted">This report is <?= e(statusLabel($r['status'])) ?> and can no longer be changed.</p>
    <?php else: ?>
      <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrfField() ?>
        <label for="status">New status</label>
        <select id="status" name="status">
          <option value="in_progress">In progress</option>
          <option value="resolved">Resolved</option>
        </select>
        <label for="remark">What did you do?</label>
        <textarea id="remark" name="remark" style="min-height:90px"></textarea>
        <p style="margin-top:16px"><button class="btn" type="submit">Post update</button></p>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
