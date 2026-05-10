<?php
// pages/login.php  — standalone (no layout)

// Prevent browser caching of the login page.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password']   ?? '';
    $admin = db_row("SELECT * FROM admins WHERE email = ?", [$email]);
    if ($admin && password_verify($pass, $admin['password'])) {
        // Regenerate session ID to prevent session fixation attacks.
        session_regenerate_id(true);

        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_role'] = $admin['role'];

        // A new CSRF token is generated for the new, authenticated session.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        db_execute("UPDATE admins SET last_login=NOW() WHERE id=?",[$admin['id']]);
        header('Location: index.php?page=dashboard'); exit;
    }
    $error = 'Invalid email or password.';
}
// Check for flash messages to display
$flash_success = flash('success');
$flash_error   = flash('error');
$flash_info    = flash('info');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — UB AttendQR</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <div class="logo-icon" style="box-shadow:0 0 30px var(--glow);"><i class="bi bi-clipboard-check"></i></div>
      <h1>UB Attend<span style="color:var(--accent);">QR</span></h1>
      <p>Event Attendance Registration System</p>
    </div>

    <?php if (isset($error)): ?>
      <div class="alert alert-error" style="border-radius:var(--radius-sm);margin-bottom:20px;padding:12px 16px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($flash_success): ?>
      <div class="alert alert-success" style="border-radius:var(--radius-sm);margin-bottom:20px;padding:12px 16px;"><?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
      <div class="alert alert-error" style="border-radius:var(--radius-sm);margin-bottom:20px;padding:12px 16px;"><?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>
    <?php if ($flash_info): ?>
      <div class="alert alert-info" style="border-radius:var(--radius-sm);margin-bottom:20px;padding:12px 16px;"><?= htmlspecialchars($flash_info) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=login">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-input"
               placeholder="admin@attendqr.com" required autofocus
               value="<?= htmlspecialchars($_POST['email']??'') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-input" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="margin-top:8px;padding:13px;">
        Login to AttendQR
      </button>
    </form>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
