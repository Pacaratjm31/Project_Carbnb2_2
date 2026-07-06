<?php
include '../database/db.php';
session_start();
$conn = $conn ?? $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);

// Get renter account state
$stmt = $conn->prepare("SELECT id, full_name, status, disapproval_reason FROM users WHERE id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$renter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$renter) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Check if renter is approved
if (($renter['status'] ?? 'pending') !== 'approved') {
    $account_state = [
        'status' => $renter['status'] ?? 'pending',
        'title' => $renter['status'] === 'disapproved' ? 'Account Disapproved' : 'Pending Admin Approval',
        'message' => $renter['status'] === 'disapproved' 
            ? ($renter['disapproval_reason'] ?? 'Your account was disapproved.') 
            : 'Your account is waiting for admin approval. Records are disabled.',
        'restricted' => true
    ];
    
    // Show restricted page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Records Restricted | Carbnb</title>
        <link rel="stylesheet" href="css/renter_style.css?v=2">
    </head>
    <body>
        <div class="record-container">
            <h2>Records Restricted</h2>
            <div class="approval-card">
                <h3><?= htmlspecialchars($account_state['title']) ?></h3>
                <p><?= htmlspecialchars($account_state['message']) ?></p>
                <a href="browse.php" class="back-link">← Back to Browse</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$msg = '';
$reviewMsg = '';

// Handle general feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_feedback'])) {
    $feedback = trim($_POST['feedback'] ?? '');

    if (!empty($feedback)) {
        try {
            $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' AND is_deleted = 0 ORDER BY id ASC LIMIT 1");
            $adminStmt->execute();
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, (int) $admin['id'], $feedback]);
            } else {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, 'Feedback Submitted', $feedback]);
            }

            $msg = 'Feedback sent successfully!';
        } catch (PDOException $e) {
            $msg = 'Error sending feedback.';
        }
    } else {
        $msg = 'Please write feedback before submitting.';
    }
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($booking_id > 0 && $rating >= 1 && $rating <= 5) {
        try {
            // Get the vehicle and owner for this booking
            $stmt = $conn->prepare("SELECT b.vehicle_id, v.owner_id FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id WHERE b.id = ? AND b.renter_id = ? AND b.status = 'completed'");
            $stmt->execute([$booking_id, $user_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($booking) {
                // Check if review already exists for this booking
                $stmt = $conn->prepare("SELECT id FROM reviews WHERE renter_id = ? AND vehicle_id = ?");
                $stmt->execute([$user_id, $booking['vehicle_id']]);
                
                if (!$stmt->fetch()) {
                    // Insert the review
                    $stmt = $conn->prepare("INSERT INTO reviews (renter_id, owner_id, vehicle_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $booking['owner_id'], $booking['vehicle_id'], $rating, $comment]);
                    
                    $reviewMsg = 'Review submitted successfully!';
                } else {
                    $reviewMsg = 'You have already reviewed this vehicle.';
                }
            } else {
                $reviewMsg = 'Invalid booking or booking not completed yet.';
            }
        } catch (PDOException $e) {
            $reviewMsg = 'Error submitting review.';
        }
    } else {
        $reviewMsg = 'Please select a rating and provide a valid booking.';
    }
}

$stmt = $conn->prepare("SELECT b.id, b.start_date, b.end_date, b.total_price, b.status, v.name AS vehicle_name, v.owner_id FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id WHERE b.renter_id = ? ORDER BY b.created_at DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT p.id, p.amount, p.proof_image AS receipt_image, p.status AS payment_status FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE b.renter_id = ? ORDER BY p.created_at DESC");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed bookings that haven't been reviewed yet
$stmt = $conn->prepare("
    SELECT b.id, b.vehicle_id, v.name AS vehicle_name, v.owner_id, u.full_name AS owner_name 
    FROM bookings b 
    JOIN vehicles v ON v.id = b.vehicle_id 
    JOIN users u ON u.id = v.owner_id 
    WHERE b.renter_id = ? AND b.status = 'completed' 
    AND NOT EXISTS (
        SELECT 1 FROM reviews r 
        WHERE r.renter_id = ? AND r.vehicle_id = b.vehicle_id
    )
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$reviewableBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Records | Carbnb</title>
<link rel="stylesheet" href="css/renter_style.css?v=2">
</head>

<body>

<div class="record-container">

    <div class="header-row">
        <h2>My Rental Records</h2>
        <a href="browse.php" class="back-link">← Back to Browse</a>
    </div>

    <h3>Booking History</h3>

    <?php if (empty($bookings)): ?>
        <p>No rental history found.</p>
    <?php else: ?>
        <?php foreach ($bookings as $b): ?>
            <div class="record-card">
                <div class="record-info">
                    <p><strong>Car:</strong> <?= htmlspecialchars($b['vehicle_name']) ?></p>
                    <p><small><?= htmlspecialchars($b['start_date']) ?> → <?= htmlspecialchars($b['end_date']) ?></small></p>
                    <p><strong>Total:</strong> ₱<?= number_format((float) $b['total_price'], 2) ?></p>
                </div>

                <span class="status status-<?= strtolower($b['status']) ?>">
                    <?= ucfirst($b['status']) ?>
                </span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Payment History</h3>

    <?php if (empty($payments)): ?>
        <p>No payment records found.</p>
    <?php else: ?>
        <?php foreach ($payments as $p): ?>
            <div class="record-card">
                <div class="record-info">
                    <p><strong>Amount:</strong> ₱<?= number_format((float) $p['amount'], 2) ?></p>
                    <span class="status payment-<?= strtolower($p['payment_status']) ?>">
                        <?= ucfirst($p['payment_status']) ?>
                    </span>
                </div>

                <?php if (!empty($p['receipt_image'])): ?>
                    <a href="../uploads/payments/<?= htmlspecialchars($p['receipt_image']) ?>" target="_blank">
                        <img src="../uploads/payments/<?= htmlspecialchars($p['receipt_image']) ?>" class="receipt-img">
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($reviewableBookings)): ?>
    <div class="feedback-box">
        <h3>Rate Your Experience</h3>
        
        <?php if ($reviewMsg): ?>
            <p class="success-msg"><?= $reviewMsg ?></p>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="submit_review" value="1">
            <div class="form-group">
                <label for="booking_id">Select Completed Booking:</label>
                <select name="booking_id" id="booking_id" required>
                    <option value="">-- Select a booking --</option>
                    <?php foreach ($reviewableBookings as $rb): ?>
                        <option value="<?= $rb['id'] ?>"><?= htmlspecialchars($rb['vehicle_name']) ?> (with <?= htmlspecialchars($rb['owner_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Rating:</label>
                <div class="star-rating" style="margin-bottom:10px;">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required>
                        <label for="star<?= $i ?>" style="color:#ffd700; font-size:1.5rem; cursor:pointer;">★</label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="comment">Comment (optional):</label>
                <textarea name="comment" id="comment" placeholder="Share your experience with this vehicle and owner..."></textarea>
            </div>
            <button type="submit">Submit Review</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="feedback-box">
        <h3>Send Feedback to Admin</h3>

        <?php if ($msg): ?>
            <p class="success-msg"><?= $msg ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="send_feedback" value="1">
            <textarea name="feedback" placeholder="How was your experience with Carbnb?" required></textarea>
            <button type="submit">Submit Feedback</button>
        </form>
    </div>

</div>

</body>
</html>