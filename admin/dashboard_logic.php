<?php
// Dashboard Logic - Statistics and data for admin dashboard
require_once 'admin_auth.php';

// Initialize statistics variables
$totalUsers = 0;
$totalOwners = 0;
$totalRenters = 0;
$totalVehicles = 0;
$totalBookings = 0;
$totalPayments = 0;
$totalPendingUsers = 0;
$totalPendingVehicles = 0;
$totalPendingBookings = 0;
$totalPendingPayments = 0;
$totalDeletedUsers = 0;
$totalContactMessages = 0;
$totalPendingMessages = 0;

try {
    // Total Users (excluding admin)
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE role <> 'admin'
        AND is_deleted = 0
    ");
    $totalUsers = (int)$stmt->fetchColumn();

    // Total Owners
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'owner'
        AND is_deleted = 0
    ");
    $totalOwners = (int)$stmt->fetchColumn();

    // Total Renters
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'renter'
        AND is_deleted = 0
    ");
    $totalRenters = (int)$stmt->fetchColumn();

    // Total Vehicles
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM vehicles
        WHERE is_deleted = 0
    ");
    $totalVehicles = (int)$stmt->fetchColumn();

    // Total Bookings
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM bookings
    ");
    $totalBookings = (int)$stmt->fetchColumn();

    // Total Payments
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM payments
    ");
    $totalPayments = (int)$stmt->fetchColumn();

    // Pending Users
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE status = 'pending'
        AND is_deleted = 0
    ");
    $totalPendingUsers = (int)$stmt->fetchColumn();

    // Pending Vehicles
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM vehicles
        WHERE approval_status = 'pending'
        AND is_deleted = 0
    ");
    $totalPendingVehicles = (int)$stmt->fetchColumn();

    // Pending Bookings
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM bookings
        WHERE status = 'pending'
    ");
    $totalPendingBookings = (int)$stmt->fetchColumn();

    // Pending Payments
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM payments
        WHERE status = 'pending'
    ");
    $totalPendingPayments = (int)$stmt->fetchColumn();

    // Deleted Users
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE is_deleted = 1
    ");
    $totalDeletedUsers = (int)$stmt->fetchColumn();

    // Total Contact Messages
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM contact_messages
    ");
    $totalContactMessages = (int)$stmt->fetchColumn();

    // Pending Contact Messages
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM contact_messages
        WHERE is_replied = 0
    ");
    $totalPendingMessages = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
