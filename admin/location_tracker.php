<?php
// ============================================
// FIX: Ensure admin_auth.php loads correctly
// ============================================
require_once __DIR__ . '/admin_auth.php';

// ============================================
// FIX: Make sure $pdo is available
// ============================================
$pdo = $GLOBALS['pdo'] ?? null;

// If still not available, try to load db.php directly
if (!$pdo) {
    require_once __DIR__ . '/../database/db.php';
    $pdo = $GLOBALS['pdo'] ?? null;
}

// If STILL not available, try to connect directly
if (!$pdo) {
    try {
        // InfinityFree database credentials
        $host = 'sql207.infinityfree.com';
        $dbname = 'if0_42554417_carbnb';
        $username = 'if0_42554417';
        $password = 'YOUR_DATABASE_PASSWORD_HERE'; // ← CHANGE THIS
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['pdo'] = $pdo;
    } catch (PDOException $e) {
        // Connection failed
    }
}

// ============================================
// API ROUTER - Handles both GET and POST
// ============================================
$ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// --- POST: Renter sends location ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$ajax) {
    header('Content-Type: application/json');
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
        exit;
    }
    
    $user_id = (int) $_SESSION['user_id'];
    $latitude = isset($_POST['latitude']) ? (float) $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? (float) $_POST['longitude'] : null;
    $accuracy = isset($_POST['accuracy']) ? (float) $_POST['accuracy'] : 0;
    $recorded_at = isset($_POST['recorded_at']) ? $_POST['recorded_at'] : date('Y-m-d H:i:s');
    
    // Validate coordinates
    if ($latitude === null || $longitude === null) {
        echo json_encode(['success' => false, 'message' => 'Missing latitude or longitude']);
        exit;
    }
    
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
        exit;
    }
    
    try {
        if (!$pdo) {
            throw new Exception('Database connection not available');
        }
        
        // Insert location
        $stmt = $pdo->prepare("
            INSERT INTO location_tracker (user_id, latitude, longitude, accuracy, recorded_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $latitude, $longitude, $accuracy, $recorded_at]);
        
        // Clean up old records (older than 1 hour)
        $stmt = $pdo->prepare("DELETE FROM location_tracker WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Location saved successfully',
            'user_id' => $user_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// --- GET: Admin fetches locations ---
if ($ajax && ($_GET['section'] ?? '') === 'locations') {
    header('Content-Type: application/json');
    
    try {
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database connection not available', 'points' => []]);
            exit;
        }
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'location_tracker'");
        if ($stmt->rowCount() == 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'No locations found yet',
                'points' => []
            ]);
            exit;
        }
        
        // Get locations from the last 30 minutes
        $stmt = $pdo->prepare("
            SELECT 
                lt.id,
                lt.user_id,
                lt.latitude,
                lt.longitude,
                lt.accuracy,
                lt.recorded_at,
                u.full_name
            FROM location_tracker lt
            LEFT JOIN users u ON lt.user_id = u.id
            WHERE lt.recorded_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            AND u.is_deleted = 0
            ORDER BY lt.recorded_at ASC
        ");
        $stmt->execute();
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'points' => $points,
            'count' => count($points),
            'message' => count($points) . ' location(s) found'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage(),
            'points' => []
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Renter Location Tracker | Carbnb Admin</title>
  <link rel="stylesheet" href="css/admin_style.css?v=20260702">
  <link rel="stylesheet" href="css/admin_style_backup.css?v=20260702">
  <link rel="stylesheet" href="css/admin_responsive.css?v=20260801">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
        crossorigin=""/>
  <style>
    #tracker-map { 
      height: 540px; 
      width: 100%; 
      border-radius: 12px; 
      border: 1px solid #d7dde7;
      background: #1a1a2e;
    }
    .tracker-panel { margin-top: 1rem; }
    .tracker-badge { 
      display: inline-block; 
      padding: .35rem .6rem; 
      border-radius: 999px; 
      background: #eaf7ee; 
      color: #19723d; 
      font-weight: 600; 
    }
    .tracker-status {
      margin-top: 12px;
      padding: 10px 14px;
      border-radius: 8px;
      background: rgba(255,255,255,0.05);
      border: 1px solid var(--border);
      color: var(--muted);
    }
    .tracker-status .count {
      color: var(--accent);
      font-weight: 700;
    }
    .tracker-status.error {
      background: rgba(248,113,113,0.1);
      border-color: rgba(248,113,113,0.2);
      color: var(--danger);
    }
    .tracker-status.success {
      background: rgba(110,231,183,0.1);
      border-color: rgba(110,231,183,0.2);
      color: var(--success);
    }
    .tracker-error {
      display: none;
      padding: 15px 20px;
      margin-bottom: 15px;
      background: rgba(248,113,113,0.15);
      border: 1px solid rgba(248,113,113,0.3);
      border-radius: 10px;
      color: var(--danger);
    }
    .tracker-error.show {
      display: block;
    }
    .loading-spinner {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid var(--border);
      border-radius: 50%;
      border-top-color: var(--accent);
      animation: spin 0.8s ease-in-out infinite;
      margin-right: 8px;
      vertical-align: middle;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    
    @media (max-width: 767px) {
      #tracker-map { height: 320px; }
    }
  </style>
</head>
<body>
  <div class="overlay"></div>
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2>Carbnb Admin</h2>
      <button class="sidebar-close" type="button" aria-label="Close sidebar"></button>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="manage_users.php">Verify Users</a>
      <a href="verify_vehicles.php">Verify Vehicles</a>
      <a href="booking_records.php">Rental Records</a>
      <a href="account_control.php">Account Control</a>
      <a href="earnings.php">Earnings & Commission</a>
      <a href="contact_messages.php">Contact Messages</a>
      <a href="delete_user.php">Delete Users</a>
      <a href="trashbin.php">Trash Bin</a>
      <a href="location_tracker.php" class="active">Renter Tracker</a>
      <a href="../auth/logout.php" class="topbar-action" style="display:block; margin-top:1rem; text-align:center;">Logout</a>
    </nav>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <button class="sidebar-toggle" type="button" aria-label="Open sidebar"></button>
      <h1>Renter Location Tracker</h1>
    </header>

    <main class="page">
      <section class="hero-card">
        <div>
          <h2>Live renter movement map</h2>
          <p>View recent shared GPS positions from renters in the last 30 minutes.</p>
        </div>
        <div>
          <span class="tracker-badge">🔄 Auto-refresh every 10 seconds</span>
        </div>
      </section>

      <!-- Error message container -->
      <div id="trackerError" class="tracker-error">
        <strong>⚠️ Unable to load location data</strong>
        <p id="errorMessage">Please check your connection and try again.</p>
        <button onclick="refreshLocations()" class="action-btn-small approve" style="margin-top:8px;">
          Retry
        </button>
      </div>

      <section class="card">
        <div class="tracker-panel">
          <div id="tracker-map"></div>
          <div id="trackerStatus" class="tracker-status">
            <span class="loading-spinner"></span> Loading latest positions...
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
          crossorigin=""></script>
  
  <script>
    // ============================
    // Configuration
    // ============================
    const REFRESH_INTERVAL = 10000;
    let map = null;
    let markers = [];
    let polylines = [];
    let refreshTimer = null;
    let isFirstLoad = true;

    // ============================
    // DOM Elements
    // ============================
    const mapEl = document.getElementById('tracker-map');
    const statusEl = document.getElementById('trackerStatus');
    const errorEl = document.getElementById('trackerError');
    const errorMsgEl = document.getElementById('errorMessage');

    // ============================
    // Sidebar
    // ============================
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
          sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });
      }
      if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
      if (overlay) overlay.addEventListener('click', closeSidebar);

      document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
          if (window.innerWidth <= 900) closeSidebar();
        });
      });
    });

    // ============================
    // Error Handling
    // ============================
    function showError(message) {
      errorMsgEl.textContent = message;
      errorEl.classList.add('show');
      statusEl.className = 'tracker-status error';
      statusEl.innerHTML = '❌ Error loading locations. Please try again.';
    }

    function hideError() {
      errorEl.classList.remove('show');
    }

    function setStatus(message, type = 'info', count = 0) {
      statusEl.className = 'tracker-status';
      if (type === 'error') statusEl.classList.add('error');
      else if (type === 'success') statusEl.classList.add('success');
      
      if (count > 0) {
        statusEl.innerHTML = message.replace('{count}', '<span class="count">' + count + '</span>');
      } else {
        statusEl.innerHTML = message;
      }
    }

    // ============================
    // Map
    // ============================
    function initMap() {
      try {
        if (typeof L === 'undefined') {
          showError('Leaflet library failed to load.');
          return false;
        }

        map = L.map(mapEl).setView([14.5995, 120.9842], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        setTimeout(function() { if (map) map.invalidateSize(); }, 500);
        return true;
      } catch (e) {
        showError('Failed to initialize map: ' + e.message);
        return false;
      }
    }

    function clearMarkers() {
      markers.forEach(m => { try { m.remove(); } catch(e) {} });
      markers = [];
      polylines.forEach(l => { try { l.remove(); } catch(e) {} });
      polylines = [];
    }

    function colorForUser(userId) {
      const palette = ['#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', '#46f0f0', '#f032e6', '#bcf60c', '#fabebe'];
      return palette[Math.abs(userId) % palette.length] || '#19723d';
    }

    function renderPoints(points) {
      if (!map) { if (!initMap()) return; }
      clearMarkers();

      if (!points || points.length === 0) {
        setStatus('📍 No renter positions were shared in the last 30 minutes.', 'info');
        hideError();
        return;
      }

      const users = {};
      points.forEach(p => {
        const uid = p.user_id || 0;
        if (!users[uid]) {
          users[uid] = { full_name: p.full_name || 'Renter', points: [] };
        }
        users[uid].points.push(p);
      });

      const allBounds = [];
      let userCount = 0;

      Object.keys(users).forEach(uid => {
        const u = users[uid];
        u.points.sort((a, b) => new Date(a.recorded_at) - new Date(b.recorded_at));
        const coords = u.points.filter(pt => pt.latitude && pt.longitude).map(pt => [pt.latitude, pt.longitude]);
        if (!coords.length) return;

        const color = colorForUser(parseInt(uid, 10));
        
        if (coords.length > 1) {
          const poly = L.polyline(coords, { color: color, weight: 4, opacity: 0.8 }).addTo(map);
          polylines.push(poly);
        }

        const last = u.points[u.points.length - 1];
        if (last.latitude && last.longitude) {
          const marker = L.circleMarker([last.latitude, last.longitude], { 
            radius: 8, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9 
          }).addTo(map);
          marker.bindPopup(`<strong>${u.full_name}</strong><br>${new Date(last.recorded_at).toLocaleString()}`);
          markers.push(marker);
        }

        coords.forEach(c => allBounds.push(c));
        userCount++;
      });

      if (allBounds.length) {
        try { map.fitBounds(allBounds, { padding: [50, 50] }); } catch(e) {}
      }

      setStatus(`📍 Showing {count} renter${userCount === 1 ? '' : 's'} with recent positions.`, 'success', userCount);
      hideError();
    }

    // ============================
    // Fetch Locations (GET API)
    // ============================
    function refreshLocations() {
      if (isFirstLoad) setStatus('🔄 Loading latest positions...', 'info');

      const url = window.location.pathname + '?ajax=1&section=locations';

      fetch(url, {
        headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' }
      })
        .then(function(response) {
          if (!response.ok) throw new Error('HTTP ' + response.status);
          return response.json();
        })
        .then(function(data) {
          isFirstLoad = false;
          if (data.success) {
            renderPoints(data.points || []);
          } else {
            showError(data.message || 'Unable to load location data.');
            setStatus('❌ ' + (data.message || 'Unable to load locations'), 'error');
          }
        })
        .catch(function(error) {
          console.error('Location fetch error:', error);
          showError('Connection error: ' + error.message);
          setStatus('❌ Unable to load locations right now.', 'error');
        });
    }

    // ============================
    // Initialize
    // ============================
    function initialize() {
      if (typeof L === 'undefined') {
        showError('Map library failed to load. Please refresh the page.');
        setStatus('❌ Map library failed to load', 'error');
        return;
      }
      initMap();
      refreshLocations();
      if (refreshTimer) clearInterval(refreshTimer);
      refreshTimer = setInterval(refreshLocations, REFRESH_INTERVAL);
    }

    window.addEventListener('beforeunload', function() {
      if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }
    });

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initialize);
    } else {
      initialize();
    }
  </script>
</body>
</html>