<?php
require_once '../database/db.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$role = trim((string) ($_POST['role'] ?? 'renter'));

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

$allowed_roles = ['renter', 'owner', 'admin'];
if (!in_array($role, $allowed_roles, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

$stmt = $conn->prepare("SELECT id, full_name, email, role, password FROM users WHERE email = ? AND role = ? AND is_deleted = 0 LIMIT 1");
$stmt->execute([$email, $role]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['full_name'];

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
        'id' => (int) $user['id'],
        'name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role']
    ]
]);
