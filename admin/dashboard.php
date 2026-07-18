<?php require_once 'dashboard_logic.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Carbnb</title>
  <link rel="stylesheet" href="css/admin_style.css?v=20260702">
  <link rel="stylesheet" href="css/admin_style_backup.css?v=20260702">
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

      <section class="stats-grid">
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
            <tbody>
              <tr>
                <td>Total Users (Owners + Renters)</td>
                <td><?= $totalUsers ?></td>
              </tr>
              <tr>
                <td>Total Owners</td>
                <td><?= $totalOwners ?></td>
              </tr>
              <tr>
                <td>Total Renters</td>
                <td><?= $totalRenters ?></td>
              </tr>
              <tr>
                <td>Total Vehicles</td>
                <td><?= $totalVehicles ?></td>
              </tr>
              <tr>
                <td>Total Bookings</td>
                <td><?= $totalBookings ?></td>
              </tr>
              <tr>
                <td>Total Payments</td>
                <td><?= $totalPayments ?></td>
              </tr>
              <tr>
                <td>Deleted Users</td>
                <td><?= $totalDeletedUsers ?></td>
              </tr>
              <tr>
                <td>Total Contact Messages</td>
                <td><?= $totalContactMessages ?></td>
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
  </script>
</body>
</html>