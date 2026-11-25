<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

if ($u = syncActiveUser()) {
    redirectTo(homeFor($u['role']));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!csrfVerify($_POST['csrf'] ?? null)) {
        http_response_code(419);
        $error = 'Session expired. Please try logging in again.';
    } elseif (loginLocked($email, $ip)) {
        $error = 'Too many failed attempts. Please wait 10 minutes and try again.';
    } else {
        $stmt = pdo()->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Email or password is incorrect.';
            loginFailed($email, $ip);
        } elseif ($user['status'] !== 'active') {
            $error = 'This account is inactive. Contact the barangay office.';
        } else {
            loginClear($email);
            session_regenerate_id(true);
            $_SESSION['csrf'] = $_SESSION['csrf'] ?: bin2hex(random_bytes(16));
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];
            redirectTo(homeFor($user['role']));
        }
    }
}

$title = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
  <aside class="auth-brand">
    <a class="brand" href="<?= BASE_URL ?>/index.php"><span class="brand-mark"><?= icon('shield', 20) ?></span><span><?= e(setting('site_name', 'CAIRS')) ?></span></a>
    <div class="auth-copy">
      <h2>Report it. Track it. Get it resolved.</h2>
      <p>Community Assistance and Incident Reporting System for <?= e(setting('barangay_name', 'your barangay')) ?>.</p>
      <ul class="auth-points">
        <li><?= icon('pin', 18) ?> Pin the exact location on a map</li>
        <li><?= icon('bell', 18) ?> Get notified at every step</li>
        <li><?= icon('check', 18) ?> See when your report is resolved</li>
      </ul>
    </div>
    <p class="auth-foot">&copy; <?= date('Y') ?> <?= e(setting('site_name', 'CAIRS')) ?></p>
  </aside>
  <section class="auth-panel">
    <header class="auth-header">
      <a class="auth-back" href="<?= BASE_URL ?>/index.php"><?= icon('arrow-left', 16) ?><span>Back to home</span></a>
      <div class="auth-header-actions">
        <span class="muted">New to <?= e(setting('site_name', 'CAIRS')) ?>?</span>
        <a class="auth-header-link" href="<?= BASE_URL ?>/register.php">Create account</a>
      </div>
    </header>
    <div class="auth-panel-body">
      <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="muted">Log in to your account to continue.</p>
        <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" id="login-form">
          <?= csrfField() ?>
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autofocus autocomplete="email" value="<?= e($email) ?>">
          <label for="password">Password</label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Show password"><?= icon('eye', 19) ?></button>
          </div>
          <small class="caps-warn" id="caps-warn" hidden>Caps Lock is on.</small>
          <p style="margin-top:18px"><button class="btn" type="submit" id="login-btn">Log in</button></p>
        </form>
        <p class="muted">No account yet? <a href="<?= BASE_URL ?>/register.php">Register as a resident</a>.</p>
        <p class="secure-note"><?= icon('shield', 15) ?> Secure, encrypted-password login</p>
      </div>
    </div>
  </section>
</div>
<script>
(function () {
  var pw = document.getElementById('password'), tg = document.getElementById('pw-toggle');
  var caps = document.getElementById('caps-warn'), form = document.getElementById('login-form'), btn = document.getElementById('login-btn');
  tg.addEventListener('click', function () {
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    tg.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    tg.style.opacity = show ? '1' : '.8';
  });
  function updateCaps(e) {
    caps.hidden = !(e.getModifierState && e.getModifierState('CapsLock'));
  }
  pw.addEventListener('keydown', updateCaps);
  pw.addEventListener('keyup', updateCaps);
  pw.addEventListener('blur', function () { caps.hidden = true; });
  form.addEventListener('submit', function () {
    btn.classList.add('is-loading'); btn.textContent = 'Logging in...';
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
