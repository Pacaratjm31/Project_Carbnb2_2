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

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$booking_id = (int) ($_POST['booking_id'] ?? 0);

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

$amount = (float) $booking['total_price'];

// Validate uploaded receipt/proof image
if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'Please upload a valid receipt or proof of payment image.'
    ]);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$fileType = mime_content_type($_FILES['proof_image']['tmp_name']);

if (!in_array($fileType, $allowedTypes, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Only JPEG, PNG, and WebP images are allowed for the receipt.'
    ]);
    exit;
}

if ($_FILES['proof_image']['size'] > 5 * 1024 * 1024) {
    echo json_encode([
        'success' => false,
        'message' => 'Receipt image must be less than 5MB.'
    ]);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/payments/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$fileName = 'payment_' . $booking_id . '_' . time() . '_' . basename($_FILES['proof_image']['name']);
$uploadPath = $uploadDir . $fileName;

if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $uploadPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload the receipt image. Please try again.'
    ]);
    exit;
}

$proofImagePath = $fileName;

// Check existing payment
$check = $conn->prepare("SELECT id FROM payments WHERE booking_id = ?");
$check->execute([$booking_id]);

try {

    if ($check->rowCount() > 0) {

        // Update existing payment
        $update = $conn->prepare("
            UPDATE payments SET
                amount = ?,
                proof_image = ?,
                status = 'pending',
                paid_at = NOW()
            WHERE booking_id = ?
        ");

        $update->execute([
            $amount,
            $proofImagePath,
            $booking_id
        ]);

    } else {

        // Create new payment
        $insert = $conn->prepare("
            INSERT INTO payments
            (
                booking_id,
                amount,
                proof_image,
                status,
                paid_at
            )
            VALUES
            (?, ?, ?, 'pending', NOW())
        ");

        $insert->execute([
            $booking_id,
            $amount,
            $proofImagePath
        ]);

    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment submitted. Waiting for admin verification.'
    ]);

} catch (PDOException $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}
