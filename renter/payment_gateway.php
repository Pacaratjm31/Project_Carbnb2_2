<?php
require_once '../database/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to pay.']);
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$booking_id = (int) ($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking reference.']);
    exit;
}

$stmt = $conn->prepare("SELECT b.id, b.total_price, b.status, b.renter_id, v.name AS vehicle_name FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id = ? AND b.renter_id = ?");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

$amount = (float) ($booking['total_price'] ?? 0);
$amount_cents = (int) round($amount * 100);

if ($amount_cents <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Booking total must be greater than zero.']);
    exit;
}

$secret_key = getenv('STRIPE_SECRET_KEY') ?: '';

if ($secret_key === '' || strpos($secret_key, 'sk_') !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Stripe is not configured yet. Please set STRIPE_SECRET_KEY in your environment or add a test key.'
    ]);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $scheme . '://' . $host . '/Capstone4/Carbnb4';
$success_url = $base_url . '/renter/payment_success.php?booking_id=' . $booking_id . '&session_id={CHECKOUT_SESSION_ID}';
$cancel_url = $base_url . '/renter/paid.php?booking_id=' . $booking_id;

$payload = http_build_query([
    'mode' => 'payment',
    'success_url' => $success_url,
    'cancel_url' => $cancel_url,
    'line_items[0][quantity]' => 1,
    'line_items[0][price_data][currency]' => 'php',
    'line_items[0][price_data][unit_amount]' => $amount_cents,
    'line_items[0][price_data][product_data][name]' => 'Carbnb Booking #' . $booking_id,
    'line_items[0][price_data][product_data][description]' => 'Vehicle rental booking payment',
    'metadata[booking_id]' => $booking_id,
    'metadata[user_id]' => $user_id,
]);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 400) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Stripe checkout could not be started. Please verify the Stripe key and try again.'
    ]);
    exit;
}

$result = json_decode($response, true);
if (empty($result['url'])) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Stripe did not return a checkout URL.']);
    exit;
}

echo json_encode([
    'success' => true,
    'checkout_url' => $result['url'],
    'session_id' => $result['id'] ?? ''
]);
?>
