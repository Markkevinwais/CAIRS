<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    if (($_POST['action'] ?? '') === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = pdo()->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'volunteer'");
        $stmt->execute([$id]);
        setFlash('Volunteer status changed.');
        redirectTo('admin/volunteers.php');
    }

    $old = [
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
    ];
    $pass = (string) ($_POST['password'] ?? '');

    if ($old['full_name'] === '') $errors[] = "Enter the volunteer's name.";
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (!$errors) {
        $chk = pdo()->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $chk->execute([$old['email']]);
        if ($chk->fetch()) {
            $errors[] = 'That email is already registered.';
        }
    }

    if (!$errors) {
        $ins = pdo()->prepare("INSERT INTO users (full_name, email, password, role, phone, status) VALUES (?, ?, ?, 'volunteer', ?, 'active')");
        $ins->execute([
            $old['full_name'],
            $old['email'],
            password_hash($pass, PASSWORD_BCRYPT),
            $old['phone'] !== '' ? $old['phone'] : null,
        ]);
        setFlash('Volunteer account created.');
        redirectTo('admin/volunteers.php');
    }
}

$rows = pdo()->query(
    "SELECT u.*,
            (SELECT COUNT(*) FROM reports r WHERE r.volunteer_id = u.id AND r.status IN ('assigned', 'in_progress')) AS active_jobs,
            (SELECT COUNT(*) FROM reports r WHERE r.volunteer_id = u.id AND r.status = 'resolved') AS done
     FROM users u
     WHERE u.role = 'volunteer'
     ORDER BY u.full_name ASC"
)->fetchAll();

$title = 'Volunteers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Volunteers</h1>
    <p class="page-head-sub"><?= count($rows) ?> responder account<?= count($rows) === 1 ? '' : 's' ?> on roster</p>
  </div>
</div>
<div class="two">
  <div class="card tablewrap">
  <?php if (!$rows): ?><p class="empty">No volunteers yet. Add the first one.</p><?php else: ?>
  <table>
    <tr><th>Name</th><th>Contact</th><th class="num">Active</th><th class="num">Resolved</th><th>Status</th><th class="act"></th></tr>
    <?php foreach ($rows as $volUser): ?>
    <tr><td><?= e($volUser['full_name']) ?></td><td><?= e($volUser['email']) ?><br><span class="muted"><?= e($volUser['phone'] ?? '') ?></span></td>
        <td class="num"><?= (int) $volUser['active_jobs'] ?></td><td class="num"><?= (int) $volUser['done'] ?></td><td><span class="badge <?= $volUser['status'] === 'active' ? 's-resolved' : 's-rejected' ?>"><?= e(ucfirst((string) $volUser['status'])) ?></span></td>
        <td class="act"><form method="post" data-confirm="Change this volunteer's status?"><?= csrfField() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $volUser['id'] ?>">
          <button class="btn small <?= $volUser['status'] === 'active' ? 'danger' : '' ?>" type="submit"><?= $volUser['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
  <div class="card">
    <h2>Add a volunteer</h2>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add">
      <label for="full_name">Full name</label><input id="full_name" name="full_name" required value="<?= e($old['full_name']) ?>">
      <label for="email">Email</label><input type="email" id="email" name="email" required value="<?= e($old['email']) ?>">
      <label for="phone">Mobile number</label><input id="phone" name="phone" value="<?= e($old['phone']) ?>">
      <label for="password">Temporary password</label><input type="password" id="password" name="password" required minlength="6">
      <p style="margin-top:16px"><button class="btn" type="submit">Create volunteer</button></p>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
