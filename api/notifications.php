<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');

$u = syncActiveUser();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if (!$u) {
    http_response_code(401);
    echo json_encode(['error' => 'login']);
    exit;
}

$stmt = pdo()->prepare(
    'SELECT n.*, r.title AS report_title, r.is_sos AS report_is_sos
     FROM notifications n
     LEFT JOIN reports r ON r.id = n.report_id
     WHERE n.user_id = ?
     ORDER BY n.id DESC'
);
$stmt->execute([(int) $u['id']]);
$all = $stmt->fetchAll();

$unread = 0;
foreach ($all as $n) {
    if ((int) $n['is_read'] === 0) $unread++;
}

$items = [];
foreach (array_slice($all, 0, 30) as $n) {
    $rid = $n['report_id'] ? (int) $n['report_id'] : null;
    $isSos = ((int) ($n['report_is_sos'] ?? 0) === 1) || (bool) preg_match('/^SOS\b/i', (string) $n['message']);
    $ref = $rid ? refNo($rid) : '';
    $title = 'Incident Update';
    if ($isSos) {
        $title = 'Emergency SOS Alert';
    } elseif (!empty($n['report_title'])) {
        $title = $ref . ' · ' . $n['report_title'];
    } elseif ($ref !== '') {
        $title = $ref . ' Update';
    }
    $items[] = [
        'id' => (int) $n['id'],
        'report_id' => $rid,
        'title' => $title,
        'message' => $n['message'],
        'is_read' => (int) $n['is_read'] === 1,
        'is_sos' => $isSos,
        'ref' => $ref,
        'created_at' => $n['created_at'],
        'created_fmt' => fmtDate($n['created_at']),
        'url' => $rid ? BASE_URL . '/' . $u['role'] . '/report_details.php?id=' . $rid : '',
        'cta' => $rid ? 'View ' . $ref : '',
    ];
}

echo json_encode([
    'unread' => $unread,
    'total' => count($all),
    'items' => $items,
]);
