<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');

function categoryNameError(string $name, int $except = 0): string
{
    if ($name === '') return 'Enter a category name.';
    if (mb_strlen($name) > 60) return 'The name is too long (60 characters at most).';
    $stmt = pdo()->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
    $stmt->execute([$name, $except]);
    return $stmt->fetch() ? 'That category already exists.' : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));

    if ($action === 'add') {
        $err = categoryNameError($name);
        if ($err !== '') {
            setFlash($err, 'error');
        } else {
            $ins = pdo()->prepare('INSERT INTO categories (name) VALUES (?)');
            $ins->execute([$name]);
            setFlash('Category added.');
        }
    } elseif ($action === 'rename') {
        $err = categoryNameError($name, $id);
        if ($err !== '') {
            setFlash($err, 'error');
        } else {
            $upd = pdo()->prepare('UPDATE categories SET name = ? WHERE id = ?');
            $upd->execute([$name, $id]);
            setFlash('Category renamed.');
        }
    } elseif ($action === 'delete') {
        $chk = pdo()->prepare('SELECT COUNT(*) FROM reports WHERE category_id = ?');
        $chk->execute([$id]);
        if ((int) $chk->fetchColumn() > 0) {
            setFlash('This category is used by reports, so it cannot be deleted.', 'error');
        } else {
            $del = pdo()->prepare('DELETE FROM categories WHERE id = ?');
            $del->execute([$id]);
            setFlash('Category deleted.');
        }
    }
    redirectTo('admin/categories.php');
}

$rows = pdo()->query(
    'SELECT c.*, (SELECT COUNT(*) FROM reports r WHERE r.category_id = c.id) AS total
     FROM categories c
     ORDER BY c.name ASC'
)->fetchAll();

$title = 'Categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Categories</h1>
    <p class="page-head-sub">Manage incident classifications and automatic priority mapping.</p>
  </div>
</div>
<div class="two">
  <div class="card tablewrap">
  <?php if (!$rows): ?><p class="empty">No categories yet. Add the first one.</p><?php else: ?>
  <table>
    <tr><th>Name</th><th>Priority</th><th class="num">Reports</th><th class="act"></th></tr>
    <?php foreach ($rows as $c): ?>
    <tr>
      <td>
        <form method="post" style="display:flex;gap:8px;align-items:center"><?= csrfField() ?>
          <input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <input name="name" maxlength="60" required value="<?= e($c['name']) ?>" aria-label="Category name" style="flex:1;min-width:140px">
          <button class="btn ghost small" type="submit">Save</button>
        </form>
      </td>
      <td><?= prio(defaultPriority($c['name'])) ?></td>
      <td class="num"><?= (int) $c['total'] ?></td>
      <td class="act">
        <?php if ((int) $c['total'] > 0): ?><span class="muted">In use</span>
        <?php else: ?>
        <form method="post" data-confirm="Delete the category &quot;<?= e($c['name']) ?>&quot;?"><?= csrfField() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <button class="btn danger small" type="submit">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>

  <div class="card">
    <h2>Add a category</h2>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add">
      <label for="name">Name</label>
      <input id="name" name="name" maxlength="60" required>
      <p style="margin-top:16px"><button class="btn" type="submit">Add category</button></p>
    </form>
    <p class="muted" style="margin-top:14px">New reports get their priority from the category name: Fire, Flood, Medical emergency, Road accident and Theft / Crime are High; Noise, Garbage and Streetlight are Low; the rest are Medium. The SOS buttons file under the default category names, so keep those if you can.</p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
