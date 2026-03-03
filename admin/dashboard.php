<?php
// ============================================================
//  admin/dashboard.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$admin = getCurrentAdmin();
$pdo   = getDB();

// Stats
$totalBookings    = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$newBookings      = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn();
$confirmedBookings= $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed'")->fetchColumn();
$totalMessages    = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$unreadMessages   = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='unread'")->fetchColumn();
$thisMonthBookings= $pdo->query("SELECT COUNT(*) FROM bookings WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

// Recent bookings
$recentBookings = $pdo->query("SELECT * FROM bookings ORDER BY created_at DESC LIMIT 8")->fetchAll();

// Recent messages
$recentMessages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Selvi Resort Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
<?php include __DIR__ . '/admin_style.css.php'; ?>
</style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <?php include __DIR__ . '/topbar.php'; ?>
  <div class="content">
    <div class="page-title">
      <h2>Dashboard</h2>
      <p>Welcome back, <?= htmlspecialchars($admin['full_name']) ?>!</p>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="sc-icon" style="background:rgba(201,169,110,.12)">📋</div>
        <div class="sc-info"><div class="sc-num"><?= $totalBookings ?></div><div class="sc-label">Total Bookings</div></div>
      </div>
      <div class="stat-card">
        <div class="sc-icon" style="background:rgba(239,68,68,.1)">🔔</div>
        <div class="sc-info"><div class="sc-num" style="color:#ef4444"><?= $newBookings ?></div><div class="sc-label">New Enquiries</div></div>
      </div>
      <div class="stat-card">
        <div class="sc-icon" style="background:rgba(34,197,94,.1)">✅</div>
        <div class="sc-info"><div class="sc-num" style="color:#22c55e"><?= $confirmedBookings ?></div><div class="sc-label">Confirmed</div></div>
      </div>
      <div class="stat-card">
        <div class="sc-icon" style="background:rgba(59,130,246,.1)">📅</div>
        <div class="sc-info"><div class="sc-num" style="color:#3b82f6"><?= $thisMonthBookings ?></div><div class="sc-label">This Month</div></div>
      </div>
      <div class="stat-card">
        <div class="sc-icon" style="background:rgba(168,85,247,.1)">✉️</div>
        <div class="sc-info"><div class="sc-num" style="color:#a855f7"><?= $unreadMessages ?></div><div class="sc-label">Unread Messages</div></div>
      </div>
    </div>

    <div class="two-col">
      <!-- RECENT BOOKINGS -->
      <div class="card">
        <div class="card-header">
          <h3>Recent Booking Enquiries</h3>
          <a href="bookings.php" class="btn-sm">View All</a>
        </div>
        <table class="data-table">
          <thead><tr><th>Ref</th><th>Name</th><th>Event</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach ($recentBookings as $b): ?>
            <tr>
              <td><code><?= htmlspecialchars($b['booking_ref']) ?></code></td>
              <td><?= htmlspecialchars($b['full_name']) ?></td>
              <td><?= htmlspecialchars($b['event_type']) ?></td>
              <td><?= $b['event_date'] ? date('d M Y', strtotime($b['event_date'])) : '—' ?></td>
              <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
              <td><a href="booking_view.php?id=<?= $b['id'] ?>" class="btn-xs">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- RECENT MESSAGES -->
      <div class="card">
        <div class="card-header">
          <h3>Recent Messages</h3>
          <a href="messages.php" class="btn-sm">View All</a>
        </div>
        <?php foreach ($recentMessages as $m): ?>
        <div class="msg-item <?= $m['status'] === 'unread' ? 'unread' : '' ?>">
          <div class="msg-name"><?= htmlspecialchars($m['full_name']) ?> <?php if($m['status']==='unread'): ?><span class="dot"></span><?php endif; ?></div>
          <div class="msg-sub"><?= htmlspecialchars($m['subject'] ?: 'No subject') ?></div>
          <div class="msg-preview"><?= htmlspecialchars(substr($m['message'], 0, 80)) ?>…</div>
          <div class="msg-date"><?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>