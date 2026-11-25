<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');

$allReports = pdo()->query('SELECT status, created_at, updated_at FROM reports')->fetchAll();
$total = count($allReports);
$resolvedRows = array_values(array_filter($allReports, fn($r) => $r['status'] === 'resolved'));
$resolved = count($resolvedRows);

$avgHours = null;
if ($resolved > 0) {
    $sumHours = 0.0;
    foreach ($resolvedRows as $r) {
        $sumHours += (strtotime((string) $r['updated_at']) - strtotime((string) $r['created_at'])) / 3600.0;
    }
    $avgHours = $sumHours / $resolved;
}

$byStatus = [
    'pending' => 0,
    'assigned' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'rejected' => 0,
];
foreach ($allReports as $r) {
    $byStatus[$r['status']] = ($byStatus[$r['status']] ?? 0) + 1;
}

$catRows = pdo()->query(
    'SELECT c.name, COUNT(r.id) AS cnt
     FROM categories c
     LEFT JOIN reports r ON r.category_id = c.id
     GROUP BY c.id, c.name
     ORDER BY cnt DESC, c.name ASC'
)->fetchAll();
$byCat = [];
foreach ($catRows as $cr) {
    $byCat[$cr['name']] = (int) $cr['cnt'];
}

$tz = new DateTimeZone('Asia/Manila');
$baseMonth = new DateTimeImmutable('first day of this month 12:00:00', $tz);
$monthBuckets = [];
for ($offset = 5; $offset >= 0; $offset--) {
    $dt = $baseMonth->modify("-{$offset} months");
    $ym = $dt->format('Y-m');
    $shortMon = $dt->format('M');
    $yr = $dt->format('Y');
    $monthBuckets[$ym] = [
        'label' => $shortMon,
        'subLabel' => $yr,
        'ym' => $dt->format('Y/m'),
        'fullLabel' => $shortMon . ' ' . $yr,
        'value' => 0,
        'resolved' => 0,
    ];
}

foreach ($allReports as $r) {
    try {
        $dt = (new DateTimeImmutable((string) $r['created_at']))->setTimezone($tz);
        $ym = $dt->format('Y-m');
        if (isset($monthBuckets[$ym])) {
            $monthBuckets[$ym]['value']++;
            if ($r['status'] === 'resolved') {
                $monthBuckets[$ym]['resolved']++;
            }
        }
    } catch (Throwable $e) {
    }
}

$byMonth = array_values($monthBuckets);

$title = 'Analytics';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Analytics</h1>
    <p class="page-head-sub">Incident volume, resolution efficiency, and geographic distribution.</p>
  </div>
  <div class="page-actions noprint">
    <button type="button" class="btn ghost small" data-print>Print / Save as PDF</button>
  </div>
</div>
<div class="grid grid-3">
  <?= statCard('file', 'Total reports', $total, 'All time', 'primary') ?>
  <?= statCard('check', 'Resolution rate', ($total ? (int) round(($resolved / $total) * 100) : 0) . '%', 'Reports resolved', 'success') ?>
  <?= statCard('clock', 'Avg. hours to resolve', $avgHours !== null ? number_format($avgHours, 1) : '-', 'From filing to resolved', 'warning') ?>
</div>
<div class="two even">
  <div class="card"><div class="card-head"><h2>Reports by category</h2><span class="muted">Distribution</span></div><?= renderBars($byCat) ?></div>
  <div class="card"><div class="card-head"><h2>Reports by status</h2><span class="muted">Lifecycle breakdown</span></div><?= renderBars($byStatus, statusTones()) ?></div>
</div>
<div class="card">
  <div class="card-head"><h2>Last 6 months</h2><span class="muted">Monthly incident volume</span></div>
  <?= renderColumns($byMonth) ?>
</div>
<?= renderIncidentMap('Incident map', 420) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
