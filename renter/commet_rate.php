<?php
include '../database/db.php';
include __DIR__ . '/../helpers/duplicate_functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$conn = $conn ?? $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);

// Check if user is approved
$stmt = $conn->prepare("SELECT id, full_name, status FROM users WHERE id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$renter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$renter || $renter['status'] !== 'approved') {
    header('Location: browse.php');
    exit;
}

$success = '';
$error = '';

// Handle comment and rate submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $tokenError = validate_form_token_or_error('submit_review');
    if ($tokenError) {
        $error = $tokenError;
    } else {
        $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
        
        if ($vehicle_id > 0 && $rating >= 1 && $rating <= 5) {
            // Get owner_id from vehicle
            $stmt = $conn->prepare("SELECT owner_id FROM vehicles WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$vehicle_id]);
            $vehicle = $stmt->fetch();
            
if ($vehicle) {
                // Check if review already exists
                $stmt = $conn->prepare("SELECT id FROM reviews WHERE renter_id = ? AND vehicle_id = ?");
                $stmt->execute([$user_id, $vehicle_id]);
                
                if ($stmt->fetch()) {
                    $error = 'You have already reviewed this vehicle.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO reviews (renter_id, owner_id, vehicle_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $vehicle['owner_id'], $vehicle_id, $rating, $comment]);
                    $success = 'Your review has been submitted successfully!';
                }
            } else {
                $error = 'Vehicle not found.';
            }
        } else {
            $error = 'Please provide a valid rating (1-5 stars).';
        }
    }
}

// Get vehicle_id from URL
$vehicle_id = (int)($_GET['vehicle_id'] ?? 0);

// Get vehicle info
$stmt = $conn->prepare("SELECT v.id, v.name, v.image, v.price_per_day, v.category, v.transmission, v.model_year, u.full_name AS owner_name
                       FROM vehicles v
                       JOIN users u ON v.owner_id = u.id
                       WHERE v.id = ? AND v.is_deleted = 0");
$stmt->execute([$vehicle_id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    header('Location: browse.php');
    exit;
}

// Get existing reviews for this vehicle
$stmt = $conn->prepare("SELECT r.rating, r.comment, r.created_at, u.full_name AS renter_name
                       FROM reviews r
                       JOIN users u ON r.renter_id = u.id
                       WHERE r.vehicle_id = ? AND r.rating IS NOT NULL
                       ORDER BY r.created_at DESC");
$stmt->execute([$vehicle_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating
$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE vehicle_id = ? AND rating IS NOT NULL");
$stmt->execute([$vehicle_id]);
$rating_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$avg_rating = $rating_stats['avg_rating'] ? round($rating_stats['avg_rating'], 1) : 0;
$total_reviews = (int) $rating_stats['total_reviews'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate & Comment | Carbnb</title>
    <link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/renter_style.css?v=5">
    <link rel="stylesheet" href="css/renter_style_backup.css?v=4">
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

    <div class="header-text">
        <h1><span class="blue">Rate &</span> <span class="orange">Comment</span></h1>
    </div>

    <div class="record-container" style="max-width:800px; margin:120px auto 40px;">
        <?php if ($success): ?>
            <div class="alert success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert" style="color:#dc3545;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

<div class="card" style="background:#2a2a2a; border:1px solid #333; border-radius:12px; padding:20px; margin-bottom:20px;">
            <h3 style="color:#ffd700; margin-bottom:15px;"><?= htmlspecialchars($vehicle['name']) ?></h3>
            <p style="color:#cfcfcf;"><strong style="color:#aaa;">Owner:</strong> <?= htmlspecialchars($vehicle['owner_name']) ?></p>
            <p style="color:#cfcfcf;"><strong style="color:#aaa;">Rate:</strong> ₱<?= number_format((float) $vehicle['price_per_day'], 2) ?>/day</p>
            
            <?php if ($total_reviews > 0): ?>
                <div style="margin:15px 0; color:#cfcfcf;">
                    <strong style="color:#aaa;">Average Rating:</strong> 
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span style="color:<?= $i <= $avg_rating ? '#ffd700' : '#444' ?>;"><?= $i <= $avg_rating ? '★' : '☆' ?></span>
                    <?php endfor; ?>
                    <span style="color:#cfcfcf;">(<?= $avg_rating ?>/5 from <?= $total_reviews ?> review<?= $total_reviews > 1 ? 's' : '' ?>)</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="background:#2a2a2a; border:1px solid #333; border-radius:12px; padding:20px; margin-bottom:20px;">
            <h3 style="color:#ffd700; margin-bottom:15px;">Write Your Review</h3>
            <form method="POST">
                <?= form_token_input('submit_review') ?>
                <input type="hidden" name="vehicle_id" value="<?= $vehicle_id ?>">
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="color:#aaa; display:block; margin-bottom:5px;">Rating (1-5 stars)</label>
                    <div class="star-rating" style="display:flex; flex-direction:row-reverse; justify-content:flex-start;">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required>
                            <label for="star<?= $i ?>" style="color:#444; font-size:2rem; cursor:pointer;">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
                
<div class="form-group" style="margin-bottom:15px;">
                    <label style="color:#aaa; display:block; margin-bottom:5px;">Comment</label>
                    <textarea name="comment" rows="4" class="form-control" placeholder="Share your experience with this vehicle..." style="width:100%; padding:10px; border-radius:6px; border:1px solid #555; background:#1e1e1e; color:#cfcfcf;"></textarea>
                </div>
                
                <button type="submit" name="submit_review" class="btn-book">Submit Review</button>
            </form>
        </div>

        <?php if (!empty($reviews)): ?>
            <div class="card" style="background:#2a2a2a; border:1px solid #333; border-radius:12px; padding:20px;">
                <h3 style="color:#ffd700; margin-bottom:15px;">Previous Reviews</h3>
                <?php foreach ($reviews as $review): ?>
                    <div style="border-bottom:1px solid #333; padding-bottom:15px; margin-bottom:15px;">
                        <p><strong><?= htmlspecialchars($review['renter_name']) ?></strong> 
                            <span style="color:#ffd700;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= $review['rating'] ? '★' : '☆' ?>
                                <?php endfor; ?>
                            </span>
                        </p>
<?php if (!empty($review['comment'])): ?>
                            <p style="color:#cfcfcf;"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                        <?php endif; ?>
                        <small style="color:#888;"><?= date('M d, Y', strtotime($review['created_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="vehicle_details.php?car_id=<?= $vehicle_id ?>" class="back-link" style="color:#ffd700; text-decoration:none; display:inline-block; margin-top:20px;">← Back to Vehicle Details</a>
    </div>

    <script>
        // Star rating hover effect
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star-rating input');
            const labels = document.querySelectorAll('.star-rating label');
            
            labels.forEach((label, index) => {
                label.addEventListener('mouseover', function() {
                    const starValue = 5 - index;
                    labels.forEach((l, i) => {
                        l.style.color = (5 - i) <= starValue ? '#ffd700' : '#444';
                    });
                });
                
                label.addEventListener('mouseout', function() {
                    const checked = document.querySelector('.star-rating input:checked');
                    if (checked) {
                        const checkedValue = parseInt(checked.value);
                        labels.forEach((l, i) => {
                            l.style.color = (5 - i) <= checkedValue ? '#ffd700' : '#444';
                        });
                    } else {
                        labels.forEach(l => l.style.color = '#444');
                    }
                });
            });
        });
    </script>
</body>
</html>