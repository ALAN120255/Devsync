<?php
// api/messages.php — JSON API for real-time chat polling
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');
$db   = getDB();
$uid  = (int)$_SESSION['user_id'];

$action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {

    // ── Send a message ────────────────────────────────────────────────
    case 'send':
        $to      = (int)($_POST['receiver_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if (!$to || $message === '') { echo json_encode(['ok'=>false,'error'=>'Missing fields']); exit; }

        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?,?,?)");
        $stmt->execute([$uid, $to, $message]);
        $id = $db->lastInsertId();

        addNotification($to, 'New message', substr($message,0,80), 'message');

        echo json_encode([
            'ok'      => true,
            'id'      => $id,
            'message' => htmlspecialchars($message),
            'time'    => date('g:i A'),
        ]);
        break;

    // ── Fetch new messages since last_id ─────────────────────────────
    case 'fetch':
        $with   = (int)($_GET['with']    ?? 0);
        $lastId = (int)($_GET['last_id'] ?? 0);

        // mark as read
        $db->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?")
           ->execute([$uid, $with]);

        $stmt = $db->prepare(
            "SELECT m.id, m.sender_id, m.message, m.created_at,
                    u.full_name AS sender_name
             FROM   messages m
             JOIN   users u ON u.id = m.sender_id
             WHERE  ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
               AND  m.id > ?
             ORDER  BY m.created_at ASC"
        );
        $stmt->execute([$uid, $with, $with, $uid, $lastId]);
        echo json_encode($stmt->fetchAll());
        break;

    // ── Unread counts (badge numbers) ────────────────────────────────
    case 'unread_counts':
        $msgs  = getUnreadMessages($uid);
        $notif = getUnreadNotifications($uid);
        echo json_encode(['messages' => $msgs, 'notifications' => $notif]);
        break;

    // ── Mark thread as read ──────────────────────────────────────────
    case 'mark_read':
        $with = (int)($_POST['with'] ?? 0);
        $db->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?")
           ->execute([$uid, $with]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
