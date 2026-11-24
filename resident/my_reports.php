<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('resident');
$uid = (int) $user['id'];

$status = (string) ($_GET['status'] ?? '');
$sql = 'SELECT r.*, COALESCE(c.name, "Other") AS category
        FROM reports r
        LEFT JOIN categories c ON c.id = r.category_id
        WHERE r.resident_id = ?';
$params = [$uid];
if ($status !== '') {
    $sql .= ' AND r.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY r.id DESC';
$stmt = pdo()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$title = 'My reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>My reports</h1>
    <p class="page-head-sub"><?= count($rows) ?> report<?= count($rows) === 1 ? '' : 's' ?> <?= $status ? 'matching filter' : 'filed' ?></p>
  </div>
  <div class="page-actions">
    <a class="btn" href="report.php"><?= icon('plus', 17) ?> Report an incident</a>
  </div>
</div>
<form class="filters filters-compact" method="get">
  <div class="filter-field">
    <select name="status" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" aria-label="Filter by status">
      <option value="">All statuses</option>
      <?php foreach (['pending','assigned','in_progress','resolved','rejected'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(statusLabel($s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($status): ?><a class="btn ghost" href="my_reports.php">Reset</a><?php endif; ?>
</form>
<div class="card tablewrap">
<?php if (!$rows): ?><p class="empty">No reports found.</p><?php else: ?>
<table>
  <tr><th>Ref</th><th>Title</th><th>Category</th><th>Filed</th><th>Status</th><th class="act">Action</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= refNo($r['id']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['category']) ?></td>
      <td><?= e(fmtDate($r['created_at'])) ?></td><td><?= badge($r['status']) ?></td>
      <td class="act"><a href="report_details.php?id=<?= (int) $r['id'] ?>">View</a></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
