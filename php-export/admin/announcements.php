<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');

$levels = ['info', 'warning', 'critical'];
$errors = [];
$old = ['title' => '', 'body' => '', 'level' => 'info'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = pdo()->prepare('UPDATE announcements SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('Announcement updated.');
        redirectTo('admin/announcements.php');
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = pdo()->prepare('DELETE FROM announcements WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('Announcement deleted.');
        redirectTo('admin/announcements.php');
    }

    $old = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'body' => trim((string) ($_POST['body'] ?? '')),
        'level' => trim((string) ($_POST['level'] ?? '')),
    ];
    if ($old['title'] === '') $errors[] = 'Enter a title.';
    if (mb_strlen($old['title']) > 150) $errors[] = 'The title is too long (150 characters at most).';
    if ($old['body'] === '') $errors[] = 'Write the message.';
    if (mb_strlen($old['body']) > 2000) $errors[] = 'The message is too long (2000 characters at most).';
    if (!in_array($old['level'], $levels, true)) $errors[] = 'Choose a level.';

    if (!$errors) {
        $stmt = pdo()->prepare('INSERT INTO announcements (user_id, title, body, level, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([(int) $user['id'], $old['title'], $old['body'], $old['level']]);
        setFlash('Announcement posted.');
        redirectTo('admin/announcements.php');
    }
}

$rows = pdo()->query(
    'SELECT a.*, COALESCE(u.full_name, "Admin") AS full_name
     FROM announcements a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.id DESC'
)->fetchAll();

$title = 'Announcements';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Announcements</h1>
    <p class="page-head-sub">Publish community notices shown on resident and volunteer dashboards.</p>
  </div>
</div>
<div class="two">
  <div>
    <?php if (!$rows): ?><div class="card"><p class="empty">No announcements yet. Post the first one.</p></div><?php endif; ?>
    <?php foreach ($rows as $a): ?>
    <article class="ann ann-<?= e($a['level']) ?><?= !empty($a['is_active']) ? '' : ' is-off' ?>">
      <span class="ann-ic"><?= icon($a['level'] === 'critical' ? 'siren' : 'megaphone', 20) ?></span>
      <div>
        <strong><?= e($a['title']) ?></strong> <?= !empty($a['is_active']) ? '' : '<span class="badge">Hidden</span>' ?>
        <p><?= nl2br_safe($a['body']) ?></p>
        <span class="muted"><?= e(fmtDate($a['created_at'])) ?> &middot; <?= e($a['full_name']) ?> &middot; <?= ucfirst($a['level']) ?></span>
        <div class="ann-actions">
          <form method="post"><?= csrfField() ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <button class="btn ghost small" type="submit"><?= !empty($a['is_active']) ? 'Hide' : 'Show again' ?></button></form>
          <form method="post" data-confirm="Delete this announcement for good?"><?= csrfField() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <button class="btn danger small" type="submit">Delete</button></form>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>Post an announcement</h2>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add">
      <label for="title">Title</label>
      <input id="title" name="title" maxlength="150" required value="<?= e($old['title']) ?>">
      <label for="level">Level</label>
      <select id="level" name="level">
        <?php foreach ($levels as $l): ?><option value="<?= $l ?>" <?= $old['level'] === $l ? 'selected' : '' ?>><?= ucfirst($l) ?></option><?php endforeach; ?>
      </select>
      <label for="body">Message</label>
      <textarea id="body" name="body" required style="min-height:110px"><?= e($old['body']) ?></textarea>
      <p style="margin-top:16px"><button class="btn" type="submit">Post announcement</button></p>
    </form>
    <p class="muted" style="margin-top:14px">Active announcements show on the home page and on the resident and volunteer dashboards. Use <b>Critical</b> only for real emergencies.</p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
