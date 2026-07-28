<?php
include '../database/db.php';
include __DIR__ . '/../helpers/duplicate_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = $conn ?? $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Get renter account state
$stmt = $conn->prepare("
    SELECT id, full_name, status, disapproval_reason
    FROM users
    WHERE id = ?
    AND is_deleted = 0
");
$stmt->execute([$user_id]);
$renter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$renter) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Check renter approval
if (($renter['status'] ?? 'pending') !== 'approved') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Restricted | Carbnb</title>
    <link rel="stylesheet" href="css/renter_style.css?v=2">
</head>
<body>

    <div class="payment-container">
        <h2>Payment Restricted</h2>

        <div class="payment-box">
            <h3>
                <?= htmlspecialchars(
                    $renter['status'] === 'disapproved'
                        ? 'Account Disapproved'
                        : 'Pending Admin Approval'
                ) ?>
            </h3>

            <p>
                <?= htmlspecialchars(
                    $renter['status'] === 'disapproved'
                        ? ($renter['disapproval_reason'] ?? 'Your account was disapproved.')
                        : 'Your account is waiting for admin approval. Payment is disabled.'
                ) ?>
            </p>

            <a href="browse.php" class="btn-return">← Back to Browse</a>
        </div>
    </div>

</body>
</html>
<?php
    exit;
}

// Check booking ID
if (!isset($_GET['booking_id'])) {
    die('Invalid request.');
}

$booking_id = (int) $_GET['booking_id'];

// Get booking details
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.total_price,
        b.status,
        v.name AS vehicle_name,
        v.image AS car_image
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.id = ?
    AND b.renter_id = ?
");
$stmt->execute([$booking_id, $user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('Booking not found.');
}

// Check for an existing verified payment (prevents duplicate Xendit invoices)
$paymentStmt = $conn->prepare("
    SELECT status
    FROM payments
    WHERE booking_id = ?
");
$paymentStmt->execute([$booking_id]);
$existingPayment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
$alreadyPaid = $existingPayment && $existingPayment['status'] === 'verified';

// Build vehicle image path
function build_vehicle_image_path($value): string
{
    if (empty($value)) {
        return '../uploads/vehicles/default-car.svg';
    }

    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    if (preg_match('#^uploads/#', $value)) {
        return '../' . $value;
    }

    if (strpos($value, '../') === 0 || strpos($value, '/') === 0) {
        return $value;
    }

    return '../uploads/vehicles/' . basename($value);
}

$imagePath = build_vehicle_image_path($data['car_image'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | Carbnb</title>
    <link rel="stylesheet" href="css/renter_style.css?v=2">
</head>
<body>

    <div class="payment-container">
        <h2>Payment</h2>

        <div class="payment-box">
            <img src="<?= htmlspecialchars($imagePath) ?>"
                 class="payment-image"
                 onerror="this.src='../uploads/vehicles/default-car.svg'">

            <p><strong>Vehicle:</strong> <?= htmlspecialchars($data['vehicle_name']) ?></p>
            <p><strong>Total:</strong> ₱<?= htmlspecialchars((string) $data['total_price']) ?></p>
        </div>

        <?php if ($alreadyPaid): ?>

            <div class="payment-box">
                <h3>Payment Already Completed</h3>
                <p>This booking has already been paid for. No further action is needed.</p>
                <a href="record.php" class="btn-return">View Payment History</a>
            </div>

        <?php else: ?>

            <div class="payment-box">
                <h3>Payment Method</h3>
                <p>Continue your secure payment through Xendit.</p>
            </div>

            <div class="payment-form">
                <button class="btn" type="button" id="payWithXendit">Pay with Xendit</button>
                <a href="javascript:history.back()" class="btn-return">← Return</a>
            </div>

        <?php endif; ?>

    </div>

    <?php if (!$alreadyPaid): ?>
    <script>
        document.getElementById('payWithXendit')?.addEventListener('click', async function () {
            const formData = new FormData();
            formData.append('booking_id', '<?= (int) $booking_id ?>');

            try {
                const response = await fetch('payment_gateway.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success && result.checkout_url) {
                    window.location.href = result.checkout_url;
                } else {
                    alert(result.message || 'Unable to start Xendit payment.');
                }
            } catch (error) {
                console.error(error);
                alert('Xendit payment could not be started.');
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>