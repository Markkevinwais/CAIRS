<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $action = (string) ($_POST['action'] ?? 'toggle');

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = pdo()->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'resident'");
        $stmt->execute([$id]);
        setFlash('Resident account status updated.');
        redirectTo('admin/residents.php');
    } elseif ($action === 'add') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');

        if ($fullName === '') $errors[] = "Enter the resident's full name.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if ($address === '') $errors[] = 'Enter the resident address or purok.';
        if (strlen($pass) < 6) $errors[] = 'Temporary password must be at least 6 characters.';

        if (!$errors) {
            $chk = pdo()->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $errors[] = 'That email address is already registered.';
            }
        }
        if (!$errors) {
            $ins = pdo()->prepare("INSERT INTO users (full_name, email, password, role, phone, address, status) VALUES (?, ?, ?, 'resident', ?, ?, 'active')");
            $ins->execute([
                $fullName,
                $email,
                password_hash($pass, PASSWORD_BCRYPT),
                $phone !== '' ? $phone : null,
                $address,
            ]);
            setFlash('Resident account registered.');
            redirectTo('admin/residents.php');
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if ($id <= 0 || $fullName === '') {
            setFlash('Enter a valid full name for the resident.', 'error');
        } else {
            $upd = pdo()->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ? AND role = 'resident'");
            $upd->execute([
                $fullName,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $id,
            ]);
            setFlash('Resident profile updated.');
        }
        redirectTo('admin/residents.php');
    }
}

$allRows = pdo()->query(
    "SELECT u.*,
            (SELECT COUNT(*) FROM reports r WHERE r.resident_id = u.id) AS total,
            (SELECT COUNT(*) FROM reports r WHERE r.resident_id = u.id AND r.status IN ('pending', 'assigned', 'in_progress')) AS open_reports,
            (SELECT COUNT(*) FROM reports r WHERE r.resident_id = u.id AND r.status = 'resolved') AS resolved_reports
     FROM users u
     WHERE u.role = 'resident'
     ORDER BY u.created_at DESC"
)->fetchAll();

$totalCount = count($allRows);
$activeCount = 0;
$inactiveCount = 0;
$totalReportsFiled = 0;
foreach ($allRows as $r) {
    if ($r['status'] === 'active') $activeCount++;
    else $inactiveCount++;
    $totalReportsFiled += (int) $r['total'];
}

$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) $statusFilter = 'all';
$sort = (string) ($_GET['sort'] ?? 'newest');
if (!in_array($sort, ['newest', 'name', 'reports'], true)) $sort = 'newest';

$rows = array_values(array_filter($allRows, function (array $r) use ($q, $statusFilter): bool {
    if ($statusFilter !== 'all' && $r['status'] !== $statusFilter) return false;
    if ($q !== '') {
        $lq = strtolower($q);
        $hay = strtolower(($r['full_name'] ?? '') . ' ' . ($r['email'] ?? '') . ' ' . ($r['phone'] ?? '') . ' ' . ($r['address'] ?? ''));
        if (strpos($hay, $lq) === false) return false;
    }
    return true;
}));

usort($rows, function (array $a, array $b) use ($sort): int {
    if ($sort === 'name') return strcasecmp((string) $a['full_name'], (string) $b['full_name']);
    if ($sort === 'reports') return ((int) $b['total'] <=> (int) $a['total']) ?: strcmp((string) $b['created_at'], (string) $a['created_at']);
    return strcmp((string) $b['created_at'], (string) $a['created_at']);
});

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cairs-residents.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'Email', 'Phone', 'Address', 'Total Reports', 'Open Reports', 'Resolved Reports', 'Status', 'Joined']);
    foreach ($rows as $r) {
        fputcsv($out, array_map('csvSafe', [
            $r['full_name'],
            $r['email'],
            $r['phone'] ?: '',
            $r['address'] ?: '',
            $r['total'],
            $r['open_reports'],
            $r['resolved_reports'],
            ucfirst((string) $r['status']),
            $r['created_at'],
        ]));
    }
    fclose($out);
    exit;
}

$exportParams = array_filter(['q' => $q, 'status' => $statusFilter !== 'all' ? $statusFilter : '', 'sort' => $sort !== 'newest' ? $sort : '', 'export' => '1']);
$hasFilters = $q !== '' || $statusFilter !== 'all' || $sort !== 'newest';

$title = 'Residents';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Residents</h1>
    <p class="page-head-sub">Manage community member accounts, verify contact info, and track incident submissions.</p>
  </div>
  <div class="page-actions noprint">
    <a class="btn ghost small" href="?<?= e(http_build_query($exportParams)) ?>"><?= icon('download', 15) ?> Export CSV</a>
    <button type="button" class="btn small" data-modal-open="res-add-modal"><?= icon('plus', 16) ?> Register Resident</button>
  </div>
</div>

<?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>

<div class="grid grid-4">
  <?= statCard('users', 'Total residents', $totalCount, 'Registered community accounts', 'primary') ?>
  <?= statCard('check', 'Active accounts', $activeCount, 'Verified & able to file reports', 'success') ?>
  <?= statCard('alert', 'Inactive accounts', $inactiveCount, 'Deactivated or suspended', $inactiveCount > 0 ? 'warning' : 'muted') ?>
  <?= statCard('file', 'Reports filed', $totalReportsFiled, 'Total community submissions', 'info') ?>
</div>

<form class="filters" method="get">
  <input class="filter-search" name="q" placeholder="Search name, email, phone, or purok..." value="<?= e($q) ?>">
  <div class="filter-field">
    <select name="status">
      <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
      <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active only</option>
      <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
    </select>
  </div>
  <div class="filter-field">
    <select name="sort">
      <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest joined</option>
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name (A–Z)</option>
      <option value="reports" <?= $sort === 'reports' ? 'selected' : '' ?>>Most reports</option>
    </select>
  </div>
  <button class="btn" type="submit">Filter</button>
  <?php if ($hasFilters): ?><a class="btn ghost" href="residents.php">Reset</a><?php endif; ?>
  <span class="filters-count"><?= count($rows) ?> of <?= $totalCount ?> shown</span>
</form>

<div class="card tablewrap<?= $rows ? ' has-mobile-cards' : '' ?>">
<?php if (!$rows): ?>
  <div class="empty-state">
    <span class="empty-state-ic"><?= icon('users', 22) ?></span>
    <h3><?= $hasFilters ? 'No matching residents' : 'No residents registered yet' ?></h3>
    <p><?= $hasFilters ? 'Try clearing or adjusting your search query and status filter.' : 'Register a community member manually or wait for residents to sign up.' ?></p>
    <?php if ($hasFilters): ?><a class="btn ghost small" href="residents.php">Clear filters</a><?php else: ?><button type="button" class="btn small" data-modal-open="res-add-modal"><?= icon('plus', 15) ?> Register Resident</button><?php endif; ?>
  </div>
<?php else: ?>
<table class="dir-table mobile-cards">
  <thead>
    <tr>
      <th>Resident</th>
      <th>Contact</th>
      <th>Address / Purok</th>
      <th class="num">Reports</th>
      <th>Status</th>
      <th class="act">Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $resUser): ?>
    <tr>
      <td data-label="Resident">
        <div class="user-cell">
          <?= renderAvatar($resUser) ?>
          <div class="user-cell-copy">
            <span class="user-cell-name"><?= e($resUser['full_name']) ?></span>
            <span class="user-cell-meta">Joined <?= e(fmtDate($resUser['created_at'])) ?></span>
          </div>
        </div>
      </td>
      <td data-label="Contact">
        <div class="contact-stack">
          <span class="contact-line"><?= icon('mail', 14) ?><span><?= e($resUser['email']) ?></span></span>
          <span class="contact-line is-sub is-phone"><?= icon('phone', 14) ?><span><?= e($resUser['phone'] ?: 'No phone saved') ?></span></span>
        </div>
      </td>
      <td data-label="Address / Purok">
        <span class="contact-line"><?= icon('pin', 14) ?><span><?= e($resUser['address'] ?: 'Not specified') ?></span></span>
      </td>
      <td class="num" data-label="Reports">
        <div class="table-num-stack">
          <strong><?= (int) $resUser['total'] ?></strong>
          <?php if ((int) $resUser['total'] > 0): ?>
            <a href="reports.php?q=<?= e(urlencode((string) $resUser['full_name'])) ?>"><?= (int) $resUser['open_reports'] ?> open &middot; <?= (int) $resUser['resolved_reports'] ?> done</a>
          <?php else: ?>
            <small>No reports</small>
          <?php endif; ?>
        </div>
      </td>
      <td data-label="Status">
        <span class="badge <?= $resUser['status'] === 'active' ? 's-resolved' : 's-rejected' ?>"><?= e(ucfirst((string) $resUser['status'])) ?></span>
      </td>
      <td class="act" data-label="Actions">
        <div class="row-actions">
          <button type="button" class="btn ghost small"
                  data-res-edit
                  data-modal-open="res-edit-modal"
                  data-id="<?= (int) $resUser['id'] ?>"
                  data-name="<?= e($resUser['full_name']) ?>"
                  data-email="<?= e($resUser['email']) ?>"
                  data-phone="<?= e($resUser['phone'] ?? '') ?>"
                  data-address="<?= e($resUser['address'] ?? '') ?>">
            <?= icon('edit', 14) ?> Edit
          </button>
          <form method="post" data-confirm="<?= $resUser['status'] === 'active' ? 'Deactivate this resident account? They will not be able to log in.' : 'Reactivate this resident account?' ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int) $resUser['id'] ?>">
            <button class="btn small <?= $resUser['status'] === 'active' ? 'danger' : 'ghost' ?>" type="submit"><?= $resUser['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
          </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>

<div class="modal" id="res-add-modal">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="res-add-title">
    <div class="modal-head">
      <div>
        <h3 id="res-add-title">Register Resident Account</h3>
        <p>Create a verified resident profile for community reporting and SOS access.</p>
      </div>
      <button type="button" class="notif-head-ic" data-modal-close aria-label="Close"><?= icon('x', 16) ?></button>
    </div>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add">
      <label for="res-add-name">Full name</label>
      <input id="res-add-name" name="full_name" required placeholder="e.g. Maria Clara Santos" data-autofocus>
      <div class="form-row">
        <div>
          <label for="res-add-email">Email address</label>
          <input type="email" id="res-add-email" name="email" required placeholder="resident@example.com">
        </div>
        <div>
          <label for="res-add-phone">Mobile number</label>
          <input id="res-add-phone" name="phone" placeholder="09XX-XXX-XXXX">
        </div>
      </div>
      <label for="res-add-address">Address / Purok</label>
      <input id="res-add-address" name="address" required placeholder="e.g. Purok 4, Rizal Street">
      <label for="res-add-password">Temporary password</label>
      <div class="pw-gen-row">
        <div class="pm-input-ic pm-pw-ic">
          <?= icon('key', 16) ?>
          <input id="res-add-password" type="password" name="password" required minlength="6" placeholder="Minimum 6 characters">
          <button type="button" class="pm-eye-btn" data-pm-eye="res-add-password" aria-label="Show password"><?= icon('eye', 16) ?></button>
        </div>
        <button type="button" class="btn ghost small pw-gen-btn" data-pw-gen="res-add-password">Generate</button>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn">Register Resident</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="res-edit-modal">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="res-edit-title">
    <div class="modal-head">
      <div>
        <h3 id="res-edit-title">Edit Resident Details</h3>
        <p>Update resident contact details and purok information.</p>
      </div>
      <button type="button" class="notif-head-ic" data-modal-close aria-label="Close"><?= icon('x', 16) ?></button>
    </div>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="edit"><input type="hidden" id="res-edit-id" name="id" value="">
      <label for="res-edit-name">Full name</label>
      <input id="res-edit-name" name="full_name" required data-autofocus>
      <div class="form-row">
        <div>
          <label for="res-edit-email">Account email</label>
          <input id="res-edit-email" type="email" disabled>
        </div>
        <div>
          <label for="res-edit-phone">Mobile number</label>
          <input id="res-edit-phone" name="phone" placeholder="09XX-XXX-XXXX">
        </div>
      </div>
      <label for="res-edit-address">Address / Purok</label>
      <input id="res-edit-address" name="address" placeholder="e.g. Purok 5">
      <div class="modal-foot">
        <button type="button" class="btn ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
