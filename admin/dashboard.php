<?php require_once 'dashboard_logic.php'; 

$ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
if ($ajax && ($_GET['section'] ?? '') === 'stats-grid') {
  echo '<div class="stat-box"><h3>Pending Users</h3><p>' . (int) $totalPendingUsers . '</p></div>';
  echo '<div class="stat-box"><h3>Pending Vehicles</h3><p>' . (int) $totalPendingVehicles . '</p></div>';
  echo '<div class="stat-box"><h3>Pending Bookings</h3><p>' . (int) $totalPendingBookings . '</p></div>';
  echo '<div class="stat-box"><h3>Pending Payments</h3><p>' . (int) $totalPendingPayments . '</p></div>';
  echo '<div class="stat-box"><h3>Pending Messages</h3><p>' . (int) $totalPendingMessages . '</p></div>';
  exit;
}

if ($ajax && ($_GET['section'] ?? '') === 'overview-table') {
  echo '<tr><td data-label="Category">Total Users (Owners + Renters)</td><td data-label="Count">' . (int) $totalUsers . '</td></tr>';
  echo '<tr><td data-label="Category">Total Owners</td><td data-label="Count">' . (int) $totalOwners . '</td></tr>';
  echo '<tr><td data-label="Category">Total Renters</td><td data-label="Count">' . (int) $totalRenters . '</td></tr>';
  echo '<tr><td data-label="Category">Total Vehicles</td><td data-label="Count">' . (int) $totalVehicles . '</td></tr>';
  echo '<tr><td data-label="Category">Total Bookings</td><td data-label="Count">' . (int) $totalBookings . '</td></tr>';
  echo '<tr><td data-label="Category">Total Payments</td><td data-label="Count">' . (int) $totalPayments . '</td></tr>';
  echo '<tr><td data-label="Category">Deleted Users</td><td data-label="Count">' . (int) $totalDeletedUsers . '</td></tr>';
  echo '<tr><td data-label="Category">Total Contact Messages</td><td data-label="Count">' . (int) $totalContactMessages . '</td></tr>';
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Carbnb</title>
  <link rel="stylesheet" href="css/admin_style.css?v=20260702">
  <link rel="stylesheet" href="css/admin_style_backup.css?v=20260702">
  <link rel="stylesheet" href="css/admin_responsive.css?v=20260801">
</head>
<body>
  <div class="overlay"></div>
  
  <aside class="sidebar">
<div class="sidebar-header">
      <h2>Carbnb Admin</h2>
      <button class="sidebar-close" type="button" aria-label="Close sidebar"></button>
    </div>
    <nav class="sidebar-nav">
      <a class="active" href="dashboard.php">Dashboard</a>
      <a href="manage_users.php">Verify Users</a>
      <a href="verify_vehicles.php">Verify Vehicles</a>
      <a href="booking_records.php">Rental Records</a>
      <a href="account_control.php">Account Control</a>
      <a href="earnings.php">Earnings & Commission</a>
      <a href="contact_messages.php">Contact Messages</a>
      <a href="delete_user.php">Delete Users</a>
      <a href="trashbin.php">Trash Bin</a>
      <a href="../auth/logout.php" class="topbar-action" style="display:block; margin-top:1rem; text-align:center;">Logout</a>
    </nav>
  </aside>

  <div class="main-content">
<header class="topbar">
      <button class="sidebar-toggle" type="button" aria-label="Open sidebar"></button>
      <h1>Admin Dashboard</h1>
    </header>

    <main class="page">
      <section class="hero-card">
        <div>
          <h2>Welcome, Administrator</h2>
          <p>Manage users, vehicles, bookings, payments, and system records.</p>
        </div>
      </section>

      <?php if (!empty($error)): ?>
        <div class="alert error">
          <?= clean($error) ?>
        </div>
      <?php endif; ?>

      <section class="stats-grid" id="admin-dashboard-stats" data-live-refresh="dashboard.php?ajax=1&section=stats-grid" data-live-target="#admin-dashboard-stats">
        <div class="stat-box">
          <h3>Pending Users</h3>
          <p><?= $totalPendingUsers ?></p>
        </div>

        <div class="stat-box">
          <h3>Pending Vehicles</h3>
          <p><?= $totalPendingVehicles ?></p>
        </div>

        <div class="stat-box">
          <h3>Pending Bookings</h3>
          <p><?= $totalPendingBookings ?></p>
        </div>

      <div class="stat-box">
          <h3>Pending Payments</h3>
          <p><?= $totalPendingPayments ?></p>
        </div>

        <div class="stat-box">
          <h3>Pending Messages</h3>
          <p><?= $totalPendingMessages ?></p>
        </div>
      </section>

      <section class="card">
        <h3 class="section-title">Quick Actions</h3>
      <div class="quick-actions">
          <a href="manage_users.php" class="action-btn">Verify Users</a>
          <a href="verify_vehicles.php" class="action-btn">Verify Vehicles</a>
          <a href="booking_records.php" class="action-btn">View Bookings</a>
          <a href="contact_messages.php" class="action-btn">Contact Messages</a>
        </div>
      </section>

      <section class="card">
        <h3 class="section-title">System Overview</h3>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Count</th>
              </tr>
            </thead>
            <tbody id="admin-overview-table" data-live-refresh="dashboard.php?ajax=1&section=overview-table" data-live-target="#admin-overview-table">
              <tr>
                <td data-label="Category">Total Users (Owners + Renters)</td>
                <td data-label="Count"><?= $totalUsers ?></td>
              </tr>
              <tr>
                <td data-label="Category">Total Owners</td>
                <td data-label="Count"><?= $totalOwners ?></td>
              </tr>
              <tr>
                <td data-label="Category">Total Renters</td>
                <td data-label="Count"><?= $totalRenters ?></td>
              </tr>
              <tr>
                <td data-label="Category">Total Vehicles</td>
                <td data-label="Count"><?= $totalVehicles ?></td>
              </tr>
              <tr>
                <td data-label="Category">Total Bookings</td>
                <td data-label="Count"><?= $totalBookings ?></td>
              </tr>
              <tr>
                <td data-label="Category">Total Payments</td>
                <td data-label="Count"><?= $totalPayments ?></td>
              </tr>
              <tr>
                <td data-label="Category">Deleted Users</td>
                <td data-label="Count"><?= $totalDeletedUsers ?></td>
              </tr>
              <tr>
                <td data-label="Category">Total Contact Messages</td>
                <td data-label="Count"><?= $totalContactMessages ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const sidebar = document.querySelector('.sidebar');
      const overlay = document.querySelector('.overlay');
      const toggleBtn = document.querySelector('.sidebar-toggle');
      const closeBtn = document.querySelector('.sidebar-close');

      function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.classList.add('sidebar-open');
      }

      function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.classList.remove('sidebar-open');
      }

      if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
          if (sidebar.classList.contains('open')) {
            closeSidebar();
          } else {
            openSidebar();
          }
        });
      }

      if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
      }

      if (overlay) {
        overlay.addEventListener('click', closeSidebar);
      }

      document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
          if (window.innerWidth <= 900) {
            closeSidebar();
          }
        });
      });
    });

    (function () {
      const refreshTargets = document.querySelectorAll('[data-live-refresh]');
      refreshTargets.forEach(function (element) {
        const refreshUrl = element.dataset.liveRefresh;
        const targetSelector = element.dataset.liveTarget || '#' + element.id;
        const refreshSection = function () {
          fetch(refreshUrl)
            .then(function (response) { return response.text(); })
            .then(function (html) {
              const targetNode = document.querySelector(targetSelector);
              if (targetNode) {
                targetNode.innerHTML = html;
              }
            })
            .catch(function (error) {
              console.log('Dashboard live refresh failed:', error);
            });
        };

        refreshSection();
        setInterval(refreshSection, 8000);
      });
    })();
  </script>
</body>
</html>