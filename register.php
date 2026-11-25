<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

if ($u = syncActiveUser()) {
    redirectTo(homeFor($u['role']));
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'address' => trim((string) ($_POST['address'] ?? '')),
    ];
    $pass = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (!csrfVerify($_POST['csrf'] ?? null)) {
        http_response_code(419);
        $errors[] = 'Session expired. Please submit the form again.';
    } else {
        if ($old['full_name'] === '') $errors[] = 'Enter your full name.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if ($old['address'] === '') $errors[] = 'Enter your address or purok.';
        if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($pass !== $confirm) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            $chk = pdo()->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $chk->execute([$old['email']]);
            if ($chk->fetch()) {
                $errors[] = 'That email is already registered.';
            }
        }

        if (!$errors) {
            $stmt = pdo()->prepare(
                'INSERT INTO users (full_name, email, password, role, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $old['full_name'],
                $old['email'],
                password_hash($pass, PASSWORD_BCRYPT),
                'resident',
                $old['phone'] ?: null,
                $old['address'],
                'active',
            ]);
            setFlash('Account created. You can log in now.');
            redirectTo('login.php');
        }
    }
}

$title = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
  <aside class="auth-brand">
    <a class="brand" href="<?= BASE_URL ?>/index.php"><span class="brand-mark"><?= icon('shield', 20) ?></span><span><?= e(setting('site_name', 'CAIRS')) ?></span></a>
    <div class="auth-copy">
      <h2>Join your community's safety network.</h2>
      <p>Create a resident account to report concerns and follow them until they are resolved.</p>
      <ul class="auth-points">
        <li><?= icon('plus', 18) ?> File a report in under a minute</li>
        <li><?= icon('shield', 18) ?> Volunteers are assigned to respond</li>
        <li><?= icon('bell', 18) ?> Updates arrive as notifications</li>
      </ul>
    </div>
    <p class="auth-foot">&copy; <?= date('Y') ?> <?= e(setting('site_name', 'CAIRS')) ?></p>
  </aside>
  <section class="auth-panel">
    <header class="auth-header">
      <a class="auth-back" href="<?= BASE_URL ?>/index.php"><?= icon('arrow-left', 16) ?><span>Back to home</span></a>
    </header>
    <div class="auth-panel-body">
      <div class="auth-card auth-card-wide">
        <div class="auth-card-head">
          <p class="auth-kicker"><?= e(setting('barangay_name', 'Barangay Portal')) ?></p>
          <h1>Create a resident account</h1>
          <p class="muted">Register once to report concerns and follow responder updates.</p>
        </div>
        <?php foreach ($errors as $er): ?><div class="flash error" role="alert"><?= icon('alert', 17) ?><span><?= e($er) ?></span></div><?php endforeach; ?>
        <form method="post" id="register-form" class="auth-form">
          <?= csrfField() ?>
          <div class="auth-field">
            <div class="auth-label-row"><label for="full_name">Full name</label></div>
            <div class="pm-input-ic">
              <?= icon('user', 16) ?>
              <input id="full_name" name="full_name" placeholder="Juan Dela Cruz" required autofocus autocomplete="name" value="<?= e($old['full_name']) ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="auth-field">
              <div class="auth-label-row"><label for="email">Email</label></div>
              <div class="pm-input-ic">
                <?= icon('mail', 16) ?>
                <input type="email" id="email" name="email" placeholder="name@example.com" required autocomplete="email" value="<?= e($old['email']) ?>">
              </div>
            </div>
            <div class="auth-field">
              <div class="auth-label-row"><label for="phone">Mobile number</label><span class="auth-opt">Optional</span></div>
              <div class="pm-input-ic">
                <?= icon('phone', 16) ?>
                <input type="tel" id="phone" name="phone" placeholder="09XX XXX XXXX" autocomplete="tel" value="<?= e($old['phone']) ?>">
              </div>
            </div>
          </div>
          <div class="auth-field">
            <div class="auth-label-row"><label for="address">Address / Purok</label></div>
            <div class="pm-input-ic">
              <?= icon('pin', 16) ?>
              <input id="address" name="address" placeholder="e.g. Purok 3, Rizal St." required autocomplete="street-address" value="<?= e($old['address']) ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="auth-field">
              <div class="auth-label-row"><label for="password">Password</label></div>
              <div class="pm-input-ic pm-pw-ic">
                <?= icon('lock', 16) ?>
                <input type="password" id="password" name="password" placeholder="Min. 6 characters" required minlength="6" autocomplete="new-password">
                <button type="button" class="pm-eye-btn" data-pw-eye="password" aria-label="Show password" aria-pressed="false"><?= icon('eye', 17) ?></button>
              </div>
            </div>
            <div class="auth-field">
              <div class="auth-label-row"><label for="confirm">Confirm password</label></div>
              <div class="pm-input-ic pm-pw-ic">
                <?= icon('key', 16) ?>
                <input type="password" id="confirm" name="confirm" placeholder="Repeat password" required autocomplete="new-password">
                <button type="button" class="pm-eye-btn" data-pw-eye="confirm" aria-label="Show password" aria-pressed="false"><?= icon('eye', 17) ?></button>
              </div>
            </div>
          </div>
          <div class="auth-pw-feedback">
            <div class="pm-pw-meter">
              <div class="pm-pw-bar"><span id="reg-pw-fill"></span></div>
              <small id="reg-pw-hint">Minimum 6 characters; mix letters and numbers for strength</small>
            </div>
            <p class="auth-match-hint" id="reg-match-hint" hidden><?= icon('check-simple', 14) ?><span>Passwords match</span></p>
            <small class="caps-warn" id="caps-warn" hidden><?= icon('alert', 14) ?><span>Caps Lock is on.</span></small>
          </div>
          <button class="btn auth-submit" type="submit" id="register-btn">Create resident account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="<?= BASE_URL ?>/login.php">Log in</a></p>
        <p class="secure-note"><?= icon('shield', 15) ?><span>Your details stay private within <?= e(setting('barangay_name', 'your barangay')) ?></span></p>
      </div>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
