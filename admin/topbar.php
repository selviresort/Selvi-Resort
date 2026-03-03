<?php
// admin/topbar.php
$admin = getCurrentAdmin();
$titles = [
    'dashboard.php'    => 'Dashboard',
    'bookings.php'     => 'Booking Enquiries',
    'booking_view.php' => 'Booking Details',
    'messages.php'     => 'Contact Messages',
    'packages.php'     => 'Package Management',
    'settings.php'     => 'Site Settings',
];
$current = basename($_SERVER['PHP_SELF']);
$title   = $titles[$current] ?? 'Admin Panel';
?>
<div class="topbar">
  <h3><?= $title ?></h3>
  <div class="topbar-right">
    <span class="topbar-user">Logged in as <strong><?= htmlspecialchars($admin['full_name']) ?></strong></span>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</div>
