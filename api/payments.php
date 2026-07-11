<?php
require_once '../database/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT p.id, p.amount, p.status, p.payment_method, p.transaction_reference, p.paid_at, b.id AS booking_id, v.name AS vehicle_name FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN vehicles v ON v.id = b.vehicle_id WHERE b.renter_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true, 'payments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
