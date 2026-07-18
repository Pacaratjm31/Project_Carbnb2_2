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

if (!isset($_GET['car_id']) || empty($_GET['car_id'])) {
    die('Invalid request.');
}

$car_id = (int) $_GET['car_id'];

// Check if user is approved
$stmt = $conn->prepare("SELECT id, full_name, status FROM users WHERE id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$renter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$renter || $renter['status'] !== 'approved') {
    $account_restricted = true;
} else {
    $account_restricted = false;
}

$stmt = $conn->prepare("SELECT v.id, v.name AS vehicle_name, v.description, v.price_per_day AS rate, v.image AS car_image, 
                               v.availability_status AS status, v.approval_status, v.category, v.transmission, v.model_year,
                               u.full_name AS owner_name, u.email AS owner_email
                        FROM vehicles v 
                        JOIN users u ON v.owner_id = u.id 
                        WHERE v.id = ? AND v.is_deleted = 0");
$stmt->execute([$car_id]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$car) {
    die('Car not found.');
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

$imgPath = build_vehicle_image_path($car['car_image'] ?? '');

// Format category for display
$categoryDisplay = str_replace('_', ' ', $car['category'] ?? '');
$categoryDisplay = str_replace('+', 'plus', $categoryDisplay);
$categoryDisplay = ucfirst($categoryDisplay);

// Format transmission for display
$transmissionDisplay = ucfirst($car['transmission'] ?? '');

// Status labels
$statusLabels = [
    'available' => ['Available', 'status-available'],
    'rented' => ['In Use', 'status-inuse'],
    'maintenance' => ['Maintenance', 'status-maintenance']
];
$statusInfo = $statusLabels[$car['status'] ?? 'available'] ?? ['Unknown', 'status-inuse'];

// Get reviews for this vehicle
$stmt = $conn->prepare("SELECT r.rating, r.comment, r.created_at, u.full_name AS renter_name
                       FROM reviews r
                       JOIN users u ON r.renter_id = u.id
                       WHERE r.vehicle_id = ? AND r.rating IS NOT NULL
                       ORDER BY r.created_at DESC");
$stmt->execute([$car_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating
$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE vehicle_id = ? AND rating IS NOT NULL");
$stmt->execute([$car_id]);
$rating_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$avg_rating = $rating_stats['avg_rating'] ? round($rating_stats['avg_rating'], 1) : 0;
$total_reviews = (int) $rating_stats['total_reviews'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Details | Carbnb</title>
    <link rel="stylesheet" href="css/renter_style.css?v=3">
    <link rel="stylesheet" href="css/renter_style_backup.css?v=4">
    <style>
    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
        white-space: nowrap;
    }
    .status-available {
        background: #28a745;
        color: white;
    }
    .status-inuse {
        background: #dc3545;
        color: white;
    }
    .status-maintenance {
        background: #ffc107;
        color: black;
    }
    </style>
</head>

<body>

<div class="top-nav">
    <div class="nav-left">
        <h2>Carbnb</h2>
    </div>
    <div class="nav-right">
        <a href="browse.php" class="nav-all-cars">All Cars</a>
        <a href="record.php" class="nav-my-records">My Records</a>
        <a href="view_profile.php" class="nav-my-profile">My Profile</a>
        <a href="renter_messages.php" class="nav-my-messages">Messages</a>
        <a href="../auth/logout.php" class="logout-link">Logout</a>
    </div>
</div>

<div class="vehicle-details-container">
    <div class="vehicle-details-header">
        <h2>Vehicle Details</h2>
    </div>

    <div class="vehicle-details-wrapper">

        <!-- Left Column: Vehicle Image -->
        <div class="vehicle-image-card">
            <img src="<?= htmlspecialchars($imgPath) ?>"
                 class="vehicle-image"
                 alt="Car Image"
                 onerror="this.src='../uploads/vehicles/default-car.svg'">
            <div class="vehicle-branding-footer">
                <h4 class="branding-title">Carbnb Philippines</h4>
                <p class="branding-text">Safe Vehicle Rentals</p>
                <p class="branding-text">Verified Owners</p>
                <p class="branding-text">Secure Booking System</p>
                <p class="branding-copyright">© 2026 Carbnb Philippines</p>
            </div>
        </div>

<!-- Right Column: Vehicle Information -->
        <div class="vehicle-info-card">
            <div class="vehicle-header">
                <div class="vehicle-title">
                    <h1><?= htmlspecialchars($car['vehicle_name']) ?></h1>

                    <span class="status-badge <?= $statusInfo[1] ?>">
                        <?= $statusInfo[0] ?>
                    </span>
                </div>
            </div>

            <div class="vehicle-info-grid">
                <div class="info-item">
                    <span class="info-label">Category</span>
                    <span class="info-value"><?= htmlspecialchars($categoryDisplay) ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label">Transmission</span>
                    <span class="info-value"><?= htmlspecialchars($transmissionDisplay) ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label">Model Year</span>
                    <span class="info-value"><?= htmlspecialchars($car['model_year']) ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label">Daily Rate</span>
                    <span class="info-value rate-highlight">₱<?= number_format((float) $car['rate'], 2) ?>/day</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Owner</span>
                    <span class="info-value"><?= htmlspecialchars($car['owner_name']) ?></span>
                </div>
            </div>

<?php if (!empty($car['description'])): ?>
            <div class="vehicle-description-box">
                <h4 class="description-title">Description</h4>
                <p class="description-text"><?= nl2br(htmlspecialchars($car['description'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Reviews Section -->
            <div class="vehicle-reviews-box" style="margin-top:20px; padding:15px; background:#1e1e1e; border-radius:8px;">
                <h4 class="description-title" style="color:#ffd700;">Reviews (<?= $total_reviews ?>)</h4>
                <?php if ($total_reviews > 0): ?>
                    <div style="margin-bottom:10px;">
                        <strong>Average Rating:</strong> 
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span style="color:<?= $i <= $avg_rating ? '#ffd700' : '#444' ?>; font-size:1.2rem;">★</span>
                        <?php endfor; ?>
                        <span style="color:#cfcfcf;">(<?= $avg_rating ?>/5)</span>
                    </div>
                <?php endif; ?>
                <?php if (empty($reviews)): ?>
                    <p style="color:#888; font-size:0.9rem;">No reviews yet. Be the first to review!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div style="border-bottom:1px solid #333; padding-bottom:10px; margin-bottom:10px;">
                            <p style="margin:5px 0;">
                                <strong style="color:#cfcfcf;"><?= htmlspecialchars($review['renter_name']) ?></strong>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span style="color:<?= $i <= $review['rating'] ? '#ffd700' : '#444' ?>;">★</span>
                                <?php endfor; ?>
                            </p>
<?php if (!empty($review['comment'])): ?>
                                <p style="color:#aaa; margin:5px 0;"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                            <?php endif; ?>
                            <small style="color:#666;"><?= date('M d, Y', strtotime($review['created_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Action Buttons -->
    <div class="vehicle-actions">
        <?php
// Check if renter has a completed or returned booking for this vehicle
        $hasCompletedBooking = false;
        $hasReviewed = false;
        if (!$account_restricted) {
            $stmt = $conn->prepare("SELECT id FROM bookings WHERE renter_id = ? AND vehicle_id = ? AND (status = 'completed' OR status = 'return_requested') LIMIT 1");
            $stmt->execute([$user_id, $car['id']]);
            $hasCompletedBooking = (bool) $stmt->fetch();
            
            // Check if already reviewed
            $stmt = $conn->prepare("SELECT id FROM reviews WHERE renter_id = ? AND vehicle_id = ? LIMIT 1");
            $stmt->execute([$user_id, $car['id']]);
            $hasReviewed = (bool) $stmt->fetch();
        }
        ?>
        <?php if ($car['status'] === 'available'): ?>
            <a href="book.php?car_id=<?= $car['id'] ?>" class="btn-book">Book Now</a>
        <?php endif; ?>
        <a href="commet_rate.php?vehicle_id=<?= $car['id'] ?>" class="btn-book" style="background:#17a2b8;">Comment & Rate</a>
        <a href="browse.php" class="btn-back">← Back to Browse</a>
    </div>
</div>

</body>
</html>