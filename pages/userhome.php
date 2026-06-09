<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/php/bootstrap.php';

$auth = Auth::check();
$bodyClass = $auth ? 'is-auth ' . Auth::bodyRoleClass() : 'is-guest';
$user = $auth ? Auth::user() : [];
$displayName = htmlspecialchars((string) ($user['full_name'] ?? 'User'), ENT_QUOTES, 'UTF-8');
$avatarUrl = !empty($user['avatar_path'])
    ? htmlspecialchars(Upload::publicUrl((string) $user['avatar_path']), ENT_QUOTES, 'UTF-8')
    : '../images/user-profile.svg';
$bioValue = htmlspecialchars((string) ($user['bio'] ?? ''), ENT_QUOTES, 'UTF-8');

$walletBalance = '0.00';
$userId = $auth ? Auth::id() : null;
$userRole = $auth ? (Auth::role() ?? 'user') : null;

if ($auth && $userId !== null) {
    $walletBalance = number_format(WalletService::balance($userId), 2);
}

$platformStats = CampaignService::stats();
$discoverCampaigns = CampaignService::listActive();
$myCampaigns = ($auth && $userId !== null && $userRole !== null)
    ? CampaignService::listForUser($userId, $userRole) : [];
$walletTransactions = ($auth && $userId !== null) ? WalletService::transactions($userId) : [];
$activityRows = ($auth && $userId !== null) ? DonationService::activityForUser($userId) : [];
$notifications = ($auth && $userId !== null) ? NotificationService::listForUser($userId) : [];
$inboxThreads = ($auth && $userId !== null) ? MessageService::inbox($userId) : [];
$activeThreadId = (int) ($_GET['thread'] ?? 0);
$threadMessages = [];
$activeThread = null;
$urgentCampaigns = CampaignService::listUrgent(5);
$featuredCampaigns = CampaignService::listFeatured(3);
$recentDonations = ($auth && $userId !== null && $userRole === 'user')
    ? DonationService::recentForDonor($userId) : [];
$recentOrgDonations = ($auth && $userId !== null && $userRole === 'organisation')
    ? DonationService::recentForOrganisation($userId) : [];
$topOrgCampaigns = ($auth && $userId !== null && $userRole === 'organisation')
    ? CampaignService::listTopForOrganisation($userId) : [];
$recentDonors = ($auth && $userId !== null && $userRole === 'beneficiary')
    ? DonationService::recentDonorsForOwner($userId) : [];
$primaryTakerCampaign = ($auth && $userId !== null && $userRole === 'beneficiary')
    ? CampaignService::primaryForBeneficiary($userId) : null;

if ($auth && $userId !== null && $activeThreadId === 0 && $inboxThreads) {
    $activeThreadId = (int) $inboxThreads[0]['id'];
}
if ($auth && $userId !== null && $activeThreadId > 0) {
    try {
        $threadMessages = MessageService::messages($activeThreadId, $userId);
        foreach ($inboxThreads as $t) {
            if ((int) $t['id'] === $activeThreadId) {
                $activeThread = $t;
                break;
            }
        }
    } catch (Throwable) {
        $activeThreadId = 0;
        $threadMessages = [];
        $activeThread = null;
    }
}

$activityTotalPaid = 0.0;
$activityTotalFees = 0.0;
foreach ($activityRows as $ar) {
    $activityTotalPaid += (float) ($ar['total_charged'] ?? 0);
    $activityTotalFees += (float) ($ar['fee_amount'] ?? 0);
}
$receiptCount = 0;
if ($auth && $userId !== null) {
    $rc = db()->prepare('SELECT COUNT(*) FROM receipts WHERE user_id = ?');
    $rc->execute([$userId]);
    $receiptCount = (int) $rc->fetchColumn();
}

$partial = dirname(__DIR__) . '/php/partials/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="../images/sawa.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/tokens.css">
  <link rel="stylesheet" href="../css/userhome.css">
  <script src="../js/theme.js" defer></script>
  <title>Dashboard — Sawa</title>
</head>
<body class="<?= $bodyClass ?>">
  <!--
    Body classes drive what's visible. PHP sets them server-side:

      Auth state:  is-auth | is-guest
      User role:   role-donor | role-taker | role-org   (only when is-auth)

    Example:
      <?php $mode = isset($_SESSION['user_id']) ? 'is-auth' : 'is-guest'; ?>
      <?php $role = $_SESSION['user_role'] ?? 'donor'; // donor|taker|org ?>
      <body class="<?= $mode ?> role-<?= $role ?>">

    For testing without PHP:
      Direct static HTML fallback is is-guest.
      ?role=taker       → forces role-taker
      ?role=org         → forces role-org
  -->

  <!-- ─── Guest-only unified header (replaces sidebar for guests) ─── -->
  <header class="site-header guest-only" role="banner">
    <div class="site-header-inner">
      <a href="../index.html" class="site-brand" aria-label="Sawa home">
        <span class="site-brand-mark">
          <img src="../images/sawa_v2.svg" alt="">
        </span>
        <span class="site-brand-name">Sawa</span>
      </a>
      <nav class="site-header-nav" aria-label="Primary navigation">
        <button class="site-nav-link is-active" type="button" data-jump="dashboard">Home</button>
        <button class="site-nav-link" type="button" data-jump="discover">Browse</button>
        <a class="site-nav-link" href="about-us.html">About</a>
      </nav>
      <div class="site-header-auth">
        <a href="login.php" class="site-auth-link">Log In</a>
        <a href="signup.php" class="site-auth-btn">Sign Up Free</a>
        <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode">
          <svg class="theme-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <svg class="theme-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        </button>
      </div>
      <button class="site-header-burger" id="site-burger" type="button" aria-label="Open menu" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" width="22" height="22" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
  </header>

  <!-- Slide-out drawer for mobile guests -->
  <div class="guest-drawer-backdrop" id="guest-drawer-backdrop" hidden></div>
  <aside class="guest-drawer guest-only" id="guest-drawer" aria-label="Mobile menu" hidden>
    <div class="guest-drawer-head">
      <a href="../index.html" class="site-brand">
        <span class="site-brand-mark"><img src="../images/sawa_v2.svg" alt=""></span>
        <span class="site-brand-name">Sawa</span>
      </a>
      <button class="guest-drawer-close" id="guest-drawer-close" type="button" aria-label="Close menu">&times;</button>
    </div>
    <nav class="guest-drawer-nav" aria-label="Mobile primary navigation">
      <button class="guest-drawer-link is-active" type="button" data-jump="dashboard">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Home
      </button>
      <button class="guest-drawer-link" type="button" data-jump="discover">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Browse Campaigns
      </button>
      <a class="guest-drawer-link" href="about-us.html">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        About Sawa
      </a>
    </nav>
    <div class="guest-drawer-auth">
      <a href="signup.php" class="guest-drawer-cta">Sign Up Free
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
      <a href="login.php" class="guest-drawer-link-secondary">Log In</a>
    </div>
  </aside>

  <!-- ─── Auth-only top header (logo replaced with user profile chip on userhome) ─── -->
  <header class="site-header auth-only site-header--user-lead" role="banner">
    <div class="site-header-inner">
      <!-- Profile chip pinned to the left; nav links sit on the right. -->
      <button class="site-header-user" id="nav-user-btn" type="button" aria-label="Open account menu">
        <img src="<?= $avatarUrl ?>" alt="" id="nav-avatar">
        <span class="site-header-user-text">
          <strong id="nav-name"><?= $displayName ?></strong>
          <small class="site-header-user-role" aria-hidden="true"></small>
        </span>
      </button>
      <nav class="site-header-nav" aria-label="Primary navigation">
        <button class="site-nav-link is-active" type="button" data-jump="dashboard">Home</button>
        <button class="site-nav-link" type="button" data-jump="discover">Campaigns</button>
        <a class="site-nav-link" href="about-us.html">About</a>
      </nav>
    </div>
  </header>

  <div class="mobile-overlay"></div>

  <div class="layout">

    <aside class="sidebar">
      <div class="sidebar-brand">
        <div class="sidebar-logo-circle">
          <img src="../images/sawa_v2.svg" alt="Sawa">
        </div>
        <span>Sawa</span>
        <button class="sidebar-toggle" aria-label="Collapse sidebar" title="Collapse sidebar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
      </div>

      <!-- User profile card (auth only) — sits at top of sidebar, common dashboard pattern -->
      <button type="button" class="sidebar-user auth-only" data-section="profile" aria-label="Go to my profile">
        <img class="sidebar-user-avatar" src="../images/user-profile.svg" alt="" id="sidebar-user-avatar">
        <span class="sidebar-user-info">
          <strong class="sidebar-user-name" id="sidebar-user-name">User</strong>
          <small class="sidebar-user-role"></small>
        </span>
        <svg class="sidebar-user-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
      <button class="sidebar-item active" data-section="dashboard">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span>Dashboard</span>
      </button>
      <button class="sidebar-item" data-section="discover">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <span>Discover</span>
      </button>
      <button class="sidebar-item auth-only" data-section="profile">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        <span>My Profile</span>
      </button>
      <button class="sidebar-item auth-only" data-section="wallet">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        <span>My Wallet</span>
      </button>
      <button class="sidebar-item auth-only" data-section="activity">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3h6l1 2h3a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1h3l1-2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11h8M8 15h5"/></svg>
        <span>Activity &amp; Bills</span>
      </button>
      <button class="sidebar-item auth-only" data-section="messages">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9h8M8 13h5"/></svg>
        <span>Messages</span>
      </button>
      <button class="sidebar-item auth-only" data-section="campaign-new">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Create Campaign</span>
      </button>
      <?php if ($auth && $userRole === 'admin'): ?>
      <a class="sidebar-item auth-only" href="admin.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>Admin</span>
      </a>
      <?php endif; ?>
      <button class="sidebar-item auth-only" data-section="campaigns">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        <span>My Campaigns</span>
      </button>

      <!-- Auth section — pinned to bottom, visually separated from primary nav -->
      <div class="sidebar-auth-section">
        <span class="sidebar-section-label guest-only">Join Sawa</span>

        <!-- Logged-in only: logout -->
        <button class="sidebar-item sidebar-theme-toggle auth-only" type="button" data-theme-toggle aria-label="Switch to dark mode">
          <svg class="theme-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <svg class="theme-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
          <span>Theme</span>
        </button>
        <button class="sidebar-item logout auth-only" onclick="window.location.href='../php/auth/logout.php'">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          <span>Logout</span>
        </button>

        <!-- Guests only: Sign Up + Log In CTA pair -->
        <a class="sidebar-item sidebar-cta guest-only" href="signup.php">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 8v6M16 11h6"/></svg>
          <span>Sign Up Free</span>
        </a>
        <a class="sidebar-item sidebar-auth-link guest-only" href="login.php">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 003 3h2a3 3 0 003-3V7a3 3 0 00-3-3h-2a3 3 0 00-3 3v1"/></svg>
          <span>Log In</span>
        </a>
      </div>
    </aside>

    <main class="main-content">

      <section id="dashboard" class="section active">
        <!-- Guest hero (only shown when body.is-guest) — dark navy + horizontal stats -->
        <section class="guest-hero-v2 guest-only" aria-labelledby="guest-hero-heading">
          <div class="guest-hero-v2-inner">
            <span class="guest-hero-v2-eyebrow">
              <span class="hero-eyebrow-dot" aria-hidden="true"></span>
              Lebanon&rsquo;s transparent donation platform
            </span>
            <h1 id="guest-hero-heading">Help families across Lebanon &mdash; <span class="hero-accent">directly</span>.</h1>
            <p>No middlemen. Sawa shows its transparent platform support fee before checkout.</p>
            <div class="guest-hero-v2-cta-row">
              <button class="hero-primary-btn" type="button" onclick="document.querySelector('[data-section=&quot;discover&quot;]').click()">
                Browse Campaigns
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              </button>
              <a href="signup.php" class="hero-text-link">or sign up free
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              </a>
            </div>

            <!-- Horizontal stats grid inside the hero -->
            <div class="hero-stats">
              <div class="hero-stat">
                <span class="hero-stat-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                </span>
                <strong class="stat-tile-value" id="hero-raised">$250k+</strong>
                <span class="hero-stat-label">Total raised on Sawa</span>
              </div>
              <div class="hero-stat">
                <span class="hero-stat-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <strong class="stat-tile-value" id="hero-helped">1,200+</strong>
                <span class="hero-stat-label">Families helped since launch</span>
              </div>
              <div class="hero-stat">
                <span class="hero-stat-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </span>
                <strong class="stat-tile-value" id="hero-active"><?= (int)$platformStats['active'] ?></strong>
                <span class="hero-stat-label">Active campaigns now</span>
              </div>
              <div class="hero-stat">
                <span class="hero-stat-icon hero-stat-icon-accent" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <strong class="stat-tile-value" id="hero-direct">5%</strong>
                <span class="hero-stat-label">Platform fee for members</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Top header: greeting + search + bell + CTA (auth only) -->
        <div class="dash-header auth-only">
          <div class="dash-greeting">
            <h1><span id="dash-greeting-word">Welcome back</span>, <span id="dash-name"><?= $displayName ?></span></h1>
            <p>Here's your giving overview.</p>
          </div>
          <div class="dash-header-actions">
            <label class="dash-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="search" id="dash-search-input" placeholder="Search campaigns…" autocomplete="off">
            </label>
            <div class="dash-bell-wrap">
              <button class="dash-bell" id="dash-bell-btn" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span class="dash-bell-dot"></span>
              </button>
              <div class="dash-notif-panel" id="dash-notif-panel" role="menu">
                <div class="dash-notif-head">
                  <strong>Notifications</strong>
                  <a href="#" class="dash-notif-mark" id="dash-notif-mark">Mark all read</a>
                  <form id="dash-notif-mark-form" method="POST" action="../php/engagement/notifications-mark-read.php" hidden>
                    <?= Csrf::field() ?>
                  </form>
                </div>
                <ul class="dash-notif-list" id="dash-notif-list">
                  <?php if (!$notifications): ?>
                  <li class="dash-notif-row"><div class="dash-notif-text"><strong>No notifications</strong><small>You are all caught up.</small></div></li>
                  <?php else: foreach ($notifications as $n): include $partial . 'notification-row.php'; endforeach; endif; ?>
                </ul>
                <a href="#" class="dash-notif-foot">View all notifications</a>
              </div>
            </div>
            <button class="dash-cta donor-only" type="button" onclick="document.querySelector('[data-section=&quot;discover&quot;]').click()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>New Donation</span>
            </button>
            <button class="dash-cta taker-only" type="button" onclick="document.querySelector('[data-section=&quot;campaign-new&quot;]').click()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>New Request</span>
            </button>
            <button class="dash-cta org-only" type="button" onclick="document.querySelector('[data-section=&quot;campaign-new&quot;]').click()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>New Campaign</span>
            </button>
          </div>
        </div>

        <div class="quick-actions auth-only">
          <button type="button" class="action-btn donor-only" onclick="document.querySelector('[data-section=&quot;discover&quot;]').click()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-4.35-7-10a4 4 0 017-2.65A4 4 0 0119 11c0 5.65-7 10-7 10z"/></svg>
            <span>Donate</span>
          </button>
          <button type="button" class="action-btn" onclick="document.querySelector('[data-section=&quot;campaign-new&quot;]').click()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            <span>New Campaign</span>
          </button>
          <button type="button" class="action-btn" onclick="document.querySelector('[data-section=&quot;activity&quot;]').click()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6l1 2h3a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1h3l1-2z"/><path d="M8 11h8M8 15h5"/></svg>
            <span>Bills</span>
          </button>
          <button type="button" class="action-btn" onclick="document.querySelector('[data-section=&quot;messages&quot;]').click()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4z"/><path d="M8 9h8M8 13h5"/></svg>
            <span>Chat</span>
          </button>
          <button type="button" class="action-btn" onclick="document.querySelector('[data-section=&quot;wallet&quot;]').click()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            <span>Wallet</span>
          </button>
        </div>

        <!-- 3 stat tiles — DONOR variant -->
        <div class="dash-stats donor-only">
          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">Total Donated</span>
              <span class="stat-tile-icon stat-icon-trend">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="dash-donated">$0</strong>
            <span class="stat-tile-delta delta-up">+12% from last month</span>
          </div>

          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">Families Helped</span>
              <span class="stat-tile-icon stat-icon-users">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="dash-helped">0</strong>
            <span class="stat-tile-delta delta-up">+3 from last month</span>
          </div>

          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">This Month</span>
              <span class="stat-tile-icon stat-icon-cal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="dash-month">$0</strong>
            <span class="stat-tile-delta delta-up">+8% from last month</span>
          </div>
        </div>

        <!-- 3 stat tiles — TAKER variant (your campaign progress) -->
        <div class="dash-stats taker-only">
          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">Campaign Goal</span>
              <span class="stat-tile-icon stat-icon-cal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="taker-goal">$10,000</strong>
            <span class="stat-tile-delta">your fundraising target</span>
          </div>

          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">Total Raised</span>
              <span class="stat-tile-icon stat-icon-trend">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="taker-raised">$3,200</strong>
            <span class="stat-tile-delta delta-up">32% of goal reached</span>
          </div>

          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">Donors</span>
              <span class="stat-tile-icon stat-icon-users">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="taker-donors">14</strong>
            <span class="stat-tile-delta delta-up">+2 this week</span>
          </div>
        </div>

        <!-- 3 stat tiles — ORGANISATION variant -->
        <div class="dash-stats org-only">
          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">Active Campaigns</span>
              <span class="stat-tile-icon stat-icon-cal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="org-active">4</strong>
            <span class="stat-tile-delta">in progress</span>
          </div>

          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">Total Raised</span>
              <span class="stat-tile-icon stat-icon-trend">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="org-raised">$28,400</strong>
            <span class="stat-tile-delta delta-up">+$1,200 this month</span>
          </div>

          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">People Helped</span>
              <span class="stat-tile-icon stat-icon-users">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="org-helped">312</strong>
            <span class="stat-tile-delta delta-up">across all campaigns</span>
          </div>
        </div>

        <!-- Guest stats are now embedded in the hero above; nothing needed here for guests. -->

        <!-- Two-column lower: recent donations | urgent campaigns -->
        <div class="dash-lower">
          <div class="dash-card donor-only">
            <div class="dash-card-head">
              <h3>Recent Donations</h3>
              <a class="dash-card-link" onclick="document.querySelector('[data-section=&quot;activity&quot;]').click(); return false;" href="#">View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            </div>
            <ul class="recent-donations" id="recent-donations-list">
              <?php if (!$recentDonations): ?>
              <li class="recent-donation"><div class="rd-meta"><strong>No donations yet</strong><small>Your giving history will appear here.</small></div></li>
              <?php else: foreach ($recentDonations as $don): include $partial . 'recent-donation.php'; endforeach; endif; ?>
            </ul>
          </div>

          <!-- Urgent Campaigns: shown for donors + guests -->
          <div class="dash-card donor-only guest-show">
            <div class="dash-card-head">
              <h3>Urgent Campaigns</h3>
              <a class="dash-card-link" onclick="document.querySelector('[data-section=&quot;discover&quot;]').click(); return false;" href="#">View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            </div>
            <div class="urgent-list" id="urgent-list">
              <?php if (!$urgentCampaigns): ?>
              <p class="empty-inline">No urgent campaigns right now.</p>
              <?php else: foreach ($urgentCampaigns as $camp): include $partial . 'urgent-item.php'; endforeach; endif; ?>
            </div>
          </div>

          <!-- TAKER variant: Campaign Status (wide) + Recent Donors -->
          <div class="dash-card taker-only dash-card-wide">
            <div class="dash-card-head">
              <h3>Your Campaign</h3>
              <span class="status-pill status-active">Active &middot; Verified</span>
            </div>
            <div class="taker-camp-summary">
              <?php if ($primaryTakerCampaign):
                $tc = $primaryTakerCampaign;
                $tcRaised = (float) $tc['raised_amount'];
                $tcGoal = (float) $tc['goal_amount'];
                $tcPct = $tcGoal > 0 ? min(100, (int) round(($tcRaised / $tcGoal) * 100)) : 0;
              ?>
              <h4><?= htmlspecialchars((string) $tc['title'], ENT_QUOTES, 'UTF-8') ?></h4>
              <p><?= htmlspecialchars(mb_substr((string) $tc['description'], 0, 200), ENT_QUOTES, 'UTF-8') ?></p>
              <div class="urgent-progress-row">
                <small>Raised: <strong>$<?= number_format($tcRaised) ?></strong></small>
                <small>$<?= number_format($tcGoal) ?> goal</small>
              </div>
              <div class="progress-bar"><div class="progress-fill" style="width:<?= $tcPct ?>%"></div></div>
              <?php else: ?>
              <h4>No active campaign</h4>
              <p>Create a campaign to start receiving donations.</p>
              <div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div>
              <?php endif; ?>
              <div class="taker-camp-actions">
                <button type="button" class="btn btn-outline" onclick="document.querySelector('[data-section=&quot;campaigns&quot;]').click()">Edit Campaign</button>
                <button type="button" class="btn btn-primary" id="taker-share-btn">Share Link</button>
              </div>
            </div>
          </div>
          <div class="dash-card taker-only">
            <div class="dash-card-head">
              <h3>Recent Donors</h3>
              <a class="dash-card-link" href="#">View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            </div>
            <ul class="donors-list">
              <?php if (!$recentDonors): ?>
              <li class="donor-row"><div class="donor-meta"><strong>No donors yet</strong><small>Share your campaign to get started.</small></div></li>
              <?php else: foreach ($recentDonors as $don): include $partial . 'donor-row.php'; endforeach; endif; ?>
            </ul>
          </div>

          <!-- ORGANISATION variant: Recent Donations Received + Top Campaigns -->
          <div class="dash-card org-only">
            <div class="dash-card-head">
              <h3>Recent Donations Received</h3>
              <a class="dash-card-link" onclick="document.querySelector('[data-section=&quot;activity&quot;]').click(); return false;" href="#">View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            </div>
            <ul class="recent-donations">
              <?php if (!$recentOrgDonations): ?>
              <li class="recent-donation"><div class="rd-meta"><strong>No donations yet</strong><small>Donations to your campaigns appear here.</small></div></li>
              <?php else: foreach ($recentOrgDonations as $don):
                $don['_org_meta'] = true;
                include $partial . 'recent-donation.php';
              endforeach; endif; ?>
            </ul>
          </div>
          <div class="dash-card org-only">
            <div class="dash-card-head">
              <h3>Top Performing Campaigns</h3>
              <a class="dash-card-link" href="#">View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            </div>
            <div class="urgent-list">
              <?php if (!$topOrgCampaigns): ?>
              <p class="empty-inline">No campaigns yet.</p>
              <?php else: foreach ($topOrgCampaigns as $camp):
                $camp['creator_name'] = ((int) ($camp['donor_count'] ?? 0)) . ' donors';
                $camp['_pct_badge'] = true;
                $camp['_hide_donate'] = true;
                include $partial . 'urgent-item.php';
              endforeach; endif; ?>
            </div>
          </div>
        </div>

        <!-- ─── Guest-only content below stats ─── -->
        <div class="guest-feature-grid guest-only">
          <div class="dash-card">
            <div class="dash-card-head">
              <h3>Featured Campaigns</h3>
              <a class="dash-card-link" href="#" onclick="document.querySelector('[data-section=&quot;discover&quot;]').click(); return false;">View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            </div>
            <div class="featured-list">
              <?php if (!$featuredCampaigns): ?>
              <p class="empty-inline">No campaigns to feature yet.</p>
              <?php else: foreach ($featuredCampaigns as $camp): include $partial . 'featured-camp.php'; endforeach; endif; ?>
            </div>
          </div>

          <div class="dash-card how-it-works-card">
            <div class="dash-card-head">
              <h3>How It Works</h3>
            </div>
            <ol class="how-steps">
              <li>
                <span class="how-step-num">1</span>
                <div>
                  <strong>Browse verified campaigns</strong>
                  <small>Every family and NGO passes our review before joining.</small>
                </div>
              </li>
              <li>
                <span class="how-step-num">2</span>
                <div>
                  <strong>Donate directly</strong>
                  <small>No middlemen. The Sawa support fee (5% for members, 10% for guests) is shown before checkout.</small>
                </div>
              </li>
              <li>
                <span class="how-step-num">3</span>
                <div>
                  <strong>Track real impact</strong>
                  <small>Campaigns post updates so you can see what your gift did.</small>
                </div>
              </li>
            </ol>
            <a href="signup.php" class="how-cta-btn">Sign Up to Track Donations
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
          </div>
        </div>

        <!-- Trust strip — placeholder testimonials, swap with real quotes later -->
        <div class="trust-strip guest-only">
          <div class="trust-strip-head">
            <h3>Voices from the Sawa community</h3>
            <p>Real stories from donors and families we've helped. <em>(Placeholder content — replace with real testimonials.)</em></p>
          </div>
          <div class="testimonials-grid">
            <!-- TESTIMONIAL_PLACEHOLDER: replace quote, author name, role with real content -->
            <figure class="testimonial-card">
              <blockquote>"Campaign updates and receipts will appear here once real testimonial content is collected."</blockquote>
              <figcaption>
                <span class="testimonial-avatar" aria-hidden="true">D</span>
                <span><strong>Donor testimonial</strong><small>Placeholder content</small></span>
              </figcaption>
            </figure>
            <figure class="testimonial-card">
              <blockquote>"Organisation proof, campaign updates, and donor feedback should be rendered here from real records."</blockquote>
              <figcaption>
                <span class="testimonial-avatar" aria-hidden="true">O</span>
                <span><strong>Organisation testimonial</strong><small>Placeholder content</small></span>
              </figcaption>
            </figure>
            <figure class="testimonial-card">
              <blockquote>"Use this card for a real quote after consent, including the transparent Sawa fee policy."</blockquote>
              <figcaption>
                <span class="testimonial-avatar" aria-hidden="true">T</span>
                <span><strong>Trust testimonial</strong><small>Placeholder content</small></span>
              </figcaption>
            </figure>
          </div>
        </div>

        <!-- ─── Guest unlock banner — what you get when you sign up ─── -->
        <section class="guest-unlock guest-only" aria-labelledby="guest-unlock-heading">
          <div class="guest-unlock-text">
            <span class="guest-unlock-eyebrow">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Locked for guests
            </span>
            <h3 id="guest-unlock-heading">Sign in to unlock the full Sawa experience</h3>
            <p>Guests can browse and donate. Sign up free to access your wallet, create campaigns, manage your profile, and track every donation.</p>
            <ul class="guest-unlock-list">
              <li>
                <span class="guest-unlock-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></span>
                <div><strong>My Wallet</strong><small>Top up once, donate to many campaigns</small></div>
              </li>
              <li>
                <span class="guest-unlock-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                <div><strong>Create a campaign</strong><small>Raise funds if you or a family you know need help</small></div>
              </li>
              <li>
                <span class="guest-unlock-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                <div><strong>Profile &amp; impact</strong><small>Track donations and see real-time progress updates</small></div>
              </li>
            </ul>
            <div class="guest-unlock-cta-row">
              <a href="signup.php" class="guest-unlock-cta">Sign Up Free
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              </a>
              <a href="login.php" class="guest-unlock-link">Already have an account? Log in</a>
            </div>
          </div>
        </section>

        <!-- ─── Live activity feed + Why trust Sawa (guest-only) ─── -->
        <div class="dash-two-col guest-only">
          <div class="dash-card activity-card">
            <div class="dash-card-head">
              <h3>
                <span class="live-dot" aria-hidden="true"></span>
                Live activity
              </h3>
              <a href="#" class="dash-card-link" onclick="document.querySelector('[data-section=&quot;discover&quot;]').click(); return false;">Browse all
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            </div>
            <!-- ACTIVITY_PLACEHOLDER: PHP can replace these rows with the live donation stream. -->
            <ul class="activity-feed" id="activity-feed">
              <li class="activity-row">
                <span class="activity-avatar">L</span>
                <div class="activity-text">
                  <strong>Verified donor</strong> donated <strong class="activity-amount">$50</strong>
                  <span class="activity-meta">to Help Sara Beat Cancer &middot; <time>2 min ago</time></span>
                </div>
              </li>
              <li class="activity-row">
                <span class="activity-avatar activity-avatar-anon">?</span>
                <div class="activity-text">
                  <strong>Anonymous</strong> donated <strong class="activity-amount">$200</strong>
                  <span class="activity-meta">to Winter Food Packages &middot; <time>14 min ago</time></span>
                </div>
              </li>
              <li class="activity-row">
                <span class="activity-avatar">A</span>
                <div class="activity-text">
                  <strong>Community donor</strong> donated <strong class="activity-amount">$25</strong>
                  <span class="activity-meta">to School Supplies for Akkar &middot; <time>32 min ago</time></span>
                </div>
              </li>
              <li class="activity-row">
                <span class="activity-avatar">Y</span>
                <div class="activity-text">
                  <strong>Sawa donor</strong> donated <strong class="activity-amount">$100</strong>
                  <span class="activity-meta">to Heart Surgery for Ali &middot; <time>1 hr ago</time></span>
                </div>
              </li>
              <li class="activity-row">
                <span class="activity-avatar">M</span>
                <div class="activity-text">
                  <strong>Verified donor</strong> donated <strong class="activity-amount">$15</strong>
                  <span class="activity-meta">to Blankets for Bekaa &middot; <time>2 hrs ago</time></span>
                </div>
              </li>
              <li class="activity-row">
                <span class="activity-avatar">H</span>
                <div class="activity-text">
                  <strong>Community donor</strong> donated <strong class="activity-amount">$75</strong>
                  <span class="activity-meta">to Emergency Roof Repair &middot; <time>3 hrs ago</time></span>
                </div>
              </li>
            </ul>
          </div>

          <div class="dash-card trust-card">
            <div class="dash-card-head">
              <h3>Why families trust Sawa</h3>
            </div>
            <ul class="trust-list">
              <li class="trust-item">
                <span class="trust-icon trust-icon-green">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </span>
                <div>
                  <strong>0 intermediaries</strong>
                  <small>Donations are routed to verified campaigns, with the Sawa fee shown before payment.</small>
                </div>
              </li>
              <li class="trust-item">
                <span class="trust-icon trust-icon-blue">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 2l8 4v6c0 5-3.5 8.7-8 10-4.5-1.3-8-5-8-10V6l8-4z"/></svg>
                </span>
                <div>
                  <strong>Verified creators only</strong>
                  <small>All NGOs and families pass a review before campaigns go live.</small>
                </div>
              </li>
              <li class="trust-item">
                <span class="trust-icon trust-icon-purple">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <div>
                  <strong>Secure payments</strong>
                  <small>Encrypted transactions, no card details stored on our servers.</small>
                </div>
              </li>
              <li class="trust-item">
                <span class="trust-icon trust-icon-amber">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
                <div>
                  <strong>Real-time updates</strong>
                  <small>Campaigns post progress so you can see your impact directly.</small>
                </div>
              </li>
            </ul>
            <div class="trust-stats">
              <div><strong>300+</strong><span>families helped</span></div>
              <div><strong>120+</strong><span>verified donors</span></div>
              <div><strong>5%</strong><span>member fee</span></div>
            </div>
          </div>
        </div>
      </section>

      <section id="discover" class="section">
        <div class="section-header">
          <h2>Discover Campaigns</h2>
          <p>Browse active campaigns and make a donation.</p>
        </div>

        <!-- Redesigned filter toolbar: search + sort + chip row + advanced -->
        <div class="discover-toolbar">
          <label class="discover-search-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="discover-search" placeholder="Search by name, keyword, or category…" autocomplete="off">
            <button type="button" class="discover-search-clear" id="discover-search-clear" aria-label="Clear search" hidden>&times;</button>
          </label>
          <div class="discover-sort-wrap">
            <label for="discover-sort" class="discover-sort-label">Sort</label>
            <select id="discover-sort">
              <option value="newest">Newest first</option>
              <option value="most-funded">Most funded</option>
              <option value="closest">Closest to goal</option>
              <option value="urgent">Most urgent</option>
            </select>
          </div>
        </div>

        <div class="discover-filters">
          <div class="discover-cat-row">
            <button class="cat-chip active" data-category="All" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
              All <span class="cat-chip-count" data-count-for="All">0</span>
            </button>
            <button class="cat-chip" data-category="Medical" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
              Medical <span class="cat-chip-count" data-count-for="Medical">0</span>
            </button>
            <button class="cat-chip" data-category="Educational" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
              Education <span class="cat-chip-count" data-count-for="Educational">0</span>
            </button>
            <button class="cat-chip" data-category="Food" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
              Food <span class="cat-chip-count" data-count-for="Food">0</span>
            </button>
            <button class="cat-chip" data-category="Shelter" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              Shelter <span class="cat-chip-count" data-count-for="Shelter">0</span>
            </button>
            <button class="cat-chip cat-chip-more" id="filter-toggle" type="button" aria-expanded="false" aria-controls="filter-advanced">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
              More filters
              <span class="filter-active-count" id="filter-active-count" hidden>0</span>
            </button>
          </div>

          <div class="discover-advanced" id="filter-advanced" hidden>
            <div class="adv-group">
              <span class="adv-label">Urgency</span>
              <div class="adv-pills" data-filter="urgency">
                <button class="adv-pill active" data-urgency="All" type="button">All</button>
                <button class="adv-pill" data-urgency="Urgent" type="button">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="10"/></svg>
                  Urgent only
                </button>
              </div>
            </div>
            <div class="adv-group">
              <label class="adv-label" for="discover-location-select">Location</label>
              <select id="discover-location-select">
                <option value="all" selected>All locations</option>
                <option value="Beirut">Beirut</option>
                <option value="Tripoli">Tripoli</option>
                <option value="Akkar">Akkar</option>
                <option value="Batroun">Batroun</option>
                <option value="Baalbek">Baalbek</option>
                <option value="South_lb">South Lebanon</option>
              </select>
            </div>
            <button type="button" class="adv-reset" id="filter-reset">Reset all</button>
          </div>

          <div class="discover-active-filters" id="discover-active-filters"></div>
        </div>

        <div class="discover-result-bar">
          <span class="discover-result-count" id="discover-result-count">— campaigns</span>
        </div>
        <div class="discover-empty" id="discover-empty" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <h3>No campaigns match your filters</h3>
          <p>Try clearing a filter or searching for something different.</p>
          <button type="button" class="adv-reset" onclick="document.getElementById('filter-reset').click()">Reset filters</button>
        </div>
        <div id="discover-grid" class="campaign-grid">
          <?php if (!$discoverCampaigns): ?>
            <p class="activity-empty" style="grid-column:1/-1;">No active campaigns yet. Check back soon.</p>
          <?php else: foreach ($discoverCampaigns as $camp): include $partial . 'campaign-card.php'; endforeach; endif; ?>
        </div>
      </section>

      <section id="profile" class="section">
        <div class="section-header">
          <h2>My Profile</h2>
          <p>Update your personal information and photo.</p>
        </div>
        <div class="card profile-card">
          <form action="../php/users/update-profile.php" method="POST" enctype="multipart/form-data" class="profile-header">
            <?= Csrf::field() ?>
            <div class="avatar-column">
              <label class="avatar-upload" for="avatar-input">
                <img src="../images/user-profile.svg" alt="avatar" id="avatar-preview">
                <span class="avatar-overlay">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                  <span>Change Photo</span>
                </span>
                <input type="file" name="profile_image" id="avatar-input" accept="image/*" hidden>
              </label>
              <h3 class="profile-display-name is-placeholder" id="profile-display-name">Your Name</h3>
              <!--
                Role badge under the avatar. Label text is driven by the body class
                (role-donor | role-taker | role-org) set by PHP — see userhome.css.
                No JS or inline PHP echo needed; PHP already sets <body class="role-…">.
              -->
              <span class="profile-role-badge" aria-label="Account role"></span>
            </div>
            <div class="profile-fields">
              <label for="profile-name">Full Name</label>
              <input type="text" name="full_name" id="profile-name" placeholder="Your name" maxlength="50" value="<?= $displayName ?>">

              <label for="profile-bio">Bio</label>
              <textarea name="bio" id="profile-bio" placeholder="Tell us about yourself..." maxlength="250"><?= $bioValue ?></textarea>

              <button type="submit" class="btn btn-primary profile-save-btn">Save Changes</button>
            </div>
          </form>
        </div>
      </section>

      <section id="wallet" class="section">
        <div class="section-header">
          <h2>My Wallet</h2>
          <p>Manage your donation balance and view transaction history.</p>
        </div>
        <div class="card wallet-card">
          <div class="balance-display">
            <div class="balance-label">Available Balance</div>
            <div class="wallet-balance" id="wallet-balance">$<?= $walletBalance ?></div>
          </div>
          <form action="../php/wallet/top-up.php" method="POST" class="top-up-form">
            <?= Csrf::field() ?>
            <div class="preset-amounts">
              <button type="button" class="preset-btn" data-amount="10">$10</button>
              <button type="button" class="preset-btn" data-amount="25">$25</button>
              <button type="button" class="preset-btn" data-amount="50">$50</button>
              <button type="button" class="preset-btn" data-amount="100">$100</button>
            </div>
            <div class="top-up-input-row">
              <input type="number" name="amount" id="top-up-amount" placeholder="Or enter amount ($)" min="1" step="1" required>
              <button type="submit" class="btn btn-white">Add Funds</button>
            </div>
          </form>
        </div>
        <div class="card wallet-cashout-card">
          <div class="wallet-cashout-head">
            <div>
              <h3>Cash Out</h3>
              <p>Transfer available balance to Whish Money or a bank/card payout route.</p>
            </div>
            <span class="wallet-fee-pill">5% cash-out fee</span>
          </div>

          <form action="../php/wallet/cash-out.php" method="POST" class="cashout-form">
            <?= Csrf::field() ?>
            <div class="cashout-methods" role="radiogroup" aria-label="Cash out method">
              <label class="cashout-method active">
                <input type="radio" name="cashout_method" value="whish" checked>
                <span class="cashout-method-icon">W</span>
                <span>
                  <strong>Whish Money</strong>
                  <small>Send to a Whish-registered Lebanese mobile number.</small>
                </span>
              </label>
              <label class="cashout-method">
                <input type="radio" name="cashout_method" value="bank_card">
                <span class="cashout-method-icon card-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                </span>
                <span>
                  <strong>Bank / card payout</strong>
                  <small>Use a verified bank or payout card destination.</small>
                </span>
              </label>
            </div>

            <div class="cashout-grid">
              <label>
                Amount ($)
                <input type="number" name="cashout_amount" min="1" step="1" placeholder="50">
              </label>
              <label>
                Destination
                <input type="text" name="cashout_destination" placeholder="+961 Whish number or payout reference">
              </label>
            </div>

            <div class="cashout-note">
              <strong>Processing time:</strong> cash-out and transfer requests usually take <strong>2-3 business days</strong>. PHP will confirm balance, calculate the 5% fee, and track payout status.
            </div>

            <button type="submit" class="btn btn-primary">Request Cash Out</button>
          </form>
        </div>
        <div class="card">
          <h3>Transaction History</h3>
          <ul class="transaction-list" id="transaction-list">
            <?php if (!$walletTransactions): ?>
            <li class="activity-empty">No transactions yet.</li>
            <?php else: foreach ($walletTransactions as $tx): include $partial . 'wallet-tx.php'; endforeach; endif; ?>
            </ul>
        </div>
      </section>

      <section id="activity" class="section">
        <div class="section-header">
          <h2>Activity &amp; Bills</h2>
          <p>Track payments, support fees, wallet movement, and receipt records.</p>
        </div>

        <div class="activity-bills-summary">
          <div class="activity-total-card">
            <span>Total paid</span>
            <strong>$<?= number_format($activityTotalPaid, 2) ?></strong>
            <small>Across donations and wallet top-ups</small>
          </div>
          <div class="activity-total-card">
            <span>Sawa fees</span>
            <strong>$<?= number_format($activityTotalFees, 2) ?></strong>
            <small>Required support fees shown before checkout</small>
          </div>
          <div class="activity-total-card">
            <span>Receipts</span>
            <strong><?= $receiptCount ?></strong>
            <small>Ready for PDF download</small>
          </div>
        </div>

        <div class="activity-bills-layout">
          <div class="activity-ledger card">
            <div class="activity-ledger-head">
              <h3>Recent Activity</h3>
              <div class="activity-filter-tabs" role="tablist" aria-label="Activity filters">
                <button type="button" class="active">All</button>
                <button type="button">Paid</button>
                <button type="button">Fees</button>
              </div>
            </div>

            <!-- PHP later: replace these rows from user_transactions / donations / wallet_events. -->
            <div class="activity-ledger-list" id="activity-ledger-list">
              <?php if (!$activityRows): ?>
                <p class="activity-empty">No activity yet.</p>
              <?php else: $i=0; foreach ($activityRows as $row): $row['_first'] = ($i===0); $i++; include $partial . 'activity-row.php'; endforeach; endif; ?>
            </div>
          </div>

          <aside class="bill-preview card" id="bill-preview" aria-label="Selected bill preview">
            <!-- PHP later: render selected receipt lines server-side and generate signed PDF. -->
            <div class="bill-paper">
              <div class="bill-brand-row">
                <span class="bill-logo-mark">S</span>
                <span>
                  <strong>Sawa</strong>
                  <small>Universal payment receipt</small>
                </span>
                <span class="bill-standard-badge">ISO-style record</span>
              </div>

              <div class="bill-parties">
                <span>
                  <small>Issued by</small>
                  <strong>Sawa Together</strong>
                  <em>Lebanon - Tripoli</em>
                </span>
                <span>
                  <small>Paid to</small>
                  <strong id="bill-recipient">Batroun School Renovation</strong>
                  <em>Verified campaign</em>
                </span>
              </div>

              <div class="bill-meta-grid">
                <span>
                  <small>Bill ID</small>
                  <strong id="bill-id">SAWA-2026-0001</strong>
                </span>
                <span>
                  <small>Date</small>
                  <strong id="bill-date">June 6, 2026 at 4:32 PM</strong>
                </span>
                <span>
                  <small>Status</small>
                  <strong class="bill-status-paid">Paid</strong>
                </span>
                <span>
                  <small>Method</small>
                  <strong id="bill-method">Whish Money</strong>
                </span>
                <span>
                  <small>Provider ref</small>
                  <strong id="bill-provider-ref">WH-9F42-7710</strong>
                </span>
                <span>
                  <small>Currency</small>
                  <strong>USD</strong>
                </span>
              </div>

              <div class="bill-lines">
                <div>
                  <span>Donation</span>
                  <strong>$75.00</strong>
                </div>
                <div>
                  <span>Sawa support fee</span>
                  <strong>$7.50</strong>
                </div>
                <div>
                  <span>Provider processing</span>
                  <strong>Confirmed by gateway</strong>
                </div>
                <div class="bill-total-line">
                  <span>Total paid</span>
                  <strong id="bill-total">$82.50</strong>
                </div>
              </div>

              <div class="bill-verification">
                <span>
                  <small>Receipt checksum</small>
                  <strong>SHA256: 7D4C...91AF</strong>
                </span>
                <span>
                  <small>Generated</small>
                  <strong>Server timestamp after payment callback</strong>
                </span>
              </div>

              <div class="bill-foot-note">
                <span>Final payment reference and provider fee are confirmed by PHP after checkout returns.</span>
              </div>
            </div>

            <div class="bill-actions">
              <button type="button" class="btn btn-primary" id="bill-download-btn" data-bill-download="SAWA-2026-0001">
                Download PDF
              </button>
              <button type="button" class="btn btn-outline" id="bill-print-btn">
                Print
              </button>
            </div>
          </aside>
        </div>
      </section>

      <section id="campaign-new" class="section">
        <div class="section-header">
          <h2>Create Campaign</h2>
          <p>Launch a new fundraising campaign. Fill in all the details below.</p>
        </div>
        <div class="card">
          <form action="../php/campaigns/create.php" method="POST" enctype="multipart/form-data" class="campaign-form">
            <?= Csrf::field() ?>
            <label for="camp-title">Campaign Title</label>
            <input type="text" name="title" id="camp-title" placeholder="e.g. Help Ahmed's Family" maxlength="100" required>

            <label for="camp-desc">Description</label>
            <textarea name="description" id="camp-desc" placeholder="Tell your story..." required></textarea>

            <div class="form-row">
              <div>
                <label for="camp-goal">Goal Amount ($)</label>
                <input type="number" name="goal" id="camp-goal" placeholder="1000" min="1" required>
              </div>
              <div>
                <label for="camp-category">Category</label>
                <select name="category" id="camp-category" required>
                  <option value="">Select category</option>
                  <option value="Medical">Medical</option>
                  <option value="Educational">Educational</option>
                  <option value="Food">Food</option>
                  <option value="Shelter">Shelter</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>

            <label for="camp-location">Location</label>
            <select name="location" id="camp-location" required>
              <option value="">Select location</option>
              <option value="Beirut">Beirut</option>
              <option value="Tripoli">Tripoli</option>
              <option value="Akkar">Akkar</option>
              <option value="Batroun">Batroun</option>
              <option value="Baalbek">Baalbek</option>
              <option value="South_lb">South Lebanon</option>
            </select>

            <span class="label_text" id="camp-images-label">Campaign Images</span>
            <p class="campaign-upload-hint" id="camp-images-help">Add up to 6 images. The main image appears on the campaign card; all images appear in the slideshow when the campaign is opened.</p>
            <label class="campaign-upload campaign-upload-multi" for="camp-image-input">
              <div id="camp-upload-preview" class="campaign-upload-preview is-empty">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Click to upload campaign images</span>
              </div>
              <input type="file" name="camp_images[]" id="camp-image-input" accept="image/*" multiple data-max-files="6" aria-labelledby="camp-images-label" aria-describedby="camp-images-help" hidden>
              <input type="hidden" name="cover_image_index" id="camp-cover-index" value="0">
            </label>
            <p class="campaign-upload-count" id="camp-upload-count">0 / 6 images selected</p>

            <button type="submit" class="btn btn-green">Launch Campaign</button>
          </form>
        </div>
      </section>

      <section id="campaigns" class="section">
        <div class="section-header">
          <h2>My Campaigns</h2>
          <p>All campaigns you have created.</p>
        </div>
        <div id="my-campaigns-list" class="campaign-grid">
          <?php if (!$myCampaigns): ?>
          <div class="empty-state">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            <h3>No Campaigns Yet</h3>
            <p>Start a campaign to help those in need.</p>
            <button class="btn btn-primary" onclick="document.querySelector('[data-section=campaign-new]').click()">Create Your First Campaign</button>
          </div>
          <?php else: foreach ($myCampaigns as $camp): include $partial . 'campaign-card.php'; endforeach; endif; ?>
        </div>
      </section>

      <section id="messages" class="section">
        <div class="section-header">
          <h2>Messages</h2>
          <p>Chat directly with donors, recipients, and verified organisations.</p>
        </div>

        <div class="messages-shell card">
          <aside class="messages-list" aria-label="Conversation list">
            <div class="messages-list-head">
              <h3>Inbox</h3>
              <button type="button" class="messages-new-btn" aria-label="New message">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
              </button>
            </div>
            <label class="messages-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="search" placeholder="Search chats">
            </label>
            <?php if (!$inboxThreads): ?>
            <p class="empty-inline" style="padding:1rem;">No conversations yet.</p>
            <?php else: foreach ($inboxThreads as $thread): include $partial . 'message-thread.php'; endforeach; endif; ?>
          </aside>

          <div class="chat-panel" aria-label="Selected conversation">
            <div class="chat-head">
              <?php if ($activeThread):
                $headName = htmlspecialchars((string) $activeThread['other_name'], ENT_QUOTES, 'UTF-8');
                $headInitials = Format::initials((string) $activeThread['other_name']);
                $headRole = (string) ($activeThread['other_role'] ?? 'user');
                $headAvatarClass = match ($headRole) {
                    'beneficiary' => ' recipient',
                    default => $headRole === 'organisation' ? '' : ' donor',
                };
              ?>
              <span class="message-avatar<?= $headAvatarClass ?>"><?= htmlspecialchars($headInitials, ENT_QUOTES, 'UTF-8') ?></span>
              <span>
                <strong><?= $headName ?></strong>
                <small>Direct message</small>
              </span>
              <?php else: ?>
              <span class="message-avatar">—</span>
              <span><strong>Select a conversation</strong><small>Choose a thread from your inbox</small></span>
              <?php endif; ?>
            </div>

            <div class="chat-body">
              <?php if (!$activeThread): ?>
              <p class="empty-inline">Your messages will appear here.</p>
              <?php elseif (!$threadMessages): ?>
              <p class="empty-inline">No messages yet. Say hello.</p>
              <?php else: foreach ($threadMessages as $msg):
                $outgoing = (int) $msg['sender_id'] === $userId;
                $bubbleClass = $outgoing ? 'outgoing' : 'incoming';
                $body = htmlspecialchars((string) $msg['body'], ENT_QUOTES, 'UTF-8');
                $time = date('g:i A', strtotime((string) $msg['created_at']));
              ?>
              <div class="chat-bubble <?= $bubbleClass ?>">
                <p><?= $body ?></p>
                <time><?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?></time>
              </div>
              <?php endforeach; endif; ?>
            </div>

            <form class="chat-compose" action="../php/messaging/send.php" method="POST">
              <?= Csrf::field() ?>
              <input type="hidden" name="thread_id" value="<?= $activeThreadId > 0 ? $activeThreadId : '' ?>">
              <button type="button" class="chat-icon-btn" aria-label="Attach file">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
              </button>
              <input type="text" name="message" placeholder="Write a message..." autocomplete="off">
              <button type="submit" class="btn btn-primary chat-send-btn">Send</button>
            </form>
          </div>
        </div>
      </section>

    </main>
  </div>

  <div class="modal-overlay" id="donate-modal">
    <div class="modal">
      <button class="modal-close" type="button">&times;</button>

      <!-- Step 1: Select amount -->
      <div class="modal-step" id="modal-step-1">
        <h3 id="modal-camp-title">Donate</h3>

        <div class="modal-goal-info" id="modal-goal-info">
          <div class="progress-bar" style="margin-bottom:0.6rem;">
            <div class="progress-fill" id="modal-progress-fill" style="width:0%"></div>
          </div>
          <div class="modal-goal-text">
            <span id="modal-raised" class="raised">$0</span>
            raised of
            <span id="modal-goal">$0</span> goal
            &mdash; <span id="modal-remaining" style="color:var(--color-primary);font-weight:600;">$0 remaining</span>
          </div>
        </div>

        <form id="donate-form" action="../php/donations/donate.php" method="POST">
          <?= Csrf::field() ?>
          <input type="hidden" name="campaign_id" id="modal-camp-id-hidden">
          <input type="hidden" name="payment_method" id="modal-payment-method" value="whish">
          <input type="hidden" name="cover_platform_fee" value="1">
          <label for="modal-amount">Donation Amount ($)</label>
          <div class="preset-amounts modal-presets">
            <button type="button" class="preset-btn" data-amount="5">$5</button>
            <button type="button" class="preset-btn" data-amount="10">$10</button>
            <button type="button" class="preset-btn" data-amount="25">$25</button>
            <button type="button" class="preset-btn" data-amount="50">$50</button>
          </div>
          <input type="number" name="amount" id="modal-amount" placeholder="Or enter amount" min="1" step="1">

          <div class="donation-fee-summary">
            <div><span>Your donation</span><strong id="modal-summary-amount">$0</strong></div>
            <div><span>Sawa support fee</span><strong class="fee-rate-pill">Shown at confirmation</strong></div>
          </div>

          <!-- Guest-only fields (shown when body.is-guest) -->
          <div class="guest-only" style="display: contents;">
            <label for="modal-donor-name" class="guest-only-label">Your name</label>
            <input type="text" name="donor_name" id="modal-donor-name" placeholder="Full name" autocomplete="name">
            <label for="modal-donor-email" class="guest-only-label">Email (optional)</label>
            <input type="email" name="donor_email" id="modal-donor-email" placeholder="you@example.com" autocomplete="email">
            <label for="modal-donor-phone" class="guest-only-label">Mobile number (optional)</label>
            <input type="tel" name="donor_phone" id="modal-donor-phone" placeholder="+961 XX XXX XXX" autocomplete="tel">
          </div>
        </form>

        <div class="modal-actions">
          <button type="button" class="btn btn-outline" id="modal-cancel">Cancel</button>
          <button type="button" class="btn btn-primary modal-next-btn" id="modal-review-btn">
            Continue
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </div>
      </div>

      <!-- Step 2: Payment method -->
      <div class="modal-step" id="modal-step-payment" hidden>
        <h3>Choose Payment</h3>
        <p class="modal-sub">Sawa redirects to a trusted checkout. Card details are never typed into this frontend.</p>

        <fieldset class="payment-method-list">
          <legend>Payment method</legend>
          <label class="payment-method-option is-recommended">
            <input type="radio" name="payment_method_choice" value="whish" checked>
            <span class="payment-method-icon payment-method-icon--whish" aria-hidden="true">W</span>
            <span class="payment-method-body">
              <strong>Whish Money</strong>
              <small>Pay from your Lebanese mobile wallet — instant.</small>
              <span class="payment-method-fee">10% Sawa fee</span>
              <span class="payment-method-extra">
                <span class="pm-extra-row"><span class="pm-extra-label">How it works</span><span>You'll be redirected to Whish to confirm the payment.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">You'll need</span><span>Your Whish-registered Lebanese mobile number.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Settlement</span><span>Funds arrive within a few minutes.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Whish fee</span><span>A small transfer fee from Whish is shown before checkout.</span></span>
              </span>
            </span>
          </label>
          <label class="payment-method-option">
            <input type="radio" name="payment_method_choice" value="hosted_checkout">
            <span class="payment-method-icon payment-method-icon--card" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </span>
            <span class="payment-method-body">
              <strong>Visa / Mastercard</strong>
              <small>Secure hosted checkout — your card details never touch Sawa.</small>
              <span class="payment-method-fee">10% Sawa fee</span>
              <span class="payment-method-extra">
                <span class="pm-extra-row"><span class="pm-extra-label">Accepted</span><span>Visa, Mastercard, Amex.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Security</span><span>3-D Secure (one-time code from your bank) may be required.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Where</span><span>You pay on the provider's hosted page, then return here.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Card fee</span><span>Card processing fee from the provider is shown before payment.</span></span>
              </span>
            </span>
          </label>
          <label class="payment-method-option is-best auth-only">
            <input type="radio" name="payment_method_choice" value="wallet">
            <span class="payment-method-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </span>
            <span class="payment-method-body">
              <strong>Sawa Wallet</strong>
              <small>Use your account balance — instant, members only.</small>
              <span class="payment-method-fee payment-method-fee--best">5% Sawa fee &middot; saves 5%</span>
              <span class="payment-method-extra">
                <span class="pm-extra-row"><span class="pm-extra-label">Source</span><span>Uses your Sawa wallet balance only.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Speed</span><span>Instant — no provider redirect.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Top up</span><span>Add funds from the Wallet section before donating.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Why cheaper</span><span>Members pay 5% on wallet donations. Direct payment (Whish/Visa) is 10%.</span></span>
              </span>
            </span>
          </label>
        </fieldset>

        <div class="payment-method-details" id="payment-method-details" aria-live="polite"></div>

        <div class="modal-cover-fee modal-required-fee" role="note">
          <span class="required-fee-icon" aria-hidden="true">%</span>
          <span>A required <strong id="cover-fee-rate-label">10%</strong> Sawa support fee is added at checkout.</span>
        </div>

        <div class="payment-trust">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>Payment status is confirmed after the provider redirects back.</span>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-outline modal-back-btn" id="payment-back-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
            Back
          </button>
          <button type="button" class="btn btn-primary modal-next-btn" id="payment-next-btn">
            Review
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </div>
      </div>

      <!-- Step 3: Confirm — review-style summary card -->
      <div class="modal-step modal-confirm" id="modal-step-2" hidden>
        <div class="modal-confirm-icon" id="review-method-icon" aria-hidden="true">W</div>
        <p class="modal-confirm-label">You're about to donate</p>
        <div class="modal-confirm-amount" id="review-amount-display">$0</div>
        <p class="modal-confirm-to">to <strong id="review-campaign-display">—</strong></p>

        <!-- Fee breakdown -->
        <ul class="modal-confirm-breakdown">
          <li>
            <span>Your donation</span>
            <strong id="review-donation-line">$0</strong>
          </li>
          <li>
            <span>
              Sawa support fee
              <small id="review-fee-rate">(5%)</small>
            </span>
            <strong id="review-fee-line">$0</strong>
          </li>
          <li class="modal-confirm-total">
            <span>You'll be charged</span>
            <strong id="review-total-line">$0</strong>
          </li>
        </ul>

        <!-- Payment chip -->
        <div class="modal-confirm-method-chip" id="review-payment-display">
          <span class="modal-confirm-method-icon" aria-hidden="true"></span>
          <span class="modal-confirm-method-text">Paying with Whish</span>
        </div>

        <p class="modal-confirm-note">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Encrypted checkout &middot; final fee confirmed by the provider.
        </p>

        <div class="modal-actions">
          <button type="button" class="btn btn-outline modal-back-btn" id="review-back-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
            Back
          </button>
          <button type="submit" class="btn btn-primary modal-next-btn" form="donate-form" id="confirm-donate-btn" data-loading-label="Redirecting...">
            Continue to Payment
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </div>
      </div>

      <!-- Step 4: Payment status (shown only after PHP redirects with a fixed status code) -->
      <div class="modal-step modal-thanks" id="modal-step-thanks" hidden>
        <div class="thanks-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h3 id="payment-status-title">Payment status</h3>
        <p class="thanks-amount-line" id="payment-status-message">Your confirmed, pending, failed, or cancelled payment result will appear here after the provider redirects back.</p>
        <p class="thanks-receipt">Create an account to keep receipts, wallet history, and saved campaigns in one place.</p>

        <!-- Guest-only sign-up CTA shown after donating -->
        <div class="thanks-guest-cta guest-only">
          <p class="thanks-cta-title">Want to track this donation?</p>
          <p class="thanks-cta-body">Sign up for free to manage a <strong>wallet</strong>, keep <strong>receipts</strong>, and follow campaign <strong>updates</strong>.</p>
          <a href="signup.php" class="btn btn-primary thanks-cta-btn">Sign Up Free
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
        </div>

        <div class="modal-actions" style="justify-content:center;">
          <button type="button" class="btn btn-outline" id="thanks-close-btn">Close</button>
        </div>
      </div>

    </div>
  </div>

  <!-- Donors modal — opens when a campaign card is clicked -->
  <div class="modal-overlay" id="donors-modal">
    <div class="modal donors-modal-content">
      <button class="modal-close" type="button" id="donors-modal-close" aria-label="Close">&times;</button>

      <div class="donors-modal-head">
        <h3 id="donors-modal-title">Donors</h3>
        <p id="donors-modal-subtitle">People who supported this campaign</p>
        <div class="donors-summary">
          <div class="donors-summary-item">
            <strong id="donors-total-amount">$0</strong>
            <small>Total raised</small>
          </div>
          <div class="donors-summary-divider"></div>
          <div class="donors-summary-item">
            <strong id="donors-total-count">0</strong>
            <small>Donors</small>
          </div>
        </div>
      </div>

      <ul class="donors-list" id="donors-list">
        <!-- Placeholder rows — PHP will replace with a loop over real donations for this campaign -->
        <li class="donor-row">
          <span class="donor-avatar">VD</span>
          <div class="donor-meta">
            <strong>Verified donor</strong>
            <small>2 days ago</small>
          </div>
          <span class="donor-amount">$50</span>
        </li>
        <li class="donor-row">
          <span class="donor-avatar">CD</span>
          <div class="donor-meta">
            <strong>Community donor</strong>
            <small>3 days ago</small>
          </div>
          <span class="donor-amount">$120</span>
        </li>
        <li class="donor-row">
          <span class="donor-avatar">SD</span>
          <div class="donor-meta">
            <strong>Sawa donor</strong>
            <small>4 days ago</small>
          </div>
          <span class="donor-amount">$25</span>
        </li>
        <li class="donor-row">
          <span class="donor-avatar donor-anon">?</span>
          <div class="donor-meta">
            <strong>Anonymous</strong>
            <small>5 days ago</small>
          </div>
          <span class="donor-amount">$200</span>
        </li>
        <li class="donor-row">
          <span class="donor-avatar">VD</span>
          <div class="donor-meta">
            <strong>Verified donor</strong>
            <small>1 week ago</small>
          </div>
          <span class="donor-amount">$30</span>
        </li>
        <li class="donor-row">
          <span class="donor-avatar">CD</span>
          <div class="donor-meta">
            <strong>Community donor</strong>
            <small>1 week ago</small>
          </div>
          <span class="donor-amount">$75</span>
        </li>
        <li class="donor-row">
          <span class="donor-avatar">SD</span>
          <div class="donor-meta">
            <strong>Sawa donor</strong>
            <small>2 weeks ago</small>
          </div>
          <span class="donor-amount">$15</span>
        </li>
      </ul>

      <div class="donors-modal-foot">
        <button type="button" class="btn btn-primary" id="donors-modal-donate">Donate to this campaign</button>
      </div>
    </div>
  </div>

  <!-- Campaign detail modal — opens when a card is clicked; richer than donors modal -->
  <div class="modal-overlay" id="campaign-modal" aria-hidden="true">
    <div class="modal camp-modal" role="dialog" aria-modal="true" aria-labelledby="cm-title">
      <button class="modal-close" type="button" id="cm-close" aria-label="Close">&times;</button>

      <!-- Image slideshow (auto-built from data-images on the source card) -->
      <div class="cm-slideshow" id="cm-slideshow">
        <div class="cm-slides" id="cm-slides"></div>
        <button type="button" class="cm-slide-nav cm-slide-prev" id="cm-prev" aria-label="Previous image" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <button type="button" class="cm-slide-nav cm-slide-next" id="cm-next" aria-label="Next image" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        <div class="cm-dots" id="cm-dots" hidden></div>
        <span class="cm-badge cm-urgent" id="cm-urgent" hidden>Urgent</span>
        <span class="cm-badge cm-category" id="cm-category"></span>
      </div>

      <div class="cm-body">
        <h3 class="cm-title" id="cm-title">Campaign</h3>

        <div class="cm-meta-row">
          <span class="cm-meta-item" id="cm-location-wrap" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span id="cm-location">—</span>
          </span>
          <span class="cm-meta-item" id="cm-days-wrap" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span id="cm-days">— days left</span>
          </span>
          <span class="cm-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <span id="cm-donor-count">0 donors</span>
          </span>
        </div>

        <div class="cm-creator">
          <span class="cm-creator-avatar" id="cm-creator-avatar" aria-hidden="true">VO</span>
          <span class="cm-creator-meta">
            <small>Created by</small>
            <strong id="cm-creator-name">Verified organisation
              <span class="cm-verified" id="cm-verified" title="Verified creator" aria-label="Verified">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0l3 3 4 1 1 4 3 3-3 3-1 4-4 1-3 3-3-3-4-1-1-4-3-3 3-3 1-4 4-1z"/></svg>
                <svg class="cm-verified-tick" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
              </span>
            </strong>
          </span>
          <a class="cm-message-btn auth-only" id="cm-message-btn" href="#" aria-label="Message the campaign creator">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            <span>Message</span>
          </a>
        </div>

        <div class="cm-progress">
          <div class="cm-progress-figures">
            <span><strong id="cm-raised">$0</strong> raised</span>
            <span class="cm-progress-pct" id="cm-pct">0%</span>
            <span>of <strong id="cm-goal">$0</strong> goal</span>
          </div>
          <div class="progress-bar"><div class="progress-fill" id="cm-progress-fill" style="width:0%"></div></div>
        </div>

        <div class="cm-tabs" role="tablist">
          <button type="button" class="cm-tab is-active" data-cm-tab="about" role="tab" aria-selected="true">About</button>
          <button type="button" class="cm-tab" data-cm-tab="donors" role="tab" aria-selected="false">Donors</button>
          <button type="button" class="cm-tab" data-cm-tab="comments" role="tab" aria-selected="false">
            Comments <span class="cm-tab-badge" id="cm-comments-count" hidden>0</span>
          </button>
        </div>

        <div class="cm-tab-panels">
          <div class="cm-panel is-active" data-cm-panel="about" role="tabpanel">
            <p class="cm-desc" id="cm-desc">—</p>
          </div>
          <div class="cm-panel" data-cm-panel="donors" role="tabpanel" hidden>
            <ul class="donors-list" id="cm-donors-list">
              <li class="activity-empty">Loading donors…</li>
            </ul>
          </div>
          <div class="cm-panel cm-comments-panel" data-cm-panel="comments" role="tabpanel" hidden>
            <!-- Comment composer (auth-only — PHP swaps action to comments/create.php) -->
            <form class="cm-comment-form auth-only" id="cm-comment-form" action="../php/engagement/comment.php" method="POST">
              <?= Csrf::field() ?>
              <input type="hidden" name="campaign_id" id="cm-comment-camp-id">
              <div class="cm-comment-form-row">
                <img class="cm-comment-form-avatar" src="../images/user-profile.svg" alt="" aria-hidden="true">
                <textarea name="body" id="cm-comment-input" placeholder="Add a comment — be kind." maxlength="500" rows="2" required></textarea>
              </div>
              <div class="cm-comment-form-foot">
                <span class="cm-comment-counter" id="cm-comment-counter">0 / 500</span>
                <button type="submit" class="btn btn-primary cm-comment-submit">Post</button>
              </div>
            </form>
            <!-- Guest CTA (shown when body.is-guest) -->
            <div class="cm-comment-guest-cta guest-only">
              <p>Sign in to leave a comment for the campaign.</p>
              <a href="signup.php" class="btn btn-primary">Sign Up Free</a>
            </div>
            <!-- Comment feed — PHP populates this list (Twitter/X-style thread) -->
            <ul class="cm-comments-list" id="cm-comments-list">
              <li class="activity-empty">No comments yet — be the first to post.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="cm-footer">
        <button type="button" class="btn btn-outline" id="cm-share-btn">Share</button>
        <button type="button" class="btn btn-primary" id="cm-donate-btn">Donate to this campaign</button>
      </div>
    </div>
  </div>

  <div id="toast" class="toast"></div>

  <!-- Bottom nav (mobile only) -->
  <nav class="bottom-nav" id="bottom-nav">
    <button class="bottom-nav-item active" data-section="dashboard">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Home</span>
    </button>
    <button class="bottom-nav-item" data-section="discover">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <span>Discover</span>
    </button>
    <button class="bottom-nav-item bottom-nav-fab" data-section="campaign-new" title="New Campaign" aria-label="Create new campaign">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
    </button>
    <button class="bottom-nav-item" data-section="messages">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.1" d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.1" d="M8 9h8M8 13h5"/></svg>
      <span>Chat</span>
    </button>
    <button class="bottom-nav-item" data-section="wallet">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
      <span>Wallet</span>
    </button>
    <button class="bottom-nav-item" data-section="profile">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profile</span>
    </button>
  </nav>

  <div class="auth-overlay" id="auth-overlay">
    <div class="auth-modal">
      <button type="button" class="auth-modal-close" id="auth-modal-close" aria-label="Close">&times;</button>
      <div class="auth-logo-wrap">
        <img src="../images/sawa_v2.svg" alt="Sawa">
      </div>
      <h2 class="auth-title">Welcome to Sawa</h2>
      <p class="auth-subtitle">Sign in or create a free account to access your dashboard, support campaigns, and make a real difference.</p>
      <div class="auth-links">
        <a href="signup.php" class="auth-btn auth-btn-primary">Sign Up Free</a>
        <a href="login.php" class="auth-btn auth-btn-outline">Log In</a>
      </div>
      <button type="button" class="auth-modal-cancel" id="auth-modal-cancel">Keep browsing as guest</button>
    </div>
  </div>

  <!-- ─── Page footer (guest-only — auth dashboard doesn't need marketing footer) ─── -->
  <footer class="site-footer guest-only">
    <div class="site-footer-inner">
      <div class="site-footer-brand">
        <a href="../index.html" class="site-brand">
          <span class="site-brand-mark"><img src="../images/sawa_v2.svg" alt=""></span>
          <span class="site-brand-name">Sawa</span>
        </a>
        <p class="site-footer-mission">Connecting Lebanese donors with families in need — transparently and directly, with zero intermediaries.</p>
        <div class="site-footer-social" aria-label="Sawa social links">
          <a href="https://www.facebook.com/sawatogether" target="_blank" rel="noopener" aria-label="Facebook">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          </a>
          <a href="https://www.instagram.com/sawatogether" target="_blank" rel="noopener" aria-label="Instagram">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
          </a>
          <a href="mailto:sawatogether961@gmail.com" aria-label="Email Sawa">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.91 12 9.818l6.545-4.91 1.528-1.418C21.691 2.28 24 3.434 24 5.457z"/></svg>
          </a>
        </div>
      </div>

      <div class="site-footer-col">
        <h4>Browse</h4>
        <button type="button" data-jump="discover" data-category="All">All campaigns</button>
        <button type="button" data-jump="discover" data-category="Medical">Medical</button>
        <button type="button" data-jump="discover" data-category="Food">Food</button>
        <button type="button" data-jump="discover" data-category="Shelter">Shelter</button>
        <button type="button" data-jump="discover" data-category="Educational">Education</button>
      </div>

      <div class="site-footer-col">
        <h4>Sawa</h4>
        <a href="about-us.html">About us</a>
        <a href="about-us.html#how-to-donate">How to donate</a>
        <a href="signup.php">Become a creator</a>
        <a href="about-us.html#faq">FAQs</a>
      </div>

      <div class="site-footer-col">
        <h4>Trust &amp; legal</h4>
        <a href="about-us.html#terms">Terms &amp; conditions</a>
        <a href="about-us.html#privacy">Privacy policy</a>
        <a href="about-us.html#faq">How verification works</a>
        <a href="mailto:sawatogether961@gmail.com">Report a campaign</a>
      </div>

      <div class="site-footer-col">
        <h4>Contact</h4>
        <a href="tel:+96171612269">+961 71 61 22 69</a>
        <a href="mailto:sawatogether961@gmail.com">sawatogether961@gmail.com</a>
        <span class="site-footer-meta">Lebanon &mdash; Tripoli</span>
      </div>
    </div>
    <div class="site-footer-bottom">
      <p>&copy; 2026 Sawa. All rights reserved. Built in response to the Lebanese crisis.</p>
      <p class="site-footer-trust"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Secure donations &middot; SSL encrypted</p>
    </div>
  </footer>

  <script src="../js/userhome.js"></script>
</body>
</html>



