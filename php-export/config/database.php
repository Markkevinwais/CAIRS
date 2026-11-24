<?php
declare(strict_types=1);

define('BASE_URL', ''); // Set to '/CAIRS' if hosted in a /CAIRS subfolder

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'cairs');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 30 * 24 * 60 * 60,
        'path' => '/',
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => $isHttps ? 'None' : 'Lax',
    ]);
    session_start();
}

function pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    ensureDemoAccounts($pdo);
    return $pdo;
}

function ensureDemoAccounts(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password, role, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(['System Admin', 'admin@cairs.local', password_hash('admin123', PASSWORD_BCRYPT), 'admin', '0900-000-0001', 'Barangay Hall', 'active']);
            $stmt->execute(['Vince Volunteer', 'volunteer@cairs.local', password_hash('volunteer123', PASSWORD_BCRYPT), 'volunteer', '0900-000-0002', 'Purok 2', 'active']);
            $stmt->execute(['Rita Resident', 'resident@cairs.local', password_hash('resident123', PASSWORD_BCRYPT), 'resident', '0900-000-0003', 'Purok 5', 'active']);
        }
    } catch (Throwable $e) {
        // Ignore if schema has not been imported yet
    }
}
