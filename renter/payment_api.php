<?php
require_once '../database/db.php';
include __DIR__ . '/../helpers/duplicate_functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
$conn = $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to submit a payment.'
    ]);
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$booking_id = (int) ($_POST['booking_id'] ?? $_REQUEST['booking_id'] ?? 0);
$method = trim((string) ($_POST['method'] ?? $_REQUEST['method'] ?? ''));
$transaction_reference = trim((string) ($_POST['transaction_reference'] ?? $_REQUEST['transaction_reference'] ?? ''));
$allowed_methods = ['gcash', 'paymaya', 'cash', 'bank_transfer'];

if ($booking_id <= 0 || !in_array($method, $allowed_methods, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment request.'
    ]);
    exit;
}

if (!isset($_FILES['receipt']) || !is_uploaded_file($_FILES['receipt']['tmp_name'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Receipt upload is required.'
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT b.id, b.total_price, b.status, b.renter_id FROM bookings b WHERE b.id = ? AND b.renter_id = ?");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found.'
    ]);
    exit;
}

$tmp_name = $_FILES['receipt']['tmp_name'];
$size = (int) ($_FILES['receipt']['size'] ?? 0);
$ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));

if ($size > 2 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode([
        'success' => false,
        'message' => 'Receipt is too large. Maximum upload is 2MB.'
    ]);
    exit;
}

$allowed_ext = ['jpg', 'jpeg', 'png'];
if (!in_array($ext, $allowed_ext, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Only JPG and PNG receipt files are allowed.'
    ]);
    exit;
}

$upload_dir = __DIR__ . '/../uploads/payments/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_name = time() . '_' . uniqid() . '.' . $ext;
$destination = $upload_dir . $file_name;

if (!move_uploaded_file($tmp_name, $destination)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save receipt.'
    ]);
    exit;
}

$amount = (float) ($booking['total_price'] ?? 0);
$reference = $transaction_reference !== '' ? $transaction_reference : 'CARBNB-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $booking_id;
$gateway_response = 'Payment submitted through renter API using ' . $method . '.';

$check = $conn->prepare('SELECT id FROM payments WHERE booking_id = ?');
$check->execute([$booking_id]);

try {
    $conn->beginTransaction();
    
    if ($check->rowCount() > 0) {
        // Update existing payment
        $stmt = $conn->prepare(
            "UPDATE payments
             SET amount = ?, proof_image = ?, payment_method = ?, transaction_reference = ?, gateway_response = ?, status = 'pending', paid_at = NOW()
             WHERE booking_id = ?"
        );
        $stmt->execute([$amount, $file_name, $method, $reference, $gateway_response, $booking_id]);
        $payment_id = $check->fetchColumn();
    } else {
        // Insert new payment
        $stmt = $conn->prepare(
            "INSERT INTO payments (booking_id, amount, proof_image, payment_method, transaction_reference, gateway_response, status, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([$booking_id, $amount, $file_name, $method, $reference, $gateway_response]);
        $payment_id = (int) $conn->lastInsertId();
    }
    
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment submitted successfully. Please wait for admin approval.',
        'payment_id' => $payment_id,
        'redirect' => 'browse.php'
    ]);
} catch (PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save payment. ' . $e->getMessage()
    ]);
}
?>