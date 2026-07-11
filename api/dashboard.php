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
$user_role = (string) ($_SESSION['user_role'] ?? 'renter');

if ($user_role !== 'admin') {
    echo json_encode([
        'success' => true,
        'role' => $user_role,
        'message' => 'Access granted to non-admin dashboard view'
    ]);
    exit;
}

$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role <> 'admin' AND is_deleted = 0");
$total_users = (int) $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM bookings");
$total_bookings = (int) $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
$pending_payments = (int) $stmt->fetchColumn();

echo json_encode([
    'success' => true,
    'role' => 'admin',
    'stats' => [
        'total_users' => $total_users,
        'total_bookings' => $total_bookings,
        'pending_payments' => $pending_payments
    ]
]);
