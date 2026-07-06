<?php
require_once __DIR__ . '/owner_logic.php';
$pdo = get_owner_pdo();
$owner = get_current_owner($pdo);

// Update session status from database to reflect any admin changes
if (isset($_SESSION['user_id']) && $owner['id'] > 0) {
    $_SESSION['status'] = $owner['status'];
    $_SESSION['approval_status'] = $owner['status'];
    $_SESSION['approval_reason'] = $owner['disapproval_reason'] ?? '';
}

$current_page = basename($_SERVER['PHP_SELF'] ?? 'owner_dashboard.php');
if (function_exists('enforce_owner_access')) {
    enforce_owner_access($pdo, $owner, $current_page);
} elseif (($owner['status'] ?? 'pending') !== 'approved' && $current_page !== 'owner_dashboard.php') {
    header('Location: owner_dashboard.php');
    exit();
}

// Handle reply submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'], $_POST['message_id'])) {
    $messageId = (int)$_POST['message_id'];
    $reply = trim($_POST['reply']);
    
    if (!empty($reply)) {
        // Get the original message to find the sender (renter)
        $stmt = $pdo->prepare("SELECT sender_id, receiver_id FROM messages WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$messageId, $owner['id']]);
        $originalMsg = $stmt->fetch();
        
        if ($originalMsg) {
            // Insert reply as a new message from owner to renter
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, message) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$owner['id'], $originalMsg['sender_id'], $reply]);
            
            // Mark original message as read
            $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
            $stmt->execute([$messageId]);
            
            $success = 'Reply sent successfully!';
        }
    }
}

$messages = get_owner_messages($pdo, $owner['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owner Messages</title>
  <link rel="stylesheet" href="css/owner_style.css?v=20260702">
</head>
<body>
  <div class="overlay"></div>
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2>Carbnb Owner</h2>
      <button class="sidebar-close" type="button">×</button>
    </div>
    <nav class="sidebar-nav">
      <a href="owner_dashboard.php">Dashboard</a>
      <a href="add_vehicle.php">Add Vehicle</a>
      <a href="manage_vehicles.php">Manage Vehicles</a>
      <a href="booking_requests.php">Booking Requests</a>
      <a href="vehicle_status.php">Vehicle Status</a>
      <a href="owner_income.php">Income</a>
      <a href="rental_history.php">Rental History</a>
      <a href="owner_profile.php">Profile</a>
      <a class="active" href="owner_message.php">Messages</a>
      <a href="owner_reviews.php">Reviews</a>
      <a href="../auth/logout.php" class="topbar-action" style="display:block; margin-top:1rem; text-align:center;">Logout</a>
    </nav>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <button class="sidebar-toggle" type="button">☰</button>
      <h1>Messages</h1>
      <a class="topbar-action" href="owner_dashboard.php">Home</a>
    </header>

    <main class="page">
      <?php if ($success): ?>
        <div class="alert success"><?= clean($success) ?></div>
      <?php endif; ?>
      
      <?php if ($error): ?>
        <div class="alert error"><?= clean($error) ?></div>
      <?php endif; ?>

      <section class="card">
        <h3 class="section-title">Inbox</h3>
        
        <?php if (empty($messages)) : ?>
          <p class="empty-state">No messages yet.</p>
        <?php else : ?>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>From</th>
                  <th>Message</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($messages as $msg): ?>
                  <tr>
                    <td><?= clean($msg['sender_name'] ?: 'System') ?></td>
                    <td><?= clean(substr($msg['message'], 0, 100)) ?><?= strlen($msg['message']) > 100 ? '...' : '' ?></td>
                    <td>
                      <span class="status-badge <?= $msg['is_read'] ? 'available' : 'pending' ?>">
                        <?= $msg['is_read'] ? 'Read' : 'New' ?>
                      </span>
                    </td>
                    <td><?= format_date($msg['created_at']) ?></td>
                    <td>
                      <button class="action-btn" onclick="openReplyModal(<?= $msg['id'] ?>, <?= json_encode($msg['message']) ?>)">Reply</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
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
          <button type="submit" class="primary" style="flex:1;">Send Reply</button>
          <button type="button" onclick="closeReplyModal()" class="action-btn" style="flex:1;">Cancel</button>
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

  <script src="js/owner_script.js"></script>
</body>
</html>