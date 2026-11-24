<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('resident');
$uid = (int) $user['id'];

$cats = pdo()->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
$errors = [];
$old = ['category_id' => '', 'title' => '', 'description' => '', 'location' => '', 'latitude' => '', 'longitude' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $old = [
        'category_id' => trim((string) ($_POST['category_id'] ?? '')),
        'title' => trim((string) ($_POST['title'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'location' => trim((string) ($_POST['location'] ?? '')),
        'latitude' => trim((string) ($_POST['latitude'] ?? '')),
        'longitude' => trim((string) ($_POST['longitude'] ?? '')),
    ];

    $cat = null;
    foreach ($cats as $c) {
        if ((string) $c['id'] === $old['category_id']) {
            $cat = $c;
            break;
        }
    }
    if (!$cat) $errors[] = 'Choose a category.';
    if ($old['title'] === '') $errors[] = 'Enter a short title.';
    if (mb_strlen($old['description']) < 10) $errors[] = 'Describe the incident in at least 10 characters.';
    if ($old['location'] === '') $errors[] = 'Enter where it happened.';

    $hasLat = $old['latitude'] !== '' && is_numeric($old['latitude']);
    $hasLng = $old['longitude'] !== '' && is_numeric($old['longitude']);
    $lat = $hasLat ? (float) $old['latitude'] : null;
    $lng = $hasLng ? (float) $old['longitude'] : null;
    if (($lat === null) !== ($lng === null) || ($lat !== null && (abs($lat) > 90 || abs($lng) > 180))) {
        $errors[] = 'The map pin is not valid. Click the map to place it again.';
    }

    $photoFilename = null;
    if (!empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $fileErr = $_FILES['photo']['error'] ?? UPLOAD_ERR_OK;
        if ($fileErr === UPLOAD_ERR_INI_SIZE || $fileErr === UPLOAD_ERR_FORM_SIZE || ($_FILES['photo']['size'] ?? 0) > 3 * 1024 * 1024) {
            $errors[] = 'Photo must be 3 MB or smaller.';
        } elseif ($fileErr !== UPLOAD_ERR_OK) {
            $errors[] = 'Photo upload failed.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['photo']['tmp_name']);
            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extMap[$mime])) {
                $errors[] = 'Photo must be a JPG, PNG or WEBP image.';
            } else {
                $photoFilename = bin2hex(random_bytes(8)) . '.' . $extMap[$mime];
            }
        }
    }

    if (!$errors) {
        if ($photoFilename !== null) {
            $uploadDir = __DIR__ . '/../assets/uploads';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . '/' . $photoFilename);
        }

        $stmt = pdo()->prepare(
            'INSERT INTO reports (resident_id, category_id, title, description, location, latitude, longitude, photo, priority, status, is_sos)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([
            $uid,
            (int) $cat['id'],
            $old['title'],
            $old['description'],
            $old['location'],
            $lat,
            $lng,
            $photoFilename,
            defaultPriority($cat['name']),
            'pending',
        ]);
        $rid = (int) pdo()->lastInsertId();
        logUpdate($rid, $uid, 'pending', 'Report submitted.');

        $admins = pdo()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
        foreach ($admins as $a) {
            notify((int) $a['id'], $rid, 'New report ' . refNo($rid) . ': ' . $old['title']);
        }
        setFlash('Report submitted as ' . refNo($rid) . '. We will notify you of any update.');
        redirectTo('resident/report_details.php?id=' . $rid);
    }
}

$title = 'Report an incident';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Report an incident</h1>
    <p class="page-head-sub">Share what happened and pin the location so responders can act quickly.</p>
  </div>
  <div class="page-actions">
    <a class="btn ghost" href="my_reports.php">My reports</a>
  </div>
</div>
<div class="card">
  <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrfField() ?>
    <div class="report-form-grid">
      <div>
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
          <option value="">Choose one</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (string) $old['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="title">Title</label>
        <input id="title" name="title" maxlength="150" placeholder="Short summary of the incident" required value="<?= e($old['title']) ?>">
        <label for="location">Location</label>
        <input id="location" name="location" placeholder="Street, purok, or landmark" required value="<?= e($old['location']) ?>">
        <label for="description">What happened?</label>
        <textarea id="description" name="description" placeholder="Include helpful details such as hazards, people affected, or landmarks." required><?= e($old['description']) ?></textarea>
      </div>
      <div>
        <label>Pin the exact spot (optional)</label>
        <?= renderMapPicker($old['latitude'], $old['longitude']) ?>
        <label for="photo">Photo (optional, up to 3 MB)</label>
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
        <div id="preview"></div>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn ghost" href="dashboard.php">Cancel</a>
      <button class="btn" type="submit">Submit report</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
