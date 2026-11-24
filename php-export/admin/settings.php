<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'site') {
        foreach (['site_name', 'barangay_name', 'contact_phone', 'map_lat', 'map_lng'] as $k) {
            $v = trim((string) ($_POST[$k] ?? ''));
            if (in_array($k, ['map_lat', 'map_lng'], true) && ($v === '' || !is_numeric($v))) continue;
            setSetting($k, $v);
        }
        setFlash('Settings saved.');
        redirectTo('admin/settings.php');
    }

    if ($action === 'sms') {
        setSetting('sms_enabled', !empty($_POST['sms_enabled']) ? '1' : '0');
        setSetting('sms_sender', trim((string) ($_POST['sms_sender'] ?? '')));
        $apiKey = trim((string) ($_POST['sms_api_key'] ?? ''));
        if ($apiKey !== '') setSetting('sms_api_key', $apiKey);
        setFlash('SMS settings saved.');
        redirectTo('admin/settings.php');
    }

    if ($action === 'sms_test') {
        [$ok, $msg] = sendSms((string) ($user['phone'] ?? ''), 'Test message from ' . setting('site_name', 'CAIRS') . '.');
        setFlash($ok ? 'Test SMS sent to your admin phone number.' : "Test failed: {$msg}", $ok ? 'success' : 'error');
        redirectTo('admin/settings.php');
    }

    if ($action === 'hotlines') {
        foreach (['emergency_hotline', 'hotline_fire', 'hotline_police', 'hotline_medical'] as $k) {
            setSetting($k, mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 50));
        }
        setFlash('Hotlines saved.');
        redirectTo('admin/settings.php');
    }

    if ($action === 'password') {
        $currentPw = (string) ($_POST['current'] ?? '');
        $newPw = (string) ($_POST['new'] ?? '');
        if (!password_verify($currentPw, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } else {
            $stmt = pdo()->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($newPw, PASSWORD_BCRYPT), (int) $user['id']]);
            setFlash('Password changed.');
            redirectTo('admin/settings.php');
        }
    }
}

$title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="page-head-copy">
    <h1>Settings</h1>
    <p class="page-head-sub">Configure barangay identity, map center, emergency hotlines, SMS alerts, and account security.</p>
  </div>
</div>
<div class="two even">
  <div class="card">
    <h2>System</h2>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="site">
      <label for="site_name">System name</label><input id="site_name" name="site_name" required value="<?= e(setting('site_name')) ?>">
      <label for="barangay_name">Barangay name</label><input id="barangay_name" name="barangay_name" value="<?= e(setting('barangay_name')) ?>">
      <label for="contact_phone">Contact number</label><input id="contact_phone" name="contact_phone" value="<?= e(setting('contact_phone')) ?>">
      <div class="form-row">
        <div><label for="map_lat">Map center latitude</label><input id="map_lat" name="map_lat" value="<?= e(setting('map_lat', '8.4542')) ?>"></div>
        <div><label for="map_lng">Map center longitude</label><input id="map_lng" name="map_lng" value="<?= e(setting('map_lng', '124.6319')) ?>"></div>
      </div>
      <p style="margin-top:16px"><button class="btn" type="submit">Save settings</button></p>
    </form>
  </div>
  <div class="card">
    <h2>Emergency hotlines</h2>
    <p class="muted">Shown to residents on their dashboard and on the SOS page. Leave a box empty to hide it.</p>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="hotlines">
      <label for="emergency_hotline">Emergency (e.g. 911)</label><input id="emergency_hotline" name="emergency_hotline" value="<?= e(setting('emergency_hotline', '911')) ?>">
      <label for="hotline_fire">Fire station</label><input id="hotline_fire" name="hotline_fire" value="<?= e(setting('hotline_fire')) ?>">
      <label for="hotline_police">Police</label><input id="hotline_police" name="hotline_police" value="<?= e(setting('hotline_police')) ?>">
      <label for="hotline_medical">Medical / ambulance</label><input id="hotline_medical" name="hotline_medical" value="<?= e(setting('hotline_medical')) ?>">
      <p style="margin-top:16px"><button class="btn" type="submit">Save hotlines</button></p>
    </form>
  </div>
</div>
<div class="two even">
  <div class="card">
    <h2>SMS alerts</h2>
    <p class="muted">Residents and volunteers get a text when a report changes. Uses your own Semaphore account (semaphore.co); each message uses SMS credits.</p>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="sms">
      <label class="check-field"><input type="checkbox" name="sms_enabled" value="1" <?= setting('sms_enabled', '0') === '1' ? 'checked' : '' ?>><span>Send SMS alerts</span></label>
      <label for="sms_api_key">Semaphore API key</label>
      <input id="sms_api_key" name="sms_api_key" type="password" autocomplete="off" placeholder="<?= setting('sms_api_key') !== '' ? 'Saved. Leave blank to keep it.' : 'Paste your API key' ?>">
      <label for="sms_sender">Sender name (optional)</label>
      <input id="sms_sender" name="sms_sender" maxlength="11" value="<?= e(setting('sms_sender')) ?>">
      <p style="margin-top:16px"><button class="btn" type="submit">Save SMS settings</button></p>
    </form>
    <form method="post" style="margin-top:10px"><?= csrfField() ?><input type="hidden" name="action" value="sms_test">
      <button class="btn ghost small" type="submit">Send a test SMS to my phone</button>
    </form>
  </div>
  <div class="card">
    <h2>Change my password</h2>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="password">
      <label for="current">Current password</label><input type="password" id="current" name="current" required>
      <label for="new">New password</label><input type="password" id="new" name="new" required minlength="6">
      <p style="margin-top:16px"><button class="btn" type="submit">Change password</button></p>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
