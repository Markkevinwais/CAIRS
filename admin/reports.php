<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');

$status = (string) ($_GET['status'] ?? '');
$cat = (int) ($_GET['category'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$exporting = isset($_GET['export']);

$sql = 'SELECT r.*, COALESCE(c.name, "Other") AS category,
               COALESCE(res.full_name, "Unknown") AS resident_name,
               vol.full_name AS volunteer_name
        FROM reports r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN users res ON res.id = r.resident_id
        LEFT JOIN users vol ON vol.id = r.volunteer_id
        WHERE 1=1';
$params = [];

if ($status !== '') {
    $sql .= ' AND r.status = ?';
    $params[] = $status;
}
if ($cat > 0) {
    $sql .= ' AND r.category_id = ?';
    $params[] = $cat;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= ' AND (r.title LIKE ? OR r.location LIKE ? OR res.full_name LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY r.id DESC';
if (!$exporting) {
    $sql .= ' LIMIT 200';
}

$stmt = pdo()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($exporting) {
    $today = date('Y-m-d');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cairs-reports-' . $today . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Reference',
        'Title',
        'Category',
        'Resident',
        'Volunteer',
        'Location',
        'Latitude',
        'Longitude',
        'Priority',
        'Status',
        'Filed',
        'Last updated',
    ]);
    foreach ($rows as $r) {
        fputcsv($out, array_map('csvSafe', [
            refNo($r['id']),
            $r['title'],
            $r['category'],
            $r['resident_name'],
            $r['volunteer_name'] ?? '',
            $r['location'],
            $r['latitude'] ?? '',
            $r['longitude'] ?? '',
            ucfirst((string) $r['priority']),
            statusLabel($r['status']),
            $r['created_at'],
            $r['updated_at'],
        ]));
    }
    fclose($out);
    exit;
}

$cats = pdo()->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
$exportParams = [];
if ($q !== '') $exportParams['q'] = $q;
if ($status !== '') $exportParams['status'] = $status;
if ($cat > 0) $exportParams['category'] = $cat;
$exportParams['export'] = '1';
$exportQuery = http_build_query($exportParams);

$title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Reports</h1>
    <p class="page-head-sub"><?= count($rows) ?> incident report<?= count($rows) === 1 ? '' : 's' ?> shown</p>
  </div>
  <div class="page-actions noprint">
    <a class="btn ghost small" href="?<?= e($exportQuery) ?>">Download Excel (CSV)</a>
    <button type="button" class="btn ghost small" data-print>Print / Save as PDF</button>
  </div>
</div>
<form class="filters" method="get">
  <input class="filter-search" name="q" placeholder="Search title, location or resident" value="<?= e($q) ?>">
  <div class="filter-field">
    <select name="status" aria-label="Status">
      <option value="">All statuses</option>
      <?php foreach (['pending', 'assigned', 'in_progress', 'resolved', 'rejected'] as $s): ?>
        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(statusLabel($s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-field">
    <select name="category" aria-label="Category">
      <option value="0">All categories</option>
      <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <button class="btn" type="submit">Filter</button>
  <?php if ($q !== '' || $status !== '' || $cat > 0): ?><a class="btn ghost" href="reports.php">Reset</a><?php endif; ?>
</form>
<div class="card tablewrap">
<?php if (!$rows): ?><p class="empty">No reports match these filters.</p><?php else: ?>
<table>
  <tr><th>Ref</th><th>Title</th><th>Category</th><th>Resident</th><th>Volunteer</th><th>Priority</th><th>Status</th><th class="noprint act"></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= refNo($r['id']) ?></td><td><?= e($r['title']) ?><?php if (!empty($r['is_sos'])): ?> <span class="badge sos">SOS</span><?php endif; ?></td><td><?= e($r['category']) ?></td>
      <td><?= e($r['resident_name']) ?></td><td><?= e($r['volunteer_name'] ?: '-') ?></td>
      <td><?= prio($r['priority']) ?></td><td><?= badge($r['status']) ?></td>
      <td class="noprint act"><a href="report_details.php?id=<?= (int) $r['id'] ?>">Open</a></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
