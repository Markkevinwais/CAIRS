<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = pdo()->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'resident'");
    $stmt->execute([$id]);
    setFlash('Resident status changed.');
    redirectTo('admin/residents.php');
}

$rows = pdo()->query(
    "SELECT u.*, (SELECT COUNT(*) FROM reports r WHERE r.resident_id = u.id) AS total
     FROM users u
     WHERE u.role = 'resident'
     ORDER BY u.created_at DESC"
)->fetchAll();

$title = 'Residents';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Residents</h1>
    <p class="page-head-sub"><?= count($rows) ?> registered resident account<?= count($rows) === 1 ? '' : 's' ?></p>
  </div>
</div>
<div class="card tablewrap">
<?php if (!$rows): ?><p class="empty">No residents have registered yet.</p><?php else: ?>
<table>
  <tr><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th class="num">Reports</th><th>Status</th><th class="act"></th></tr>
  <?php foreach ($rows as $u): ?>
  <tr><td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['phone'] ?: '-') ?></td><td><?= e($u['address'] ?: '-') ?></td>
      <td class="num"><?= (int) $u['total'] ?></td><td><span class="badge <?= $u['status'] === 'active' ? 's-resolved' : 's-rejected' ?>"><?= ucfirst($u['status']) ?></span></td>
      <td class="act"><form method="post" data-confirm="Change this resident's status?"><?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <button class="btn small <?= $u['status'] === 'active' ? 'danger' : '' ?>" type="submit"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
