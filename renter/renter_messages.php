<?php
include '../database/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$conn = $conn ?? $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

// Get renter info
$stmt = $conn->prepare("SELECT id, full_name, status, disapproval_reason FROM users WHERE id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$renter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$renter) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Handle reply submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'], $_POST['message_id'])) {
    $messageId = (int)$_POST['message_id'];
    $reply = trim($_POST['reply']);
    
    if (!empty($reply)) {
        // Get the original message to find the sender (owner)
        $stmt = $conn->prepare("SELECT sender_id FROM messages WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$messageId, $user_id]);
        $originalMsg = $stmt->fetch();
        
        if ($originalMsg) {
            // Insert reply as a new message from renter to owner
            $stmt = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user_id, $originalMsg['sender_id'], $reply]);
            
            // Mark original message as read
            $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
            $stmt->execute([$messageId]);
            
            $success = 'Reply sent successfully!';
        }
    }
}

// Get messages for this renter
$stmt = $conn->prepare("
    SELECT m.id, m.message, m.is_read, m.created_at, u.full_name AS sender_name, u.role AS sender_role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Carbnb</title>
    <link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/renter_style.css?v=4">
</head>
<body>
    <div class="top-nav">
        <div class="nav-left">
            <h2>Carbnb</h2>
        </div>
        <div class="nav-right">
            <a href="browse.php" class="nav-all-cars">All Cars</a>
            <a href="record.php" class="nav-my-records">My Records</a>
            <a href="view_profile.php" class="nav-my-profile">My Profile</a>
            <a href="renter_messages.php" class="nav-my-messages active">Messages</a>
            <a href="../auth/logout.php" class="logout-link">Logout</a>
        </div>
    </div>

    <div class="header-text">
        <h1><span class="blue">My</span> <span class="orange">Messages</span></h1>
    </div>

    <div class="record-container">
        <?php if ($success): ?>
            <div class="alert success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert" style="color: #dc3545;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($messages)): ?>
            <div class="no-results">
                <h3>No messages yet.</h3>
                <p>Messages from vehicle owners will appear here.</p>
            </div>
        <?php else: ?>
            <div class="message-list">
                <?php foreach ($messages as $msg): ?>
                    <div class="record-card">
                        <div class="record-info">
                            <p><strong>From:</strong> <?= htmlspecialchars($msg['sender_name']) ?> (<?= ucfirst($msg['sender_role']) ?>)</p>
                            <p><strong>Message:</strong> <?= htmlspecialchars($msg['message']) ?></p>
                            <p><small><?= date('M d, Y h:i A', strtotime($msg['created_at'])) ?></small></p>
                        </div>
                        <div>
                            <span class="status-badge <?= $msg['is_read'] ? 'status-pending' : 'status-approved' ?>">
                                <?= $msg['is_read'] ? 'Read' : 'New' ?>
                            </span>
                            <button class="btn-book" style="margin-top: 10px;" onclick="openReplyModal(<?= $msg['id'] ?>, <?= json_encode($msg['message']) ?>)">Reply</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reply Modal -->
    <div id="replyModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center;">
        <div class="card" style="max-width:500px; width:90%; padding:20px; background:#2a2a2a; border-radius:12px;">
            <h3 style="margin-bottom:15px; color:#ffd700;">Reply to Message</h3>
            <form method="POST">
                <input type="hidden" name="message_id" id="messageId">
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="color:#aaa;">Original Message</label>
                    <p id="originalMessage" style="background:#1e1e1e; padding:10px; border-radius:6px; margin-bottom:10px; color:#cfcfcf;"></p>
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="color:#aaa;">Your Reply</label>
                    <textarea name="reply" id="replyText" rows="4" style="width:100%; padding:10px; border-radius:6px; border:1px solid #555; background:#1e1e1e; color:#cfcfcf;" required></textarea>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-book" style="flex:1;">Send Reply</button>
                    <button type="button" onclick="closeReplyModal()" class="btn-return" style="flex:1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReplyModal(id, message) {
            document.getElementById('messageId').value = id;
            document.getElementById('originalMessage').textContent = message;
            document.getElementById('replyText').value = '';
            document.getElementById('replyModal').style.display = 'flex';
        }

        function closeReplyModal() {
            document.getElementById('replyModal').style.display = 'none';
        }

        // Close modal on outside click
        document.getElementById('replyModal').addEventListener('click', function(e) {
            if (e.target === this) closeReplyModal();
        });
    </script>

    <footer>
        <p>&copy; 2026 Carbnb Philippines. All rights reserved.</p>
    </footer>
</body>
</html>