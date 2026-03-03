<?php
// ============================================================
//  admin/messages.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$pdo = getDB();

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['search'] ?? '');
$view   = (int)($_GET['view'] ?? 0);

// Handle reply / mark read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $view) {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $pdo->prepare("UPDATE contact_messages SET status='read' WHERE id=?")->execute([$view]);
    } elseif ($action === 'reply') {
        $reply = clean($_POST['reply'] ?? '');
        if ($reply) $pdo->prepare("UPDATE contact_messages SET status='replied', admin_reply=? WHERE id=?")->execute([$reply, $view]);
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$view]);
        header('Location: messages.php'); exit;
    }
}

// Single message view
$msg = null;
if ($view) {
    $s = $pdo->prepare("SELECT * FROM contact_messages WHERE id=?"); $s->execute([$view]); $msg = $s->fetch();
    if ($msg && $msg['status'] === 'unread') {
        $pdo->prepare("UPDATE contact_messages SET status='read' WHERE id=?")->execute([$view]);
        $msg['status'] = 'read';
    }
}

// List
$where = []; $params = [];
if ($filter)  { $where[] = "status=?"; $params[] = $filter; }
if ($search)  { $where[] = "(full_name LIKE ? OR email LIKE ? OR subject LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';
$messages = $pdo->prepare("SELECT * FROM contact_messages $whereSQL ORDER BY created_at DESC LIMIT 50");
$messages->execute($params);
$messages = $messages->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Messages — Selvi Resort Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
<?php include __DIR__.'/admin_style.css.php'; ?>
.inbox-layout{display:grid;grid-template-columns:320px 1fr;gap:0;border:1px solid rgba(201,169,110,.14);background:#fff}
.inbox-list{border-right:1px solid rgba(201,169,110,.12);max-height:80vh;overflow-y:auto}
.inbox-item{padding:14px 18px;border-bottom:1px solid rgba(201,169,110,.08);cursor:pointer;transition:background .2s;text-decoration:none;display:block}
.inbox-item:hover,.inbox-item.active{background:#fdfaf5}
.inbox-item.unread{background:#fffbf3}
.inbox-item .in-name{font-weight:600;font-size:.86rem;color:var(--dark);display:flex;justify-content:space-between}
.inbox-item .in-date{font-size:.7rem;color:#bbb}
.inbox-item .in-sub{font-size:.78rem;color:var(--gold);margin-top:3px}
.inbox-item .in-prev{font-size:.78rem;color:#888;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.msg-detail{padding:26px;overflow-y:auto;max-height:80vh}
.msg-meta{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:22px;padding-bottom:16px;border-bottom:1px solid rgba(201,169,110,.12)}
.msg-meta div{font-size:.82rem;color:#555}
.msg-meta div strong{color:var(--dark);display:block;font-size:.68rem;letter-spacing:2px;text-transform:uppercase;margin-bottom:3px;color:var(--gold)}
.msg-body{font-size:.9rem;color:#333;line-height:1.85;background:#fdfaf5;padding:18px;border:1px solid rgba(201,169,110,.1);margin-bottom:22px}
.reply-box{background:#fdfaf5;border:1px solid rgba(201,169,110,.18);padding:18px}
.reply-box h4{font-family:'Cormorant Garamond',serif;font-size:1rem;color:var(--dark);margin-bottom:13px}
.empty-state{display:flex;align-items:center;justify-content:center;height:300px;color:#bbb;font-size:.88rem;font-style:italic}
</style>
</head>
<body>
<?php include __DIR__.'/sidebar.php'; ?>
<div class="main">
<?php include __DIR__.'/topbar.php'; ?>
<div class="content">
  <div class="page-title"><h2>Contact Messages</h2></div>

  <!-- Filter bar -->
  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search messages..." style="border:1px solid rgba(201,169,110,.28);padding:8px 12px;font-family:Jost,sans-serif;font-size:.82rem;outline:none">
    <select name="filter" style="border:1px solid rgba(201,169,110,.28);padding:8px 12px;font-family:Jost,sans-serif;font-size:.82rem;outline:none">
      <option value="">All Messages</option>
      <option value="unread" <?= $filter==='unread'?'selected':'' ?>>Unread</option>
      <option value="read"   <?= $filter==='read'?'selected':'' ?>>Read</option>
      <option value="replied"<?= $filter==='replied'?'selected':'' ?>>Replied</option>
    </select>
    <button type="submit" class="btn-sm">Filter</button>
    <?php if($search||$filter): ?><a href="messages.php" class="btn-xs">Clear</a><?php endif; ?>
  </form>

  <div class="inbox-layout">
    <!-- LIST -->
    <div class="inbox-list">
      <?php if(empty($messages)): ?>
        <div class="empty-state">No messages found.</div>
      <?php else: ?>
        <?php foreach($messages as $m): ?>
          <a href="?view=<?= $m['id'] ?><?= $filter?"&filter=$filter":'' ?>" class="inbox-item <?= $m['status']==='unread'?'unread':'' ?> <?= $view===$m['id']?'active':'' ?>">
            <div class="in-name"><?= htmlspecialchars($m['full_name']) ?><span class="in-date"><?= date('d M', strtotime($m['created_at'])) ?></span></div>
            <div class="in-sub"><?= htmlspecialchars($m['subject'] ?: 'No subject') ?></div>
            <div class="in-prev"><?= htmlspecialchars(substr($m['message'],0,55)) ?>…</div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- DETAIL -->
    <div class="msg-detail">
      <?php if(!$msg): ?>
        <div class="empty-state">Select a message to read it.</div>
      <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:10px">
          <div>
            <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--dark);font-weight:400"><?= htmlspecialchars($msg['subject'] ?: 'No subject') ?></h3>
          </div>
          <div style="display:flex;gap:8px">
            <span class="badge badge-<?= $msg['status'] ?>"><?= ucfirst($msg['status']) ?></span>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn-danger" onclick="return confirm('Delete this message?')">Delete</button>
            </form>
          </div>
        </div>
        <div class="msg-meta">
          <div><strong>From</strong><?= htmlspecialchars($msg['full_name']) ?></div>
          <?php if($msg['email']): ?><div><strong>Email</strong><a href="mailto:<?= htmlspecialchars($msg['email']) ?>" style="color:var(--gold)"><?= htmlspecialchars($msg['email']) ?></a></div><?php endif; ?>
          <?php if($msg['phone']): ?><div><strong>Phone</strong><a href="tel:<?= htmlspecialchars($msg['phone']) ?>" style="color:var(--gold)"><?= htmlspecialchars($msg['phone']) ?></a></div><?php endif; ?>
          <div><strong>Received</strong><?= date('d M Y, h:i A', strtotime($msg['created_at'])) ?></div>
        </div>
        <div class="msg-body"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>

        <?php if($msg['admin_reply']): ?>
          <div style="background:rgba(201,169,110,.06);border:1px solid rgba(201,169,110,.18);padding:14px;margin-bottom:16px">
            <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:8px">Your Reply</div>
            <div style="font-size:.88rem;color:#444;line-height:1.75"><?= nl2br(htmlspecialchars($msg['admin_reply'])) ?></div>
          </div>
        <?php endif; ?>

        <div class="reply-box">
          <h4>Write a Reply Note</h4>
          <form method="POST">
            <input type="hidden" name="action" value="reply">
            <div class="form-group" style="margin-bottom:12px">
              <textarea name="reply" rows="4" placeholder="Write your reply or internal note..."><?= htmlspecialchars($msg['admin_reply'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn-primary">Save Reply</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
</body></html>
