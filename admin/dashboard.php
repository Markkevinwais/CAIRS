<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');
$uid = (int) $user['id'];

$allReports = pdo()->query(
    'SELECT r.*, COALESCE(c.name, "Other") AS category,
            COALESCE(res.full_name, "Unknown") AS resident_name,
            COALESCE(res.phone, "") AS resident_phone,
            vol.full_name AS volunteer_name
     FROM reports r
     LEFT JOIN categories c ON c.id = r.category_id
     LEFT JOIN users res ON res.id = r.resident_id
     LEFT JOIN users vol ON vol.id = r.volunteer_id
     ORDER BY r.id DESC'
)->fetchAll();

$c = [];
$prioCounts = ['high' => 0, 'medium' => 0, 'low' => 0];
foreach ($allReports as $r) {
    $c[$r['status']] = ($c[$r['status']] ?? 0) + 1;
    if (isset($prioCounts[$r['priority']])) {
        $prioCounts[$r['priority']]++;
    }
}

$total = count($allReports);
$residents = (int) pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'resident'")->fetchColumn();
$volunteers = (int) pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'volunteer' AND status = 'active'")->fetchColumn();

$startCur = strtotime(date('Y-m-01 00:00:00'));
$startPrev = strtotime('-1 month', $startCur);
$cur = 0;
$prev = 0;
foreach ($allReports as $r) {
    $ts = strtotime((string) $r['created_at']);
    if ($ts >= $startCur) {
        $cur++;
    } elseif ($ts >= $startPrev && $ts < $startCur) {
        $prev++;
    }
}
if ($prev > 0) {
    $diff = (int) round((($cur - $prev) / $prev) * 100);
    $trend = [$diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'), ($diff > 0 ? '+' : '') . $diff . '% this month'];
} elseif ($cur > 0) {
    $trend = ['up', $cur . ' new this month'];
} else {
    $trend = ['flat', 'No new reports this month'];
}

$recent = array_slice($allReports, 0, 6);
$pending = array_values(array_filter($allReports, fn($r) => $r['status'] === 'pending'));
usort($pending, fn($a, $b) => strtotime((string) $a['created_at']) <=> strtotime((string) $b['created_at']));
$pending = array_slice($pending, 0, 8);

$sos = array_values(array_filter(
    $allReports,
    fn($r) => !empty($r['is_sos']) && in_array($r['status'], ['pending', 'assigned', 'in_progress'], true)
));
$sos = array_slice($sos, 0, 6);

$activity = pdo()->query(
    'SELECT ru.report_id, COALESCE(r.title, "") AS title, ru.status, ru.created_at,
            COALESCE(u.full_name, "System") AS full_name, ru.remark
     FROM report_updates ru
     LEFT JOIN reports r ON r.id = ru.report_id
     LEFT JOIN users u ON u.id = ru.user_id
     ORDER BY ru.id DESC
     LIMIT 8'
)->fetchAll();

$title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<?= renderWelcome(
    $user['full_name'],
    'Here is what is happening in your community today.',
    '<a class="btn" href="reports.php?status=pending">' . icon('clock', 17) . ' Review pending</a>'
) ?>
<?= renderAlerts($uid) ?>
<?php if ($sos): ?>
<section class="sos-board" aria-label="Active SOS alerts">
  <div class="sos-board-head"><span class="live-dot"></span><h2>Active SOS alerts (<?= count($sos) ?>)</h2></div>
  <div class="report-grid">
    <?php foreach ($sos as $r): echo renderReportCard($r, 'report_details.php?id=' . (int) $r['id']); endforeach; ?>
  </div>
</section>
<?php endif; ?>
<div class="grid grid-3">
  <?= statCard('file', 'Total reports', $total, '', 'primary', $trend) ?>
  <?= statCard('clock', 'Pending', $c['pending'] ?? 0, 'Needs attention', 'warning') ?>
  <?= statCard('loader', 'In progress', ($c['assigned'] ?? 0) + ($c['in_progress'] ?? 0), 'Currently being handled', 'progress') ?>
  <?= statCard('check', 'Resolved', $c['resolved'] ?? 0, 'Successfully completed', 'success') ?>
  <?= statCard('users', 'Residents', $residents, 'Registered accounts', 'info') ?>
  <?= statCard('shield', 'Volunteers', $volunteers, 'Active responders', 'primary') ?>
</div>

<div class="two even">
  <div class="card"><div class="card-head"><h2>Status overview</h2></div><?= renderDonut($c) ?></div>
  <div class="card"><div class="card-head"><h2>Priority overview</h2></div><?= renderBars($prioCounts, priorityTones()) ?></div>
</div>

<div class="two">
  <div class="card">
    <div class="card-head"><h2>Recent reports</h2><a href="reports.php">View all</a></div>
    <?php if (!$recent): ?><p class="empty">No reports yet.</p><?php else: ?>
    <div class="report-grid">
      <?php foreach ($recent as $r): echo renderReportCard($r, 'report_details.php?id=' . (int) $r['id']); endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>Activity</h2></div><?= renderActivity($activity, 'report_details.php?id=') ?></div>
</div>

<div class="card">
  <div class="card-head"><h2>Waiting for review (oldest first)</h2></div>
  <?php if (!$pending): ?><p class="empty">All caught up. No pending reports.</p><?php else: ?>
  <div class="tablewrap"><table>
    <tr><th>Ref</th><th>Title</th><th>Category</th><th>Resident</th><th>Filed</th><th class="act"></th></tr>
    <?php foreach ($pending as $r): ?>
    <tr><td><?= refNo($r['id']) ?></td><td><?= e($r['title']) ?><?php if (isOverdue($r)): ?> <span class="badge overdue">Overdue</span><?php endif; ?></td><td><?= e($r['category']) ?></td>
        <td><?= e($r['resident_name']) ?></td><td><?= e(fmtDate($r['created_at'])) ?></td>
        <td class="act"><a class="btn small" href="report_details.php?id=<?= (int) $r['id'] ?>">Review</a></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>

<?= renderIncidentMap('Incident map', 380) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
