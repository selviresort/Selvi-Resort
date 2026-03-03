<?php
// ============================================================
//  admin/packages.php — Manage packages (CRUD)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$pdo = getDB();

$success = $error = '';
$editing = null;

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = (int)($_POST['id'] ?? 0);
        $name       = clean($_POST['name']       ?? '');
        $slug       = clean($_POST['slug']       ?? '');
        $icon       = clean($_POST['icon']       ?? '🎉');
        $subtitle   = clean($_POST['subtitle']   ?? '');
        $price      = (float)($_POST['price']    ?? 0);
        $priceLabel = clean($_POST['price_label']?? 'per event');
        $maxGuests  = (int)($_POST['max_guests'] ?? 0);
        $duration   = clean($_POST['duration']   ?? '');
        $featuresRaw= trim($_POST['features']    ?? '');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isActive   = isset($_POST['is_active'])   ? 1 : 0;
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);

        // Build features JSON from textarea lines
        $featArr = array_filter(array_map('trim', explode("\n", $featuresRaw)));
        $featJSON = json_encode(array_values($featArr));

        if (!$name || !$slug) { $error = 'Name and slug are required.'; }
        else {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE packages SET name=?,slug=?,icon=?,subtitle=?,price=?,price_label=?,max_guests=?,duration=?,features=?,is_featured=?,is_active=?,sort_order=? WHERE id=?");
                $stmt->execute([$name,$slug,$icon,$subtitle,$price,$priceLabel,$maxGuests,$duration,$featJSON,$isFeatured,$isActive,$sortOrder,$id]);
                $success = 'Package updated successfully.';
                logActivity("Updated package: $name", 'package', $id);
            } else {
                $stmt = $pdo->prepare("INSERT INTO packages (name,slug,icon,subtitle,price,price_label,max_guests,duration,features,is_featured,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$name,$slug,$icon,$subtitle,$price,$priceLabel,$maxGuests,$duration,$featJSON,$isFeatured,$isActive,$sortOrder]);
                $success = 'Package created successfully.';
                logActivity("Created package: $name", 'package', $pdo->lastInsertId());
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE packages SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        $success = 'Package status updated.';
    } elseif ($action === 'toggle_availability') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE packages SET is_available = NOT is_available, updated_at = NOW() WHERE id=?")->execute([$id]);
        $success = 'Package availability updated.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM packages WHERE id=?")->execute([$id]);
        $success = 'Package deleted.';
    }
}

// Load for editing
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM packages WHERE id=?"); $s->execute([(int)$_GET['edit']]); $editing = $s->fetch();
}

$packages = $pdo->query("SELECT *, COALESCE(is_available, 1) as is_available FROM packages ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Packages — Selvi Resort Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style><?php include __DIR__.'/admin_style.css.php'; ?></style>
</head>
<body>
<?php include __DIR__.'/sidebar.php'; ?>
<div class="main">
<?php include __DIR__.'/topbar.php'; ?>
<div class="content">
  <div class="page-title"><h2>Package Management</h2><p>Add, edit, or disable event packages</p></div>

  <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 380px;gap:22px;align-items:start">
    <!-- PACKAGES LIST -->
    <div class="card">
      <div class="card-header"><h3>All Packages</h3><a href="packages.php" class="btn-sm">+ New Package</a></div>
      <table class="data-table">
        <thead><tr><th>Icon</th><th>Name</th><th>Price</th><th>Guests</th><th>Featured</th><th>Active</th><th>Availability</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($packages as $p): ?>
        <tr>
          <td><?= $p['icon'] ?></td>
          <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><small style="color:#888"><?= htmlspecialchars($p['subtitle']) ?></small></td>
          <td><?= $p['price'] > 0 ? '₹'.number_format($p['price']) : 'Custom' ?></td>
          <td><?= $p['max_guests'] < 9999 ? $p['max_guests'] : 'Unlimited' ?></td>
          <td><?= $p['is_featured'] ? '⭐ Yes' : '—' ?></td>
          <td><span class="badge" style="background:<?= $p['is_active'] ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)' ?>;color:<?= $p['is_active'] ? '#16a34a' : '#dc2626' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td>
            <?php $avail = (int)($p['is_available'] ?? 1); ?>
            <button
              class="toggle-avail-btn <?= $avail ? 'is-available' : 'is-full' ?>"
              id="avail-btn-<?= $p['id'] ?>"
              data-id="<?= $p['id'] ?>"
              data-available="<?= $avail ?>"
              onclick="toggleAvailability(this)"
            >
              <span class="btn-spinner"></span>
              <span class="btn-label"><?= $avail ? '✅ Available' : '🚫 Full' ?></span>
            </button>
          </td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="?edit=<?= $p['id'] ?>" class="btn-xs">Edit</a>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button type="submit" class="btn-xs"><?= $p['is_active'] ? 'Disable' : 'Enable' ?></button></form>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button type="submit" class="btn-danger" onclick="return confirm('Delete this package?')">Del</button></form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ADD/EDIT FORM -->
    <div class="card">
      <div class="card-header"><h3><?= $editing ? 'Edit Package' : 'Add New Package' ?></h3></div>
      <form method="POST" style="padding:20px">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
        <div class="form-grid">
          <div class="form-group"><label>Icon (emoji)</label><input type="text" name="icon" value="<?= htmlspecialchars($editing['icon'] ?? '🎉') ?>" maxlength="5"></div>
          <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="<?= $editing['sort_order'] ?? 0 ?>"></div>
          <div class="form-group full"><label>Package Name *</label><input type="text" name="name" value="<?= htmlspecialchars($editing['name'] ?? '') ?>" required></div>
          <div class="form-group full"><label>URL Slug *</label><input type="text" name="slug" value="<?= htmlspecialchars($editing['slug'] ?? '') ?>" placeholder="gold-package" required></div>
          <div class="form-group full"><label>Subtitle</label><input type="text" name="subtitle" value="<?= htmlspecialchars($editing['subtitle'] ?? '') ?>" placeholder="Grand Celebrations"></div>
          <div class="form-group"><label>Price (₹)</label><input type="number" name="price" value="<?= $editing['price'] ?? 0 ?>" min="0" step="100"></div>
          <div class="form-group"><label>Price Label</label><input type="text" name="price_label" value="<?= htmlspecialchars($editing['price_label'] ?? 'per event') ?>"></div>
          <div class="form-group"><label>Max Guests</label><input type="number" name="max_guests" value="<?= $editing['max_guests'] ?? 100 ?>"></div>
          <div class="form-group"><label>Duration</label><input type="text" name="duration" value="<?= htmlspecialchars($editing['duration'] ?? '') ?>" placeholder="6 hours"></div>
          <div class="form-group full">
            <label>Features (one per line)</label>
            <textarea name="features" rows="7" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"><?php
              if ($editing) {
                $feats = json_decode($editing['features'], true) ?? [];
                echo htmlspecialchars(implode("\n", $feats));
              }
            ?></textarea>
          </div>
          <div class="form-group"><label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:.86rem;cursor:pointer"><input type="checkbox" name="is_featured" <?= ($editing['is_featured'] ?? 0) ? 'checked' : '' ?>> Mark as Featured</label></div>
          <div class="form-group"><label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:.86rem;cursor:pointer"><input type="checkbox" name="is_active" <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>> Active / Visible</label></div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $editing ? 'Update Package' : 'Create Package' ?></button>
          <?php if($editing): ?><a href="packages.php" class="btn-xs">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
<style>
/* ── Availability Toggle Button ─────────────────────────── */
.toggle-avail-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 14px;
    border: none;
    border-radius: 3px;
    font-family: 'Jost', sans-serif;
    font-size: .68rem;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all .25s;
    white-space: nowrap;
    min-width: 110px;
    justify-content: center;
}
.toggle-avail-btn.is-available {
    background: rgba(34,197,94,.12);
    color: #16a34a;
    border: 1px solid rgba(34,197,94,.3);
}
.toggle-avail-btn.is-available:hover {
    background: rgba(239,68,68,.12);
    color: #dc2626;
    border-color: rgba(239,68,68,.3);
}
.toggle-avail-btn.is-full {
    background: rgba(239,68,68,.12);
    color: #dc2626;
    border: 1px solid rgba(239,68,68,.3);
}
.toggle-avail-btn.is-full:hover {
    background: rgba(34,197,94,.12);
    color: #16a34a;
    border-color: rgba(34,197,94,.3);
}
.toggle-avail-btn:disabled { opacity: .5; cursor: not-allowed; }
.btn-spinner {
    display: none;
    width: 9px;
    height: 9px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: avail-spin .6s linear infinite;
}
.toggle-avail-btn.loading .btn-spinner { display: inline-block; }
.toggle-avail-btn.loading .btn-label   { display: none; }
@keyframes avail-spin { to { transform: rotate(360deg); } }

/* Toast */
#pkg-toast {
    position: fixed;
    bottom: 28px;
    right: 28px;
    padding: 12px 22px;
    border-radius: 3px;
    font-family: 'Jost', sans-serif;
    font-size: .82rem;
    font-weight: 500;
    color: #fff;
    z-index: 9999;
    opacity: 0;
    transform: translateY(10px);
    transition: all .3s;
    pointer-events: none;
}
#pkg-toast.show { opacity: 1; transform: translateY(0); }
#pkg-toast.success { background: #16a34a; }
#pkg-toast.error   { background: #dc2626; }
</style>

<div id="pkg-toast"></div>

<script>
function toggleAvailability(btn) {
    const id        = btn.dataset.id;
    const current   = parseInt(btn.dataset.available);
    const newVal    = current === 1 ? 0 : 1;

    btn.classList.add('loading');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('id',           id);
    fd.append('is_available', newVal);

    fetch('toggle_package.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.dataset.available = newVal;
            if (newVal === 1) {
                btn.className = 'toggle-avail-btn is-available';
                btn.querySelector('.btn-label').textContent = '✅ Available';
                showPkgToast('Package marked as Available — frontend updated!', 'success');
            } else {
                btn.className = 'toggle-avail-btn is-full';
                btn.querySelector('.btn-label').textContent = '🚫 Full';
                showPkgToast('Package marked as Full — frontend updated!', 'success');
            }
        } else {
            showPkgToast(data.message || 'Update failed.', 'error');
        }
    })
    .catch(() => showPkgToast('Network error. Try again.', 'error'))
    .finally(() => {
        btn.classList.remove('loading');
        btn.disabled = false;
    });
}

function showPkgToast(msg, type) {
    const t = document.getElementById('pkg-toast');
    t.textContent = msg;
    t.className = 'show ' + type;
    setTimeout(() => { t.className = ''; }, 3000);
}
</script>
</body></html>
