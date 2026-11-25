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
      <div class="auth-header-actions">
        <span class="muted">Already have an account?</span>
        <a class="auth-header-link" href="<?= BASE_URL ?>/login.php">Log in</a>
      </div>
    </header>
    <div class="auth-panel-body">
      <div class="auth-card">
        <h1>Create a resident account</h1>
        <p class="muted">It only takes a minute.</p>
        <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post">
          <?= csrfField() ?>
          <label for="full_name">Full name</label>
          <input id="full_name" name="full_name" required autocomplete="name" value="<?= e($old['full_name']) ?>">
          <div class="form-row">
            <div><label for="email">Email</label>
              <input type="email" id="email" name="email" required autocomplete="email" value="<?= e($old['email']) ?>"></div>
            <div><label for="phone">Mobile number</label>
              <input id="phone" name="phone" autocomplete="tel" value="<?= e($old['phone']) ?>"></div>
          </div>
          <label for="address">Address / Purok</label>
          <input id="address" name="address" required value="<?= e($old['address']) ?>">
          <div class="form-row">
            <div><label for="password">Password</label>
              <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password"></div>
            <div><label for="confirm">Confirm password</label>
              <input type="password" id="confirm" name="confirm" required autocomplete="new-password"></div>
          </div>
          <p style="margin-top:18px"><button class="btn" type="submit">Create account</button></p>
        </form>
        <p class="muted">Already registered? <a href="<?= BASE_URL ?>/login.php">Log in</a>.</p>
      </div>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
