<?php
require_once 'admin_auth.php';

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'], $_POST['message_id'])) {
    $messageId = (int)$_POST['message_id'];
    $reply = trim($_POST['reply']);
    
    if (!empty($reply)) {
        $stmt = $pdo->prepare("
            UPDATE contact_messages 
            SET reply = ?, is_replied = 1, replied_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$reply, $messageId]);
        redirectSuccess('contact_messages.php', 'Reply sent successfully!');
    }
}

// Get all contact messages
$stmt = $pdo->query("
    SELECT * FROM contact_messages 
    ORDER BY created_at DESC
");
$contactMessages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Messages | Admin</title>
  <link rel="stylesheet" href="css/admin_style.css?v=20260702">
</head>
<body>
  <div class="overlay"></div>
  
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2>Carbnb Admin</h2>
      <button class="sidebar-close" type="button">×</button>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="manage_users.php">Verify Users</a>
      <a href="verify_vehicles.php">Verify Vehicles</a>
      <a href="booking_records.php">Rental Records</a>
      <a href="account_control.php">Account Control</a>
      <a href="earnings.php">Earnings & Commission</a>
      <a href="contact_messages.php" class="active">Contact Messages</a>
      <a href="delete_user.php">Delete Users</a>
      <a href="trashbin.php">Trash Bin</a>
      <a href="../auth/logout.php" class="topbar-action" style="display:block; margin-top:1rem; text-align:center;">Logout</a>
    </nav>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <button class="sidebar-toggle" type="button">☰</button>
      <h1>Contact Messages</h1>
    </header>

    <main class="page">
      <?php if (!empty($success)): ?>
        <div class="alert success"><?= clean($success) ?></div>
      <?php endif; ?>
      
      <?php if (!empty($error)): ?>
        <div class="alert error"><?= clean($error) ?></div>
      <?php endif; ?>

      <section class="card">
        <h3 class="section-title">All Contact Messages</h3>
        
        <?php if (empty($contactMessages)): ?>
          <p class="empty-state">No contact messages found.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Message</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($contactMessages as $msg): ?>
                  <tr>
                    <td><?= clean($msg['name']) ?></td>
                    <td><?= clean($msg['email']) ?></td>
                    <td><?= clean(substr($msg['message'], 0, 100)) ?><?= strlen($msg['message']) > 100 ? '...' : '' ?></td>
                    <td>
                      <span class="status-badge <?= $msg['is_replied'] ? 'available' : 'pending' ?>">
                        <?= $msg['is_replied'] ? 'Replied' : 'Pending' ?>
                      </span>
                    </td>
                    <td><?= formatDate($msg['created_at']) ?></td>
                    <td>
                      <button class="action-btn" onclick="openReplyModal(<?= $msg['id'] ?>, <?= json_encode($msg['email']) ?>, <?= json_encode($msg['message']) ?>, <?= json_encode($msg['reply'] ?? '') ?>)">
                        <?= $msg['is_replied'] ? 'View/Edit' : 'Reply' ?>
                      </button>
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
    <div class="card" style="max-width:500px; width:90%; padding:20px;">
      <h3 style="margin-bottom:15px;">Reply to Message</h3>
      <form method="POST">
        <input type="hidden" name="message_id" id="messageId">
        <div class="form-group" style="margin-bottom:15px;">
          <label>Original Message</label>
          <p id="originalMessage" style="background:#1e1e1e; padding:10px; border-radius:6px; margin-bottom:10px;"></p>
        </div>
        <div class="form-group" style="margin-bottom:15px;">
          <label>Your Reply</label>
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
    function openReplyModal(id, email, message, reply) {
      document.getElementById('messageId').value = id;
      document.getElementById('originalMessage').textContent = message;
      document.getElementById('replyText').value = reply || '';
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

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const sidebar = document.querySelector('.sidebar');
      const overlay = document.querySelector('.overlay');
      const toggleBtn = document.querySelector('.sidebar-toggle');
      const closeBtn = document.querySelector('.sidebar-close');

      function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.classList.add('sidebar-open');
      }

      function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.classList.remove('sidebar-open');
      }

      if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
          if (sidebar.classList.contains('open')) {
            closeSidebar();
          } else {
            openSidebar();
          }
        });
      }

      if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
      }

      if (overlay) {
        overlay.addEventListener('click', closeSidebar);
      }

      document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
          if (window.innerWidth <= 900) {
            closeSidebar();
          }
        });
      });
    });
  </script>
</body>
</html>