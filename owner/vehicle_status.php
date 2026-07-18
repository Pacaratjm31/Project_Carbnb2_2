<?php
require_once __DIR__ . '/owner_logic.php';
$pdo = get_owner_pdo();
$owner = get_current_owner($pdo);

// Update session status from database to reflect any admin changes
if (isset($_SESSION['user_id']) && $owner['id'] > 0) {
    $_SESSION['status'] = $owner['status'];
    $_SESSION['approval_status'] = $owner['status'];
    $_SESSION['approval_reason'] = $owner['disapproval_reason'] ?? '';
}

$current_page = basename($_SERVER['PHP_SELF'] ?? 'owner_dashboard.php');
if (function_exists('enforce_owner_access')) {
    enforce_owner_access($pdo, $owner, $current_page);
} elseif (($owner['status'] ?? 'pending') !== 'approved' && $current_page !== 'owner_dashboard.php') {
    header('Location: owner_dashboard.php');
    exit();
}
$vehicles = get_owner_vehicles($pdo, $owner['id']);
$available = 0;
$rented = 0;
$maintenance = 0;
$maintenance_vehicles = [];

foreach ($vehicles as $vehicle) {
    // Only count approved vehicles in status summary
    if ($vehicle['approval_status'] === 'approved') {
        if ($vehicle['availability_status'] === 'available') $available++;
        elseif ($vehicle['availability_status'] === 'rented') $rented++;
        elseif ($vehicle['availability_status'] === 'maintenance') {
            $maintenance++;
            $maintenance_vehicles[] = $vehicle;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vehicle Status</title>
  <link rel="stylesheet" href="css/owner_style.css?v=20260702">
</head>
<body>
  <div class="overlay"></div>
  <aside class="sidebar">
<div class="sidebar-header">
      <h2>Carbnb Owner</h2>
      <button class="sidebar-close" type="button" aria-label="Close sidebar"></button>
    </div>
    <nav class="sidebar-nav">
      <a href="owner_dashboard.php">Dashboard</a>
      <a href="add_vehicle.php">Add Vehicle</a>
      <a href="manage_vehicles.php">Manage Vehicles</a>
      <a href="booking_requests.php">Booking Requests</a>
      <a class="active" href="vehicle_status.php">Vehicle Status</a>
      <a href="owner_income.php">Income</a>
      <a href="rental_history.php">Rental History</a>
      <a href="owner_profile.php">Profile</a>
      <a href="owner_message.php">Messages</a>
      <a href="owner_reviews.php">Reviews</a>
      <a href="../auth/logout.php" class="topbar-action" style="display:block; margin-top:1rem; text-align:center;">Logout</a>
    </nav>
  </aside>

  <div class="main-content">
<header class="topbar">
      <button class="sidebar-toggle" type="button" aria-label="Open sidebar"></button>
      <h1>Vehicle Status</h1>
      <a class="topbar-action" href="owner_profile.php">Profile</a>
    </header>

    <main class="page">
      <section class="content-grid">
        <div class="card">
          <h3 class="section-title">Available Now</h3>
          <p><?php echo $available; ?> vehicle(s)</p>
        </div>
        <div class="card">
          <h3 class="section-title">In Use</h3>
          <p><?php echo $rented; ?> vehicle(s)</p>
        </div>
        <div class="card">
          <h3 class="section-title">Maintenance</h3>
          <p><?php echo $maintenance; ?> vehicle(s)</p>
        </div>
      </section>

      <?php if (!empty($maintenance_vehicles)): ?>
      <section class="card" style="margin-top:20px;">
        <h3 class="section-title">Vehicles Under Maintenance</h3>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Vehicle</th>
                <th>Category</th>
                <th>Price</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($maintenance_vehicles as $vehicle): ?>
                <tr>
                  <td><?php echo htmlspecialchars($vehicle['name']); ?></td>
                  <td><?php echo htmlspecialchars(str_replace('_', ' ', $vehicle['category'])); ?></td>
                  <td><?php echo format_currency($vehicle['price_per_day']); ?>/day</td>
                  <td><span class="status-badge pending"><?php echo htmlspecialchars(status_label($vehicle['availability_status'])); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>
    </main>
  </div>

  <script src="js/owner_script.js"></script>
</body>
