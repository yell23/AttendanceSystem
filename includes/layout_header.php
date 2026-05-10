<?php
// includes/layout_header.php

// Prevent browser caching of pages that require authentication.
// This helps prevent issues with stale CSRF tokens after a session has expired or the user has logged out.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Called at the top of every page after requireLogin()
$current_page = $_GET['page'] ?? 'dashboard';
$admin_name   = $_SESSION['admin_name']  ?? 'Admin';
$admin_role   = $_SESSION['admin_role']  ?? 'admin';
$admin_initials = implode('', array_map(function($w) { return strtoupper($w[0]); }, array_slice(explode(' ', $admin_name), 0, 2)));

$page_titles = [
    'dashboard'  => 'Dashboard',
    'events'     => 'Events',
    'forms'      => 'Google Forms',
    'scanner'    => 'QR Scanner',
    'attendees'  => 'Attendees',
    'qrcodes'    => 'QR Codes',
    'reports'    => 'Analytics & Reports',
    'logs'       => 'Audit Logs',
    'settings'   => 'Settings',
    'notifications' => 'All Notifications',
    'login'      => 'Login',
];
$page_title = $page_titles[$current_page] ?? ucfirst($current_page);

$nav_items = [
    ['icon'=>'bi-house-door','label'=>'Dashboard', 'page'=>'dashboard','group'=>'Main'],
    ['icon'=>'bi-calendar-event','label'=>'Events',    'page'=>'events',   'group'=>'Main'],
    ['icon'=>'bi-file-earmark-text','label'=>'Google Forms','page'=>'forms',  'group'=>'Main'],
    ['icon'=>'bi-qr-code-scan','label'=>'QR Scanner','page'=>'scanner',  'group'=>'Operations'],
    ['icon'=>'bi-people','label'=>'Attendees', 'page'=>'attendees','group'=>'Operations'],
    ['icon'=>'bi-qr-code','label'=>'QR Codes',  'page'=>'qrcodes',  'group'=>'Operations'],
    ['icon'=>'bi-bar-chart-line','label'=>'Analytics', 'page'=>'reports',  'group'=>'Reports'],
    ['icon'=>'bi-gear','label'=>'Settings',  'page'=>'settings', 'group'=>'System'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — <?= APP_NAME ?></title>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body>
<div class="app">

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><i class="bi bi-clipboard-check"></i></div>
    <div class="logo-text">UB Attend<span>QR</span></div>
  </div>

  <nav class="nav-section">
    <?php
    $last_group = '';
    foreach ($nav_items as $item):
        if ($item['group'] !== $last_group):
            $last_group = $item['group'];
    ?>
        <div class="nav-label"><?= $item['group'] ?></div>
    <?php endif; ?>
    <a href="index.php?page=<?= $item['page'] ?>"
       class="nav-item <?= $current_page === $item['page'] ? 'active' : '' ?>">
      <i class="icon bi <?= $item['icon'] ?>"></i>
      <?= $item['label'] ?>
      <?php if ($item['page'] === 'events'): ?>
        <span class="nav-badge" id="nav-event-count">—</span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= $admin_initials ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($admin_name) ?></div>
        <div class="user-role"><?= ucfirst($admin_role) ?></div>
      </div>
      <a href="index.php?page=logout" title="Logout" class="action-btn" style="font-size: 1.2rem;"><i class="bi bi-door-open"></i></a>
    </div>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<main class="main">

  <!-- TOP BAR -->
  <div class="topbar">
    <button class="btn-icon sidebar-toggle" id="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><?= htmlspecialchars($page_title) ?></div>
    <div class="topbar-actions">
      <form class="search-bar" method="GET" action="index.php">
        <input type="hidden" name="page" value="attendees">
        <span class="search-icon"><i class="bi bi-search"></i></span>
        <input type="text" name="q" placeholder="Search attendees..."
               value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
      </form>
      <button class="btn btn-ghost icon-btn" id="theme-toggle-btn" title="Toggle Theme">
        <i class="bi bi-sun"></i>
      </button>
      <a href="index.php?page=events&action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> <span>New Event</span></a>
    </div>
  </div>

  <!-- Flash Messages -->
  <?php
  $flash_success = flash('success');
  $flash_error   = flash('error');
  $flash_info    = flash('info');
  if ($flash_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
  <?php endif; ?>
  <?php if ($flash_info): ?>
    <div class="alert alert-info"><?= htmlspecialchars($flash_info) ?></div>
  <?php endif; ?>
  <?php
  $flash_email_debug = flash('email_debug');
  if ($flash_email_debug):
    // This flash contains raw HTML for email previews, so we don't escape it.
    // It's generated internally, so it's safe.
    echo $flash_email_debug;
  endif;
  ?>

  <!-- PAGE CONTENT -->
  <div class="page-content">
