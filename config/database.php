<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    // Auto-detect the subfolder CAIRS lives in (empty string when it is the site root).
    $cairsDocRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $cairsAppRoot = realpath(__DIR__ . '/..');
    if ($cairsDocRoot !== '' && $cairsAppRoot !== false) {
        $cairsDocRootReal = realpath($cairsDocRoot);
        if ($cairsDocRootReal !== false) {
            $cairsDoc = rtrim(str_replace('\\', '/', $cairsDocRootReal), '/');
            $cairsApp = str_replace('\\', '/', $cairsAppRoot);
            define('BASE_URL', str_starts_with($cairsApp, $cairsDoc . '/') ? substr($cairsApp, strlen($cairsDoc)) : '');
        }
    }
    if (!defined('BASE_URL')) {
        define('BASE_URL', '');
    }
}

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

    ensureDatabaseExists();

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    ensureSchema($pdo);
    ensureDemoAccounts($pdo);
    return $pdo;
}

function ensureDatabaseExists(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ];

    try {
        $server = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
        $stmt = $server->prepare('SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([DB_NAME]);
        if (!$stmt->fetchColumn()) {
            $name = str_replace('`', '``', DB_NAME);
            $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    } catch (PDOException $e) {
        throw new RuntimeException(
            'CAIRS could not connect to MySQL. Make sure MySQL is running in Laragon and that DB_USER/DB_PASS in config/database.php are correct. ' . $e->getMessage(),
            0,
            $e
        );
    }
}

function ensureSchema(PDO $pdo): void
{
    try {
        $stmt = $pdo->query("SELECT v FROM settings WHERE k = 'schema_version' LIMIT 1");
        if ($stmt && $stmt->fetchColumn() === '1') {
            return;
        }
    } catch (Throwable $e) {
        // Fresh database: the settings table does not exist yet.
    }

    $sqlFile = __DIR__ . '/../database/cairs.sql';
    if (is_readable($sqlFile)) {
        $sql = (string) file_get_contents($sqlFile);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*CREATE DATABASE[^;]*;\s*/mi', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*USE\s+[^;]+;\s*/mi', '', $sql) ?? $sql;

        foreach (splitSqlStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    $stmt = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $stmt->execute(['schema_version', '1']);
}

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $quote = '';
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];

        if ($quote !== '') {
            $current .= $char;
            if ($char === '\\' && $i + 1 < $length) {
                $current .= $sql[++$i];
            } elseif ($char === $quote) {
                $quote = '';
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $current .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($current);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $current = '';
            continue;
        }

        $current .= $char;
    }

    $statement = trim($current);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
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
