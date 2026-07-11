<?php
require_once '../database/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$booking_id = (int) ($_GET['booking_id'] ?? 0);
$session_id = trim((string) ($_GET['session_id'] ?? ''));

if ($booking_id > 0) {
    $stmt = $conn->prepare("SELECT id FROM payments WHERE booking_id = ?");
    $stmt->execute([$booking_id]);

    if ($stmt->rowCount() > 0) {
        $conn->prepare("UPDATE payments SET status = 'verified', gateway_response = ?, transaction_reference = ?, paid_at = NOW() WHERE booking_id = ?")
            ->execute(["Stripe checkout completed. Session: " . $session_id, $session_id, $booking_id]);
    } else {
        $conn->prepare("INSERT INTO payments (booking_id, amount, proof_image, payment_method, transaction_reference, gateway_response, status, paid_at) VALUES (?, 0, NULL, 'gcash', ?, ?, 'verified', NOW())")
            ->execute([$booking_id, $session_id, "Stripe checkout completed. Session: " . $session_id]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
    <link rel="stylesheet" href="css/renter_style.css?v=2">
</head>
<body>
    <div class="payment-container">
        <h2>Payment Successful</h2>
        <div class="approval-card">
            <p>Your booking payment was completed successfully.</p>
            <a href="record.php" class="btn-return">View Payment History</a>
        </div>
    </div>
</body>
</html>
