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
  <section class="cat-directory" aria-label="Category directory">
    <div class="cat-dir-head">
      <h2>Category Directory</h2>
      <span class="muted"><?= count($rows) ?> classification<?= count($rows) === 1 ? '' : 's' ?> &middot; Click Rename to edit</span>
    </div>
    <?php if (!$rows): ?>
      <div class="card">
        <div class="empty-state">
          <span class="empty-state-ic"><?= icon('tag', 22) ?></span>
          <h3><?= $hasFilters ? 'No categories match your search' : 'No categories configured' ?></h3>
          <p><?= $hasFilters ? 'Try resetting the priority filter or search term.' : 'Create your first incident category using the panel on the right.' ?></p>
          <?php if ($hasFilters): ?><a class="btn ghost small" href="categories.php">Clear filters</a><?php endif; ?>
        </div>
      </div>
    <?php else:
      $prioToneMap = priorityTones();
    ?>
      <div class="cat-grid">
        <?php foreach ($rows as $c):
          $tot = (int) $c['total'];
          $openCnt = (int) $c['open_count'];
          $sharePct = $categorizedReports > 0 ? (int) round(($tot / $categorizedReports) * 100) : 0;
          $pr = defaultPriority((string) $c['name']);
          $icTone = $prioToneMap[$pr] ?? 'primary';
          $isSosCore = in_array(strtolower(trim((string) $c['name'])), $sosDefaults, true);
        ?>
        <article class="cat-card">
          <div class="cat-card-head">
            <div class="cat-card-title">
              <span class="cat-card-ic tone-<?= e($icTone) ?>"><?= icon($isSosCore ? 'siren' : 'tag', 17) ?></span>
              <div class="cat-card-copy">
                <h3 class="cat-card-name"><?= e($c['name']) ?></h3>
                <?php if ($isSosCore): ?>
                  <span class="cat-sos-tag"><?= icon('siren', 12) ?> Mapped to SOS quick button</span>
                <?php endif; ?>
              </div>
            </div>
            <?= prio($pr) ?>
          </div>

          <div class="cat-card-metrics">
            <div class="meter-top">
              <span><strong><?= $tot ?></strong> total &middot; <?= $openCnt ?> open</span>
              <small><?= $sharePct ?>% share</small>
            </div>
            <div class="meter-track"><i class="meter-fill<?= $tot === 0 ? ' tone-muted' : '' ?>" style="width:<?= $tot > 0 ? max(6, $sharePct) : 0 ?>%"></i></div>
          </div>

          <div class="cat-card-foot">
            <a class="btn ghost small" href="reports.php?category=<?= (int) $c['id'] ?>"><?= icon('file', 14) ?> View reports</a>
            <div class="cat-card-actions">
              <button type="button" class="btn ghost small"
                      data-cat-edit
                      data-modal-open="cat-edit-modal"
                      data-id="<?= (int) $c['id'] ?>"
                      data-name="<?= e($c['name']) ?>"
                      data-sos="<?= $isSosCore ? '1' : '0' ?>">
                <?= icon('edit', 14) ?> Rename
              </button>
              <?php if ($tot > 0): ?>
                <span class="cat-inuse-tag" title="Categories with existing reports cannot be deleted"><?= icon('lock', 12) ?> In use</span>
              <?php else: ?>
                <form method="post" data-confirm="Delete the category &quot;<?= e($c['name']) ?>&quot;?">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn danger small" type="submit"><?= icon('trash', 14) ?> Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

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

<div class="modal" id="cat-edit-modal">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="cat-edit-title">
    <div class="modal-head">
      <div class="modal-head-copy">
        <span class="modal-head-ic"><?= icon('tag', 18) ?></span>
        <div>
          <h3 id="cat-edit-title">Rename Category</h3>
          <p>Update the display name and preview its automatic dispatch priority.</p>
        </div>
      </div>
      <button type="button" class="notif-head-ic" data-modal-close aria-label="Close"><?= icon('x', 16) ?></button>
    </div>
    <form class="modal-form" method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="rename">
      <input type="hidden" id="cat-edit-id" name="id" value="">
      <div class="modal-body">
        <label for="cat-edit-name">Category name</label>
        <input id="cat-edit-name" name="name" maxlength="60" required data-autofocus>
        <div class="cat-prio-preview">
          <span>Auto-assigned priority:</span>
          <span class="badge p-medium" id="cat-edit-prio-badge">Medium</span>
        </div>
        <div class="cat-sos-note" id="cat-edit-sos-note" hidden>
          <?= icon('alert', 16) ?>
          <span>This category is mapped to a Resident SOS quick button. Renaming it changes how emergency SOS alerts are prioritized and matched.</span>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
