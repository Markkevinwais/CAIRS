<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('volunteer');
$uid = (int) $user['id'];

$stmt = pdo()->prepare(
    'SELECT r.*, COALESCE(c.name, "Other") AS category, COALESCE(res.full_name, "Unknown") AS resident_name,
            COALESCE(res.phone, "") AS resident_phone, vol.full_name AS volunteer_name
     FROM reports r
     LEFT JOIN categories c ON c.id = r.category_id
     LEFT JOIN users res ON res.id = r.resident_id
     LEFT JOIN users vol ON vol.id = r.volunteer_id
     WHERE r.volunteer_id = ?'
);
$stmt->execute([$uid]);
$assigned = $stmt->fetchAll();

$c = [];
foreach ($assigned as $r) {
    $c[$r['status']] = ($c[$r['status']] ?? 0) + 1;
}

$prioRank = ['high' => 1, 'medium' => 2, 'low' => 3];
$todo = array_values(array_filter($assigned, fn($r) => in_array($r['status'], ['assigned', 'in_progress'], true)));
usort($todo, function ($a, $b) use ($prioRank) {
    $diff = ($prioRank[$a['priority']] ?? 9) <=> ($prioRank[$b['priority']] ?? 9);
    return $diff !== 0 ? $diff : strtotime($a['created_at']) <=> strtotime($b['created_at']);
});
$todo = array_slice($todo, 0, 6);

$actStmt = pdo()->prepare(
    'SELECT ru.*, r.title, COALESCE(u.full_name, "System") AS full_name
     FROM report_updates ru
     INNER JOIN reports r ON r.id = ru.report_id
     LEFT JOIN users u ON u.id = ru.user_id
     WHERE r.volunteer_id = ?
     ORDER BY ru.id DESC
     LIMIT 6'
);
$actStmt->execute([$uid]);
$activity = $actStmt->fetchAll();

$title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<?= renderWelcome(explode(' ', $user['full_name'])[0], 'Your assigned incidents, highest priority first.', '<a class="btn ghost" href="assigned_reports.php">' . icon('clipboard', 17) . ' All assignments</a>') ?>
<?= renderAnnouncements() ?>
<?= renderAlerts($uid) ?>
<div class="grid grid-3">
  <?= statCard('clipboard', 'New assignments', $c['assigned'] ?? 0, 'Waiting for you to start', 'warning') ?>
  <?= statCard('loader', 'In progress', $c['in_progress'] ?? 0, 'Currently being handled', 'progress') ?>
  <?= statCard('check', 'Resolved by you', $c['resolved'] ?? 0, 'Successfully completed', 'success') ?>
</div>
<div class="two">
  <div class="card">
    <div class="card-head"><h2>Your next tasks</h2></div>
    <?php if (!$todo): ?><p class="empty">No open assignments. The admin will assign new reports to you.</p><?php else: ?>
      <div class="report-grid">
        <?php foreach ($todo as $r): echo renderReportCard($r, 'report_details.php?id=' . (int) $r['id']); endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>Recent activity</h2></div><?= renderActivity($activity, 'report_details.php?id=') ?></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
