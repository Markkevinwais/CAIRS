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

$stmt = pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([(int) $u['id']]);
$out = ['unread' => (int) $stmt->fetchColumn()];

if ($u['role'] === 'admin') {
    $sosRows = pdo()->query(
        "SELECT id, title FROM reports WHERE is_sos = 1 AND status IN ('pending','assigned','in_progress') ORDER BY id DESC"
    )->fetchAll();
    $out['sos'] = count($sosRows);
    $last = $sosRows[0] ?? null;
    $out['sos_last'] = $last ? (int) $last['id'] : 0;
    $out['sos_title'] = $last ? refNo($last['id']) . ' ' . $last['title'] : '';
    $out['sos_url'] = $last ? BASE_URL . '/admin/report_details.php?id=' . (int) $last['id'] : '';
}

echo json_encode($out);
