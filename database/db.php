<?php

$host = "sql209.infinityfree.com";
$dbname = "if0_42528508_carbnb"; // Replace XXX with your actual database name
$username = "if0_42528508";
$password = "YOUR_MYSQL_PASSWORD"; // Replace with your MySQL password

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    // Check if the table exists first
    $checkTable = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = ?
    ");

    $checkTable->execute([$table]);

    if (!$checkTable->fetchColumn()) {
        return;
    }

    // Check if the column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (($col['Field'] ?? '') === $column) {
            return;
        }
    }

    // Add the column if it doesn't exist
    $pdo->exec("
        ALTER TABLE `$table`
        ADD COLUMN `$column` $definition
    ");
}

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $GLOBALS['pdo'] = $pdo;
    $GLOBALS['conn'] = $pdo;
    $conn = $pdo;

    ensure_column($pdo, 'users', 'face_descriptor', 'TEXT NULL');
    ensure_column($pdo, 'vehicles', 'availability_status', "ENUM('available','rented','maintenance') DEFAULT 'available'");
    ensure_column($pdo, 'vehicles', 'approval_status', "ENUM('pending','approved','disapproved') DEFAULT 'pending'");
    ensure_column($pdo, 'bookings', 'total_days', 'INT NOT NULL DEFAULT 1');
    ensure_column($pdo, 'bookings', 'total_price', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    ensure_column($pdo, 'payments', 'payment_method', "ENUM('gcash','paymaya','cash','bank_transfer') NULL");
    ensure_column($pdo, 'payments', 'transaction_reference', 'VARCHAR(100) NULL');
    ensure_column($pdo, 'payments', 'gateway_response', 'TEXT NULL');
    ensure_column($pdo, 'payments', 'paid_at', 'DATETIME NULL');

} catch (PDOException $e) {

    die("Database connection failed: " . $e->getMessage());

}

?>