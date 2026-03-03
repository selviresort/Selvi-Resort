<?php
// ============================================================
//  admin/booking_view.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$pdo = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: bookings.php'); exit; }

$booking = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
$booking->execute([$id]);
$b = $booking->fetch();
if (!$b) { header('Location: bookings.php'); exit; }

$success = $error = '';

// Handle status/notes update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? $b['status'];
    $notes     = clean($_POST['admin_notes'] ?? '');
    $valid = ['new','contacted','confirmed','completed','cancelled'];
    if (in_array($newStatus, $valid)) {
        $stmt = $pdo->prepare("UPDATE bookings SET status=?, admin_notes=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$newStatus, $notes, $id]);
        logActivity("Updated booking #{$b['booking_ref']} to status: $newStatus", 'booking', $id);
        $success = 'Booking updated successfully.';
        $b['status']       = $newStatus;
        $b['admin_notes']  = $notes;
    } else {
        $error = 'Invalid status.';
    }
}

$statuses = ['new','contacted','confirmed','completed','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Booking <?= htmlspecialchars($b['booking_ref']) ?> — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style><?php include __DIR__.'/admin_style.css.php'; ?></style>
</head>
<body>
<?php include __DIR__.'/sidebar.php'; ?>
<div class="main">
<?php include __DIR__.'/topbar.php'; ?>
<div class="content">
  <div style="display:flex;align-items:center;gap:14px;margin-bottom:22px">
    <a href="bookings.php" style="color:var(--gold);text-decoration:none;font-size:.78rem;letter-spacing:1px">← Back to Bookings</a>
    <div class="page-title" style="margin:0">
      <h2>Booking <?= htmlspecialchars($b['booking_ref']) ?></h2>
      <p>Received: <?= date('d M Y, h:i A', strtotime($b['created_at'])) ?></p>
    </div>
    <span class="badge badge-<?= $b['status'] ?>" style="margin-left:auto;font-size:.78rem;padding:5px 14px"><?= ucfirst($b['status']) ?></span>
  </div>

  <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:22px">
    <!-- DETAILS -->
    <div>
      <div class="card">
        <div class="card-header"><h3>👤 Personal Information</h3></div>
        <div class="detail-grid">
          <div class="detail-item"><div class="detail-label">Full Name</div><div class="detail-value"><?= htmlspecialchars($b['full_name']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value"><a href="tel:<?= htmlspecialchars($b['phone']) ?>" style="color:var(--gold)"><?= htmlspecialchars($b['phone']) ?></a></div></div>
          <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value"><?= $b['email'] ? '<a href="mailto:'.htmlspecialchars($b['email']).'" style="color:var(--gold)">'.htmlspecialchars($b['email']).'</a>' : '—' ?></div></div>
          <div class="detail-item"><div class="detail-label">WhatsApp</div><div class="detail-value"><?= htmlspecialchars($b['whatsapp'] ?: '—') ?></div></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>🎉 Event Information</h3></div>
        <div class="detail-grid">
          <div class="detail-item"><div class="detail-label">Event Type</div><div class="detail-value"><?= htmlspecialchars($b['event_type']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Package</div><div class="detail-value"><?= htmlspecialchars($b['package_name'] ?: '—') ?></div></div>
          <div class="detail-item"><div class="detail-label">🕐 Check-In</div><div class="detail-value" style="color:#16a34a;font-weight:600"><?= $b['event_date'] ? date('d M Y', strtotime($b['event_date'])) . ' — 1:00 PM' : '—' ?></div></div>
          <div class="detail-item"><div class="detail-label">🕙 Check-Out</div><div class="detail-value" style="color:#dc2626;font-weight:600"><?= !empty($b['checkout_date']) ? date('d M Y', strtotime($b['checkout_date'])) . ' — 10:00 AM' : ($b['event_date'] ? date('d M Y', strtotime($b['event_date'] . ' +1 day')) . ' — 10:00 AM' : '—') ?></div></div>
          <div class="detail-item"><div class="detail-label">📆 Duration</div><div class="detail-value"><?php if($b['event_date']){ $nights = (new DateTime($b['event_date']))->diff(new DateTime(!empty($b['checkout_date']) ? $b['checkout_date'] : date('Y-m-d', strtotime($b['event_date'].' +1 day'))))->days; echo $nights . ' night' . ($nights != 1 ? 's' : ''); } else { echo '—'; } ?></div></div>
          <div class="detail-item"><div class="detail-label">Alternate Date</div><div class="detail-value"><?= $b['alt_date'] ? date('d M Y', strtotime($b['alt_date'])) : '—' ?></div></div>
          
          <div class="detail-item"><div class="detail-label">Expected Guests</div><div class="detail-value"><?= htmlspecialchars($b['guest_count'] ?: '—') ?></div></div>
          <div class="detail-item"><div class="detail-label">Add-On Service</div><div class="detail-value"><?= htmlspecialchars($b['addon_service'] ?: 'None') ?></div></div>
          <div class="detail-item"><div class="detail-label">Heard From</div><div class="detail-value"><?= htmlspecialchars($b['heard_from'] ?: '—') ?></div></div>
        </div>
        <?php if($b['special_request']): ?>
        <div style="padding:14px 20px;border-top:1px solid rgba(201,169,110,.1)">
          <div class="detail-label">Special Requirements</div>
          <div style="font-size:.88rem;color:#444;margin-top:6px;line-height:1.7"><?= nl2br(htmlspecialchars($b['special_request'])) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- STATUS UPDATE PANEL -->
    <div>
      <div class="card">
        <div class="card-header"><h3>Update Status</h3></div>
        <form method="POST">
          <div class="status-form">
            <div class="form-group" style="margin-bottom:14px">
              <label>Status</label>
              <select name="status">
                <?php foreach($statuses as $s): ?>
                  <option value="<?= $s ?>" <?= $b['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Admin Notes</label>
              <textarea name="admin_notes" rows="5" placeholder="Internal notes about this booking..."><?= htmlspecialchars($b['admin_notes'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary">Save Changes</button>
          </div>
        </form>
      </div>

      <div class="card">
        <div class="card-header"><h3>Quick Actions</h3></div>
        <div style="padding:16px 20px;display:flex;flex-direction:column;gap:10px">
          <a href="tel:<?= htmlspecialchars($b['phone']) ?>" class="btn-sm" style="text-align:center">📞 Call Customer</a>
          <?php if($b['email']): ?><a href="mailto:<?= htmlspecialchars($b['email']) ?>" class="btn-sm" style="text-align:center;background:transparent;border:1px solid var(--gold);color:var(--gold)">✉️ Send Email</a><?php endif; ?>
          <?php if($b['whatsapp']): ?><a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$b['whatsapp']) ?>" target="_blank" class="btn-sm" style="text-align:center;background:#25d366;color:#fff">💬 WhatsApp</a><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
</body></html>
