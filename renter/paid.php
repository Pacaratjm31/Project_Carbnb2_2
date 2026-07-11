<?php
include '../database/db.php';
session_start();
$conn = $conn ?? $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);

// Get renter account state
$stmt = $conn->prepare("SELECT id, full_name, status, disapproval_reason FROM users WHERE id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$renter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$renter) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Check if renter is approved
if (($renter['status'] ?? 'pending') !== 'approved') {
    $account_state = [
        'status' => $renter['status'] ?? 'pending',
        'title' => $renter['status'] === 'disapproved' ? 'Account Disapproved' : 'Pending Admin Approval',
        'message' => $renter['status'] === 'disapproved' 
            ? ($renter['disapproval_reason'] ?? 'Your account was disapproved.') 
            : 'Your account is waiting for admin approval. Payment is disabled.',
        'restricted' => true
    ];
    
    // Show restricted page
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
            <div class="approval-card">
                <h3><?= htmlspecialchars($account_state['title']) ?></h3>
                <p><?= htmlspecialchars($account_state['message']) ?></p>
                <a href="browse.php" class="btn-return">← Back to Browse</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!isset($_GET['booking_id'])) {
    die('Invalid request.');
}

$booking_id = (int) $_GET['booking_id'];

$stmt = $conn->prepare("SELECT b.id, b.total_price, b.status, v.name AS vehicle_name, v.image AS car_image FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id = ? AND b.renter_id = ?");
$stmt->execute([$booking_id, $user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('Booking not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = trim($_POST['method'] ?? '');
    $allowedMethods = ['gcash', 'paymaya', 'cash', 'bank_transfer'];

    if (!in_array($method, $allowedMethods, true)) {
        die('Invalid payment method.');
    }

    if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        die('Upload receipt required.');
    }

    $transactionReference = trim((string) ($_POST['transaction_reference'] ?? ''));

    $tmp = $_FILES['receipt']['tmp_name'];
    $size = (int) ($_FILES['receipt']['size'] ?? 0);
    $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));

    if ($size > 2 * 1024 * 1024) {
        die('File too large (max 2MB).');
    }

    $allowedExt = ['jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowedExt, true)) {
        die('Invalid file type.');
    }

    $uploadDir = __DIR__ . '/../uploads/payments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . '_' . uniqid() . '.' . $ext;
    move_uploaded_file($tmp, $uploadDir . $fileName);

    $amount = (float) $data['total_price'];
    $reference = $transactionReference !== '' ? $transactionReference : 'CARBNB-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $booking_id;
    $gatewayResponse = 'Payment submitted through renter form using ' . $method . '.';

    $check = $conn->prepare('SELECT id FROM payments WHERE booking_id = ?');
    $check->execute([$booking_id]);

    if ($check->rowCount() > 0) {
        $update = $conn->prepare("UPDATE payments SET amount = ?, proof_image = ?, payment_method = ?, transaction_reference = ?, gateway_response = ?, status = 'pending', paid_at = NOW() WHERE booking_id = ?");
        $update->execute([$amount, $fileName, $method, $reference, $gatewayResponse, $booking_id]);
    } else {
        $insert = $conn->prepare("INSERT INTO payments (booking_id, amount, proof_image, payment_method, transaction_reference, gateway_response, status, paid_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $insert->execute([$booking_id, $amount, $fileName, $method, $reference, $gatewayResponse]);
    }

    header('Location: paid.php?booking_id=' . $booking_id);
    exit;
}

function build_vehicle_image_path($value): string {
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
<title>Payment</title>
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

    <div class="payment-box">
        <p><strong>GCash:</strong> 09123456789</p>
        <p><strong>PayMaya:</strong> 09876543210</p>
    </div>

    <form method="POST" enctype="multipart/form-data" class="payment-form" id="paymentForm">

        <label>Payment Method</label>
        <select name="method" required>
            <option value="gcash">GCash</option>
            <option value="paymaya">PayMaya</option>
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank Transfer</option>
        </select>

        <label>Transaction Reference</label>
        <input type="text" name="transaction_reference" placeholder="Optional reference number" maxlength="100">

        <label>Upload Receipt</label>
        <input type="file" name="receipt" required accept="image/png,image/jpeg">

        <button class="btn" type="submit">Submit Payment</button>
        <button class="btn" type="button" id="payWithStripe">Pay with Stripe</button>
        <a href="javascript:history.back()" class="btn-return">← Return</a>

    </form>

    <script>
    document.getElementById('paymentForm')?.addEventListener('submit', async function (event) {
        event.preventDefault();

        const fileInput = this.querySelector('input[name="receipt"]');
        if (fileInput && fileInput.files.length === 0) {
            alert('Please select a receipt image before submitting.');
            return;
        }

        const formData = new FormData(this);
        formData.append('booking_id', '<?= (int) $booking_id ?>');

        try {
            const response = await fetch('payment_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                window.location.href = 'record.php';
            } else {
                alert(result.message || 'Unable to submit payment.');
            }
        } catch (error) {
            console.error(error);
            alert('Payment submission failed. Please try again.');
        }
    });

    document.getElementById('payWithStripe')?.addEventListener('click', async function () {
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
                alert(result.message || 'Unable to start Stripe Checkout.');
            }
        } catch (error) {
            console.error(error);
            alert('Stripe checkout could not be started.');
        }
    });
    </script>

</div>

</body>
</html>