<?php
// Earnings Logic - Revenue and commission tracking
require_once 'earnings_logic.php';

// Initialize variables
$totalRevenue = 0;
$totalCommission = 0;
$totalOwnerIncome = 0;
$totalTransactions = 0;
$verifiedRevenue = 0;
$pendingRevenue = 0;
$months = [];
$revenues = [];

// Total Revenue (All Status)
try {
    $stmt = $pdo->query("
        SELECT 
            SUM(amount) AS total_revenue,
            COUNT(*) AS total_transactions,
            SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) AS verified_revenue,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) AS pending_revenue
        FROM payments
    ");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalRevenue = $total['total_revenue'] ?? 0;
    $totalTransactions = $total['total_transactions'] ?? 0;
    $verifiedRevenue = $total['verified_revenue'] ?? 0;
    $pendingRevenue = $total['pending_revenue'] ?? 0;

    // Calculate commission (20%) and owner income (80%)
    $totalCommission = $totalRevenue * 0.20;
    $totalOwnerIncome = $totalRevenue * 0.80;

    // Monthly Data (Only Verified)
    $stmt2 = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            SUM(amount) AS revenue
        FROM payments
        WHERE status = 'verified'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $data = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as $row) {
        $months[] = $row['month'];
        $revenues[] = $row['revenue'];
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Earnings & Commission | Carbnb Admin</title>
  <link rel="stylesheet" href="css/admin_style.css?v=20260702">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
      <a href="account_control.php">Account Control</a>
      <a href="earnings.php" class="active">Earnings & Commission</a>
      <a href="delete_user.php">Delete Users</a>
      <a href="trashbin.php">Trash Bin</a>
      <a href="../auth/logout.php" class="topbar-action" style="display:block; margin-top:1rem; text-align:center;">Logout</a>
    </nav>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <button class="sidebar-toggle" type="button">☰</button>
      <h1>Earnings & Commission</h1>
    </header>

    <main class="page">
      <section class="hero-card">
        <div>
          <h2>Revenue Overview</h2>
          <p>Track platform earnings, commission, and owner income from all transactions.</p>
        </div>
        <a class="topbar-action" href="dashboard.php">← Return to Dashboard</a>
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

      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
        <div class="stat-box">
          <h3>Total Revenue (All)</h3>
          <p>$<?= number_format($totalRevenue, 2) ?></p>
        </div>

        <div class="stat-box">
          <h3>Verified Revenue</h3>
          <p>$<?= number_format($verifiedRevenue, 2) ?></p>
        </div>

        <div class="stat-box">
          <h3>Pending Payments</h3>
          <p>$<?= number_format($pendingRevenue, 2) ?></p>
        </div>

        <div class="stat-box">
          <h3>Commission (20%)</h3>
          <p>$<?= number_format($totalCommission, 2) ?></p>
        </div>

        <div class="stat-box">
          <h3>Owner Earnings (80%)</h3>
          <p>$<?= number_format($totalOwnerIncome, 2) ?></p>
        </div>

        <div class="stat-box">
          <h3>Total Transactions</h3>
          <p><?= $totalTransactions ?></p>
        </div>
      </div>

      <!-- Pending Payments Section -->
      <section class="card" style="margin-top:20px;">
        <h3 class="section-title">Pending Payments - Approval Required</h3>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>Renter</th>
                <th>Owner</th>
                <th>Vehicle</th>
                <th>Amount</th>
                <th>Receipt</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pending_payments)): ?>
                <tr>
                  <td colspan="7" class="empty-state">No pending payments to approve.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($pending_payments as $payment): ?>
                  <tr>
                    <td>#<?= $payment['booking_id'] ?></td>
                    <td><?= clean($payment['renter_name']) ?><br><small><?= clean($payment['renter_email']) ?></small></td>
                    <td><?= clean($payment['owner_name']) ?><br><small><?= clean($payment['owner_email']) ?></small></td>
                    <td><?= clean($payment['vehicle_name']) ?></td>
                    <td>$<?= number_format($payment['amount'], 2) ?></td>
                    <td>
                      <?php if (!empty($payment['proof_image'])): ?>
                        <a href="../uploads/payments/<?= clean($payment['proof_image']) ?>" target="_blank">
                          <img src="../uploads/payments/<?= clean($payment['proof_image']) ?>" 
                               style="max-width:60px; max-height:60px; object-fit:cover; border-radius:6px;" 
                               alt="Receipt">
                        </a>
                      <?php else: ?>
                        <span class="text-muted">No receipt</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="action-group">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this payment? 20% commission will be taken, 80% goes to owner.');">
                          <input type="hidden" name="action" value="approve">
                          <input type="hidden" name="payment_id" value="<?= $payment['payment_id'] ?>">
                          <button type="submit" class="action-btn-small approve">Approve</button>
                        </form>
                        <button type="button" class="action-btn-small reject" 
                                onclick="openDisapproveModal(<?= $payment['payment_id'] ?>)">Disapprove</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Payment History Section -->
      <section class="card" style="margin-top:20px;">
        <h3 class="section-title">Payment History</h3>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>Renter</th>
                <th>Owner</th>
                <th>Vehicle</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($all_payments)): ?>
                <tr>
                  <td colspan="7" class="empty-state">No payment records found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($all_payments as $payment): ?>
                  <tr>
                    <td>#<?= $payment['booking_id'] ?></td>
                    <td><?= clean($payment['renter_name']) ?></td>
                    <td><?= clean($payment['owner_name']) ?></td>
                    <td><?= clean($payment['vehicle_name']) ?></td>
                    <td>$<?= number_format($payment['amount'], 2) ?></td>
                    <td>
                      <span class="status-badge <?= statusBadgeClass($payment['payment_status']) ?>">
                        <?= statusLabel($payment['payment_status']) ?>
                      </span>
                    </td>
                    <td><?= formatDate($payment['payment_date']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="card" style="margin-top:20px;">
        <h3 class="section-title">Monthly Revenue</h3>
        <div style="padding:16px 0;">
          <canvas id="chart" style="max-height:300px;"></canvas>
        </div>
      </section>
    </main>
  </div>

  <!-- Disapprove Modal -->
  <div id="disapproveModal" class="modal" style="display:none;">
    <div class="modal-content">
      <h3>Disapprove Payment</h3>
      <form method="POST" id="disapproveForm">
        <input type="hidden" name="action" value="disapprove">
        <input type="hidden" name="payment_id" id="disapprovePaymentId">
        <label>Reason for disapproval:</label>
        <textarea name="reason" required placeholder="Enter reason for disapproving this payment..."></textarea>
        <div class="action-group" style="margin-top:15px;">
          <button type="submit" class="action-btn-small reject">Confirm Disapprove</button>
          <button type="button" class="action-btn-small" onclick="closeDisapproveModal()" style="background:var(--muted); color:#fff;">Cancel</button>
        </div>
      </form>
    </div>
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

    function openDisapproveModal(paymentId) {
      document.getElementById('disapprovePaymentId').value = paymentId;
      document.getElementById('disapproveModal').style.display = 'flex';
      document.body.classList.add('sidebar-open');
    }

    function closeDisapproveModal() {
      document.getElementById('disapproveModal').style.display = 'none';
      document.body.classList.remove('sidebar-open');
    }

    new Chart(document.getElementById('chart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
          label: 'Revenue',
          data: <?= json_encode($revenues) ?>,
          backgroundColor: '#4cc9f0'
        }]
      },
      options: {
        plugins: {
          legend: {
            labels: { color: '#f5b942' }
          }
        },
        scales: {
          x: { ticks: { color: '#f5b942' } },
          y: { ticks: { color: '#f5b942' } }
        }
      }
    });
  </script>
</body>
</html>