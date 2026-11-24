<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');

$id = (int) ($_GET['id'] ?? 0);
$r = fetchReport($id);
if (!$r) {
    setFlash('Report not found.', 'error');
    redirectTo('admin/reports.php');
}

$vols = pdo()->query("SELECT id, full_name FROM users WHERE role = 'volunteer' AND status = 'active' ORDER BY full_name ASC")->fetchAll();
$statuses = ['pending', 'assigned', 'in_progress', 'resolved', 'rejected'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $status = (string) ($_POST['status'] ?? '');
    $priority = (string) ($_POST['priority'] ?? '');
    $vol = (int) ($_POST['volunteer_id'] ?? 0) ?: null;
    $remark = trim((string) ($_POST['remark'] ?? ''));

    if (!in_array($status, $statuses, true)) $errors[] = 'Choose a valid status.';
    if (!in_array($priority, ['low', 'medium', 'high'], true)) $errors[] = 'Choose a valid priority.';

    $volObj = null;
    if ($vol) {
        foreach ($vols as $v) {
            if ((int) $v['id'] === $vol) {
                $volObj = $v;
                break;
            }
        }
        if (!$volObj) $errors[] = 'Choose an active volunteer.';
    }
    if ($vol && $status === 'pending') $status = 'assigned';
    if (in_array($status, ['assigned', 'in_progress'], true) && !$vol) {
        $errors[] = 'Assign a volunteer before marking it ' . statusLabel($status) . '.';
    }
    if ($status === 'rejected' && $remark === '') {
        $errors[] = 'Give a reason when rejecting a report.';
    }

    if (!$errors) {
        $prevStatus = $r['status'];
        $prevVol = $r['volunteer_id'] ? (int) $r['volunteer_id'] : null;

        $upd = pdo()->prepare('UPDATE reports SET status = ?, priority = ?, volunteer_id = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$status, $priority, $vol, $id]);

        $note = $remark;
        $newVol = (bool) ($vol && $vol !== $prevVol);
        if ($newVol && $volObj) {
            $note = trim('Assigned to ' . $volObj['full_name'] . '. ' . $remark);
        }
        if ($status !== $prevStatus || $note !== '') {
            logUpdate($id, (int) $user['id'], $status, $note);
        }
        if ($status !== $prevStatus || $newVol) {
            notify((int) $r['resident_id'], $id, refNo($id) . ' is now ' . statusLabel($status) . '.');
        }
        if ($newVol) {
            notify((int) $vol, $id, 'You were assigned to ' . refNo($id) . ': ' . $r['title']);
        }
        setFlash('Report updated.');
        redirectTo('admin/report_details.php?id=' . $id);
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
        <dt>Resident</dt><dd><?= e($r['resident_name']) ?> <span class="muted"><?= e($r['resident_phone']) ?></span></dd>
        <dt>Filed</dt><dd><?= e(fmtDate($r['created_at'])) ?></dd>
      </dl>
      <h3 style="margin-top:20px">Description</h3>
      <p><?= nl2br_safe($r['description']) ?></p>
      <?php if (!empty($r['photo'])): ?><img class="photo" src="<?= BASE_URL ?>/assets/uploads/<?= e($r['photo']) ?>" alt="Photo attached to the report"><?php endif; ?>
      <?= renderMapView($r) ?>
    </div>
    <div class="card"><h2>History</h2><?= renderTimeline($id) ?></div>
  </div>
  <div class="card">
    <h2>Manage</h2>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrfField() ?>
      <label for="volunteer_id">Volunteer</label>
      <select id="volunteer_id" name="volunteer_id">
        <option value="0">Not assigned</option>
        <?php foreach ($vols as $v): ?><option value="<?= (int) $v['id'] ?>" <?= (int) $r['volunteer_id'] === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['full_name']) ?></option><?php endforeach; ?>
      </select>
      <label for="priority">Priority</label>
      <select id="priority" name="priority">
        <?php foreach (['low','medium','high'] as $p): ?><option value="<?= $p ?>" <?= $r['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option><?php endforeach; ?>
      </select>
      <label for="status">Status</label>
      <select id="status" name="status">
        <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(statusLabel($s)) ?></option><?php endforeach; ?>
      </select>
      <label for="remark">Remark (the resident can see this)</label>
      <textarea id="remark" name="remark" style="min-height:80px"></textarea>
      <p style="margin-top:16px"><button class="btn" type="submit">Save changes</button></p>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
