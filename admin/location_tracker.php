<?php
require_once 'admin_auth.php';
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
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    #tracker-map { height: 540px; width: 100%; border-radius: 12px; border: 1px solid #d7dde7; }
    .tracker-panel { margin-top: 1rem; }
    .tracker-badge { display: inline-block; padding: .35rem .6rem; border-radius: 999px; background: #eaf7ee; color: #19723d; font-weight: 600; }
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
      </section>

      <section class="card">
        <div class="tracker-panel">
          <div class="tracker-badge">Auto-refresh every 10 seconds</div>
          <div id="tracker-map" class="mt-3"></div>
          <p id="tracker-status" class="mt-3">Loading latest positions...</p>
        </div>
      </section>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const mapEl = document.getElementById('tracker-map');
      const statusEl = document.getElementById('tracker-status');
      const sidebar = document.querySelector('.sidebar');
      const overlay = document.querySelector('.overlay');
      const toggleBtn = document.querySelector('.sidebar-toggle');
      const closeBtn = document.querySelector('.sidebar-close');

      function openSidebar() { sidebar.classList.add('open'); overlay.classList.add('show'); document.body.classList.add('sidebar-open'); }
      function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('show'); document.body.classList.remove('sidebar-open'); }

      if (toggleBtn) toggleBtn.addEventListener('click', function () { sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); });
      if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
      if (overlay) overlay.addEventListener('click', closeSidebar);

      let map;
      let markers = [];
      let polylines = [];

      function initMap() {
        map = L.map(mapEl).setView([14.5995, 120.9842], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
      }

      function clearMarkers() {
        markers.forEach(marker => marker.remove());
        markers = [];
        polylines.forEach(line => line.remove());
        polylines = [];
      }

      function colorForUser(userId) {
        // deterministic color per user id
        const palette = [ '#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', '#46f0f0', '#f032e6', '#bcf60c', '#fabebe' ];
        return palette[userId % palette.length] || '#19723d';
      }

      function renderPoints(points) {
        if (!map) return;
        clearMarkers();
        if (!points.length) {
          statusEl.textContent = 'No renter positions were shared in the last 30 minutes.';
          return;
        }

        // group by user_id
        const users = {};
        points.forEach(p => {
          const uid = p.user_id || 0;
          users[uid] = users[uid] || { full_name: p.full_name || 'Renter', points: [] };
          users[uid].points.push(p);
        });

        const allBounds = [];
        let userCount = 0;

        Object.keys(users).forEach(uid => {
          const u = users[uid];
          // sort by recorded_at ascending
          u.points.sort((a,b) => new Date(a.recorded_at) - new Date(b.recorded_at));
          const coords = u.points.map(pt => [pt.latitude, pt.longitude]);
          if (!coords.length) return;

          const color = colorForUser(parseInt(uid,10));
          const poly = L.polyline(coords, { color: color, weight: 4, opacity: 0.8 }).addTo(map);
          polylines.push(poly);

          // latest point marker
          const last = u.points[u.points.length - 1];
          const marker = L.circleMarker([last.latitude, last.longitude], { radius: 7, fillColor: color, color: '#fff', weight: 1, fillOpacity: 0.9 }).addTo(map);
          marker.bindPopup(`<strong>${u.full_name}</strong><br>${new Date(last.recorded_at).toLocaleString()}`);
          markers.push(marker);

          coords.forEach(c => allBounds.push(c));
          userCount++;
        });

        if (allBounds.length) {
          map.fitBounds(allBounds);
        }

        statusEl.textContent = `Showing ${userCount} renter${userCount === 1 ? '' : 's'} with recent positions.`;
      }

      function refreshLocations() {
        fetch('../api/location_tracker.php')
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              renderPoints(data.points || []);
            } else {
              statusEl.textContent = data.message || 'Unable to load locations.';
            }
          })
          .catch(() => {
            statusEl.textContent = 'Unable to load locations right now.';
          });
      }

      window.initTrackerMap = function () {
        initMap();
        refreshLocations();
        setInterval(refreshLocations, 10000);
      };

      if (typeof window.L !== 'undefined') {
        window.initTrackerMap();
      }
    });
  </script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</body>
</html>
