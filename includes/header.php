<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$site = setting('site_name', 'CAIRS');
$u = syncActiveUser();
$userProfile = $u ? [
    'full_name' => $u['full_name'],
    'email' => $u['email'],
    'role' => $u['role'],
    'phone' => $u['phone'] ?? '',
    'address' => $u['address'] ?? '',
    'created_at' => $u['created_at'] ?? '',
    'avatar_type' => ($u['avatar_type'] ?? 'badge') === 'image' ? 'image' : 'badge',
    'avatar_style' => $u['avatar_style'] ?? 'initials',
    'avatar_shape' => ($u['avatar_shape'] ?? 'circle') === 'badge' ? 'badge' : 'circle',
    'avatar_url' => sanitizeAvatarUrl($u['avatar_url'] ?? ''),
] : null;

$unread = 0;
if ($u) {
    $stmt = pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([(int) $u['id']]);
    $unread = (int) $stmt->fetchColumn();
}

$flash = popFlash();
$current = ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/');
if (BASE_URL !== '' && str_starts_with($current, ltrim(BASE_URL, '/') . '/')) {
    $current = substr($current, strlen(ltrim(BASE_URL, '/') . '/'));
}

$menus = [
    'resident' => [
        ['resident/dashboard.php', 'Dashboard', 'dashboard', 'Home'],
        ['resident/report.php', 'Report an incident', 'plus', 'Report'],
        ['resident/sos.php', 'SOS emergency', 'siren', 'SOS'],
        ['resident/my_reports.php', 'My reports', 'list', 'Reports'],
    ],
    'admin' => [
        ['admin/dashboard.php', 'Dashboard', 'dashboard', 'Home'],
        ['admin/reports.php', 'Reports', 'file', 'Reports'],
        ['admin/residents.php', 'Residents', 'users', 'Users'],
        ['admin/volunteers.php', 'Volunteers', 'shield', 'Team'],
        ['admin/announcements.php', 'Announcements', 'megaphone', 'Notices'],
        ['admin/categories.php', 'Categories', 'tag', 'Tags'],
        ['admin/analytics.php', 'Analytics', 'chart', 'Stats'],
        ['admin/analytics.php#incident-map', 'Incident map', 'pin', 'Map'],
        ['admin/settings.php', 'Settings', 'sliders', 'Settings'],
    ],
    'volunteer' => [
        ['volunteer/dashboard.php', 'Dashboard', 'dashboard', 'Home'],
        ['volunteer/assigned_reports.php', 'Assigned reports', 'clipboard', 'Tasks'],
    ],
];

$pageTitle = $title ?? $site;
$pageDesc = "{$site} - Community Assistance and Incident Reporting System for barangay incident reporting, emergency SOS, and volunteer response.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f1b2d">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<meta name="description" content="<?= e($pageDesc) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?> | <?= e($site) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<title><?= e($pageTitle) ?> | <?= e($site) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="<?= $u ? 'app role-' . e($u['role']) : 'public' ?>"<?= $u ? ' data-poll="' . BASE_URL . '/api/poll.php"' : '' ?>>
<?php if ($u): ?>
<a class="skip-link" href="#main">Skip to content</a>
<div class="sidebar-overlay" aria-hidden="true"></div>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
  <div class="sidebar-head">
    <a class="brand" href="<?= BASE_URL ?>/<?= homeFor($u['role']) ?>">
      <span class="brand-mark"><?= icon('shield', 20) ?></span><span><?= e($site) ?></span>
    </a>
    <button type="button" class="sidebar-close" id="sidebar-close-btn" aria-label="Collapse side panel" title="Collapse side panel">
      <span class="sc-ic-close"><?= icon('panel-close', 18) ?></span>
      <span class="sc-ic-open" aria-hidden="true"><?= icon('panel-open', 18) ?></span>
    </button>
  </div>
  <p class="nav-label">
    <span class="nav-label-full">Menu</span>
    <span class="nav-label-role" aria-hidden="true"><?= $u['role'] === 'admin' ? 'ADM' : ($u['role'] === 'volunteer' ? 'VOL' : 'RES') ?></span>
  </p>
  <nav>
    <?php foreach (($menus[$u['role']] ?? []) as [$navPath, $label, $ic, $shortLabel]): ?>
      <a href="<?= BASE_URL ?>/<?= e($navPath) ?>" class="<?= $current === $navPath ? 'active' : '' ?>" title="<?= e($label) ?>">
        <?= icon($ic, 19) ?><span class="nav-text"><?= e($label) ?></span><span class="nav-short" aria-hidden="true"><?= e($shortLabel) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-user-wrap">
    <div class="sidebar-profile-popover" id="sidebar-profile-popover" role="dialog" aria-label="Account menu" hidden>
      <div class="sp-card">
        <div class="sp-head">
          <?= renderAvatar($userProfile, '', true) ?>
          <div class="sp-id">
            <div class="sp-name-row">
              <strong data-user-name><?= e($u['full_name']) ?></strong>
              <span class="sp-role"><?= e(strtoupper($u['role'])) ?></span>
            </div>
            <span class="muted sp-email"><?= e($u['email']) ?></span>
          </div>
        </div>
        <div class="sp-meta">
          <span>Details</span>
          <b id="sp-details-text"><?= e(implode(' · ', array_filter([$userProfile['address'] ?? '', $userProfile['phone'] ?? ''])) ?: 'No contact info yet') ?></b>
        </div>
      </div>
      <div class="sp-menu">
        <button type="button" class="sp-item" data-profile-open>
          <?= icon('user', 16) ?><span>Edit Profile &amp; Password</span>
        </button>
        <?php if ($u['role'] === 'admin'): ?>
          <a class="sp-item" href="<?= BASE_URL ?>/admin/settings.php" data-sp-close>
            <?= icon('sliders', 16) ?><span>System Settings</span>
          </a>
        <?php elseif ($u['role'] === 'resident'): ?>
          <a class="sp-item" href="<?= BASE_URL ?>/resident/sos.php" data-sp-close>
            <?= icon('siren', 16) ?><span>Emergency SOS</span>
          </a>
        <?php else: ?>
          <a class="sp-item" href="<?= BASE_URL ?>/volunteer/assigned_reports.php" data-sp-close>
            <?= icon('clipboard', 16) ?><span>Assigned Reports</span>
          </a>
        <?php endif; ?>
        <div class="sp-sep" role="separator"></div>
        <a class="sp-item sp-danger" href="<?= BASE_URL ?>/logout.php">
          <?= icon('logout', 16) ?><span>Log out</span>
        </a>
      </div>
    </div>
    <button type="button" class="who who-btn" id="sidebar-user-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="sidebar-profile-popover" aria-label="Account menu">
      <?= renderAvatar($userProfile, '', true) ?>
      <div class="who-info">
        <strong data-user-name><?= e($u['full_name']) ?></strong>
        <span class="muted"><?= e($u['email']) ?></span>
      </div>
      <span class="who-chev"><?= icon('chevrons-up-down', 16) ?></span>
    </button>
  </div>
</aside>
<div class="main">
  <header class="topbar">
    <button class="nav-toggle" type="button" aria-label="Toggle side panel" aria-controls="sidebar" aria-expanded="false" title="Toggle side panel"><?= icon('panel-open', 19) ?></button>
    <nav class="crumbs" aria-label="Breadcrumb">
      <a class="crumb-root" href="<?= BASE_URL ?>/<?= homeFor($u['role']) ?>">Home</a><span class="sep">/</span>
      <span><?= e(ucfirst($u['role'])) ?></span><span class="sep">/</span><strong><?= e($pageTitle) ?></strong>
    </nav>
    <?php if ($u['role'] === 'admin'): ?>
    <form class="search" action="<?= BASE_URL ?>/admin/reports.php" method="get" role="search">
      <?= icon('search', 17) ?><label class="sr-only" for="top-search">Search reports</label>
      <input id="top-search" type="search" name="q" placeholder="Search reports...">
    </form>
    <?php endif; ?>
    <div class="top-actions">
      <div class="notif-pop-wrap">
        <button type="button" class="icon-btn" id="notif-bell-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="notif-popover" aria-label="Notifications<?= $unread ? " ({$unread} unread)" : '' ?>">
          <?= icon('bell', 19) ?><?php if ($unread): ?><span class="count"><?= $unread ?></span><?php endif; ?>
        </button>
        <div class="notif-popover" id="notif-popover" role="dialog" aria-label="Notifications" hidden>
          <div class="notif-pop-head">
            <div class="notif-pop-title">
              <span class="notif-pop-ic"><?= icon('bell', 16) ?></span>
              <strong>Notifications</strong>
              <span class="notif-new-pill" id="notif-new-pill"<?= $unread ? '' : ' hidden' ?>><?= $unread ?> New</span>
            </div>
            <div class="notif-pop-actions">
              <button type="button" class="notif-head-btn" id="notif-mark-all" title="Mark all as read"><?= icon('check-double', 14) ?><span>Mark read</span></button>
              <button type="button" class="notif-head-ic" id="notif-clear-all" aria-label="Clear notifications" title="Clear all notifications"><?= icon('trash', 15) ?></button>
              <button type="button" class="notif-head-ic" id="notif-close" aria-label="Close notifications" title="Close"><?= icon('x', 16) ?></button>
            </div>
          </div>
          <div class="notif-pop-tabs" role="tablist" aria-label="Notification filter">
            <button type="button" class="notif-tab is-active" role="tab" aria-selected="true" data-notif-tab="all">All <span data-notif-count="all">(0)</span></button>
            <button type="button" class="notif-tab" role="tab" aria-selected="false" data-notif-tab="unread">Unread <span data-notif-count="unread">(0)</span></button>
            <button type="button" class="notif-tab" role="tab" aria-selected="false" data-notif-tab="sos">SOS</button>
            <button type="button" class="notif-tab" role="tab" aria-selected="false" data-notif-tab="reports">Reports</button>
          </div>
          <div class="notif-pop-list" id="notif-list">
            <div class="notif-pop-empty">Loading notifications...</div>
          </div>
          <div class="notif-pop-foot">
            <span class="notif-pop-live"><i class="live-dot"></i> Live Alerts</span>
            <span class="notif-pop-hint">Real-time feed</span>
          </div>
        </div>
      </div>
      <button class="avatar-btn" type="button" data-profile-open aria-haspopup="dialog" aria-controls="profile-modal" aria-label="Open profile">
        <?= renderAvatar($userProfile, '', false) ?>
      </button>
    </div>
  </header>
<?php if ($u['role'] === 'resident' && $current !== 'resident/sos.php'): ?>
  <a class="sos-fab" href="<?= BASE_URL ?>/resident/sos.php" aria-label="Emergency SOS"><span class="sos-ring"></span><?= icon('siren', 22) ?><b>SOS</b></a>
<?php endif; ?>
<div class="main-scroll">
<?php endif; ?>
<main class="content" id="main">
