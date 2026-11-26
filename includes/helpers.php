<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function e($str): string
{
    return htmlspecialchars((string) ($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nl2br_safe($str): string
{
    return str_replace(["\r\n", "\r", "\n"], '<br>', e($str));
}

function refNo($id): string
{
    return 'CAIRS-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function fmtDate($d): string
{
    if (!$d) return '';
    try {
        $dt = new DateTime((string) $d);
        $dt->setTimezone(new DateTimeZone('Asia/Manila'));
        return $dt->format('M j, Y, g:i A');
    } catch (Throwable $e) {
        return '';
    }
}

function fmtManilaClock(): string
{
    $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $dt->format('D, M j, g:i A');
}

function fmtManilaDateLong(): string
{
    $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $dt->format('l, F j, Y');
}

function statusLabel(?string $s): string
{
    return ucwords(str_replace('_', ' ', (string) $s));
}

function badge(?string $s): string
{
    $val = (string) $s;
    return '<span class="badge s-' . e($val) . '">' . e(statusLabel($val)) . '</span>';
}

function prio(?string $p): string
{
    $str = (string) ($p ?: 'medium');
    return '<span class="badge p-' . e($str) . '">' . e(ucfirst($str)) . '</span>';
}

function homeFor(?string $role): string
{
    $map = [
        'resident' => 'resident/dashboard.php',
        'admin' => 'admin/dashboard.php',
        'volunteer' => 'volunteer/dashboard.php',
    ];
    return $map[$role ?? ''] ?? 'login.php';
}

function redirectTo(string $relPath): never
{
    $clean = ltrim($relPath, '/');
    header('Location: ' . BASE_URL . '/' . $clean);
    exit;
}

function setFlash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'] = ['m' => $msg, 't' => $type];
}

function popFlash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function csrfVerify(?string $token): bool
{
    $expected = $_SESSION['csrf'] ?? '';
    return is_string($token) && $expected !== '' && hash_equals($expected, $token);
}

function csrfCheckOrDie(): void
{
    if (!csrfVerify($_POST['csrf'] ?? null)) {
        http_response_code(419);
        exit('Session expired. Go back, refresh the page and try again.');
    }
}

function apiCsrfCheck(): void
{
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'login']);
        exit;
    }
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $reqWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (!csrfVerify($headerToken) || $reqWith !== 'fetch') {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or expired security token.']);
        exit;
    }
}

function syncActiveUser(): ?array
{
    $u = $_SESSION['user'] ?? null;
    if (!$u) return null;
    $stmt = pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $u['id']]);
    $live = $stmt->fetch();
    if (!$live || $live['status'] !== 'active') {
        unset($_SESSION['user']);
        return null;
    }
    $_SESSION['user'] = [
        'id' => (int) $live['id'],
        'full_name' => $live['full_name'],
        'email' => $live['email'],
        'role' => $live['role'],
    ];
    return $live;
}

function requireRole(string $role): array
{
    $live = syncActiveUser();
    if (!$live) {
        redirectTo('login.php');
    }
    if ($live['role'] !== $role) {
        redirectTo(homeFor($live['role']));
    }
    return $live;
}

function setting(string $key, string $fallback = ''): string
{
    if ($key === 'sms_api_key' && getenv('SEMAPHORE_API_KEY')) {
        return (string) getenv('SEMAPHORE_API_KEY');
    }
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (pdo()->query('SELECT k, v FROM settings') as $row) {
                $cache[$row['k']] = $row['v'];
            }
        } catch (Throwable $e) {
        }
    }
    return array_key_exists($key, $cache) ? (string) $cache[$key] : $fallback;
}

function setSetting(string $key, string $value): void
{
    $stmt = pdo()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $stmt->execute([$key, $value]);
}

function initials(string $name): string
{
    $words = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));
    $words = array_slice($words, 0, 2);
    $out = '';
    foreach ($words as $w) {
        $out .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $out ?: '?';
}

function avatarBadgeStyles(): array
{
    return [
        ['id' => 'initials', 'label' => 'Primary', 'kind' => 'initials'],
        ['id' => 'indigo', 'label' => 'Indigo', 'kind' => 'initials'],
        ['id' => 'emerald', 'label' => 'Emerald', 'kind' => 'initials'],
        ['id' => 'amber', 'label' => 'Amber', 'kind' => 'initials'],
        ['id' => 'rose', 'label' => 'Rose', 'kind' => 'initials'],
        ['id' => 'shield', 'label' => 'Shield', 'kind' => 'image', 'img' => '/assets/images/badges/badge_shield.jpg'],
        ['id' => 'responder', 'label' => 'Responder', 'kind' => 'image', 'img' => '/assets/images/badges/badge_responder.jpg'],
        ['id' => 'rings', 'label' => 'Highlight', 'kind' => 'image', 'img' => '/assets/images/badges/badge_highlight.jpg'],
        ['id' => 'shapes', 'label' => 'Shapes', 'kind' => 'image', 'img' => '/assets/images/badges/badge_shapes.jpg'],
        ['id' => 'grid', 'label' => 'Grid', 'kind' => 'image', 'img' => '/assets/images/badges/badge_grid.jpg'],
    ];
}

function sanitizeAvatarUrl(?string $url): string
{
    $raw = trim((string) $url);
    if ($raw === '') return '';
    if (preg_match('/^\/assets\/uploads\/[A-Za-z0-9_.\-]+$/', $raw)) return $raw;
    $parts = parse_url($raw);
    if ($parts && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
        return $raw;
    }
    return '';
}

function icon(string $name, int $size = 20): string
{
    $p = [
        'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'file' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'plus' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/>',
        'list' => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'sliders' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'clipboard' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
        'pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'menu' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'loader' => '<path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/>',
        'check' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
        'alert' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
        'tag' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/>',
        'siren' => '<path d="M7 18v-6a5 5 0 1 1 10 0v6"/><path d="M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z"/><path d="M21 12h1"/><path d="M18.5 4.5 18 5"/><path d="M2 12h1"/><path d="M12 2v1"/><path d="m4.929 4.929.707.707"/><path d="M12 12v6"/>',
        'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'flame' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'radar' => '<path d="M19.07 4.93A10 10 0 0 0 6.99 3.34"/><path d="M4 6h.01"/><path d="M2.29 9.62A10 10 0 1 0 21.31 8.35"/><path d="M16.24 7.76A6 6 0 1 0 8.23 16.67"/><path d="M12 18h.01"/><path d="M17.99 11.66A6 6 0 0 1 15.77 16.67"/><circle cx="12" cy="12" r="2"/><path d="m13.41 10.59 5.66-5.66"/>',
        'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
        'check-double' => '<path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'panel-close' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="m16 15-3-3 3-3"/>',
        'panel-open' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="m14 9 3 3-3 3"/>',
        'chevrons-up-down' => '<path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
        'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
        'crop' => '<path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'sparkles' => '<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>',
        'shapes' => '<path d="M8.3 10a.7.7 0 0 1-.626-1.079L11.4 3a.7.7 0 0 1 1.198-.043L16.3 8.9a.7.7 0 0 1-.572 1.1Z"/><rect x="3" y="14" width="7" height="7" rx="1"/><circle cx="17.5" cy="17.5" r="3.5"/>',
        'grid' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
        'check-simple' => '<path d="M20 6 9 17l-5-5"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    ];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($p[$name] ?? $p['file']) . '</svg>';
}

function renderAvatar(?array $profile, string $extraClass = '', bool $withStatus = false): string
{
    $name = (string) ($profile['full_name'] ?? '');
    $init = initials($name);
    $type = ($profile['avatar_type'] ?? 'badge') === 'image' ? 'image' : 'badge';
    $styles = avatarBadgeStyles();
    $styleIds = array_column($styles, 'id');
    $styleId = in_array($profile['avatar_style'] ?? '', $styleIds, true) ? $profile['avatar_style'] : 'initials';
    $shape = ($profile['avatar_shape'] ?? 'circle') === 'badge' ? 'badge' : 'circle';
    $imgUrl = $type === 'image' ? sanitizeAvatarUrl($profile['avatar_url'] ?? '') : '';

    $styleMeta = $styles[0];
    foreach ($styles as $s) {
        if ($s['id'] === $styleId) {
            $styleMeta = $s;
            break;
        }
    }

    $classes = ['avatar'];
    if ($extraClass !== '') $classes[] = $extraClass;
    $classes[] = $shape === 'badge' ? 'av-shape-badge' : 'av-shape-circle';
    $hasBadgeImg = $imgUrl === '' && ($styleMeta['kind'] ?? '') === 'image';
    $classes[] = ($imgUrl !== '' || $hasBadgeImg) ? 'av-has-img' : 'av-style-' . $styleId;

    if ($imgUrl !== '') {
        $inner = '<img class="avatar-img" src="' . e($imgUrl) . '" alt="' . e($name) . '" referrerpolicy="no-referrer">';
    } elseif (($styleMeta['kind'] ?? '') === 'image') {
        $inner = '<img class="avatar-img" src="' . e(BASE_URL . $styleMeta['img']) . '" alt="' . e($styleMeta['label'] . ' badge') . '" referrerpolicy="no-referrer">';
    } elseif (($styleMeta['kind'] ?? '') === 'icon') {
        $sz = str_contains($extraClass, 'avatar-xl') ? 28 : (str_contains($extraClass, 'avatar-lg') ? 22 : 17);
        $inner = '<span class="avatar-glyph" aria-hidden="true">' . icon($styleMeta['ic'], $sz) . '</span>';
    } else {
        $inner = '<span data-user-initials>' . e($init) . '</span>';
    }

    $statusHtml = $withStatus ? '<i class="avatar-status" aria-hidden="true"></i>' : '';
    return '<span class="' . implode(' ', $classes) . '" data-user-avatar>' . $inner . $statusHtml . '</span>';
}

function greeting(): string
{
    $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $h = (int) $dt->format('G');
    return $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
}

function statusTones(): array
{
    return [
        'pending' => 'warning',
        'assigned' => 'info',
        'in_progress' => 'progress',
        'resolved' => 'success',
        'rejected' => 'danger',
    ];
}

function priorityTones(): array
{
    return ['high' => 'danger', 'medium' => 'warning', 'low' => 'muted'];
}

function defaultPriority(string $categoryName): string
{
    $high = ['fire', 'medical emergency', 'flood', 'road accident', 'theft / crime'];
    $low = ['noise disturbance', 'garbage / sanitation', 'broken streetlight / road'];
    $n = strtolower(trim($categoryName));
    if (in_array($n, $high, true)) return 'high';
    if (in_array($n, $low, true)) return 'low';
    return 'medium';
}

function isOverdue(array $r): bool
{
    return ($r['status'] ?? '') === 'pending' && strtotime((string) $r['created_at']) < (time() - 86400);
}

function csvSafe($v): string
{
    $s = (string) ($v ?? '');
    return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
}

function renderWelcome(string $name, string $subtitle, string $actionsHtml = ''): string
{
    return '<section class="welcome"><div><p class="eyebrow" data-clock-date>' . e(fmtManilaDateLong()) . '</p><h1>' . e(greeting()) . ', ' . e($name) . '</h1><p class="muted">' . e($subtitle) . '</p></div><div class="welcome-side"><span class="clock" data-clock>' . e(fmtManilaClock()) . '</span>' . $actionsHtml . '</div></section>';
}

function statCard(string $ic, string $label, $value, string $note = '', string $tone = 'primary', ?array $trend = null): string
{
    $trendHtml = $trend ? '<span class="trend ' . e($trend[0]) . '">' . e($trend[1]) . '</span>' : '';
    return '<div class="stat tone-' . e($tone) . '"><span class="stat-label">' . e($label) . '</span><span class="stat-icon">' . icon($ic, 22) . '</span><b>' . e($value) . '</b><span class="stat-note">' . $trendHtml . e($note) . '</span></div>';
}

function renderReportCard(array $r, string $link): string
{
    $pr = $r['priority'] ?? 'medium';
    $sosCls = !empty($r['is_sos']) ? ' rc-sos' : '';
    $sosBadge = !empty($r['is_sos']) ? ' <span class="badge sos">SOS</span>' : '';
    $overdueBadge = isOverdue($r) ? ' <span class="badge overdue">Overdue</span>' : '';
    $meta = '';
    if (!empty($r['category'])) $meta .= '<li>' . icon('tag', 15) . e($r['category']) . '</li>';
    if (!empty($r['location'])) $meta .= '<li>' . icon('pin', 15) . e($r['location']) . '</li>';
    $meta .= '<li>' . icon('calendar', 15) . e(fmtDate($r['created_at'])) . '</li>';
    if (!empty($r['resident_name'])) $meta .= '<li>' . icon('user', 15) . 'Reported by ' . e($r['resident_name']) . '</li>';
    $meta .= '<li>' . icon('shield', 15) . (!empty($r['volunteer_name']) ? 'Assigned to ' . e($r['volunteer_name']) : '<span>Not assigned yet</span>') . '</li>';

    return '<article class="report-card rc-' . e($pr) . $sosCls . '"><div class="rc-top"><span class="rc-ref">' . refNo($r['id']) . $sosBadge . $overdueBadge . '</span>' . badge($r['status']) . '</div><h3 class="rc-title"><a href="' . e($link) . '">' . e($r['title']) . '</a></h3><ul class="rc-meta">' . $meta . '</ul><div class="rc-foot">' . prio($pr) . '<a class="btn ghost small" href="' . e($link) . '">View details</a></div></article>';
}

function renderBars(array $dataObj, array $tones = []): string
{
    if (!$dataObj) return '<p class="empty">No data yet.</p>';
    $values = array_map('intval', array_values($dataObj));
    $total = max(1, array_sum($values));
    $html = '';
    foreach ($dataObj as $label => $raw) {
        $n = (int) $raw;
        $pct = (int) round(($n / $total) * 100);
        $w = $n > 0 ? max(3, $pct) : 0;
        $barStyle = $n > 0 ? 'width:' . $w . '%' : 'width:0;min-width:0';
        $html .= '<div class="bar" title="' . e(statusLabel((string) $label)) . ': ' . $n . ' (' . $pct . '%)"><span class="l">' . e(statusLabel((string) $label)) . '</span><span class="track"><i class="tone-' . e($tones[$label] ?? 'primary') . '" style="' . $barStyle . '"></i></span><b>' . $n . '</b><em>' . $pct . '%</em></div>';
    }
    return $html;
}

function renderDonut(array $counts): string
{
    $tones = statusTones();
    $total = array_sum(array_map('intval', $counts));
    $parts = [];
    $acc = 0.0;
    if ($total > 0) {
        foreach ($tones as $s => $t) {
            $n = (int) ($counts[$s] ?? 0);
            if (!$n) continue;
            $from = round($acc, 2);
            $acc += ($n / $total) * 100;
            $to = round($acc, 2);
            $parts[] = "var(--{$t}) {$from}% {$to}%";
        }
    }
    $bg = $parts ? 'conic-gradient(' . implode(',', $parts) . ')' : 'var(--border)';
    $legend = '';
    foreach (array_keys($tones) as $s) {
        $legend .= '<li><i class="dot dot-' . $s . '"></i>' . e(statusLabel($s)) . '<b>' . (int) ($counts[$s] ?? 0) . '</b></li>';
    }
    return '<div class="donut-wrap"><div class="donut" style="background:' . $bg . '"><div class="donut-center"><b>' . $total . '</b><span>reports</span></div></div><ul class="legend-list">' . $legend . '</ul></div>';
}

function renderColumns(array $items, string $tone = 'primary'): string
{
    if (!$items) return '<p class="empty">No data yet.</p>';
    $values = array_map(fn($it) => (int) ($it['value'] ?? 0), $items);
    $maxVal = max(0, ...$values);
    $totalSum = array_sum($values);
    $avgVal = number_format($totalSum / count($items), 1);
    $peakItem = $items[0];
    foreach ($items as $it) {
        if ((int) $it['value'] > (int) $peakItem['value']) $peakItem = $it;
    }
    $step = $maxVal > 4 ? (int) ceil($maxVal / 4) : 1;
    $maxTick = $step * 4;
    $ticks = [$maxTick, $step * 3, $step * 2, $step, 0];

    $summaryHtml = '<div class="chart-summary"><span><strong>' . $totalSum . '</strong> in period</span><span class="sep" aria-hidden="true">&middot;</span><span><strong>' . $avgVal . '</strong> / month avg</span><span class="sep" aria-hidden="true">&middot;</span><span>Peak: <strong>' . ((int) $peakItem['value'] > 0 ? e($peakItem['fullLabel']) . ' (' . (int) $peakItem['value'] . ')' : 'None') . '</strong></span></div>';
    $yAxisHtml = '<div class="col-yaxis" aria-hidden="true">' . implode('', array_map(fn($t) => '<span>' . $t . '</span>', $ticks)) . '</div>';
    $gridLinesHtml = '<div class="col-gridlines" aria-hidden="true">' . implode('', array_map(fn($idx) => '<span class="' . ($idx === count($ticks) - 1 ? 'is-base' : '') . '"></span>', array_keys($ticks))) . '</div>';

    $colsHtml = '';
    foreach ($items as $it) {
        $v = (int) $it['value'];
        $pct = $maxTick > 0 ? ($v / $maxTick) * 100 : 0;
        $h = $v > 0 ? max(3, round($pct, 1)) : 0;
        $share = $totalSum > 0 ? (int) round(($v / $totalSum) * 100) : 0;
        $resNote = isset($it['resolved']) ? ', ' . (int) $it['resolved'] . ' resolved' : '';
        $tip = $it['fullLabel'] . ': ' . $v . ' report' . ($v === 1 ? '' : 's') . ' (' . $share . '%' . $resNote . ')';
        $zeroCls = $v === 0 ? ' is-zero' : '';
        $tipBottom = $v > 0 ? "calc({$h}% + 26px)" : '28px';
        $tipMeta = $it['ym'] ?: ($it['subLabel'] ?? '');
        $resolvedRow = isset($it['resolved'])
            ? '<div class="col-tip-row"><span class="col-tip-k"><i class="dot dot-resolved"></i>Resolved</span><b class="col-tip-v tone-emerald">' . (int) $it['resolved'] . '</b></div>'
            : '';
        $colsHtml .= '<div class="col tone-' . e($tone) . $zeroCls . '" tabindex="0" aria-label="' . e($tip) . '"><div class="col-plot"><div class="col-tip" role="tooltip" style="bottom:' . $tipBottom . '"><div class="col-tip-head"><strong>' . e($it['label']) . '</strong>' . ($tipMeta ? '<span>' . e($tipMeta) . '</span>' : '') . '</div><div class="col-tip-rows"><div class="col-tip-row"><span class="col-tip-k"><i class="dot dot-reports"></i>Reports</span><b class="col-tip-v tone-primary">' . $v . '</b></div>' . $resolvedRow . '</div></div><span class="col-val" style="bottom:' . ($v > 0 ? "calc({$h}% + 6px)" : '8px') . '">' . $v . '</span><span class="col-track"><i style="height:' . $h . '%"></i></span></div><span class="col-label"><b>' . e($it['label']) . '</b>' . (!empty($it['subLabel']) ? '<small>' . e($it['subLabel']) . '</small>' : '') . '</span></div>';
    }

    return '<div class="col-chart-wrap">' . $summaryHtml . '<div class="col-chart">' . $yAxisHtml . '<div class="col-canvas">' . $gridLinesHtml . '<div class="cols" style="grid-template-columns:repeat(' . count($items) . ',minmax(0,1fr))">' . $colsHtml . '</div></div></div></div>';
}

function renderActivity(array $rows, string $linkBase): string
{
    if (!$rows) return '<p class="empty">No activity yet.</p>';
    $items = '';
    foreach ($rows as $a) {
        $items .= '<li><a href="' . e($linkBase . $a['report_id']) . '"><strong>' . refNo($a['report_id']) . '</strong> ' . e($a['title']) . '</a> ' . badge($a['status']) . '<br><span class="muted">' . e(fmtDate($a['created_at'])) . ' by ' . e($a['full_name']) . '</span>' . (!empty($a['remark']) ? '<p>' . e($a['remark']) . '</p>' : '') . '</li>';
    }
    return '<ol class="timeline">' . $items . '</ol>';
}

function renderTimeline(int $reportId): string
{
    $stmt = pdo()->prepare(
        'SELECT ru.*, COALESCE(u.full_name, "System") AS full_name
         FROM report_updates ru
         LEFT JOIN users u ON u.id = ru.user_id
         WHERE ru.report_id = ?
         ORDER BY ru.id DESC'
    );
    $stmt->execute([$reportId]);
    $items = '';
    foreach ($stmt->fetchAll() as $r) {
        $items .= '<li>' . badge($r['status']) . ' <span class="muted">' . e(fmtDate($r['created_at'])) . ' by ' . e($r['full_name']) . '</span>' . (!empty($r['remark']) ? '<p>' . e($r['remark']) . '</p>' : '') . '</li>';
    }
    return '<ol class="timeline">' . $items . '</ol>';
}

function renderAnnouncements(int $limit = 3): string
{
    $stmt = pdo()->prepare(
        'SELECT a.*, COALESCE(u.full_name, "Admin") AS full_name
         FROM announcements a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.is_active = 1
         ORDER BY a.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (!$rows) return '';
    $items = '';
    foreach ($rows as $a) {
        $items .= '<article class="ann ann-' . e($a['level']) . '"><span class="ann-ic">' . icon($a['level'] === 'critical' ? 'siren' : 'megaphone', 20) . '</span><div><strong>' . e($a['title']) . '</strong><p>' . nl2br_safe($a['body']) . '</p><span class="muted">' . e(fmtDate($a['created_at'])) . ' &middot; ' . e($a['full_name']) . '</span></div></article>';
    }
    return '<section class="alerts-strip" aria-label="Community announcements">' . $items . '</section>';
}

function renderHotlines(): string
{
    $items = [
        ['Emergency', setting('emergency_hotline', '911'), 'siren'],
        ['Barangay', setting('contact_phone', ''), 'shield'],
        ['Fire', setting('hotline_fire', ''), 'flame'],
        ['Police', setting('hotline_police', ''), 'shield'],
        ['Medical', setting('hotline_medical', ''), 'plus'],
    ];
    $links = '';
    foreach ($items as [$label, $num, $ic]) {
        if ($num === '') continue;
        $tel = preg_replace('/[^0-9+]/', '', $num);
        $links .= '<a class="hotline" href="tel:' . e($tel) . '">' . icon($ic, 20) . '<span><small>' . e($label) . '</small><b>' . e($num) . '</b></span></a>';
    }
    return '<div class="hotlines">' . $links . '</div>';
}

function mapAssets(): string
{
    return '';
}

function mapCenter(): array
{
    return [
        (float) (setting('map_lat', '8.4542') ?: 8.4542),
        (float) (setting('map_lng', '124.6319') ?: 124.6319),
    ];
}

function renderMapPicker($lat = '', $lng = ''): string
{
    $has = $lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng);
    [$cl, $cn] = $has ? [(float) $lat, (float) $lng] : mapCenter();
    return mapAssets() . '<div class="map-card"><div class="map-head"><strong>Pin the location</strong><span class="legend"><span class="legend-item"><i class="dot tone-primary"></i>Click the map to drop a pin</span></span></div><div id="map-picker" class="map" data-lat="' . $cl . '" data-lng="' . $cn . '" data-pin="' . ($has ? 1 : 0) . '"></div><input type="hidden" id="latitude" name="latitude" value="' . e($has ? $lat : '') . '"><input type="hidden" id="longitude" name="longitude" value="' . e($has ? $lng : '') . '"><div class="map-foot"><button type="button" id="locate" class="btn ghost small">' . icon('pin', 15) . ' Use my current location</button><span class="muted">The pin helps volunteers find the exact spot.</span></div></div>';
}

function renderMapView(array $r): string
{
    if (!isset($r['latitude'], $r['longitude']) || $r['latitude'] === null || $r['longitude'] === null) return '';
    $lat = (float) $r['latitude'];
    $lng = (float) $r['longitude'];
    return mapAssets() . '<div class="map-card"><div class="map-head"><strong>Pinned location</strong><a target="_blank" rel="noopener" href="https://www.google.com/maps?q=' . $lat . ',' . $lng . '">Open in Google Maps</a></div><div id="map-view" class="map" data-lat="' . $lat . '" data-lng="' . $lng . '"></div><div class="map-foot"><span class="legend-item"><i class="dot tone-primary"></i>Reported location</span><span class="muted">' . e(number_format($lat, 5)) . ', ' . e(number_format($lng, 5)) . '</span></div></div>';
}

function renderIncidentMap(string $title = 'Incident map', int $height = 420): string
{
    $rows = pdo()->query('SELECT id, title, status, latitude, longitude FROM reports WHERE latitude IS NOT NULL AND longitude IS NOT NULL')->fetchAll();
    $pts = [];
    foreach ($rows as $p) {
        $pts[] = [
            'lat' => (float) $p['latitude'],
            'lng' => (float) $p['longitude'],
            'ref' => refNo($p['id']),
            'title' => $p['title'],
            'key' => $p['status'],
            'status' => statusLabel($p['status']),
            'url' => BASE_URL . '/admin/report_details.php?id=' . (int) $p['id'],
        ];
    }
    [$mlat, $mlng] = mapCenter();
    $legend = '';
    foreach (['pending', 'assigned', 'in_progress', 'resolved', 'rejected'] as $s) {
        $legend .= '<span class="legend-item"><i class="dot dot-' . $s . '"></i>' . e(statusLabel($s)) . '</span>';
    }
    $body = !$pts
        ? '<p class="empty">No reports have a map pin yet.</p>'
        : mapAssets() . '<div id="map-all" class="map" style="height:' . $height . 'px" data-lat="' . $mlat . '" data-lng="' . $mlng . '" data-points="' . e(json_encode($pts)) . '"></div>';
    return '<section class="card map-card" id="incident-map"><div class="map-head"><div><h2>' . e($title) . '</h2><p class="muted">' . count($pts) . ' report' . (count($pts) === 1 ? '' : 's') . ' with a map pin</p></div><div class="legend">' . $legend . '<a class="btn small" href="' . BASE_URL . '/admin/dispatch.php">' . icon('radar', 15) . ' Pin &amp; Dispatch</a></div></div>' . $body . '</section>';
}

function renderAlerts(int $userId): string
{
    $stmt = pdo()->prepare('SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 8');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    if (!$rows) return '';
    $items = '';
    foreach ($rows as $n) {
        $link = $n['report_id'] ? 'report_details.php?id=' . (int) $n['report_id'] : '#';
        $items .= '<a class="notif unread" href="' . $link . '">' . e($n['message']) . '<br><span class="muted">' . e(fmtDate($n['created_at'])) . '</span></a>';
    }
    return '<div class="card"><h2>New alerts</h2>' . $items . '</div>';
}

function fetchReport(int $id): ?array
{
    $stmt = pdo()->prepare(
        'SELECT r.*, COALESCE(c.name, "Other") AS category,
                COALESCE(res.full_name, "Unknown") AS resident_name,
                COALESCE(res.phone, "") AS resident_phone,
                vol.full_name AS volunteer_name
         FROM reports r
         LEFT JOIN categories c ON c.id = r.category_id
         LEFT JOIN users res ON res.id = r.resident_id
         LEFT JOIN users vol ON vol.id = r.volunteer_id
         WHERE r.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function logUpdate(int $reportId, int $userId, string $status, ?string $remark): void
{
    $stmt = pdo()->prepare('INSERT INTO report_updates (report_id, user_id, status, remark) VALUES (?, ?, ?, ?)');
    $stmt->execute([$reportId, $userId, $status, $remark ?: null]);
}

function sendSms(string $phone, string $message): array
{
    $key = setting('sms_api_key', '');
    if ($key === '') return [false, 'No API key saved in Settings.'];
    $number = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($number) < 10) return [false, 'Invalid phone number.'];

    $params = ['apikey' => $key, 'number' => $number, 'message' => $message];
    $sender = setting('sms_sender', '');
    if ($sender !== '') $params['sendername'] = $sender;

    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp !== false && $code >= 200 && $code < 300) return [true, 'Sent.'];
    if ($err) return [false, 'Could not reach the SMS service: ' . $err];
    return [false, "SMS service replied {$code}: " . substr((string) $resp, 0, 150)];
}

function notify(int $userId, ?int $reportId, string $message): void
{
    $stmt = pdo()->prepare('INSERT INTO notifications (user_id, report_id, message, is_read) VALUES (?, ?, ?, 0)');
    $stmt->execute([$userId, $reportId, $message]);

    if (setting('sms_enabled', '0') === '1') {
        $uStmt = pdo()->prepare('SELECT role, phone FROM users WHERE id = ? LIMIT 1');
        $uStmt->execute([$userId]);
        $u = $uStmt->fetch();
        if ($u && in_array($u['role'], ['resident', 'volunteer'], true) && !empty($u['phone'])) {
            sendSms((string) $u['phone'], '[' . setting('site_name', 'CAIRS') . '] ' . $message);
        }
    }
}

function loginLocked(string $email, string $ip): bool
{
    $stmt = pdo()->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE created_at > (NOW() - INTERVAL 10 MINUTE) AND (LOWER(email) = LOWER(?) OR (ip <> "" AND ip = ?))'
    );
    $stmt->execute([$email, $ip]);
    return (int) $stmt->fetchColumn() >= 5;
}

function loginFailed(string $email, string $ip): void
{
    pdo()->exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    $stmt = pdo()->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)');
    $stmt->execute([strtolower($email), $ip]);
}

function loginClear(string $email): void
{
    $stmt = pdo()->prepare('DELETE FROM login_attempts WHERE LOWER(email) = LOWER(?)');
    $stmt->execute([$email]);
}
