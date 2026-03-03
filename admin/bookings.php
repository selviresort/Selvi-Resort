<?php
// ============================================================
//  admin/bookings.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$pdo = getDB();

// Filters
$status  = $_GET['status']  ?? '';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($status)  { $where[] = "status = ?"; $params[] = $status; }
if ($search)  { $where[] = "(full_name LIKE ? OR phone LIKE ? OR booking_ref LIKE ? OR event_type LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s,$s]); }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$totalRows = $pdo->prepare("SELECT COUNT(*) FROM bookings $whereSQL"); $totalRows->execute($params); $total = $totalRows->fetchColumn();
$pages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT b.*, p.name as pkg_display FROM bookings b LEFT JOIN packages p ON b.package_id = p.id $whereSQL ORDER BY b.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$statuses = ['new','contacted','confirmed','completed','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bookings — Selvi Resort Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style><?php include __DIR__.'/admin_style.css.php'; ?></style>
</head>
<body>
<?php include __DIR__.'/sidebar.php'; ?>
<div class="main">
<?php include __DIR__.'/topbar.php'; ?>
<div class="content">
  <div class="page-title"><h2>Booking Enquiries</h2><p>Total: <?= $total ?> bookings</p></div>

  <!-- FILTERS -->
  <div class="card" style="padding:16px 20px;margin-bottom:20px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;flex:1">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, phone, ref..." style="border:1px solid rgba(201,169,110,.28);padding:8px 12px;font-family:Jost,sans-serif;font-size:.82rem;outline:none;min-width:200px">
      <select name="status" style="border:1px solid rgba(201,169,110,.28);padding:8px 12px;font-family:Jost,sans-serif;font-size:.82rem;outline:none">
        <option value="">All Statuses</option>
        <?php foreach($statuses as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select>
      <button type="submit" class="btn-sm">Filter</button>
      <?php if($search||$status): ?><a href="bookings.php" class="btn-xs">Clear</a><?php endif; ?>
    </form>
    <a href="bookings.php?export=csv&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" class="btn-xs">📥 Export CSV</a>
  </div>

  <div class="card">
    <table class="data-table">
      <thead><tr><th>Ref</th><th>Name</th><th>Phone</th><th>Event Type</th><th>Package</th><th>Check-In</th><th>Check-Out</th><th>Nights</th><th>Guests</th><th>Status</th><th>Received</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if(empty($bookings)): ?>
        <tr><td colspan="13" style="text-align:center;padding:30px;color:#888">No bookings found.</td></tr>
      <?php else: ?>
      <?php foreach($bookings as $b): ?>
        <tr>
          <td><code><?= htmlspecialchars($b['booking_ref']) ?></code></td>
          <td><?= htmlspecialchars($b['full_name']) ?></td>
          <td><a href="tel:<?= htmlspecialchars($b['phone']) ?>" style="color:var(--gold)"><?= htmlspecialchars($b['phone']) ?></a></td>
          <td><?= htmlspecialchars($b['event_type']) ?></td>
          <td><?= htmlspecialchars($b['package_name'] ?: '—') ?></td>
          <td style="color:#16a34a;font-weight:600;white-space:nowrap"><?= $b['event_date'] ? date('d M Y', strtotime($b['event_date'])) . '<br><small style="font-weight:400">1:00 PM</small>' : '—' ?></td>
          <td style="color:#dc2626;font-weight:600;white-space:nowrap"><?= !empty($b['checkout_date']) ? date('d M Y', strtotime($b['checkout_date'])) . '<br><small style="font-weight:400">10:00 AM</small>' : ($b['event_date'] ? date('d M Y', strtotime($b['event_date'].' +1 day')) . '<br><small style="font-weight:400">10:00 AM</small>' : '—') ?></td>
          <td><?php if($b['event_date']){ $n=(new DateTime($b['event_date']))->diff(new DateTime(!empty($b['checkout_date'])?$b['checkout_date']:date('Y-m-d',strtotime($b['event_date'].' +1 day'))))->days; echo $n.' night'.($n!=1?'s':''); } else echo '—'; ?></td>
          <td><?= htmlspecialchars($b['guest_count'] ?: '—') ?></td>
          <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
          <td><?= date('d M Y', strtotime($b['created_at'])) ?></td>
          <td style="display:flex;gap:6px">
            <a href="booking_view.php?id=<?= $b['id'] ?>" class="btn-xs">View</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <!-- PAGINATION -->
    <?php if($pages > 1): ?>
    <div style="padding:16px 20px;display:flex;gap:8px;align-items:center;border-top:1px solid rgba(201,169,110,.1)">
      <?php for($i=1;$i<=$pages;$i++): ?>
        <a href="?page=<?=$i?>&status=<?=urlencode($status)?>&search=<?=urlencode($search)?>" style="padding:5px 11px;border:1px solid rgba(201,169,110,.<?=$i==$page?'5':'2'?>);color:<?=$i==$page?'var(--gold)':'#888'?>;text-decoration:none;font-size:.75rem;background:<?=$i==$page?'rgba(201,169,110,.08)':'transparent'?>"><?=$i?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>

<?php
// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt2 = $pdo->prepare("SELECT booking_ref,full_name,phone,email,event_type,package_name,event_date,guest_count,time_slot,addon_service,heard_from,special_request,status,created_at FROM bookings $whereSQL ORDER BY created_at DESC");
    $stmt2->execute($params);
    $rows = $stmt2->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="selvi_bookings_'.date('Ymd').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['Ref','Name','Phone','Email','Event Type','Package','Event Date','Guests','Time Slot','Add-On','Heard From','Special Request','Status','Received']);
    foreach($rows as $r) fputcsv($out,array_values($r));
    fclose($out);
    exit;
}
?>
</body></html>
