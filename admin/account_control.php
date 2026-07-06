<?php require_once 'account_control_logic.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Control | Carbnb Admin</title>
  <link rel="stylesheet" href="css/admin_style.css?v=20260702">
</head>
<body>
  <div class="overlay"></div>
  
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2>Carbnb Admin</h2>
      <button class="sidebar-close" type="button">×</button>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="manage_users.php">Verify Users</a>
      <a href="verify_vehicles.php">Verify Vehicles</a>
      <a href="booking_records.php">Rental Records</a>
      <a href="account_control.php" class="active">Account Control</a>
      <a href="earnings.php">Earnings & Commission</a>
      <a href="delete_user.php">Delete Users</a>
      <a href="trashbin.php">Trash Bin</a>
      <a href="../auth/logout.php" class="topbar-action" style="display:block; margin-top:1rem; text-align:center;">Logout</a>
    </nav>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <button class="sidebar-toggle" type="button">☰</button>
      <h1>Account Control</h1>
    </header>

    <main class="page">
      <section class="hero-card">
        <div>
          <h2>Account Security</h2>
          <p>Monitor locked user accounts and restore access when necessary to maintain platform security.</p>
        </div>
      </section>

      <?php if (!empty($success)): ?>
        <div class="alert success">
          <?= clean($success) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="alert error">
          <?= clean($error) ?>
        </div>
      <?php endif; ?>

      <section class="card">
        <h3 class="section-title">Locked User Accounts</h3>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Full Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Login Attempts</th>
                <th>Locked Until</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($lockedUsers)): ?>
                <tr>
                  <td colspan="6" class="empty-state">No locked accounts found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($lockedUsers as $user): ?>
                  <tr>
                    <td><?= clean($user['full_name']) ?></td>
                    <td><?= clean($user['email']) ?></td>
                    <td><?= clean(ucfirst($user['role'])) ?></td>
                    <td><?= $user['login_attempts'] ?></td>
                    <td><?= formatDate($user['locked_until']) ?></td>
                    <td>
                      <a href="account_control.php?unlock_id=<?= $user['id'] ?>" class="action-btn-small approve" onclick="return confirm('Are you sure you want to unlock this account?')">Unlock</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="card">
        <h3 class="section-title">Security Information</h3>
        <p class="empty-state">
          Carbnb automatically limits failed login attempts and temporarily locks user accounts to protect the platform from unauthorized access.
        </p>
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