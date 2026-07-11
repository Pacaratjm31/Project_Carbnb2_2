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
    $stmt = $conn->prepare("SELECT b.id, b.start_date, b.end_date, b.total_price, b.status, v.name AS vehicle_name FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id WHERE b.renter_id = ? ORDER BY b.created_at DESC");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true, 'bookings' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
