<?php
session_start();

require_once __DIR__ . '/../database/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

$userId = (int) ($_POST['user_id'] ?? 0);
$faceImage = $_POST['face_image'] ?? null;
$faceEncoding = $_POST['face_encoding'] ?? null;

if ($userId <= 0 || empty($faceImage) || empty($faceEncoding)) {
    die("Invalid face registration data.");
}

if (!isset($_SESSION['face_registration_user_id']) || (int) $_SESSION['face_registration_user_id'] !== $userId) {
    die("Session mismatch. Please register again.");
}

$uploadDir = "../uploads/face_auth/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$faceImage = str_replace('data:image/png;base64,', '', $faceImage);
$faceImage = str_replace(' ', '+', $faceImage);
$imageData = base64_decode($faceImage, true);

if ($imageData === false) {
    die("Failed to decode image.");
}

$fileName = 'face_' . $userId . '_' . time() . '.png';
$filePath = $uploadDir . $fileName;

if (!file_put_contents($filePath, $imageData)) {
    die("Failed to save face image.");
}

$dbFacePath = "uploads/face_auth/" . $fileName;
$descriptorJson = json_encode(json_decode($faceEncoding, true));

$stmt = $pdo->prepare("
    UPDATE users
    SET
        face_image = ?,
        face_descriptor = ?
    WHERE id = ?
");

$stmt->execute([$dbFacePath, $descriptorJson ?: null, $userId]);

unset($_SESSION['face_registration_user_id']);

header("Location: login.php?face_registered=1&pending=1");
exit();
