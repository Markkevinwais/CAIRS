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

$allRows = pdo()->query(
    "SELECT c.*,
            (SELECT COUNT(*) FROM reports r WHERE r.category_id = c.id) AS total,
            (SELECT COUNT(*) FROM reports r WHERE r.category_id = c.id AND r.status IN ('pending', 'assigned', 'in_progress')) AS open_count
     FROM categories c
     ORDER BY c.name ASC"
)->fetchAll();

$sosDefaults = ['fire', 'medical emergency', 'flood', 'road accident', 'theft / crime'];
$totalCats = count($allRows);
$highRules = 0;
$categorizedReports = 0;
$topCatName = 'None yet';
$topCatCount = 0;

foreach ($allRows as $c) {
    if (defaultPriority((string) $c['name']) === 'high') $highRules++;
    $cnt = (int) $c['total'];
    $categorizedReports += $cnt;
    if ($cnt > $topCatCount) {
        $topCatCount = $cnt;
        $topCatName = (string) $c['name'];
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$prioFilter = (string) ($_GET['priority'] ?? 'all');
if (!in_array($prioFilter, ['all', 'high', 'medium', 'low'], true)) $prioFilter = 'all';

$rows = array_values(array_filter($allRows, function (array $c) use ($q, $prioFilter): bool {
    $pr = defaultPriority((string) $c['name']);
    if ($prioFilter !== 'all' && $pr !== $prioFilter) return false;
    if ($q !== '' && stripos((string) $c['name'], $q) === false) return false;
    return true;
}));

$hasFilters = $q !== '' || $prioFilter !== 'all';

$title = 'Categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Categories</h1>
    <p class="page-head-sub">Organize incident classifications, inspect filing volume, and review automatic priority rules.</p>
  </div>
</div>

<div class="grid grid-4">
  <?= statCard('tag', 'Total categories', $totalCats, 'Active incident classifications', 'primary') ?>
  <?= statCard('alert', 'High-priority rules', $highRules, 'Auto-escalated on submission', 'danger') ?>
  <?= statCard('file', 'Categorized reports', $categorizedReports, 'Total assigned incidents', 'info') ?>
  <?= statCard('chart', 'Top category', $topCatCount > 0 ? $topCatName : '—', $topCatCount > 0 ? $topCatCount . ' report' . ($topCatCount === 1 ? '' : 's') . ' filed' : 'No filings recorded', 'success') ?>
</div>

<form class="filters" method="get">
  <input class="filter-search" name="q" placeholder="Search category name..." value="<?= e($q) ?>">
  <div class="filter-field">
    <select name="priority">
      <option value="all" <?= $prioFilter === 'all' ? 'selected' : '' ?>>All auto-priorities</option>
      <option value="high" <?= $prioFilter === 'high' ? 'selected' : '' ?>>High priority</option>
      <option value="medium" <?= $prioFilter === 'medium' ? 'selected' : '' ?>>Medium priority</option>
      <option value="low" <?= $prioFilter === 'low' ? 'selected' : '' ?>>Low priority</option>
    </select>
  </div>
  <button class="btn" type="submit">Filter</button>
  <?php if ($hasFilters): ?><a class="btn ghost" href="categories.php">Reset</a><?php endif; ?>
  <span class="filters-count"><?= count($rows) ?> of <?= $totalCats ?> shown</span>
</form>

<div class="two">
  <div class="card tablewrap">
  <?php if (!$rows): ?>
    <div class="empty-state">
      <span class="empty-state-ic"><?= icon('tag', 22) ?></span>
      <h3><?= $hasFilters ? 'No categories match your search' : 'No categories configured' ?></h3>
      <p><?= $hasFilters ? 'Try resetting the priority filter or search term.' : 'Create your first incident category using the panel on the right.' ?></p>
      <?php if ($hasFilters): ?><a class="btn ghost small" href="categories.php">Clear filters</a><?php endif; ?>
    </div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Category Name</th>
        <th>Auto-Priority</th>
        <th>Report Share &amp; Open</th>
        <th class="act">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $c):
      $tot = (int) $c['total'];
      $openCnt = (int) $c['open_count'];
      $sharePct = $categorizedReports > 0 ? (int) round(($tot / $categorizedReports) * 100) : 0;
      $pr = defaultPriority((string) $c['name']);
      $isSosCore = in_array(strtolower(trim((string) $c['name'])), $sosDefaults, true);
    ?>
    <tr>
      <td>
        <form method="post" style="display:flex;gap:8px;align-items:center"><?= csrfField() ?>
          <input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <input name="name" maxlength="60" required value="<?= e($c['name']) ?>" aria-label="Category name" style="flex:1;min-width:140px">
          <button class="btn ghost small" type="submit">Save</button>
        </form>
        <?php if ($isSosCore): ?>
          <div class="cat-name-meta"><?= icon('siren', 13) ?><span>Mapped to Resident SOS quick button</span></div>
        <?php endif; ?>
      </td>
      <td><?= prio($pr) ?></td>
      <td>
        <div class="meter">
          <div class="meter-top">
            <span><?= $tot ?> total &middot; <?= $openCnt ?> open</span>
            <small><?= $sharePct ?>%</small>
          </div>
          <div class="meter-track"><i class="meter-fill<?= $tot === 0 ? ' tone-muted' : '' ?>" style="width:<?= $tot > 0 ? max(6, $sharePct) : 0 ?>%"></i></div>
        </div>
      </td>
      <td class="act">
        <div class="row-actions">
          <a class="btn ghost small" href="reports.php?category=<?= (int) $c['id'] ?>">View reports</a>
          <?php if ($tot > 0): ?>
            <span class="muted" style="font-size:.8rem;padding:0 6px">In use</span>
          <?php else: ?>
          <form method="post" data-confirm="Delete the category &quot;<?= e($c['name']) ?>&quot;?"><?= csrfField() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn danger small" type="submit">Delete</button>
          </form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  </div>

  <div class="card sticky-card">
    <h2>Add a category</h2>
    <p class="card-sub">Categories organize citizen submissions and determine initial dispatch priority automatically.</p>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add">
      <label for="cat-add-name">Category name</label>
      <input id="cat-add-name" name="name" maxlength="60" required placeholder="e.g. Fallen Utility Pole">
      <div class="cat-prio-preview" id="cat-prio-preview">
        <span>Auto-assigned priority:</span>
        <span class="badge p-medium" id="cat-prio-badge">Medium</span>
      </div>
      <p style="margin-top:14px"><button class="btn" type="submit" style="width:100%"><?= icon('plus', 16) ?> Add category</button></p>
    </form>

    <div class="cat-guide-wrap">
      <h3>Auto-Priority Routing Guide</h3>
      <table class="cat-guide-table">
        <tr>
          <td><?= prio('high') ?></td>
          <td>Fire, Medical emergency, Flood, Road accident, Theft / Crime</td>
        </tr>
        <tr>
          <td><?= prio('medium') ?></td>
          <td>All custom &amp; general community concerns (default)</td>
        </tr>
        <tr>
          <td><?= prio('low') ?></td>
          <td>Noise disturbance, Garbage / Sanitation, Broken streetlight / Road</td>
        </tr>
      </table>
      <p class="cat-guide-note">Resident SOS buttons route directly to the five high-priority defaults above. Rename those carefully to preserve SOS categorization.</p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
