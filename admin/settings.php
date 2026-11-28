<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('admin');
$uid = (int) $user['id'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'site') {
        foreach (['site_name', 'barangay_name', 'contact_phone'] as $k) {
            setSetting($k, trim((string) ($_POST[$k] ?? '')));
        }
        foreach (['map_lat', 'map_lng'] as $k) {
            $v = trim((string) ($_POST[$k] ?? ''));
            if ($v !== '' && is_numeric($v)) {
                setSetting($k, $v);
            }
        }
        setFlash('System identity settings saved.');
        redirectTo('admin/settings.php');
    }

    if ($action === 'map') {
        $lat = trim((string) ($_POST['map_lat'] ?? ''));
        $lng = trim((string) ($_POST['map_lng'] ?? ''));
        if ($lat === '' || !is_numeric($lat) || $lng === '' || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            setFlash('Enter valid latitude (-90 to 90) and longitude (-180 to 180) coordinates.', 'error');
        } else {
            setSetting('map_lat', $lat);
            setSetting('map_lng', $lng);
            setFlash('Default dispatch map center updated.');
        }
        redirectTo('admin/settings.php');
    }

    if ($action === 'sms') {
        setSetting('sms_enabled', !empty($_POST['sms_enabled']) ? '1' : '0');
        setSetting('sms_sender', mb_substr(trim((string) ($_POST['sms_sender'] ?? '')), 0, 11));
        $apiKey = trim((string) ($_POST['sms_api_key'] ?? ''));
        if ($apiKey !== '') {
            setSetting('sms_api_key', $apiKey);
        }
        setFlash('SMS gateway settings saved.');
        redirectTo('admin/settings.php');
    }

    if ($action === 'sms_test') {
        [$ok, $msg] = sendSms((string) ($user['phone'] ?? ''), 'Test message from ' . setting('site_name', 'CAIRS') . '.');
        setFlash($ok ? 'Test SMS sent to your admin phone number.' : 'Test failed: ' . $msg, $ok ? 'success' : 'error');
        redirectTo('admin/settings.php');
    }

    if ($action === 'hotlines') {
        foreach (['emergency_hotline', 'hotline_fire', 'hotline_police', 'hotline_medical'] as $k) {
            setSetting($k, mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 50));
        }
        setFlash('Emergency hotlines updated.');
        redirectTo('admin/settings.php');
    }

    if ($action === 'password') {
        $currentPw = (string) ($_POST['current'] ?? '');
        $newPw = (string) ($_POST['new'] ?? '');
        if (!password_verify($currentPw, (string) $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } else {
            $upd = pdo()->prepare('UPDATE users SET password = ? WHERE id = ?');
            $upd->execute([password_hash($newPw, PASSWORD_BCRYPT), $uid]);
            setFlash('Admin password changed.');
            redirectTo('admin/settings.php');
        }
    }

    if ($action === 'admin_role') {
        $targetId = (int) ($_POST['id'] ?? 0);
        $newRole = trim((string) ($_POST['role'] ?? 'admin'));
        $newStatus = trim((string) ($_POST['status'] ?? 'active'));

        if ($targetId <= 0 || !in_array($newRole, ['admin', 'volunteer', 'resident'], true) || !in_array($newStatus, ['active', 'inactive'], true)) {
            setFlash('Choose a valid role and status.', 'error');
        } elseif ($targetId === $uid) {
            setFlash('You cannot demote or deactivate your own signed-in administrator account.', 'error');
        } else {
            $tStmt = pdo()->prepare("SELECT id, full_name, status FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
            $tStmt->execute([$targetId]);
            $target = $tStmt->fetch();
            if (!$target) {
                setFlash('Administrator account not found.', 'error');
            } else {
                $activeAdmins = (int) pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
                $removesActiveAdmin = ($target['status'] === 'active') && ($newRole !== 'admin' || $newStatus !== 'active');
                if ($removesActiveAdmin && $activeAdmins <= 1) {
                    setFlash('Keep at least one active Administrator account.', 'error');
                } else {
                    $upd = pdo()->prepare("UPDATE users SET role = ?, status = ? WHERE id = ? AND role = 'admin'");
                    $upd->execute([$newRole, $newStatus, $targetId]);
                    if ($newRole === 'volunteer') {
                        setFlash("{$target['full_name']} moved to the Volunteers roster.");
                    } elseif ($newRole === 'resident') {
                        setFlash("{$target['full_name']} moved to the Residents directory.");
                    } else {
                        setFlash("{$target['full_name']} administrator status updated.");
                    }
                }
            }
        }
        redirectTo('admin/settings.php');
    }
}

$siteName = setting('site_name', 'CAIRS');
$brgyName = setting('barangay_name', 'Barangay Sample');
$contactPhone = setting('contact_phone');
$mapLat = setting('map_lat', '8.4542');
$mapLng = setting('map_lng', '124.6319');
$smsEnabled = setting('sms_enabled', '0') === '1';
$hasSmsKey = setting('sms_api_key') !== '';
$smsSender = setting('sms_sender');
$hotlineVals = [
    setting('emergency_hotline', '911'),
    setting('hotline_fire'),
    setting('hotline_police'),
    setting('hotline_medical'),
];
$activeHotlines = count(array_filter($hotlineVals, static fn ($v) => trim((string) $v) !== ''));
$adminAccounts = pdo()->query("SELECT id, full_name, email, phone, address, status, avatar_type, avatar_style, avatar_shape, avatar_url, created_at FROM users WHERE role = 'admin' ORDER BY created_at ASC")->fetchAll();

$title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>
<header class="settings-hero">
  <div class="settings-hero-copy">
    <div class="settings-kicker"><?= icon('sliders', 15) ?><span>System &amp; Barangay Configuration</span></div>
    <h1>Settings</h1>
    <p class="settings-hero-sub">Configure barangay identity, dispatch map coordinates, emergency hotlines, SMS alerts, and admin security.</p>
  </div>
  <nav class="settings-nav" aria-label="Settings sections">
    <button type="button" class="settings-nav-btn is-active" data-settings-tab="all" aria-current="true"><?= icon('grid', 15) ?><span>All sections</span></button>
    <button type="button" class="settings-nav-btn" data-settings-tab="appearance" aria-current="false"><?= icon('sun', 15) ?><span>Appearance</span></button>
    <button type="button" class="settings-nav-btn" data-settings-tab="site" aria-current="false"><?= icon('globe', 15) ?><span>System Identity</span></button>
    <button type="button" class="settings-nav-btn" data-settings-tab="map" aria-current="false"><?= icon('pin', 15) ?><span>Map Coordinates</span></button>
    <button type="button" class="settings-nav-btn" data-settings-tab="hotlines" aria-current="false"><?= icon('phone', 15) ?><span>Emergency Hotlines</span></button>
    <button type="button" class="settings-nav-btn" data-settings-tab="sms" aria-current="false"><?= icon('message', 15) ?><span>SMS Alerts</span></button>
    <button type="button" class="settings-nav-btn" data-settings-tab="security" aria-current="false"><?= icon('lock', 15) ?><span>Admin Security</span></button>
  </nav>
</header>

<?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>

<div class="grid grid-4">
  <?= statCard('globe', 'System identity', $siteName, $brgyName ?: 'Barangay not set', 'primary') ?>
  <?= statCard('pin', 'Map center', number_format((float) $mapLat, 4) . ', ' . number_format((float) $mapLng, 4), 'Default dispatch pin focus', 'info') ?>
  <?= statCard('phone', 'Emergency hotlines', $activeHotlines . ' / 4', 'Visible on resident SOS & dashboard', $activeHotlines > 0 ? 'success' : 'warning') ?>
  <?= statCard('message', 'SMS gateway', $smsEnabled ? 'Enabled' : 'Disabled', $hasSmsKey ? 'Semaphore API key configured' : 'API key not configured', $smsEnabled && $hasSmsKey ? 'success' : ($smsEnabled ? 'warning' : 'muted')) ?>
</div>

<?= mapAssets() ?>

<div class="settings-panels">
    <section class="card setting-card" data-settings-panel="appearance">
      <div class="setting-head">
        <div class="setting-head-title">
          <span class="setting-head-ic"><?= icon('sun', 19) ?></span>
          <div>
            <h2>Appearance &amp; Theme</h2>
            <p>Choose how <?= e($siteName) ?> renders on this device—daylight light surfaces or low-glare dark mode.</p>
          </div>
        </div>
        <span class="setting-tag is-on" data-theme-status>Light Mode</span>
      </div>
      <div class="setting-body">
        <div class="theme-preset-grid" role="group" aria-label="Color theme preference">
          <button type="button" class="theme-preset-card" data-theme-set="light" aria-pressed="false">
            <span class="tp-preview is-light" aria-hidden="true">
              <span class="tp-rail"><i class="tp-brand"></i><i class="tp-nav is-on"></i><i class="tp-nav"></i><i class="tp-nav"></i></span>
              <span class="tp-main">
                <span class="tp-bar"><i></i><i class="tp-dot"></i></span>
                <span class="tp-stats"><i></i><i class="tp-accent"></i></span>
                <span class="tp-card"><i></i><i class="tp-short"></i></span>
              </span>
            </span>
            <span class="tp-meta">
              <span class="tp-title"><?= icon('sun', 16) ?><strong>Light</strong></span>
              <span class="tp-radio" aria-hidden="true"></span>
            </span>
            <span class="tp-desc">Bright alabaster workspace with crisp high-contrast ink.</span>
          </button>

          <button type="button" class="theme-preset-card" data-theme-set="dark" aria-pressed="false">
            <span class="tp-preview is-dark" aria-hidden="true">
              <span class="tp-rail"><i class="tp-brand"></i><i class="tp-nav is-on"></i><i class="tp-nav"></i><i class="tp-nav"></i></span>
              <span class="tp-main">
                <span class="tp-bar"><i></i><i class="tp-dot"></i></span>
                <span class="tp-stats"><i></i><i class="tp-accent"></i></span>
                <span class="tp-card"><i></i><i class="tp-short"></i></span>
              </span>
            </span>
            <span class="tp-meta">
              <span class="tp-title"><?= icon('moon', 16) ?><strong>Dark</strong></span>
              <span class="tp-radio" aria-hidden="true"></span>
            </span>
            <span class="tp-desc">Deep slate surfaces tuned for low-light dispatch shifts.</span>
          </button>
        </div>
      </div>
      <div class="setting-foot">
        <span class="setting-foot-note">Applies immediately on this browser and stays synced across open tabs.</span>
        <span class="setting-foot-note" data-theme-resolved-note></span>
      </div>
    </section>

    <section class="card setting-card" data-settings-panel="site">
      <div class="setting-head">
        <div class="setting-head-title">
          <span class="setting-head-ic"><?= icon('globe', 19) ?></span>
          <div>
            <h2>System &amp; Barangay Identity</h2>
            <p>Displayed in navigation headers, resident notifications, and emergency printouts.</p>
          </div>
        </div>
        <span class="setting-tag is-on">Publicly visible</span>
      </div>
      <form method="post">
        <?= csrfField() ?><input type="hidden" name="action" value="site">
        <div class="setting-body">
          <div class="form-row">
            <div>
              <label for="site_name">System name</label>
              <input id="site_name" name="site_name" required value="<?= e($siteName) ?>">
            </div>
            <div>
              <label for="barangay_name">Barangay name</label>
              <input id="barangay_name" name="barangay_name" placeholder="e.g. Barangay Carmen" value="<?= e($brgyName) ?>">
            </div>
          </div>
          <label for="contact_phone">Barangay hall contact number</label>
          <input id="contact_phone" name="contact_phone" placeholder="09XX-XXX-XXXX or landline" value="<?= e($contactPhone) ?>">
        </div>
        <div class="setting-foot">
          <span class="setting-foot-note">Updates sidebar brand and landing page header immediately.</span>
          <button class="btn" type="submit">Save identity</button>
        </div>
      </form>
    </section>

    <section class="card setting-card" data-settings-panel="map">
      <div class="setting-head">
        <div class="setting-head-title">
          <span class="setting-head-ic"><?= icon('pin', 19) ?></span>
          <div>
            <h2>Dispatch Map Center</h2>
            <p>Click the map or drag the marker to set the default camera position for reports and Pin &amp; Dispatch.</p>
          </div>
        </div>
        <span class="setting-tag" id="settings-map-readout">Center: <?= e(number_format((float) $mapLat, 5)) ?>, <?= e(number_format((float) $mapLng, 5)) ?></span>
      </div>
      <form method="post">
        <?= csrfField() ?><input type="hidden" name="action" value="map">
        <div class="setting-body">
          <div class="settings-map-wrap">
            <div id="settings-map-picker" class="map settings-map" data-lat="<?= e($mapLat) ?>" data-lng="<?= e($mapLng) ?>"></div>
            <div class="settings-map-bar">
              <button type="button" id="settings-locate" class="btn ghost small"><?= icon('pin', 15) ?> Use my location</button>
              <span>Click map or drag pin to update latitude &amp; longitude below.</span>
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="map_lat">Center latitude</label>
              <input id="map_lat" name="map_lat" required value="<?= e($mapLat) ?>">
            </div>
            <div>
              <label for="map_lng">Center longitude</label>
              <input id="map_lng" name="map_lng" required value="<?= e($mapLng) ?>">
            </div>
          </div>
        </div>
        <div class="setting-foot">
          <span class="setting-foot-note">Used as the starting viewport for citizen pins and dispatch maps.</span>
          <button class="btn" type="submit">Save map center</button>
        </div>
      </form>
    </section>

    <section class="card setting-card" data-settings-panel="hotlines">
      <div class="setting-head">
        <div class="setting-head-title">
          <span class="setting-head-ic"><?= icon('phone', 19) ?></span>
          <div>
            <h2>Emergency Hotlines</h2>
            <p>Shown as one-tap call tiles on resident dashboards and the SOS emergency screen. Leave blank to hide a service.</p>
          </div>
        </div>
        <span class="setting-tag<?= $activeHotlines > 0 ? ' is-on' : ' is-warn' ?>"><?= $activeHotlines ?> of 4 active</span>
      </div>
      <form method="post">
        <?= csrfField() ?><input type="hidden" name="action" value="hotlines">
        <div class="setting-body">
          <div class="form-row">
            <div>
              <label for="emergency_hotline">Emergency (e.g. 911)</label>
              <input id="emergency_hotline" name="emergency_hotline" maxlength="50" value="<?= e($hotlineVals[0]) ?>">
            </div>
            <div>
              <label for="hotline_fire">Fire station</label>
              <input id="hotline_fire" name="hotline_fire" maxlength="50" placeholder="(088) 856-2222" value="<?= e($hotlineVals[1]) ?>">
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="hotline_police">Police station</label>
              <input id="hotline_police" name="hotline_police" maxlength="50" placeholder="(088) 856-3333" value="<?= e($hotlineVals[2]) ?>">
            </div>
            <div>
              <label for="hotline_medical">Medical / Ambulance</label>
              <input id="hotline_medical" name="hotline_medical" maxlength="50" placeholder="(088) 856-4444" value="<?= e($hotlineVals[3]) ?>">
            </div>
          </div>

          <div class="hotline-preview-wrap">
            <span class="hotline-preview-label">Live Resident SOS Tile Preview</span>
            <div class="hotlines" id="hotline-live-preview"></div>
          </div>
        </div>
        <div class="setting-foot">
          <span class="setting-foot-note">All valid numbers render as tap-to-call buttons on mobile.</span>
          <button class="btn" type="submit">Save hotlines</button>
        </div>
      </form>
    </section>

    <section class="card setting-card" data-settings-panel="sms">
      <div class="setting-head">
        <div class="setting-head-title">
          <span class="setting-head-ic"><?= icon('message', 19) ?></span>
          <div>
            <h2>SMS Alerts (Semaphore)</h2>
            <p>Send automated text updates to residents and responders when incident status changes.</p>
          </div>
        </div>
        <span class="setting-tag<?= $hasSmsKey ? ' is-on' : ' is-warn' ?>"><?= $hasSmsKey ? 'API Key Configured' : 'No API Key Saved' ?></span>
      </div>
      <div class="setting-body">
        <form method="post">
          <?= csrfField() ?><input type="hidden" name="action" value="sms">
          <label class="check-field">
            <input type="checkbox" name="sms_enabled" value="1" <?= $smsEnabled ? 'checked' : '' ?>>
            <span>Enable outgoing SMS notifications</span>
          </label>
          <div class="form-row">
            <div>
              <label for="sms_api_key">Semaphore API key</label>
              <div class="pm-input-ic pm-pw-ic">
                <?= icon('key', 16) ?>
                <input id="sms_api_key" name="sms_api_key" type="password" autocomplete="off" placeholder="<?= $hasSmsKey ? 'Saved (leave blank to keep)' : 'Paste Semaphore API key' ?>">
                <button type="button" class="pm-eye-btn" data-pm-eye="sms_api_key" aria-label="Show API key"><?= icon('eye', 16) ?></button>
              </div>
            </div>
            <div>
              <div class="label-row">
                <label for="sms_sender">Sender name (optional)</label>
                <span class="char-count" id="sms-sender-count">0 / 11 chars</span>
              </div>
              <input id="sms_sender" name="sms_sender" maxlength="11" placeholder="e.g. CAIRS" value="<?= e($smsSender) ?>">
            </div>
          </div>
          <p style="margin-top:16px"><button class="btn" type="submit">Save SMS settings</button></p>
        </form>

        <div class="sms-test-row">
          <div class="sms-test-copy">
            <strong>Verify delivery on your admin phone</strong>
            <span>Target number: <?= e(!empty($user['phone']) ? $user['phone'] : 'Not set — add a mobile number in Edit Profile first') ?></span>
          </div>
          <form method="post" style="margin:0"><?= csrfField() ?><input type="hidden" name="action" value="sms_test">
            <button class="btn ghost small" type="submit">Send test SMS</button>
          </form>
        </div>
      </div>
    </section>

    <section class="card setting-card" data-settings-panel="security">
      <div class="setting-head">
        <div class="setting-head-title">
          <span class="setting-head-ic"><?= icon('lock', 19) ?></span>
          <div>
            <h2>Admin Security &amp; Password</h2>
            <p>Update your administrator password or open your profile studio to update contact details and avatar.</p>
          </div>
        </div>
        <button type="button" class="btn ghost small" data-profile-open><?= icon('user', 15) ?> Edit Profile</button>
      </div>
      <form method="post">
        <?= csrfField() ?><input type="hidden" name="action" value="password">
        <div class="setting-body">
          <div class="form-row">
            <div>
              <label for="sec-pw-current">Current password</label>
              <div class="pm-input-ic pm-pw-ic">
                <?= icon('lock', 16) ?>
                <input type="password" id="sec-pw-current" name="current" required autocomplete="current-password">
                <button type="button" class="pm-eye-btn" data-pm-eye="sec-pw-current" aria-label="Show current password"><?= icon('eye', 16) ?></button>
              </div>
            </div>
            <div>
              <label for="sec-pw-new">New password</label>
              <div class="pm-input-ic pm-pw-ic">
                <?= icon('key', 16) ?>
                <input type="password" id="sec-pw-new" name="new" required minlength="6" autocomplete="new-password">
                <button type="button" class="pm-eye-btn" data-pm-eye="sec-pw-new" aria-label="Show new password"><?= icon('eye', 16) ?></button>
              </div>
            </div>
          </div>
          <div class="pm-pw-meter">
            <div class="pm-pw-bar"><span id="sec-pw-fill"></span></div>
            <small id="sec-pw-hint">Minimum 6 characters; mix letters, numbers &amp; symbols</small>
          </div>
        </div>
        <div class="setting-foot">
          <span class="setting-foot-note">Applies immediately to your administrator login.</span>
          <button class="btn" type="submit">Change password</button>
        </div>
      </form>
    </section>

    <section class="card setting-card" data-settings-panel="security">
      <div class="setting-head">
        <div class="setting-head-title">
          <span class="setting-head-ic"><?= icon('shield', 19) ?></span>
          <div>
            <h2>Administrator Accounts &amp; Roles</h2>
            <p>Review accounts with Administrator access. Promote Residents or Volunteers from their directories.</p>
          </div>
        </div>
        <span class="setting-tag is-on"><?= count($adminAccounts) ?> admin<?= count($adminAccounts) === 1 ? '' : 's' ?></span>
      </div>
      <div class="setting-body">
        <div class="admin-team-list">
          <?php foreach ($adminAccounts as $adm):
            $isSelf = (int) $adm['id'] === $uid;
          ?>
          <div class="admin-team-row">
            <div class="admin-team-main">
              <?= renderAvatar($adm) ?>
              <div class="admin-team-copy">
                <span class="admin-team-name">
                  <?= e($adm['full_name']) ?>
                  <?php if ($isSelf): ?><span class="admin-team-you">You</span><?php endif; ?>
                </span>
                <span class="admin-team-email"><?= e($adm['email']) ?> &middot; Joined <?= e(fmtDate($adm['created_at'])) ?></span>
              </div>
            </div>
            <div class="admin-team-act">
              <span class="badge <?= $adm['status'] === 'active' ? 's-resolved' : 's-rejected' ?>"><?= e(ucfirst((string) $adm['status'])) ?></span>
              <?php if ($isSelf): ?>
                <span class="admin-team-self">Current session</span>
              <?php else: ?>
                <button type="button" class="btn ghost small"
                        data-adm-edit
                        data-modal-open="adm-role-modal"
                        data-id="<?= (int) $adm['id'] ?>"
                        data-name="<?= e($adm['full_name']) ?>"
                        data-email="<?= e($adm['email']) ?>"
                        data-role="admin"
                        data-status="<?= e($adm['status']) ?>">
                  <?= icon('edit', 14) ?> Change Role
                </button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="setting-foot">
        <span class="setting-foot-note">Need to promote someone? Open <a href="residents.php">Residents</a> or <a href="volunteers.php">Volunteers</a> and click Edit.</span>
      </div>
    </section>
  </div>

<div class="modal" id="adm-role-modal">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-md" role="dialog" aria-modal="true" aria-labelledby="adm-role-title">
    <div class="modal-head">
      <div class="modal-head-copy">
        <span class="modal-head-ic"><?= icon('shield', 18) ?></span>
        <div>
          <h3 id="adm-role-title">Manage Administrator Role</h3>
          <p>Reassign this account to Volunteer or Resident, or change its active status.</p>
        </div>
      </div>
      <button type="button" class="notif-head-ic" data-modal-close aria-label="Close"><?= icon('x', 16) ?></button>
    </div>
    <form class="modal-form" method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="admin_role"><input type="hidden" id="adm-role-id" name="id" value="">
      <div class="modal-body">
        <div class="form-row">
          <div>
            <label for="adm-role-name">Full name</label>
            <input id="adm-role-name" disabled>
          </div>
          <div>
            <label for="adm-role-email">Account email</label>
            <input id="adm-role-email" type="email" disabled>
          </div>
        </div>
        <div class="form-row">
          <div>
            <label for="adm-role-select">Account role</label>
            <select id="adm-role-select" name="role">
              <option value="admin">Administrator</option>
              <option value="volunteer">Volunteer (Responder)</option>
              <option value="resident">Resident</option>
            </select>
          </div>
          <div>
            <label for="adm-role-status">Account status</label>
            <select id="adm-role-status" name="status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="role-change-note" id="adm-role-note" hidden>
          <?= icon('alert', 16) ?>
          <span id="adm-role-msg"></span>
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
