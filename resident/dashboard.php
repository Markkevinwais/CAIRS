<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('resident');
$uid = (int) $user['id'];

$stmt = pdo()->prepare(
    'SELECT r.*, COALESCE(c.name, "Other") AS category, COALESCE(res.full_name, "Unknown") AS resident_name,
            COALESCE(res.phone, "") AS resident_phone, vol.full_name AS volunteer_name
     FROM reports r
     LEFT JOIN categories c ON c.id = r.category_id
     LEFT JOIN users res ON res.id = r.resident_id
     LEFT JOIN users vol ON vol.id = r.volunteer_id
     WHERE r.resident_id = ?
     ORDER BY r.id DESC'
);
$stmt->execute([$uid]);
$myReports = $stmt->fetchAll();

$c = [];
foreach ($myReports as $r) {
    $c[$r['status']] = ($c[$r['status']] ?? 0) + 1;
}
$total = count($myReports);
$open = ($c['assigned'] ?? 0) + ($c['in_progress'] ?? 0);
$recent = array_slice($myReports, 0, 6);

$actStmt = pdo()->prepare(
    'SELECT ru.*, r.title, COALESCE(u.full_name, "System") AS full_name
     FROM report_updates ru
     INNER JOIN reports r ON r.id = ru.report_id
     LEFT JOIN users u ON u.id = ru.user_id
     WHERE r.resident_id = ?
     ORDER BY ru.id DESC
     LIMIT 6'
);
$actStmt->execute([$uid]);
$activity = $actStmt->fetchAll();

$title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<?= renderWelcome(explode(' ', $user['full_name'])[0], 'Track your reports and see how your community responds.', '<a class="btn" href="report.php">' . icon('plus', 17) . ' Report an incident</a>') ?>
<?= renderAnnouncements() ?>
<div class="grid grid-4">
  <?= statCard('file', 'Total reports', $total, 'Everything you have filed', 'primary') ?>
  <?= statCard('clock', 'Pending', $c['pending'] ?? 0, 'Waiting for review', 'warning') ?>
  <?= statCard('loader', 'In progress', $open, 'Currently being handled', 'progress') ?>
  <?= statCard('check', 'Resolved', $c['resolved'] ?? 0, 'Successfully completed', 'success') ?>
</div>
<div class="two">
  <div class="card">
    <div class="card-head"><h2>Your recent reports</h2><a href="my_reports.php">View all</a></div>
    <?php if (!$recent): ?>
      <p class="empty">You haven't reported anything yet. <a href="report.php">Report your first incident</a>.</p>
    <?php else: ?>
      <div class="report-grid">
        <?php foreach ($recent as $r): echo renderReportCard($r, 'report_details.php?id=' . (int) $r['id']); endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>Latest updates</h2></div><?= renderActivity($activity, 'report_details.php?id=') ?></div>
</div>
<div class="card"><div class="card-head"><h2>Emergency hotlines</h2></div><?= renderHotlines() ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
