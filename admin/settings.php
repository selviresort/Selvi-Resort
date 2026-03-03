<?php
// ============================================================
//  admin/settings.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$pdo     = getDB();
$success = $error = '';
$admin   = getCurrentAdmin();

// Handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'site_settings') {
        $fields = ['site_name','site_phone1','site_phone2','site_email','site_address','site_whatsapp','google_maps_url','booking_email'];
        foreach ($fields as $f) {
            $val = clean($_POST[$f] ?? '');
            $pdo->prepare("INSERT INTO settings (setting_key, setting_val) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_val=?")->execute([$f,$val,$val]);
        }
        $success = 'Site settings saved.';
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) { $error = 'New password must be at least 8 characters.'; }
        elseif ($new !== $confirm) { $error = 'Passwords do not match.'; }
        else {
            $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id=?"); $stmt->execute([$admin['id']]); $row = $stmt->fetch();
            if (password_verify($current, $row['password'])) {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE admin_users SET password=? WHERE id=?")->execute([$hash, $admin['id']]);
                $success = 'Password changed successfully.';
                logActivity('Changed admin password');
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }
}

// Load settings
$settingsRow = $pdo->query("SELECT setting_key, setting_val FROM settings")->fetchAll();
$settings = [];
foreach ($settingsRow as $r) $settings[$r['setting_key']] = $r['setting_val'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings — Selvi Resort Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style><?php include __DIR__.'/admin_style.css.php'; ?></style>
</head>
<body>
<?php include __DIR__.'/sidebar.php'; ?>
<div class="main">
<?php include __DIR__.'/topbar.php'; ?>
<div class="content">
  <div class="page-title"><h2>Settings</h2><p>Manage site details and admin account</p></div>

  <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px">

    <!-- SITE SETTINGS -->
    <div class="card">
      <div class="card-header"><h3>⚙️ Site Information</h3></div>
      <form method="POST" style="padding:20px">
        <input type="hidden" name="action" value="site_settings">
        <div class="form-grid">
          <div class="form-group full"><label>Site Name</label><input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>"></div>
          <div class="form-group"><label>Phone 1</label><input type="text" name="site_phone1" value="<?= htmlspecialchars($settings['site_phone1'] ?? '') ?>"></div>
          <div class="form-group"><label>Phone 2</label><input type="text" name="site_phone2" value="<?= htmlspecialchars($settings['site_phone2'] ?? '') ?>"></div>
          <div class="form-group"><label>Email</label><input type="email" name="site_email" value="<?= htmlspecialchars($settings['site_email'] ?? '') ?>"></div>
          <div class="form-group"><label>Booking Email</label><input type="email" name="booking_email" value="<?= htmlspecialchars($settings['booking_email'] ?? '') ?>"></div>
          <div class="form-group"><label>WhatsApp Number</label><input type="text" name="site_whatsapp" value="<?= htmlspecialchars($settings['site_whatsapp'] ?? '') ?>" placeholder="919876543210"></div>
          <div class="form-group"><label>Google Maps URL</label><input type="url" name="google_maps_url" value="<?= htmlspecialchars($settings['google_maps_url'] ?? '') ?>"></div>
          <div class="form-group full"><label>Full Address</label><textarea name="site_address" rows="2"><?= htmlspecialchars($settings['site_address'] ?? '') ?></textarea></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn-primary">Save Settings</button></div>
      </form>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="card">
      <div class="card-header"><h3>🔒 Change Password</h3></div>
      <form method="POST" style="padding:20px">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group" style="margin-bottom:16px"><label>Current Password</label><input type="password" name="current_password" placeholder="Enter current password"></div>
        <div class="form-group" style="margin-bottom:16px"><label>New Password</label><input type="password" name="new_password" placeholder="Min 8 characters"></div>
        <div class="form-group" style="margin-bottom:20px"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Repeat new password"></div>
        <div class="form-actions"><button type="submit" class="btn-primary">Update Password</button></div>
      </form>

      <div style="padding:20px;border-top:1px solid rgba(201,169,110,.1)">
        <h4 style="font-family:'Cormorant Garamond',serif;color:var(--dark);font-size:1rem;margin-bottom:12px">👤 Account Info</h4>
        <div style="font-size:.85rem;color:#555;line-height:2">
          <strong>Name:</strong> <?= htmlspecialchars($admin['full_name']) ?><br>
          <strong>Username:</strong> <?= htmlspecialchars($admin['username']) ?><br>
          <strong>Email:</strong> <?= htmlspecialchars($admin['email']) ?><br>
          <strong>Role:</strong> <?= ucfirst($admin['role']) ?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
</body></html>
