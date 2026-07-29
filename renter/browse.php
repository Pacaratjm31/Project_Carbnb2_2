<?php
include '../database/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$conn = $conn ?? $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare("SELECT id, full_name, status, disapproval_reason FROM users WHERE id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$renter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$renter) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

function renter_approval_label(string $status): string {
    return match ($status) {
        'approved' => 'Approved',
        'pending' => 'Pending',
        'disapproved' => 'Disapproved',
        default => ucfirst($status),
    };
}

function renter_approval_badge_class(string $status): string {
    return match ($status) {
        'approved' => 'approved',
        'pending' => 'pending',
        'disapproved' => 'disapproved',
        default => 'pending',
    };
}

function get_renter_account_state(array $renter): array {
    $status = $renter['status'] ?? 'pending';
    $reason = trim($renter['disapproval_reason'] ?? '');

    if ($status === 'approved') {
        return [
            'status' => 'approved',
            'title' => 'Account Approved',
            'message' => 'Your renter account has been approved by admin. Full access is enabled.',
            'restricted' => false,
        ];
    }

    if ($status === 'disapproved') {
        return [
            'status' => 'disapproved',
            'title' => 'Account Disapproved',
            'message' => $reason !== '' ? $reason : 'Your renter account was disapproved by admin.',
            'restricted' => true,
        ];
    }

    return [
        'status' => 'pending',
        'title' => 'Pending Admin Approval',
        'message' => 'Your renter account is waiting for admin approval. You can view the catalog, but booking and other renter actions are temporarily disabled.',
        'restricted' => true,
    ];
}

$account_state = get_renter_account_state($renter);
$filter = trim($_GET['seater'] ?? '');
$categoryMap = [
    '4-5' => '4-5_seater',
    '4-5_seater' => '4-5_seater',
    '6-7' => '6-7_seater',
    '6-7_seater' => '6-7_seater',
    '8-9' => '8-9_seater',
    '8-9_seater' => '8-9_seater',
    '10+' => '10+_seater',
    '10+_seater' => '10+_seater',
];
$normalizedFilter = $categoryMap[$filter] ?? '';

try {
    $sql = "
        SELECT v.id, v.name AS vehicle_name, v.price_per_day AS rate, v.image AS car_image,
               v.availability_status AS status, v.approval_status, v.approval_feedback AS rejection_reason,
               v.category, u.full_name AS owner_name
        FROM vehicles v
        JOIN users u ON v.owner_id = u.id
        WHERE v.is_deleted = 0 AND v.approval_status = 'approved'
    ";

    if ($normalizedFilter !== '') {
        $sql .= ' AND v.category = :category';
    }

    $sql .= ' ORDER BY v.created_at DESC';

    $stmt = $conn->prepare($sql);

    if ($normalizedFilter !== '') {
        $stmt->execute(['category' => $normalizedFilter]);
    } else {
        $stmt->execute();
    }

    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Browse Cars | Carbnb</title>
<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
<link rel="stylesheet" href="css/renter_style.css?v=4">
<link rel="stylesheet" href="css/renter_style_backup.css?v=4">
</head>
<body data-user-id="<?php echo (int) $user_id; ?>" data-current-status="<?php echo htmlspecialchars($renter['status'] ?? 'pending'); ?>">

<div class="top-nav">
    <div class="nav-left">
        <h2>Carbnb</h2>
    </div>

        <div class="nav-right">
            <a href="browse.php" class="active nav-all-cars">All Cars</a>
            <?php if ($account_state['restricted']): ?>
                <span class="nav-link disabled-link">My Records</span>
                <span class="nav-link disabled-link">My Profile</span>
                <span class="nav-link disabled-link">Messages</span>
            <?php else: ?>
                <a href="record.php" class="nav-my-records">My Records</a>
                <a href="view_profile.php" class="nav-my-profile">My Profile</a>
                <a href="renter_messages.php" class="nav-my-messages">Messages</a>
            <?php endif; ?>
            <a href="../auth/logout.php" class="logout-link">Logout</a>
        </div>
</div>

<div class="header-text">
    <h1>Browse <span class="blue">Available</span> <span class="orange">Cars</span></h1>

    <?php if (!empty($normalizedFilter)): ?>
        <p>Filtering by: <span class="blue"><?= htmlspecialchars($filter) ?></span></p>
    <?php else: ?>
        <p>Find the perfect ride for your next trip.</p>
    <?php endif; ?>
</div>

<div class="status-section">
    <div class="status-banner <?= $account_state['restricted'] ? 'warning' : 'success' ?>">
        <div class="banner-content">
            <h3 class="banner-title"><?= htmlspecialchars($account_state['title']) ?></h3>
            <p class="banner-message"><?= htmlspecialchars($account_state['message']) ?></p>
        </div>
        <span id="renter-approval-badge" class="status-badge <?= htmlspecialchars(renter_approval_badge_class($renter['status'] ?? 'pending')) ?>">
            <?= htmlspecialchars(renter_approval_label($renter['status'] ?? 'pending')) ?>
        </span>
    </div>

    <div class="status-card">
        <h3 class="status-card-title">Account Status</h3>
        <div class="status-grid">
            <div class="status-item">
                <span class="status-label">Account Status</span>
                <span id="renter-approval-status" class="status-badge <?= htmlspecialchars(renter_approval_badge_class($renter['status'] ?? 'pending')) ?>">
                    <?= htmlspecialchars(renter_approval_label($renter['status'] ?? 'pending')) ?>
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">Admin Note</span>
                <span id="renter-approval-note" class="status-note"><?= htmlspecialchars(($renter['disapproval_reason'] ?? '') !== '' ? $renter['disapproval_reason'] : 'No admin note yet.') ?></span>
            </div>
        </div>
    </div>

<div class="filter-bar">
    <a href="browse.php">All</a>
    <a href="browse.php?seater=4-5">4-5 Seater</a>
    <a href="browse.php?seater=6-7">6-7 Seater</a>
    <a href="browse.php?seater=8-9">8-9 Seater</a>
    <a href="browse.php?seater=10+">10+ Seater</a>
</div>

<div class="car-container">

<?php if (empty($cars)): ?>
    <div class="no-results">
        <h3>No vehicles found in this category.</h3>
        <a href="browse.php" class="blue">View all cars</a>
    </div>

<?php else: ?>

<?php foreach ($cars as $car): ?>

<?php
    $status = $car['status'] ?? 'available';
    $approval = $car['approval_status'] ?? 'pending';
    $imgPath = build_vehicle_image_path($car['car_image'] ?? '');

    if ($status === 'available') {
        $label = 'Available';
        $class = 'status-available';
    } elseif ($status === 'rented') {
        $label = 'In Use';
        $class = 'status-inuse';
    } else {
        $label = 'Maintenance';
        $class = 'status-maintenance';
    }
?>

    <div class="car-card">

        <div class="text-center">
            <?php if ($approval === 'approved'): ?>
                <span class="approval-badge bg-approved">Admin Approved</span>
            <?php elseif ($approval === 'disapproved'): ?>
                <span class="approval-badge bg-rejected">Admin Rejected</span>
            <?php else: ?>
                <span class="approval-badge bg-pending">Pending Review</span>
            <?php endif; ?>
        </div>

        <img src="<?= htmlspecialchars($imgPath) ?>"
             class="car-img"
             alt="Car Image"
             onerror="this.src='../uploads/vehicles/default-car.svg'">

        <h3><?= htmlspecialchars($car['vehicle_name']) ?></h3>

        <div class="car-details">
            <p><strong>Category:</strong> <?= htmlspecialchars(str_replace('_', ' ', $car['category'] ?? '')) ?></p>
            <p><strong>Owner:</strong> <?= htmlspecialchars($car['owner_name']) ?></p>
            <p><strong>Rate:</strong> ₱<?= number_format((float) $car['rate'], 2) ?>/day</p>
        </div>

        <span class="status <?= $class ?>">
            <?= $label ?>
        </span>

<div class="card-actions">
             <div class="action-buttons">
                 <a href="vehicle_details.php?car_id=<?= (int) $car['id'] ?>" class="book-btn">View Details</a><br><br>
                 <a href="comment_rate.php?vehicle_id=<?= (int) $car['id'] ?>" class="book-btn" style="background:#17a2b8;">Comment & Rate</a><br><br>
                 <?php if ($account_state['restricted']): ?>
                     <button class="book-btn disabled" disabled>
                         <?= $account_state['status'] === 'disapproved' ? 'Access Restricted' : 'Approval Pending' ?>
                     </button>
                 <?php elseif ($approval === 'approved'): ?>
                     <?php if ($status === 'available'): ?>
                         <a href="book.php?car_id=<?= (int) $car['id'] ?>" class="book-btn">Book Now</a><br><br>
                     <?php endif; ?>
                     <?php
// Check if renter has a completed or returned booking for this vehicle
                      $hasCompletedBooking = false;
                      $hasReviewed = false;
                      if (!$account_state['restricted']) {
                          $stmt = $conn->prepare("SELECT id FROM bookings WHERE renter_id = ? AND vehicle_id = ? AND (status = 'completed' OR status = 'return_requested') LIMIT 1");
                          $stmt->execute([$user_id, $car['id']]);
                          $hasCompletedBooking = (bool) $stmt->fetch();
                          
                          // Check if already reviewed
                          $stmt = $conn->prepare("SELECT id FROM reviews WHERE renter_id = ? AND vehicle_id = ? LIMIT 1");
                          $stmt->execute([$user_id, $car['id']]);
                          $hasReviewed = (bool) $stmt->fetch();
                      }
                     ?>
                     <?php if ($hasCompletedBooking && !$hasReviewed): ?>
                         <a href="commet_rate.php?vehicle_id=<?= (int) $car['id'] ?>" class="book-btn" style="background:#17a2b8;">
                             Rate & Comment
                         </a><br><br>
                     <?php elseif ($hasReviewed): ?>
                         <button class="book-btn disabled" disabled>Review Submitted</button>
                     <?php endif; ?>
                 <?php elseif ($approval === 'disapproved'): ?>
                     <button class="book-btn disabled" disabled>Rejected</button>
                     <p class="rejection-text">
                         Reason: <?= htmlspecialchars($car['rejection_reason'] ?? 'Contact admin') ?>
                     </p>
                 <?php elseif ($approval === 'pending'): ?>
                     <button class="book-btn disabled" disabled>Wait for Approval</button>
                 <?php else: ?>
                     <button class="book-btn disabled" disabled>Unavailable</button>
                 <?php endif; ?>
             </div>
         </div>

    </div>

<?php endforeach; ?>

<?php endif; ?>

</div>

<footer>
    <p>&copy; 2026 Carbnb Philippines. All rights reserved.</p>
</footer>

<script>
// Auto-refresh approval status for pending accounts
(function() {
    const userId = document.body.getAttribute('data-user-id');
    const currentStatus = document.body.getAttribute('data-current-status');
    const statusBadge = document.getElementById('renter-approval-status');
    const approvalNote = document.getElementById('renter-approval-note');
    const approvalBannerBadge = document.getElementById('renter-approval-badge');
    
    if (!userId || !statusBadge) return;
    
    // Only poll if account is pending
    if (currentStatus !== 'pending') return;
    
    // Poll for status updates every 5 seconds
    const pollInterval = setInterval(function() {
        fetch('check_approval_status.php?user_id=' + userId)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'approved') {
                    clearInterval(pollInterval);
                    location.reload();
                } else if (data.status === 'disapproved') {
                    statusBadge.textContent = 'Disapproved';
                    statusBadge.className = 'status-badge disapproved';
                    approvalNote.textContent = data.disapproval_reason || 'Your account was disapproved.';
                    if (approvalBannerBadge) {
                        approvalBannerBadge.textContent = 'Disapproved';
                        approvalBannerBadge.className = 'status-badge disapproved';
                    }
                }
            })
            .catch(err => console.log('Status check failed:', err));
    }, 5000);
})();
</script>
<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>