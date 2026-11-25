<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
apiCsrfCheck();

$uid = (int) $_SESSION['user']['id'];
$body = json_decode((string) file_get_contents('php://input'), true) ?: [];
$rawId = $body['id'] ?? null;
$mode = (string) ($body['mode'] ?? 'all');

if ($rawId !== null && $rawId !== '') {
    $nid = (int) $rawId;
    if ($nid <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notification ID.']);
        exit;
    }
    $stmt = pdo()->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([$nid, $uid]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Notification not found.']);
        exit;
    }
} elseif ($mode === 'read') {
    $stmt = pdo()->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1');
    $stmt->execute([$uid]);
} else {
    $stmt = pdo()->prepare('DELETE FROM notifications WHERE user_id = ?');
    $stmt->execute([$uid]);
}

$cnt = pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$cnt->execute([$uid]);
echo json_encode(['ok' => true, 'unread' => (int) $cnt->fetchColumn()]);
