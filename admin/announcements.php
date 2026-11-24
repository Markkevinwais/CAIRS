<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');
$uid = (int) $user['id'];

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
        setFlash('Announcement visibility updated.');
        redirectTo('admin/announcements.php');
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = pdo()->prepare('DELETE FROM announcements WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('Announcement deleted.');
        redirectTo('admin/announcements.php');
    }
    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $tt = trim((string) ($_POST['title'] ?? ''));
        $bd = trim((string) ($_POST['body'] ?? ''));
        $lv = trim((string) ($_POST['level'] ?? 'info'));

        if ($id <= 0 || $tt === '' || $bd === '' || mb_strlen($tt) > 150 || mb_strlen($bd) > 2000 || !in_array($lv, $levels, true)) {
            setFlash('Please provide a valid title, severity level, and message.', 'error');
        } else {
            $upd = pdo()->prepare('UPDATE announcements SET title = ?, body = ?, level = ? WHERE id = ?');
            $upd->execute([$tt, $bd, $lv, $id]);
            setFlash('Announcement updated.');
        }
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
    if (!in_array($old['level'], $levels, true)) $errors[] = 'Choose a severity level.';

    if (!$errors) {
        $ins = pdo()->prepare('INSERT INTO announcements (user_id, title, body, level, is_active) VALUES (?, ?, ?, ?, 1)');
        $ins->execute([$uid, $old['title'], $old['body'], $old['level']]);
        setFlash('Announcement published to active dashboards.');
        redirectTo('admin/announcements.php');
    }
}

$allRows = pdo()->query(
    'SELECT a.*, COALESCE(u.full_name, "Admin") AS full_name
     FROM announcements a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.id DESC'
)->fetchAll();

$totalCount = count($allRows);
$liveCount = 0;
$hiddenCount = 0;
$criticalCount = 0;
foreach ($allRows as $a) {
    if (!empty($a['is_active'])) $liveCount++;
    else $hiddenCount++;
    if (($a['level'] ?? '') === 'critical') $criticalCount++;
}

$q = trim((string) ($_GET['q'] ?? ''));
$levelFilter = (string) ($_GET['level'] ?? 'all');
if (!in_array($levelFilter, ['all', 'info', 'warning', 'critical'], true)) $levelFilter = 'all';
$visFilter = (string) ($_GET['visibility'] ?? 'all');
if (!in_array($visFilter, ['all', 'active', 'hidden'], true)) $visFilter = 'all';

$rows = array_values(array_filter($allRows, function (array $a) use ($q, $levelFilter, $visFilter): bool {
    if ($levelFilter !== 'all' && $a['level'] !== $levelFilter) return false;
    if ($visFilter === 'active' && empty($a['is_active'])) return false;
    if ($visFilter === 'hidden' && !empty($a['is_active'])) return false;
    if ($q !== '') {
        $lq = strtolower($q);
        $hay = strtolower(($a['title'] ?? '') . ' ' . ($a['body'] ?? '') . ' ' . ($a['full_name'] ?? ''));
        if (strpos($hay, $lq) === false) return false;
    }
    return true;
}));

$hasFilters = $q !== '' || $levelFilter !== 'all' || $visFilter !== 'all';

$title = 'Announcements';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Announcements</h1>
    <p class="page-head-sub">Publish barangay advisories shown on the home page and resident/volunteer dashboards.</p>
  </div>
</div>

<div class="grid grid-4">
  <?= statCard('megaphone', 'Total notices', $totalCount, 'Published advisories', 'primary') ?>
  <?= statCard('check', 'Live on dashboards', $liveCount, 'Visible to community members', 'success') ?>
  <?= statCard('eye', 'Hidden notices', $hiddenCount, 'Archived or paused', $hiddenCount > 0 ? 'warning' : 'muted') ?>
  <?= statCard('siren', 'Critical alerts', $criticalCount, 'High-urgency broadcasts', $criticalCount > 0 ? 'danger' : 'muted') ?>
</div>

<form class="filters" method="get">
  <input class="filter-search" name="q" placeholder="Search announcement headline or message..." value="<?= e($q) ?>">
  <div class="filter-field">
    <select name="level">
      <option value="all" <?= $levelFilter === 'all' ? 'selected' : '' ?>>All severities</option>
      <option value="info" <?= $levelFilter === 'info' ? 'selected' : '' ?>>Info</option>
      <option value="warning" <?= $levelFilter === 'warning' ? 'selected' : '' ?>>Warning</option>
      <option value="critical" <?= $levelFilter === 'critical' ? 'selected' : '' ?>>Critical</option>
    </select>
  </div>
  <div class="filter-field">
    <select name="visibility">
      <option value="all" <?= $visFilter === 'all' ? 'selected' : '' ?>>All visibility</option>
      <option value="active" <?= $visFilter === 'active' ? 'selected' : '' ?>>Live only</option>
      <option value="hidden" <?= $visFilter === 'hidden' ? 'selected' : '' ?>>Hidden only</option>
    </select>
  </div>
  <button class="btn" type="submit">Filter</button>
  <?php if ($hasFilters): ?><a class="btn ghost" href="announcements.php">Reset</a><?php endif; ?>
  <span class="filters-count"><?= count($rows) ?> of <?= $totalCount ?> shown</span>
</form>

<div class="two">
  <div>
    <?php if (!$rows): ?>
      <div class="card">
        <div class="empty-state">
          <span class="empty-state-ic"><?= icon('megaphone', 22) ?></span>
          <h3><?= $hasFilters ? 'No announcements match your filter' : 'No announcements published yet' ?></h3>
          <p><?= $hasFilters ? 'Try resetting your severity or visibility filters.' : 'Use the composer on the right to post your first barangay advisory.' ?></p>
          <?php if ($hasFilters): ?><a class="btn ghost small" href="announcements.php">Clear filters</a><?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="ann-feed-head">
        <h2>Advisory Feed</h2>
        <span class="muted">Newest displayed first</span>
      </div>
      <?php foreach ($rows as $a):
        $isActive = !empty($a['is_active']);
        $lvl = (string) ($a['level'] ?? 'info');
      ?>
      <article class="ann ann-<?= e($lvl) ?><?= $isActive ? '' : ' is-off' ?>">
        <span class="ann-ic"><?= icon($lvl === 'critical' ? 'siren' : 'megaphone', 20) ?></span>
        <div style="flex:1;min-width:0">
          <div class="ann-card-head">
            <div class="ann-card-title">
              <strong><?= e($a['title']) ?></strong>
            </div>
            <span class="ann-state-tag<?= $isActive ? '' : ' is-hidden' ?>"><?= $isActive ? 'Live' : 'Hidden' ?></span>
          </div>
          <p><?= nl2br_safe($a['body']) ?></p>
          <div class="ann-meta-row">
            <span class="ann-level-tag lvl-<?= e($lvl) ?>"><?= e(ucfirst($lvl)) ?></span>
            <span class="sep">&middot;</span>
            <span><?= e(fmtDate($a['created_at'])) ?></span>
            <span class="sep">&middot;</span>
            <span>By <?= e($a['full_name']) ?></span>
          </div>
          <div class="ann-actions">
            <button type="button" class="btn ghost small"
                    data-ann-edit
                    data-modal-open="ann-edit-modal"
                    data-id="<?= (int) $a['id'] ?>"
                    data-title="<?= e($a['title']) ?>"
                    data-level="<?= e($lvl) ?>"
                    data-body="<?= e($a['body']) ?>">
              <?= icon('edit', 14) ?> Edit
            </button>
            <form method="post"><?= csrfField() ?>
              <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="btn ghost small" type="submit"><?= $isActive ? 'Hide' : 'Show again' ?></button>
            </form>
            <form method="post" data-confirm="Delete this announcement permanently?"><?= csrfField() ?>
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="btn danger small" type="submit">Delete</button>
            </form>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card sticky-card">
    <h2>Post an announcement</h2>
    <p class="card-sub">Broadcast notices to residents and volunteers. Preview updates live below.</p>
    <?php foreach ($errors as $er): ?><div class="flash error" style="margin-top:12px"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add">
      <div class="label-row">
        <label for="ann-composer-title">Headline</label>
        <span class="char-count" id="ann-title-count">0 / 150</span>
      </div>
      <input id="ann-composer-title" name="title" maxlength="150" required placeholder="e.g. Scheduled Water Interruption — Zone 3" value="<?= e($old['title']) ?>">
      <label for="ann-composer-level">Severity level</label>
      <select id="ann-composer-level" name="level">
        <?php foreach ($levels as $l): ?><option value="<?= e($l) ?>" <?= $old['level'] === $l ? 'selected' : '' ?>><?= e(ucfirst($l)) ?></option><?php endforeach; ?>
      </select>
      <div class="label-row">
        <label for="ann-composer-body">Message</label>
        <span class="char-count" id="ann-body-count">0 / 2000</span>
      </div>
      <textarea id="ann-composer-body" name="body" maxlength="2000" required style="min-height:110px" placeholder="Include affected puroks, time window, and safety instructions..."><?= e($old['body']) ?></textarea>

      <div class="ann-preview-box">
        <div class="ann-preview-head">
          <span>Live Resident Preview</span>
          <span id="ann-pv-level">Info</span>
        </div>
        <article class="ann ann-info" id="ann-live-preview">
          <span class="ann-ic" id="ann-pv-ic"><?= icon('megaphone', 20) ?></span>
          <div style="min-width:0">
            <strong id="ann-pv-title">Your announcement headline will appear here</strong>
            <p id="ann-pv-body">Write a concise notice for residents and responders. Active alerts display at the top of dashboards.</p>
            <span class="muted">Just now &middot; <?= e($user['full_name']) ?></span>
          </div>
        </article>
      </div>

      <p style="margin-top:16px;margin-bottom:0"><button class="btn" type="submit" style="width:100%"><?= icon('megaphone', 16) ?> Publish announcement</button></p>
    </form>
  </div>
</div>

<div class="modal" id="ann-edit-modal">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="ann-edit-modal-title">
    <div class="modal-head">
      <div>
        <h3 id="ann-edit-modal-title">Edit Announcement</h3>
        <p>Update headline, severity level, or advisory content.</p>
      </div>
      <button type="button" class="notif-head-ic" data-modal-close aria-label="Close"><?= icon('x', 16) ?></button>
    </div>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="edit"><input type="hidden" id="ann-edit-id" name="id" value="">
      <label for="ann-edit-title">Headline</label>
      <input id="ann-edit-title" name="title" maxlength="150" required data-autofocus>
      <label for="ann-edit-level">Severity level</label>
      <select id="ann-edit-level" name="level">
        <?php foreach ($levels as $l): ?><option value="<?= e($l) ?>"><?= e(ucfirst($l)) ?></option><?php endforeach; ?>
      </select>
      <label for="ann-edit-body">Message</label>
      <textarea id="ann-edit-body" name="body" maxlength="2000" required style="min-height:130px"></textarea>
      <div class="modal-foot">
        <button type="button" class="btn ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
