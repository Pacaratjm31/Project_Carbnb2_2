<?php
require_once '../database/db.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$full_name = trim((string) ($_POST['full_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$role = trim((string) ($_POST['role'] ?? 'renter'));

if ($full_name === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$allowed_roles = ['renter', 'owner', 'admin'];
if (!in_array($role, $allowed_roles, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

$check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$check->execute([$email]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    exit;
}

$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'approved')");
$stmt->execute([$full_name, $email, $hashed, $role]);

$_SESSION['user_id'] = (int) $conn->lastInsertId();
$_SESSION['user_role'] = $role;
$_SESSION['user_name'] = $full_name;

echo json_encode([
    'success' => true,
    'message' => 'Registration successful',
    'user' => [
        'name' => $full_name,
        'email' => $email,
        'role' => $role
    ]
]);
