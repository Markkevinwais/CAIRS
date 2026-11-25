<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
$user = requireRole('resident');
$uid = (int) $user['id'];

$types = [
    'fire' => ['Fire', 'Fire', 'flame'],
    'medical' => ['Medical emergency', 'Medical emergency', 'plus'],
    'crime' => ['Crime / Police', 'Theft / Crime', 'shield'],
    'flood' => ['Flood', 'Flood', 'radar'],
    'accident' => ['Road accident', 'Road accident', 'siren'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $key = (string) ($_POST['type'] ?? '');
    if (!isset($types[$key])) {
        setFlash('Choose the kind of emergency.', 'error');
        redirectTo('resident/sos.php');
    }

    $cats = pdo()->query('SELECT * FROM categories')->fetchAll();
    if (!$cats) {
        setFlash('No categories are set up yet. Please call the barangay hotline.', 'error');
        redirectTo('resident/sos.php');
    }

    $dupeStmt = pdo()->prepare(
        'SELECT id FROM reports WHERE resident_id = ? AND is_sos = 1 AND created_at > (NOW() - INTERVAL 2 MINUTE) ORDER BY id DESC LIMIT 1'
    );
    $dupeStmt->execute([$uid]);
    if ($dupeId = $dupeStmt->fetchColumn()) {
        setFlash('Your SOS was already sent a moment ago. Help is on the way.');
        redirectTo('resident/report_details.php?id=' . (int) $dupeId);
    }

    $rawLat = $_POST['latitude'] ?? '';
    $rawLng = $_POST['longitude'] ?? '';
    $lat = ($rawLat !== '' && is_numeric($rawLat)) ? (float) $rawLat : null;
    $lng = ($rawLng !== '' && is_numeric($rawLng)) ? (float) $rawLng : null;
    if (($lat === null) !== ($lng === null) || ($lat !== null && (abs($lat) > 90 || abs($lng) > 180))) {
        $lat = null;
        $lng = null;
    }

    [$label, $catName] = $types[$key];
    $catId = (int) $cats[0]['id'];
    foreach ($cats as $c) {
        if (strtolower($c['name']) === strtolower($catName)) {
            $catId = (int) $c['id'];
            break;
        }
        if (strtolower($c['name']) === 'other') {
            $catId = (int) $c['id'];
        }
    }

    $home = $user['address'] ?: 'not provided';
    $location = mb_substr($lat !== null ? "Sent from live GPS location (home: {$home})" : "No GPS. Home address: {$home}", 0, 255);
    $titleText = "SOS: {$label}";
    $desc = 'EMERGENCY SOS sent by ' . ($user['full_name'] ?: 'Resident') . ". Type: {$label}. Contact number: " . ($user['phone'] ?: 'not provided') . '.';

    $stmt = pdo()->prepare(
        'INSERT INTO reports (resident_id, category_id, title, description, location, latitude, longitude, priority, status, is_sos)
         VALUES (?, ?, ?, ?, ?, ?, ?, "high", "pending", 1)'
    );
    $stmt->execute([$uid, $catId, $titleText, $desc, $location, $lat, $lng]);
    $rid = (int) pdo()->lastInsertId();

    logUpdate($rid, $uid, 'pending', 'SOS alert sent by the resident.');
    $msg = 'SOS ' . refNo($rid) . " ({$label}) from " . ($user['full_name'] ?: 'Resident') . '. Respond now.';
    $team = pdo()->query("SELECT id FROM users WHERE role IN ('admin','volunteer') AND status = 'active'")->fetchAll();
    foreach ($team as $t) {
        notify((int) $t['id'], $rid, $msg);
    }

    setFlash('SOS sent as ' . refNo($rid) . '. The barangay has been alerted. Stay safe.');
    redirectTo('resident/report_details.php?id=' . $rid);
}

$title = 'SOS emergency';
require __DIR__ . '/../includes/header.php';
?>
<section class="sos-hero">
  <div class="sos-pulse"><span></span><span></span><span></span><?= icon('siren', 44) ?></div>
  <div>
    <h1>Emergency SOS</h1>
    <p class="muted">Tap the kind of emergency. You get 5 seconds to cancel, then the barangay admin and volunteers are alerted with your location.</p>
    <p class="gps" id="gps" data-state="wait"><i></i><span id="gps-text">Finding your location...</span></p>
  </div>
</section>

<form method="post" id="sos-form">
  <?= csrfField() ?>
  <input type="hidden" name="type" id="sos-type" value="">
  <input type="hidden" name="latitude" id="sos-lat" value="">
  <input type="hidden" name="longitude" id="sos-lng" value="">
  <div class="sos-grid">
    <?php foreach ($types as $key => [$label, $catName, $ic]): ?>
      <button type="button" class="sos-tile" data-type="<?= e($key) ?>" data-label="<?= e($label) ?>" data-modal-open="sos-modal">
        <?= icon($ic, 34) ?><span><?= e($label) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
</form>

<section class="card sos-call">
  <div class="card-head">
    <h2>Or call for help directly</h2>
    <span class="muted">For a life-threatening emergency, call right away.</span>
  </div>
  <?= renderHotlines() ?>
</section>

<div class="modal" id="sos-modal">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog sos-dialog" role="alertdialog" aria-modal="true" aria-labelledby="sos-title">
    <div class="sos-count">
      <svg viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="44"/><circle class="arc" id="sos-arc" cx="50" cy="50" r="44"/></svg>
      <b id="sos-secs">5</b>
    </div>
    <h3 id="sos-title">Sending SOS: <span id="sos-what"></span></h3>
    <p class="muted">Your alert goes out when the timer ends. Tap Cancel if this was a mistake.</p>
    <div class="modal-actions">
      <button type="button" class="btn ghost" data-modal-close>Cancel</button>
      <button type="button" class="btn danger" id="sos-now" data-autofocus>Send now</button>
    </div>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('sos-form'), modal = document.getElementById('sos-modal');
  var typeIn = document.getElementById('sos-type'), latIn = document.getElementById('sos-lat'), lngIn = document.getElementById('sos-lng');
  var gps = document.getElementById('gps'), gpsText = document.getElementById('gps-text');
  var arc = document.getElementById('sos-arc'), secs = document.getElementById('sos-secs'), what = document.getElementById('sos-what');
  var TOTAL = 5, CIRC = 276.5, left = TOTAL, timer = null, sent = false;

  document.body.appendChild(modal);

  function gpsState(state, text) { gps.setAttribute('data-state', state); gpsText.textContent = text; }
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function (p) {
      latIn.value = p.coords.latitude.toFixed(7);
      lngIn.value = p.coords.longitude.toFixed(7);
      gpsState('ok', 'Location found. It will be sent with your SOS.');
    }, function () {
      gpsState('fail', 'Location not available. Your home address will be sent instead.');
    }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
  } else {
    gpsState('fail', 'Location not supported. Your home address will be sent instead.');
  }

  function stop() { if (timer) { clearInterval(timer); timer = null; } }
  function send() { if (sent) return; sent = true; stop(); form.submit(); }

  function start(type, label) {
    stop();
    typeIn.value = type;
    what.textContent = label;
    left = TOTAL;
    secs.textContent = left;
    arc.style.transition = 'none';
    arc.style.strokeDashoffset = '0';
    void arc.getBoundingClientRect();
    arc.style.transition = '';
    arc.style.strokeDashoffset = String(CIRC / TOTAL);
    timer = setInterval(function () {
      left -= 1;
      secs.textContent = Math.max(left, 0);
      if (left <= 0) { send(); return; }
      arc.style.strokeDashoffset = String(CIRC * (TOTAL - left + 1) / TOTAL);
    }, 1000);
  }

  Array.prototype.forEach.call(document.querySelectorAll('.sos-tile'), function (tile) {
    tile.addEventListener('click', function () { start(tile.getAttribute('data-type'), tile.getAttribute('data-label')); });
  });
  document.getElementById('sos-now').addEventListener('click', send);

  new MutationObserver(function () { if (!modal.classList.contains('is-open')) stop(); })
    .observe(modal, { attributes: true, attributeFilter: ['class'] });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
