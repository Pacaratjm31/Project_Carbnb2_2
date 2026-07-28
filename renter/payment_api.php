<?php
require_once '../database/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$conn = $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in.'
    ]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$booking_id = (int) ($_POST['booking_id'] ?? 0);
$transaction_reference = trim($_POST['transaction_reference'] ?? '');
$gateway_response = trim($_POST['gateway_response'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? 'xendit');


if ($booking_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID.'
    ]);
    exit;
}


// Check booking belongs to renter
$stmt = $conn->prepare("
    SELECT id, total_price 
    FROM bookings
    WHERE id = ? AND renter_id = ?
");

$stmt->execute([
    $booking_id,
    $user_id
]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$booking) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found.'
    ]);
    exit;
}


$amount = (float) $booking['total_price'];


// Check existing payment
$check = $conn->prepare("
    SELECT id 
    FROM payments 
    WHERE booking_id = ?
");

$check->execute([$booking_id]);

try {

    if ($check->rowCount() > 0) {

        // Update existing payment
        $update = $conn->prepare("
            UPDATE payments SET
                amount = ?,
                payment_method = ?,
                transaction_reference = ?,
                gateway_response = ?,
                status = 'pending',
                paid_at = NOW()
            WHERE booking_id = ?
        ");

        $update->execute([
            $amount,
            $payment_method,
            $transaction_reference,
            $gateway_response,
            $booking_id
        ]);

    } else {

        // Create new payment
        $insert = $conn->prepare("
            INSERT INTO payments
            (
                booking_id,
                amount,
                payment_method,
                transaction_reference,
                gateway_response,
                status,
                paid_at
            )
            VALUES
            (?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $insert->execute([
            $booking_id,
            $amount,
            $payment_method,
            $transaction_reference,
            $gateway_response
        ]);

    }


    echo json_encode([
        'success' => true,
        'message' => 'Payment record saved.'
    ]);


} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}

?>