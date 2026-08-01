<?php
require_once __DIR__ . '/owner_logic.php';
include __DIR__ . '/../helpers/duplicate_functions.php';
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

// Get success message from redirect
if (isset($_GET['success'])) {
    $success = trim($_GET['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'], $_POST['review_id'])) {
    $reviewId = (int)$_POST['review_id'];
    $reply = trim($_POST['reply_text'] ?? '');
    
    if (!empty($reply)) {
        try {
            // Update the reply field in the reviews table (works for both with and without vehicle)
            $stmt = $pdo->prepare("UPDATE reviews SET reply = ? WHERE id = ? AND owner_id = ?");
            $stmt->execute([$reply, $reviewId, $owner['id']]);
            
            if ($stmt->rowCount() > 0) {
                // Redirect to prevent duplicate submission on refresh
                header('Location: owner_reviews.php?success=' . urlencode('Reply sent successfully!'));
                exit;
            } else {
                $error = 'Invalid review.';
            }
        } catch (PDOException $e) {
            $error = 'Error sending reply.';
        }
    } else {
        $error = 'Please write a reply before submitting.';
    }
}

$reviews = get_owner_reviews($pdo, $owner['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owner Reviews | Carbnb</title>
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
      <a href="owner_message.php">Messages</a>
      <a class="active" href="owner_reviews.php">Reviews</a>
      <a href="../auth/logout.php" class="topbar-action" style="display:block; margin-top:1rem; text-align:center;">Logout</a>
    </nav>
  </aside>

  <div class="main-content">
<header class="topbar">
      <button class="sidebar-toggle" type="button" aria-label="Open sidebar"></button>
      <h1>Reviews & Feedback</h1>
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
        <h3 class="section-title">Customer Reviews</h3>
        
        <?php if (empty($reviews)) : ?>
          <p class="empty-state">No reviews yet. Reviews from renters will appear here after they complete their bookings.</p>
<?php else : ?>
<div class="table-wrapper">
             <table class="table">
<thead>
                <tr>
                  <th>Renter</th>
                  <th>Vehicle</th>
                  <th>Rating</th>
                  <th>Comment</th>
                  <th>Feedback</th>
                  <th>Reply</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reviews as $review): ?>
                  <tr>
                    <td data-label="Renter"><?= clean($review['renter_name']) ?></td>
                    <td data-label="Vehicle"><?= clean($review['vehicle_name'] ?: 'General Feedback') ?></td>
                    <td data-label="Rating">
                      <?php if ($review['rating']): ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <?= $i <= $review['rating'] ? '★' : '☆' ?>
                        <?php endfor; ?>
                        (<?= $review['rating'] ?>/5)
                      <?php else: ?>
                        <em>No rating</em>
                      <?php endif; ?>
                    </td>
                    <td data-label="Comment"><?= clean($review['comment'] ?: 'No comment') ?></td>
                    <td data-label="Feedback"><?= clean($review['feedback'] ?: 'No feedback') ?></td>
                    <td data-label="Reply"><?= clean($review['reply'] ?: 'No reply yet') ?></td>
                    <td data-label="Date"><?= format_date($review['created_at']) ?></td>
                    <td data-label="Action" class="cell-actions">
                      <div class="action-group">
                        <button class="action-btn" onclick="openReplyModal(<?= $review['id'] ?>, <?= htmlspecialchars(json_encode($review['renter_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($review['comment'] ?? $review['feedback'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($review['reply'] ?? ''), ENT_QUOTES) ?>)">Reply</button>
                      </div>
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
  <div id="replyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center;">
    <div style="display:flex; align-items:center; justify-content:center; height:100%; padding:20px;">
      <div style="background:#2a2a2a; border-radius:12px; max-width:500px; width:100%; padding:20px;">
        <h3 style="margin-bottom:15px; color:#ffd700;">Reply to Review</h3>
<form method="POST" id="replyReviewForm">
          <input type="hidden" name="reply" value="1">
          <input type="hidden" name="review_id" id="reviewId">
          <input type="hidden" name="form_token" id="replyFormToken">
          <div style="margin-bottom:15px;">
            <label style="color:#aaa; display:block; margin-bottom:5px;">Renter: <span id="renterName" style="color:#cfcfcf;"></span></label>
          </div>
          <div style="margin-bottom:15px;">
            <label style="color:#aaa; display:block; margin-bottom:5px;">Original Review</label>
            <p id="originalReview" style="background:#1e1e1e; padding:10px; border-radius:6px; margin-bottom:10px; color:#cfcfcf; min-height:40px;"></p>
          </div>
          <div style="margin-bottom:15px;">
            <label style="color:#aaa; display:block; margin-bottom:5px;">Your Reply</label>
            <textarea name="reply_text" id="replyText" rows="4" style="width:100%; padding:10px; border-radius:6px; border:1px solid #555; background:#1e1e1e; color:#cfcfcf; box-sizing:border-box;" required></textarea>
          </div>
          <div style="display:flex; gap:10px;">
            <button type="submit" style="flex:1; background:#ffd700; color:#111; border:none; padding:10px; border-radius:6px; cursor:pointer;">Send Reply</button>
            <button type="button" onclick="closeReplyModal()" style="flex:1; background:#444; color:#fff; border:none; padding:10px; border-radius:6px; cursor:pointer;">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
    // Generate form token for reply form
    const replyFormToken = '<?= generate_form_token('reply_review') ?>';

    function openReplyModal(reviewId, renterName, reviewComment, existingReply) {
      document.getElementById('reviewId').value = reviewId;
      document.getElementById('renterName').textContent = renterName;
      document.getElementById('originalReview').textContent = reviewComment || 'No comment';
      document.getElementById('replyText').value = existingReply || '';
      document.getElementById('replyFormToken').value = replyFormToken;
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