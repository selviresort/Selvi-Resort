<?php
// admin/sidebar.php
$pdo = getDB();
$newCount     = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn();
$unreadCount  = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='unread'")->fetchColumn();
$current      = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <h2>Selvi Resort</h2>
    <p>Admin Panel</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php" class="<?= $current==='dashboard.php'?'active':'' ?>">
      <span class="nav-icon">🏠</span> Dashboard
    </a>

    <div class="nav-section">Bookings</div>
    <a href="bookings.php" class="<?= $current==='bookings.php'?'active':'' ?>">
      <span class="nav-icon">📋</span> All Bookings
      <?php if($newCount>0): ?><span class="badge-count"><?= $newCount ?></span><?php endif; ?>
    </a>

    <div class="nav-section">Messages</div>
    <a href="messages.php" class="<?= $current==='messages.php'?'active':'' ?>">
      <span class="nav-icon">✉️</span> Contact Messages
      <?php if($unreadCount>0): ?><span class="badge-count"><?= $unreadCount ?></span><?php endif; ?>
    </a>

    <div class="nav-section">Manage</div>
    <a href="packages.php" class="<?= $current==='packages.php'?'active':'' ?>">
      <span class="nav-icon">📦</span> Packages
    </a>
    <a href="settings.php" class="<?= $current==='settings.php'?'active':'' ?>">
      <span class="nav-icon">⚙️</span> Settings
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php">🚪 Logout</a>
  </div>
</aside>
