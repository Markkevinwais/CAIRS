<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');
$uid = (int) $user['id'];

$cats = pdo()->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
$vols = pdo()->query(
    "SELECT u.id, u.full_name, u.phone,
            SUM(CASE WHEN r.status IN ('assigned','in_progress') THEN 1 ELSE 0 END) AS active_jobs
     FROM users u
     LEFT JOIN reports r ON r.volunteer_id = u.id
     WHERE u.role = 'volunteer' AND u.status = 'active'
     GROUP BY u.id, u.full_name, u.phone
     ORDER BY u.full_name ASC"
)->fetchAll();

$errors = [];
$old = [
    'volunteer_id' => '',
    'category_id' => '',
    'title' => '',
    'location' => '',
    'priority' => 'high',
    'description' => '',
    'latitude' => '',
    'longitude' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $old = [
        'volunteer_id' => trim((string) ($_POST['volunteer_id'] ?? '')),
        'category_id' => trim((string) ($_POST['category_id'] ?? '')),
        'title' => trim((string) ($_POST['title'] ?? '')),
        'location' => trim((string) ($_POST['location'] ?? '')),
        'priority' => trim((string) ($_POST['priority'] ?? 'high')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'latitude' => trim((string) ($_POST['latitude'] ?? '')),
        'longitude' => trim((string) ($_POST['longitude'] ?? '')),
    ];

    $volObj = null;
    foreach ($vols as $v) {
        if ((string) $v['id'] === $old['volunteer_id']) {
            $volObj = $v;
            break;
        }
    }
    if (!$volObj) {
        $errors[] = 'Choose an active volunteer to send.';
    }

    $catObj = null;
    foreach ($cats as $c) {
        if ((string) $c['id'] === $old['category_id']) {
            $catObj = $c;
            break;
        }
    }
    if (!$catObj) {
        $errors[] = 'Choose an incident category.';
    }

    if ($old['title'] === '') {
        $errors[] = 'Enter a short title or task for the responder.';
    }
    if (!in_array($old['priority'], ['low', 'medium', 'high'], true)) {
        $old['priority'] = 'high';
    }

    $hasLat = $old['latitude'] !== '' && is_numeric($old['latitude']);
    $hasLng = $old['longitude'] !== '' && is_numeric($old['longitude']);
    $lat = $hasLat ? (float) $old['latitude'] : null;
    $lng = $hasLng ? (float) $old['longitude'] : null;
    if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
        $errors[] = 'Click on the map to place a pin for the volunteer.';
    }

    if (!$errors && $volObj && $catObj && $lat !== null && $lng !== null) {
        $locText = $old['location'] !== ''
            ? $old['location']
            : 'Pinned location (' . number_format($lat, 5) . ', ' . number_format($lng, 5) . ')';
        $descText = $old['description'] !== ''
            ? $old['description']
            : 'Dispatched to pinned coordinates by ' . $user['full_name'] . '.';

        $stmt = pdo()->prepare(
            'INSERT INTO reports (resident_id, category_id, volunteer_id, title, description, location, latitude, longitude, photo, priority, status, is_sos)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 0)'
        );
        $stmt->execute([
            $uid,
            (int) $catObj['id'],
            (int) $volObj['id'],
            $old['title'],
            $descText,
            $locText,
            $lat,
            $lng,
            $old['priority'],
            'assigned',
        ]);
        $rid = (int) pdo()->lastInsertId();

        $remark = 'Assigned to ' . $volObj['full_name'] . ' via Pin & Dispatch.';
        if ($old['description'] !== '') {
            $remark .= ' ' . $old['description'];
        }
        logUpdate($rid, $uid, 'assigned', $remark);
        notify((int) $volObj['id'], $rid, 'Dispatch: You were assigned to ' . refNo($rid) . ' — ' . $old['title']);

        setFlash('Dispatched ' . $volObj['full_name'] . ' to ' . refNo($rid) . '.');
        redirectTo('admin/report_details.php?id=' . $rid);
    }
}

$pinnedRows = pdo()->query('SELECT id, title, status, latitude, longitude FROM reports WHERE latitude IS NOT NULL AND longitude IS NOT NULL')->fetchAll();
$pts = [];
foreach ($pinnedRows as $p) {
    $pts[] = [
        'lat' => (float) $p['latitude'],
        'lng' => (float) $p['longitude'],
        'ref' => refNo($p['id']),
        'title' => $p['title'],
        'key' => $p['status'],
        'status' => statusLabel($p['status']),
        'url' => BASE_URL . '/admin/report_details.php?id=' . (int) $p['id'],
    ];
}
[$mlat, $mlng] = mapCenter();
$hasOldPin = $old['latitude'] !== '' && $old['longitude'] !== '' && is_numeric($old['latitude']) && is_numeric($old['longitude']);

$title = 'Pin & Dispatch';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Pin &amp; Dispatch</h1>
    <p class="page-head-sub">Click anywhere on the map to drop a pin and immediately send a volunteer to that location.</p>
  </div>
  <div class="page-actions">
    <a class="btn ghost small" href="analytics.php#incident-map">View Incident map</a>
    <a class="btn ghost small" href="reports.php">All reports</a>
  </div>
</div>

<div class="two">
  <section class="card map-card" id="dispatch-map-card">
    <div class="map-head">
      <div>
        <h2>Select dispatch location</h2>
        <p class="muted">Click, search, or drag the pin to set the responder's destination</p>
      </div>
      <div class="legend">
        <span class="legend-item"><i class="dot tone-primary"></i>Dispatch pin</span>
        <span class="legend-item"><i class="dot dot-pending"></i>Pending</span>
        <span class="legend-item"><i class="dot dot-assigned"></i>Active</span>
        <label class="legend-toggle"><input type="checkbox" id="dispatch-show-closed"><span>Show resolved</span></label>
      </div>
    </div>
    <?= mapAssets() ?>
    <div id="dispatch-map" class="map dispatch-map"
         data-lat="<?= e($hasOldPin ? $old['latitude'] : $mlat) ?>"
         data-lng="<?= e($hasOldPin ? $old['longitude'] : $mlng) ?>"
         data-center-lat="<?= e($mlat) ?>"
         data-center-lng="<?= e($mlng) ?>"
         data-pin="<?= $hasOldPin ? '1' : '0' ?>"
         data-points="<?= e(json_encode($pts)) ?>"></div>
    <div class="map-foot">
      <div class="dispatch-map-btns">
        <button type="button" id="dispatch-locate" class="btn ghost small"><?= icon('pin', 15) ?> Use my location</button>
        <button type="button" id="dispatch-recenter" class="btn ghost small"><?= icon('radar', 15) ?> Recenter</button>
        <button type="button" id="dispatch-clear" class="btn ghost small"<?= $hasOldPin ? '' : ' hidden' ?>>Clear pin</button>
      </div>
      <span class="dispatch-coords<?= $hasOldPin ? ' is-pinned' : '' ?>" id="dispatch-coords" aria-live="polite">
        <?= $hasOldPin
            ? 'Pinned: ' . e(number_format((float) $old['latitude'], 5) . ', ' . number_format((float) $old['longitude'], 5))
            : 'No pin placed yet — click the map' ?>
      </span>
    </div>
  </section>

  <div class="card">
    <h2>Send a volunteer</h2>
    <p class="muted" style="margin-bottom:14px">Creates an assigned dispatch ticket with your map pin and notifies the responder right away.</p>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <?php if (!$vols): ?>
      <div class="flash error">No active volunteers available. <a href="volunteers.php">Add or activate a volunteer</a> first.</div>
    <?php endif; ?>
    <form method="post" id="dispatch-form">
      <?= csrfField() ?>
      <input type="hidden" id="dispatch-lat" name="latitude" value="<?= e($hasOldPin ? $old['latitude'] : '') ?>">
      <input type="hidden" id="dispatch-lng" name="longitude" value="<?= e($hasOldPin ? $old['longitude'] : '') ?>">

      <div class="dispatch-target-box<?= $hasOldPin ? ' is-pinned' : '' ?>" id="dispatch-pin-status">
        <div class="dispatch-target-copy">
          <span class="dispatch-target-ic"><?= icon('pin', 15) ?></span>
          <div class="dispatch-target-text">
            <strong id="dispatch-pin-heading"><?= $hasOldPin ? 'Dispatch pin ready' : 'Location pin required' ?></strong>
            <span id="dispatch-pin-summary"><?= $hasOldPin ? e(number_format((float) $old['latitude'], 5) . ', ' . number_format((float) $old['longitude'], 5)) : 'Click or search on the map to drop a pin' ?></span>
          </div>
        </div>
        <button type="button" class="btn ghost small" id="dispatch-jump-map"><?= $hasOldPin ? 'Adjust pin' : 'Select on map' ?></button>
      </div>

      <label for="volunteer_id">Volunteer to send</label>
      <select id="volunteer_id" name="volunteer_id" required>
        <option value="">Choose an active volunteer</option>
        <?php foreach ($vols as $v): ?>
          <option value="<?= (int) $v['id'] ?>" <?= $old['volunteer_id'] === (string) $v['id'] ? 'selected' : '' ?>>
            <?= e($v['full_name']) ?> (<?= (int) $v['active_jobs'] ?> active)
          </option>
        <?php endforeach; ?>
      </select>

      <label for="category_id">Category</label>
      <select id="category_id" name="category_id" required>
        <option value="">Choose category</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $old['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="title">Incident / Task title</label>
      <input id="title" name="title" maxlength="150" placeholder="e.g. Fallen tree blocking road" required value="<?= e($old['title']) ?>">

      <label for="priority">Priority</label>
      <select id="priority" name="priority">
        <?php foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $pk => $pl): ?>
          <option value="<?= e($pk) ?>" <?= $old['priority'] === $pk ? 'selected' : '' ?>><?= e($pl) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="location">Location / Landmark (optional)</label>
      <input id="location" name="location" maxlength="255" placeholder="Street, purok, or nearby landmark" value="<?= e($old['location']) ?>">

      <label for="description">Instructions for volunteer (optional)</label>
      <textarea id="description" name="description" style="min-height:86px" placeholder="Any details, tools needed, or contact info..."><?= e($old['description']) ?></textarea>

      <p style="margin-top:16px">
        <button class="btn" type="submit" style="width:100%"<?= !$vols ? ' disabled' : '' ?>>
          <?= icon('radar', 17) ?> Pin &amp; Send Volunteer
        </button>
      </p>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
