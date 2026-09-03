<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$db = getDB();
$role = $user['role'];

// Handle AJAX message send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send'])) {
    header('Content-Type: application/json');
    $to      = (int)($_POST['receiver_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if ($to && !empty($message)) {
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $to, $message]);
        $msgId = $db->lastInsertId();
        
        // Send notification
        addNotification($to, 'New message from ' . $user['full_name'], substr($message, 0, 100), 'message');
        
        echo json_encode([
            'success' => true,
            'message' => htmlspecialchars($message),
            'time' => date('g:i A'),
            'id' => $msgId
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Handle AJAX fetch messages
if (isset($_GET['fetch_messages'])) {
    header('Content-Type: application/json');
    $with = (int)($_GET['with'] ?? 0);
    $lastId = (int)($_GET['last_id'] ?? 0);
    
    // Mark as read
    $db->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?")->execute([$user['id'], $with]);
    
    $stmt = $db->prepare("SELECT m.*, u.full_name as sender_name FROM messages m 
        JOIN users u ON m.sender_id = u.id
        WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
        AND m.id > ?
        ORDER BY m.created_at ASC");
    $stmt->execute([$user['id'], $with, $with, $user['id'], $lastId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Get chat list (people I've chatted with, or can chat with)
$chatUsers = $db->prepare("
    SELECT DISTINCT u.id, u.full_name, u.role, u.last_seen, u.profile_photo,
        (SELECT message FROM messages WHERE (sender_id=u.id AND receiver_id=?) OR (sender_id=? AND receiver_id=u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE (sender_id=u.id AND receiver_id=?) OR (sender_id=? AND receiver_id=u.id) ORDER BY created_at DESC LIMIT 1) as last_time,
        (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=? AND is_read=0) as unread_count
    FROM users u
    WHERE u.id != ? AND u.is_active = 1
    HAVING last_message IS NOT NULL
    ORDER BY last_time DESC
");
$chatUsers->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$chatList = $chatUsers->fetchAll();

// Get available doctors (for patient) or patients (for doctor) to start new chat
if ($role === 'patient') {
    $newChatUsers = $db->query("SELECT u.id, u.full_name, u.role, u.profile_photo, dp.specialization FROM users u JOIN doctor_profiles dp ON u.id = dp.user_id WHERE u.role='doctor' AND dp.is_verified=1 ORDER BY u.full_name LIMIT 20")->fetchAll();
} else {
    $myPatients = $db->prepare("SELECT DISTINCT u.id, u.full_name, u.role, u.profile_photo FROM users u JOIN appointments a ON (a.patient_id = u.id) WHERE a.doctor_id = ? ORDER BY u.full_name");
    $myPatients->execute([$user['id']]);
    $newChatUsers = $myPatients->fetchAll();
}

// Get active chat user
$activeChatId = (int)($_GET['user'] ?? 0);
$activeChatUser = null;
$messages = [];

if ($activeChatId) {
    $stmt = $db->prepare("SELECT u.*, dp.specialization FROM users u LEFT JOIN doctor_profiles dp ON u.id = dp.user_id WHERE u.id = ?");
    $stmt->execute([$activeChatId]);
    $activeChatUser = $stmt->fetch();
    
    // Mark messages as read
    $db->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?")->execute([$user['id'], $activeChatId]);
    
    // Load messages
    $msgStmt = $db->prepare("SELECT m.*, u.full_name as sender_name FROM messages m 
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
        ORDER BY m.created_at ASC LIMIT 100");
    $msgStmt->execute([$user['id'], $activeChatId, $activeChatId, $user['id']]);
    $messages = $msgStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/chat.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="chat-layout">
        <!-- Chat Sidebar -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h3><i class="fas fa-comments"></i> Messages</h3>
                <button class="btn-new-chat" onclick="document.getElementById('newChatModal').style.display='flex'">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
            <div class="chat-search">
                <i class="fas fa-search"></i>
                <input type="text" id="chatSearch" placeholder="Search conversations..." oninput="filterChats(this.value)">
            </div>
            <div class="chat-list" id="chatList">
                <?php if (empty($chatList)): ?>
                <div class="no-chats">
                    <i class="fas fa-comment-slash"></i>
                    <p>No conversations yet</p>
                    <button onclick="document.getElementById('newChatModal').style.display='flex'" class="btn-sm-primary">Start New Chat</button>
                </div>
                <?php else: foreach ($chatList as $cu): ?>
                <a href="chat.php?user=<?= $cu['id'] ?>" class="chat-list-item <?= $activeChatId == $cu['id'] ? 'active' : '' ?> <?= $cu['unread_count'] > 0 ? 'has-unread' : '' ?>" data-name="<?= htmlspecialchars(strtolower($cu['full_name'])) ?>">
                    <div class="chat-avatar-wrap">
                        <img src="<?= avatarUrl($cu, 45) ?>" alt="">
                        <span class="online-indicator <?= (strtotime($cu['last_seen']) > strtotime('-5 minutes')) ? 'online' : '' ?>"></span>
                    </div>
                    <div class="chat-item-info">
                        <div class="chat-item-top">
                            <strong><?= htmlspecialchars($cu['full_name']) ?></strong>
                            <small><?= $cu['last_time'] ? date('g:i A', strtotime($cu['last_time'])) : '' ?></small>
                        </div>
                        <div class="chat-item-bottom">
                            <p><?= htmlspecialchars(substr($cu['last_message'] ?? '', 0, 35)) ?>...</p>
                            <?php if ($cu['unread_count'] > 0): ?>
                            <span class="unread-count"><?= $cu['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Chat Main Window -->
        <div class="chat-main">
            <?php if ($activeChatUser): ?>
            <div class="chat-header">
                <div class="chat-header-user">
                    <img src="<?= avatarUrl($activeChatUser, 45) ?>" alt="">
                    <div>
                        <strong><?= htmlspecialchars($activeChatUser['full_name']) ?></strong>
                        <small><?= $activeChatUser['role'] === 'doctor' ? ($activeChatUser['specialization'] ?? 'Doctor') : 'Patient' ?></small>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <a href="video.php?user=<?= $activeChatId ?>" class="btn-icon-round" title="Video Call">
                        <i class="fas fa-video"></i>
                    </a>
                    <a href="appointments.php?doctor=<?= $activeChatId ?>" class="btn-icon-round" title="Book Appointment">
                        <i class="fas fa-calendar-plus"></i>
                    </a>
                    <button class="btn-icon-round" title="More Options">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <?php 
                $lastDate = '';
                foreach ($messages as $msg):
                    $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                    $isMe = $msg['sender_id'] == $user['id'];
                    if ($msgDate !== $lastDate):
                        $lastDate = $msgDate;
                ?>
                <div class="date-divider">
                    <span><?= date('l, M j', strtotime($msgDate)) ?></span>
                </div>
                <?php endif; ?>
                <div class="message-wrap <?= $isMe ? 'sent' : 'received' ?>">
                    <?php if (!$isMe): ?>
                    <img src="<?= avatarUrl($activeChatUser, 32) ?>" class="msg-avatar" alt="">
                    <?php endif; ?>
                    <div class="message-bubble">
                        <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                        <span class="msg-time"><?= date('g:i A', strtotime($msg['created_at'])) ?></span>
                        <?php if ($isMe): ?>
                        <span class="msg-status"><i class="fas fa-check<?= $msg['is_read'] ? '-double' : '' ?>"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($messages)): ?>
                <div class="chat-empty-start">
                    <div class="chat-empty-avatar">
                        <img src="<?= avatarUrl($activeChatUser, 80) ?>" alt="">
                    </div>
                    <h4><?= htmlspecialchars($activeChatUser['full_name']) ?></h4>
                    <p>Start your conversation. All messages are encrypted and private.</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="chat-input-area">
                <button class="chat-attach-btn" title="Attach File">
                    <i class="fas fa-paperclip"></i>
                </button>
                <div class="chat-input-wrap">
                    <textarea id="messageInput" placeholder="Type a message..." rows="1" onkeydown="handleChatKey(event)"></textarea>
                </div>
                <button class="chat-emoji-btn"><i class="fas fa-smile"></i></button>
                <button class="chat-send-btn" id="sendBtn" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>

            <?php else: ?>
            <div class="chat-placeholder">
                <div class="chat-placeholder-inner">
                    <div class="chat-placeholder-icon">
                        <i class="fas fa-comment-medical"></i>
                    </div>
                    <h3>Select a Conversation</h3>
                    <p>Choose a contact from the list or start a new conversation with a <?= $role === 'patient' ? 'doctor' : 'patient' ?>.</p>
                    <button onclick="document.getElementById('newChatModal').style.display='flex'" class="btn-primary">
                        <i class="fas fa-edit"></i> New Message
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Chat Modal -->
<div class="modal-overlay" id="newChatModal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>New Message</h3>
            <button onclick="document.getElementById('newChatModal').style.display='none'"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="modal-search">
                <i class="fas fa-search"></i>
                <input type="text" id="newChatSearch" placeholder="Search <?= $role === 'patient' ? 'doctors' : 'patients' ?>..." oninput="filterNewChat(this.value)">
            </div>
            <div class="new-chat-list" id="newChatList">
                <?php foreach ($newChatUsers as $nc): ?>
                <a href="chat.php?user=<?= $nc['id'] ?>" class="new-chat-user" data-name="<?= htmlspecialchars(strtolower($nc['full_name'])) ?>">
                    <img src="<?= avatarUrl($nc, 40) ?>" alt="">
                    <div>
                        <strong><?= htmlspecialchars($nc['full_name']) ?></strong>
                        <?php if (isset($nc['specialization'])): ?>
                        <small><?= htmlspecialchars($nc['specialization']) ?></small>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
const CURRENT_USER_ID = <?= $user['id'] ?>;
const CHAT_WITH_ID = <?= $activeChatId ?: 0 ?>;
let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;

function sendMessage() {
    const input = document.getElementById('messageInput');
    const msg = input.value.trim();
    if (!msg || !CHAT_WITH_ID) return;

    input.value = '';
    input.style.height = 'auto';

    fetch('chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_send=1&receiver_id=${CHAT_WITH_ID}&message=${encodeURIComponent(msg)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            appendMessage(msg, true, data.time, data.id);
            lastMessageId = data.id;
        }
    });
}

function appendMessage(text, isMine, time, id) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = `message-wrap ${isMine ? 'sent' : 'received'}`;
    div.setAttribute('data-id', id);
    div.innerHTML = `
        <div class="message-bubble">
            <p>${escapeHtml(text)}</p>
            <span class="msg-time">${time}</span>
            ${isMine ? '<span class="msg-status"><i class="fas fa-check"></i></span>' : ''}
        </div>
    `;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(t) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(t));
    return d.innerHTML;
}

function handleChatKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
    // Auto-resize textarea
    const ta = document.getElementById('messageInput');
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
}

function filterChats(q) {
    document.querySelectorAll('.chat-list-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q.toLowerCase()) ? 'flex' : 'none';
    });
}

function filterNewChat(q) {
    document.querySelectorAll('.new-chat-user').forEach(el => {
        el.style.display = el.dataset.name.includes(q.toLowerCase()) ? 'flex' : 'none';
    });
}

// Auto-scroll to bottom
const msgContainer = document.getElementById('chatMessages');
if (msgContainer) msgContainer.scrollTop = msgContainer.scrollHeight;

// Poll for new messages every 3 seconds
if (CHAT_WITH_ID) {
    setInterval(() => {
        fetch(`chat.php?fetch_messages=1&with=${CHAT_WITH_ID}&last_id=${lastMessageId}`)
        .then(r => r.json())
        .then(msgs => {
            msgs.forEach(m => {
                if (m.sender_id != CURRENT_USER_ID) {
                    appendMessage(m.message, false, new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}), m.id);
                    lastMessageId = m.id;
                }
            });
        });
    }, 3000);
}

// Close modal on outside click
document.getElementById('newChatModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
</body>
</html>
