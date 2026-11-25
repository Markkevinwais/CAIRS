<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $action = (string) ($_POST['action'] ?? 'add');

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = pdo()->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'volunteer'");
        $stmt->execute([$id]);
        setFlash('Volunteer status updated.');
        redirectTo('admin/volunteers.php');
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $newPw = (string) ($_POST['password'] ?? '');
        $role = trim((string) ($_POST['role'] ?? 'volunteer'));
        $allowedRoles = ['volunteer', 'resident', 'admin'];

        if ($id <= 0 || $fullName === '') {
            setFlash('Enter a valid name for the volunteer.', 'error');
        } elseif (!in_array($role, $allowedRoles, true)) {
            setFlash('Choose a valid account role.', 'error');
        } elseif ($newPw !== '' && strlen($newPw) < 6) {
            setFlash('New temporary password must be at least 6 characters.', 'error');
        } else {
            $activeJobs = 0;
            if ($role !== 'volunteer') {
                $chkJobs = pdo()->prepare("SELECT COUNT(*) FROM reports WHERE volunteer_id = ? AND status IN ('assigned', 'in_progress')");
                $chkJobs->execute([$id]);
                $activeJobs = (int) $chkJobs->fetchColumn();
            }
            if ($activeJobs > 0) {
                setFlash("Reassign or close this volunteer's {$activeJobs} active dispatch(es) before changing their role.", 'error');
            } else {
                if ($newPw !== '') {
                    $upd = pdo()->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, role = ?, password = ? WHERE id = ? AND role = 'volunteer'");
                    $upd->execute([$fullName, $phone !== '' ? $phone : null, $address !== '' ? $address : null, $role, password_hash($newPw, PASSWORD_BCRYPT), $id]);
                } else {
                    $upd = pdo()->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, role = ? WHERE id = ? AND role = 'volunteer'");
                    $upd->execute([$fullName, $phone !== '' ? $phone : null, $address !== '' ? $address : null, $role, $id]);
                }
                if ($role === 'resident') {
                    setFlash("{$fullName} moved back to the Residents directory.");
                } elseif ($role === 'admin') {
                    setFlash("{$fullName} granted Administrator access (manage in Settings → Admin Security).");
                } else {
                    setFlash('Volunteer profile updated.');
                }
            }
        }
        redirectTo('admin/volunteers.php');
    }

    $old = [
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'address' => trim((string) ($_POST['address'] ?? '')),
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
        $ins = pdo()->prepare("INSERT INTO users (full_name, email, password, role, phone, address, status) VALUES (?, ?, ?, 'volunteer', ?, ?, 'active')");
        $ins->execute([
            $old['full_name'],
            $old['email'],
            password_hash($pass, PASSWORD_BCRYPT),
            $old['phone'] !== '' ? $old['phone'] : null,
            $old['address'] !== '' ? $old['address'] : null,
        ]);
        setFlash('Volunteer account created and added to the active roster.');
        redirectTo('admin/volunteers.php');
    }
}

$allRows = pdo()->query(
    "SELECT u.*,
            (SELECT COUNT(*) FROM reports r WHERE r.volunteer_id = u.id AND r.status IN ('assigned', 'in_progress')) AS active_jobs,
            (SELECT COUNT(*) FROM reports r WHERE r.volunteer_id = u.id AND r.status = 'resolved') AS done
     FROM users u
     WHERE u.role = 'volunteer'
     ORDER BY u.full_name ASC"
)->fetchAll();

$totalVols = count($allRows);
$availableNow = 0;
$activeFieldTasks = 0;
$totalResolved = 0;
foreach ($allRows as $v) {
    $act = (int) $v['active_jobs'];
    $done = (int) $v['done'];
    $activeFieldTasks += $act;
    $totalResolved += $done;
    if ($v['status'] === 'active' && $act === 0) $availableNow++;
}

$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) $statusFilter = 'all';
$availFilter = (string) ($_GET['availability'] ?? 'all');
if (!in_array($availFilter, ['all', 'available', 'busy'], true)) $availFilter = 'all';

$rows = array_values(array_filter($allRows, function (array $v) use ($q, $statusFilter, $availFilter): bool {
    if ($statusFilter !== 'all' && $v['status'] !== $statusFilter) return false;
    if ($availFilter === 'available' && !($v['status'] === 'active' && (int) $v['active_jobs'] === 0)) return false;
    if ($availFilter === 'busy' && (int) $v['active_jobs'] === 0) return false;
    if ($q !== '') {
        $lq = strtolower($q);
        $hay = strtolower(($v['full_name'] ?? '') . ' ' . ($v['email'] ?? '') . ' ' . ($v['phone'] ?? '') . ' ' . ($v['address'] ?? ''));
        if (strpos($hay, $lq) === false) return false;
    }
    return true;
}));

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cairs-volunteers.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'Email', 'Phone', 'Station / Area', 'Active Tasks', 'Resolved', 'Resolution Rate', 'Status', 'Joined']);
    foreach ($rows as $v) {
        $tot = (int) $v['active_jobs'] + (int) $v['done'];
        $rate = $tot > 0 ? round(((int) $v['done'] / $tot) * 100) . '%' : '0%';
        fputcsv($out, array_map('csvSafe', [
            $v['full_name'],
            $v['email'],
            $v['phone'] ?: '',
            $v['address'] ?: '',
            $v['active_jobs'],
            $v['done'],
            $rate,
            ucfirst((string) $v['status']),
            $v['created_at'],
        ]));
    }
    fclose($out);
    exit;
}

$exportParams = array_filter([
    'q' => $q,
    'status' => $statusFilter !== 'all' ? $statusFilter : '',
    'availability' => $availFilter !== 'all' ? $availFilter : '',
    'export' => '1',
]);
$hasFilters = $q !== '' || $statusFilter !== 'all' || $availFilter !== 'all';

$title = 'Volunteers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Volunteers</h1>
    <p class="page-head-sub">Oversee barangay responders, track active field capacity, and onboard new team members.</p>
  </div>
  <div class="page-actions noprint">
    <a class="btn ghost small" href="?<?= e(http_build_query($exportParams)) ?>"><?= icon('download', 15) ?> Export CSV</a>
    <a class="btn ghost small" href="dispatch.php"><?= icon('radar', 16) ?> Pin &amp; Dispatch</a>
    <button type="button" class="btn small" data-modal-open="vol-add-modal"><?= icon('plus', 16) ?> Add Volunteer</button>
  </div>
</div>

<?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>

<div class="grid grid-4">
  <?= statCard('shield', 'Total responders', $totalVols, 'On community roster', 'primary') ?>
  <?= statCard('check', 'Available now', $availableNow, 'Active with 0 open tasks', 'success') ?>
  <?= statCard('loader', 'Active field tasks', $activeFieldTasks, 'Assigned or in progress', $activeFieldTasks > 0 ? 'warning' : 'muted') ?>
  <?= statCard('chart', 'Incidents resolved', $totalResolved, 'Completed by responders', 'info') ?>
</div>

<form class="filters" method="get">
  <input class="filter-search" name="q" placeholder="Search name, email, phone, or area..." value="<?= e($q) ?>">
  <div class="filter-field">
    <select name="status">
      <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
      <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
  </div>
  <div class="filter-field">
    <select name="availability">
      <option value="all" <?= $availFilter === 'all' ? 'selected' : '' ?>>All availability</option>
      <option value="available" <?= $availFilter === 'available' ? 'selected' : '' ?>>Available (0 tasks)</option>
      <option value="busy" <?= $availFilter === 'busy' ? 'selected' : '' ?>>On active task</option>
    </select>
  </div>
  <button class="btn" type="submit">Filter</button>
  <?php if ($hasFilters): ?><a class="btn ghost" href="volunteers.php">Reset</a><?php endif; ?>
  <div class="filters-end">
    <span class="filters-count"><?= count($rows) ?> of <?= $totalVols ?> shown</span>
    <div class="view-switch" role="group" aria-label="Layout view" data-dir-switch="volunteers">
      <button type="button" class="view-switch-btn is-active" data-view-mode="table" aria-pressed="true"><?= icon('list', 14) ?><span>Table</span></button>
      <button type="button" class="view-switch-btn" data-view-mode="grid" aria-pressed="false"><?= icon('grid', 14) ?><span>Cards</span></button>
    </div>
  </div>
</form>

<div class="card tablewrap<?= $rows ? ' has-mobile-cards' : '' ?>" data-dir-view="volunteers">
<?php if (!$rows): ?>
  <div class="empty-state">
    <span class="empty-state-ic"><?= icon('shield', 22) ?></span>
    <h3><?= $hasFilters ? 'No matching volunteers' : 'No volunteers on roster yet' ?></h3>
    <p><?= $hasFilters ? 'Adjust your search or availability filter to view other responders.' : 'Onboard your first barangay volunteer to start assigning field dispatches.' ?></p>
    <?php if ($hasFilters): ?><a class="btn ghost small" href="volunteers.php">Clear filters</a><?php else: ?><button type="button" class="btn small" data-modal-open="vol-add-modal"><?= icon('plus', 15) ?> Add Volunteer</button><?php endif; ?>
  </div>
<?php else: ?>
<table class="dir-table mobile-cards">
  <thead>
    <tr>
      <th>Responder</th>
      <th>Contact</th>
      <th>Workload &amp; Rate</th>
      <th>Status</th>
      <th class="act">Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $volUser):
    $actCount = (int) $volUser['active_jobs'];
    $doneCount = (int) $volUser['done'];
    $handledTotal = $actCount + $doneCount;
    $resPct = $handledTotal > 0 ? (int) round(($doneCount / $handledTotal) * 100) : 0;
    $meterTone = $handledTotal === 0 ? 'muted' : ($resPct >= 75 ? 'success' : ($resPct >= 40 ? 'warning' : 'danger'));
  ?>
  <tr>
    <td data-label="Responder">
      <div class="user-cell">
        <?= renderAvatar($volUser) ?>
        <div class="user-cell-copy">
          <span class="user-cell-name"><?= e($volUser['full_name']) ?></span>
          <span class="user-cell-meta"><?= e($volUser['address'] ?: 'Barangay Station') ?> &middot; Joined <?= e(fmtDate($volUser['created_at'])) ?></span>
        </div>
      </div>
    </td>
    <td data-label="Contact">
      <div class="contact-stack">
        <span class="contact-line"><?= icon('mail', 14) ?><span><?= e($volUser['email']) ?></span></span>
        <span class="contact-line is-sub is-phone"><?= icon('phone', 14) ?><span><?= e($volUser['phone'] ?: 'No phone saved') ?></span></span>
      </div>
    </td>
    <td data-label="Workload &amp; Rate">
      <div class="meter">
        <div class="meter-top">
          <span><?= $actCount ?> active &middot; <?= $doneCount ?> done</span>
          <small><?= $resPct ?>%</small>
        </div>
        <div class="meter-track"><i class="meter-fill tone-<?= e($meterTone) ?>" style="width:<?= $handledTotal > 0 ? max(8, $resPct) : 0 ?>%"></i></div>
      </div>
    </td>
    <td data-label="Status">
      <span class="badge <?= $volUser['status'] === 'active' ? 's-resolved' : 's-rejected' ?>"><?= e(ucfirst((string) $volUser['status'])) ?></span>
    </td>
    <td class="act" data-label="Actions">
      <div class="row-actions">
        <button type="button" class="btn ghost small"
                data-vol-edit
                data-modal-open="vol-edit-modal"
                data-id="<?= (int) $volUser['id'] ?>"
                data-name="<?= e($volUser['full_name']) ?>"
                data-email="<?= e($volUser['email']) ?>"
                data-phone="<?= e($volUser['phone'] ?? '') ?>"
                data-address="<?= e($volUser['address'] ?? '') ?>"
                data-role="<?= e($volUser['role']) ?>">
          <?= icon('edit', 14) ?> Edit
        </button>
        <?php if ($volUser['status'] === 'active'): ?>
          <a class="btn ghost small" href="dispatch.php"><?= icon('radar', 14) ?> Dispatch</a>
        <?php endif; ?>
        <form method="post" data-confirm="<?= $volUser['status'] === 'active' ? 'Deactivate this volunteer? They will no longer receive assignments.' : 'Reactivate this volunteer account?' ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $volUser['id'] ?>">
          <button class="btn small <?= $volUser['status'] === 'active' ? 'danger' : 'ghost' ?>" type="submit"><?= $volUser['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>

<div class="modal" id="vol-add-modal"<?= $errors ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="vol-add-title">
    <div class="modal-head">
      <div class="modal-head-copy">
        <span class="modal-head-ic"><?= icon('shield', 18) ?></span>
        <div>
          <h3 id="vol-add-title">Onboard a Volunteer</h3>
          <p>Create a responder account so they can log in, view assigned dispatches, and post field updates.</p>
        </div>
      </div>
      <button type="button" class="notif-head-ic" data-modal-close aria-label="Close"><?= icon('x', 16) ?></button>
    </div>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <form class="modal-form" method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add">
      <div class="modal-body">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" required placeholder="e.g. Marco Dela Cruz" value="<?= e($old['full_name']) ?>" data-autofocus>
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" required placeholder="responder@cairs.local" value="<?= e($old['email']) ?>">
        <div class="form-row">
          <div>
            <label for="phone">Mobile number</label>
            <input id="phone" name="phone" placeholder="09XX-XXX-XXXX" value="<?= e($old['phone']) ?>">
          </div>
          <div>
            <label for="address">Assigned area / station</label>
            <input id="address" name="address" placeholder="e.g. Purok 2 Outpost" value="<?= e($old['address']) ?>">
          </div>
        </div>
        <label for="vol-add-password">Temporary password</label>
        <div class="pw-gen-row">
          <div class="pm-input-ic pm-pw-ic">
            <?= icon('key', 16) ?>
            <input type="password" id="vol-add-password" name="password" required minlength="6" placeholder="Min. 6 characters">
            <button type="button" class="pm-eye-btn" data-pm-eye="vol-add-password" aria-label="Show password"><?= icon('eye', 16) ?></button>
          </div>
          <button type="button" class="btn ghost small pw-gen-btn" data-pw-gen="vol-add-password">Generate</button>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn ghost" data-modal-close>Cancel</button>
        <button class="btn" type="submit"><?= icon('plus', 16) ?> Create volunteer account</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="vol-edit-modal">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="vol-edit-title">
    <div class="modal-head">
      <div class="modal-head-copy">
        <span class="modal-head-ic"><?= icon('edit', 18) ?></span>
        <div>
          <h3 id="vol-edit-title">Edit Volunteer Profile</h3>
          <p>Update responder contact details, assigned area, or reset their password.</p>
        </div>
      </div>
      <button type="button" class="notif-head-ic" data-modal-close aria-label="Close"><?= icon('x', 16) ?></button>
    </div>
    <form class="modal-form" method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="edit"><input type="hidden" id="vol-edit-id" name="id" value="">
      <div class="modal-body">
        <label for="vol-edit-name">Full name</label>
        <input id="vol-edit-name" name="full_name" required data-autofocus>
        <div class="form-row">
          <div>
            <label for="vol-edit-email">Account email</label>
            <input id="vol-edit-email" type="email" disabled>
          </div>
          <div>
            <label for="vol-edit-phone">Mobile number</label>
            <input id="vol-edit-phone" name="phone" placeholder="09XX-XXX-XXXX">
          </div>
        </div>
        <label for="vol-edit-address">Assigned area / station</label>
        <input id="vol-edit-address" name="address" placeholder="e.g. Purok 2 Outpost">
        <label for="vol-edit-role">Account role</label>
        <select id="vol-edit-role" name="role">
          <option value="volunteer">Volunteer (Responder)</option>
          <option value="resident">Resident</option>
          <option value="admin">Administrator</option>
        </select>
        <div class="role-change-note" id="vol-edit-role-note" hidden>
          <?= icon('alert', 16) ?>
          <span id="vol-edit-role-msg"></span>
        </div>
        <label for="vol-edit-password">Reset password (optional)</label>
        <div class="pw-gen-row">
          <div class="pm-input-ic pm-pw-ic">
            <?= icon('key', 16) ?>
            <input id="vol-edit-password" type="password" name="password" minlength="6" placeholder="Optional password">
            <button type="button" class="pm-eye-btn" data-pm-eye="vol-edit-password" aria-label="Show password"><?= icon('eye', 16) ?></button>
          </div>
          <button type="button" class="btn ghost small pw-gen-btn" data-pw-gen="vol-edit-password">Generate</button>
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
