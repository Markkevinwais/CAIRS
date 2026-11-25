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
    </header>
    <div class="auth-panel-body">
      <div class="auth-card">
        <div class="auth-card-head">
          <p class="auth-kicker"><?= e(setting('barangay_name', 'Barangay Portal')) ?></p>
          <h1>Welcome back</h1>
          <p class="muted">Log in to report incidents and track response updates.</p>
        </div>
        <?php if ($error): ?><div class="flash error" role="alert"><?= icon('alert', 17) ?><span><?= e($error) ?></span></div><?php endif; ?>
        <form method="post" id="login-form" class="auth-form">
          <?= csrfField() ?>
          <div class="auth-field">
            <div class="auth-label-row"><label for="email">Email</label></div>
            <div class="pm-input-ic">
              <?= icon('mail', 16) ?>
              <input type="email" id="email" name="email" placeholder="name@example.com" required autofocus autocomplete="email" value="<?= e($email) ?>">
            </div>
          </div>
          <div class="auth-field">
            <div class="auth-label-row"><label for="password">Password</label></div>
            <div class="pm-input-ic pm-pw-ic">
              <?= icon('lock', 16) ?>
              <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
              <button type="button" class="pm-eye-btn" data-pw-eye="password" aria-label="Show password" aria-pressed="false"><?= icon('eye', 17) ?></button>
            </div>
          </div>
          <small class="caps-warn" id="caps-warn" hidden><?= icon('alert', 14) ?><span>Caps Lock is on.</span></small>
          <button class="btn auth-submit" type="submit" id="login-btn">Log in</button>
        </form>
        <p class="auth-switch">New to <?= e(setting('site_name', 'CAIRS')) ?>? <a href="<?= BASE_URL ?>/register.php">Create an account</a></p>
        <p class="secure-note"><?= icon('shield', 15) ?><span>Secure, encrypted-password login</span></p>
      </div>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
