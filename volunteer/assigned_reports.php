<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('volunteer');
$uid = (int) $user['id'];

$status = (string) ($_GET['status'] ?? '');
$sql = 'SELECT r.*, COALESCE(c.name, "Other") AS category
        FROM reports r
        LEFT JOIN categories c ON c.id = r.category_id
        WHERE r.volunteer_id = ?';
$params = [$uid];
if ($status !== '') {
    $sql .= ' AND r.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY r.id DESC';
$stmt = pdo()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$title = 'Assigned reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Assigned reports</h1>
    <p class="page-head-sub"><?= count($rows) ?> assignment<?= count($rows) === 1 ? '' : 's' ?> <?= $status ? 'matching filter' : 'assigned to you' ?></p>
  </div>
</div>
<form class="filters filters-compact" method="get">
  <div class="filter-field">
    <select name="status" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" aria-label="Filter by status">
      <option value="">All statuses</option>
      <?php foreach (['assigned','in_progress','resolved'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(statusLabel($s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($status): ?><a class="btn ghost" href="assigned_reports.php">Reset</a><?php endif; ?>
</form>
<div class="card tablewrap">
<?php if (!$rows): ?><p class="empty">Nothing here.</p><?php else: ?>
<table>
  <tr><th>Ref</th><th>Title</th><th>Category</th><th>Location</th><th>Priority</th><th>Status</th><th class="act">Action</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= refNo($r['id']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['category']) ?></td><td><?= e($r['location']) ?></td>
      <td><?= prio($r['priority']) ?></td><td><?= badge($r['status']) ?></td><td class="act"><a href="report_details.php?id=<?= (int) $r['id'] ?>">Open</a></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
