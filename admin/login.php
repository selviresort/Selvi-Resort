<?php
// ============================================================
//  admin/login.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
startSession();

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION[ADMIN_SESSION_KEY] = true;
            $_SESSION[ADMIN_USER_KEY]    = [
                'id'        => $admin['id'],
                'username'  => $admin['username'],
                'full_name' => $admin['full_name'],
                'role'      => $admin['role'],
                'email'     => $admin['email'],
            ];
            // Update last login
            $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            logActivity('Admin logged in');
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Selvi Resort</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--gold:#c9a96e;--dark:#1a1208;--dark2:#2c1f0e}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--dark);min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Jost',sans-serif}
.login-box{background:rgba(255,255,255,.04);border:1px solid rgba(201,169,110,.2);padding:50px 45px;width:420px;max-width:95vw}
.login-logo{text-align:center;margin-bottom:35px}
.login-logo h1{font-family:'Cormorant Garamond',serif;color:var(--gold);font-size:1.9rem;font-weight:400}
.login-logo p{color:rgba(255,255,255,.35);font-size:.72rem;letter-spacing:4px;text-transform:uppercase;margin-top:5px}
.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:7px;font-weight:500}
.form-group input{width:100%;background:rgba(255,255,255,.05);border:1px solid rgba(201,169,110,.22);color:#fff;padding:13px 15px;font-family:'Jost',sans-serif;font-size:.9rem;outline:none;transition:border .3s}
.form-group input:focus{border-color:var(--gold);background:rgba(201,169,110,.06)}
.form-group input::placeholder{color:rgba(255,255,255,.25)}
.btn-login{width:100%;padding:14px;background:var(--gold);color:var(--dark);font-family:'Jost',sans-serif;font-size:.82rem;letter-spacing:4px;text-transform:uppercase;font-weight:700;border:none;cursor:pointer;transition:all .3s;margin-top:8px}
.btn-login:hover{background:#e8d5a3}
.error{background:rgba(220,50,50,.12);border:1px solid rgba(220,50,50,.3);color:#f87171;padding:11px 14px;font-size:.83rem;margin-bottom:20px;text-align:center}
.hint{text-align:center;color:rgba(255,255,255,.22);font-size:.73rem;margin-top:20px}
</style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">
    <h1>Selvi Resort & Lawn</h1>
    <p>Admin Panel</p>
  </div>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" autocomplete="off">
    <div class="form-group">
      <label>Username or Email</label>
      <input type="text" name="username" placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-login">Sign In to Admin Panel</button>
  </form>
  <p class="hint">Default: admin / Admin@1234</p>
</div>
</body>
</html>
