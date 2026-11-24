<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');

$u = syncActiveUser();
if (!$u) {
    http_response_code(401);
    echo json_encode(['error' => 'login']);
    exit;
}

$uploadDir = __DIR__ . '/../assets/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$removeOldAvatars = function (int $uid, string $keep = '') use ($uploadDir): void {
    $prefix = "avatar_{$uid}_";
    foreach (scandir($uploadDir) ?: [] as $f) {
        if (str_starts_with($f, $prefix) && $f !== $keep) {
            @unlink($uploadDir . '/' . $f);
        }
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    echo json_encode([
        'full_name' => $u['full_name'],
        'email' => $u['email'],
        'phone' => $u['phone'] ?? '',
        'address' => $u['address'] ?? '',
        'role' => $u['role'],
        'created_at' => $u['created_at'],
        'created_fmt' => fmtDate($u['created_at']),
        'initials' => initials($u['full_name']),
        'avatar_type' => ($u['avatar_type'] ?? 'badge') === 'image' ? 'image' : 'badge',
        'avatar_style' => $u['avatar_style'] ?? 'initials',
        'avatar_shape' => ($u['avatar_shape'] ?? 'circle') === 'badge' ? 'badge' : 'circle',
        'avatar_url' => sanitizeAvatarUrl($u['avatar_url'] ?? ''),
    ]);
    exit;
}

apiCsrfCheck();
$body = json_decode((string) file_get_contents('php://input'), true) ?: [];

$uid = (int) $u['id'];
$name = trim((string) ($body['full_name'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$addr = trim((string) ($body['address'] ?? ''));
$curPass = (string) ($body['current_password'] ?? '');
$newPass = (string) ($body['new_password'] ?? '');

$avatarType = ($body['avatar_type'] ?? '') === 'image' ? 'image' : 'badge';
$validStyleIds = array_column(avatarBadgeStyles(), 'id');
$avatarStyle = in_array($body['avatar_style'] ?? '', $validStyleIds, true) ? $body['avatar_style'] : 'initials';
$avatarShape = ($body['avatar_shape'] ?? '') === 'badge' ? 'badge' : 'circle';
$avatarData = trim((string) ($body['avatar_data'] ?? ''));
$rawAvatarUrl = trim((string) ($body['avatar_url'] ?? ''));

$fail = function (string $msg, string $field): never {
    http_response_code(400);
    echo json_encode(['error' => $msg, 'field' => $field]);
    exit;
};

if ($name === '') $fail('Enter your full name.', 'name');
if (mb_strlen($name) > 100) $fail('Name is too long.', 'name');
if (mb_strlen($phone) > 40) $fail('Phone number is too long.', 'phone');
if (mb_strlen($addr) > 200) $fail('Address is too long.', 'address');
if ($u['role'] === 'resident' && $addr === '') $fail('Enter your address or purok.', 'address');

if ($newPass !== '') {
    if (!password_verify($curPass, $u['password'])) {
        $fail('Current password is incorrect.', 'password');
    }
    if (strlen($newPass) < 6) {
        $fail('New password must be at least 6 characters.', 'password');
    }
}

$nextAvatarUrl = '';
$nextAvatarType = $avatarType;
if ($avatarType === 'image') {
    if ($avatarData !== '') {
        if (!preg_match('/^data:image\/(png|jpeg|webp);base64,([A-Za-z0-9+\/=]+)$/', $avatarData, $m)) {
            $fail('Unsupported image format. Use PNG, JPG, or WebP.', 'avatar');
        }
        $buf = base64_decode($m[2], true);
        if ($buf === false || strlen($buf) === 0 || strlen($buf) > 3 * 1024 * 1024) {
            $fail('Profile photo must be under 3 MB.', 'avatar');
        }
        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $fname = "avatar_{$uid}_" . time() . ".{$ext}";
        file_put_contents($uploadDir . '/' . $fname, $buf);
        $removeOldAvatars($uid, $fname);
        $nextAvatarUrl = '/assets/uploads/' . $fname;
    } else {
        $safeUrl = sanitizeAvatarUrl($rawAvatarUrl);
        if ($safeUrl === '') {
            $fail('Please provide a valid http(s) image URL or upload a photo.', 'avatar');
        }
        if (!str_starts_with($safeUrl, '/assets/uploads/')) {
            $removeOldAvatars($uid);
        }
        $nextAvatarUrl = $safeUrl;
    }
} else {
    $removeOldAvatars($uid);
    $nextAvatarType = 'badge';
    $nextAvatarUrl = '';
}

$pwHash = $newPass !== '' ? password_hash($newPass, PASSWORD_BCRYPT) : $u['password'];
$stmt = pdo()->prepare(
    'UPDATE users SET full_name = ?, phone = ?, address = ?, password = ?, avatar_type = ?, avatar_style = ?, avatar_shape = ?, avatar_url = ? WHERE id = ?'
);
$stmt->execute([
    $name,
    $phone ?: null,
    $addr ?: null,
    $pwHash,
    $nextAvatarType,
    $avatarStyle,
    $avatarShape,
    $nextAvatarUrl ?: null,
    $uid,
]);

$_SESSION['user']['full_name'] = $name;

echo json_encode([
    'ok' => true,
    'user' => [
        'full_name' => $name,
        'email' => $u['email'],
        'phone' => $phone,
        'address' => $addr,
        'role' => $u['role'],
        'initials' => initials($name),
        'avatar_type' => $nextAvatarType,
        'avatar_style' => $avatarStyle,
        'avatar_shape' => $avatarShape,
        'avatar_url' => $nextAvatarUrl,
    ],
]);
