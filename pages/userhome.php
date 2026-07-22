<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/php/bootstrap.php';

$auth = Auth::check();
$user = $auth ? Auth::user() : [];
if ($auth && $user === []) {
    Auth::logout();
    $auth = false;
}

// Admins belong in the console, not the member dashboard. php/auth/login.php
// already sends role=admin to /admin, but nothing stopped an admin session
// loading this page directly — it then rendered the full donor dashboard,
// wallet and campaign cards included, for an account that has none of that.
if ($auth && Auth::role() === 'admin') {
    Response::redirect('admin');
}

$bodyClass = $auth ? 'is-auth ' . Auth::bodyRoleClass() : 'is-guest';
$displayName = htmlspecialchars((string) ($user['full_name'] ?? 'User'), ENT_QUOTES, 'UTF-8');
$avatarUrl = !empty($user['avatar_path'])
    ? htmlspecialchars(Upload::publicUrl((string) $user['avatar_path']), ENT_QUOTES, 'UTF-8')
    : '../images/user-profile.svg';
$bioValue = htmlspecialchars((string) ($user['bio'] ?? ''), ENT_QUOTES, 'UTF-8');
$bannerPath = (string) ($user['banner_path'] ?? '');
$bannerUrl = $bannerPath !== ''
    ? htmlspecialchars(Upload::publicUrl($bannerPath), ENT_QUOTES, 'UTF-8')
    : '';
// Built here rather than inline so the quoting stays readable: the attribute
// is delimited with double quotes and the CSS url() with single ones.
$bannerStyle = $bannerUrl !== '' ? " style=\"background-image: url('{$bannerUrl}')\"" : '';
$walletBalance = '0.00';
$userId = $auth ? Auth::id() : null;
$userRole = $auth ? ((string) ($user['role'] ?? Auth::role() ?? 'user')) : null;

if ($auth && $userId !== null) {
    $walletBalance = number_format(WalletService::balance($userId), 2);
}

$platformStats = CampaignService::stats();

// Hero "total raised" — real figure from CampaignService::stats(). Compact form
// ($17.4k) once past 10k so the tile does not overflow on narrow viewports.
$heroRaised = (float)$platformStats['raised'];
$heroRaisedLabel = $heroRaised >= 10000
    ? '$' . rtrim(rtrim(number_format($heroRaised / 1000, 1, '.', ''), '0'), '.') . 'k'
    : '$' . number_format($heroRaised);

// Guest-only "Live activity" feed. Public page, so donor identities are
// anonymised inside the service, not here.
$liveActivity = DonationService::recentPlatformActivity(6);

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
$donorTotals = ($auth && $userId !== null && $userRole === 'user')
    ? DonationService::donorTotals($userId)
    : ['total' => 0.0, 'families' => 0, 'this_month' => 0.0, 'last_month' => 0.0, 'delta_pct' => null, 'families_delta' => 0];
$memberSince = 'recently';
if ($auth && !empty($user['created_at'])) {
    $ts = strtotime((string) $user['created_at']);
    if ($ts) {
        $memberSince = date('M Y', $ts);
    }
}
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

$otherAccounts = Auth::check() ? Auth::otherAccounts() : [];

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
  <link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/userhome.css') ?>">
  <script src="<?= asset('js/theme.js') ?>"></script>
  <title>Dashboard — Sawa</title>
</head>
<body class="<?= $bodyClass ?>">
  <a class="skip-link" href="#main">Skip to content</a>
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
        <button class="site-nav-link is-active" type="button" data-jump="dashboard" aria-current="page">Home</button>
        <button class="site-nav-link" type="button" data-jump="discover">Browse</button>
        <a class="site-nav-link" href="about-us.php">About</a>
      </nav>
      <div class="site-header-auth">
        <a href="login.php" class="site-auth-link">Log In</a>
        <a href="signup.php" class="site-auth-btn">Sign Up Free</a>
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
      <button class="guest-drawer-link is-active" type="button" data-jump="dashboard" aria-current="page">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Home
      </button>
      <button class="guest-drawer-link" type="button" data-jump="discover">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Browse Campaigns
      </button>
      <a class="guest-drawer-link" href="about-us.php">
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

  <!-- Auth-only top header. Same visual structure on desktop AND mobile:
       chip on the left, nav links on the right. Mobile just hides the nav
       links via CSS because the bottom bar covers them. No mobile-only
       elements introduced — keeps the visual style consistent. -->
  <header class="site-header auth-only site-header--user-lead" role="banner">
    <div class="site-header-inner">
      <button class="site-header-user" id="nav-user-btn" type="button" aria-label="Open account menu">
        <img src="<?= $avatarUrl ?>" alt="" id="nav-avatar">
        <span class="site-header-user-text">
          <strong id="nav-name"><?= $displayName ?></strong>
          <small class="site-header-user-role" aria-hidden="true"></small>
        </span>
      </button>
      <nav class="site-header-nav" aria-label="Primary navigation">
        <button class="site-nav-link is-active" type="button" data-jump="dashboard" aria-current="page">Home</button>
        <button class="site-nav-link" type="button" data-jump="discover">Campaigns</button>
        <a class="site-nav-link" href="guide.html">Help</a>
      </nav>
      <?php /* js/theme.js binds [data-theme-toggle] by delegation, so this
               needs no extra script — the button works as soon as it exists.
               .theme-toggle is styled in tokens.css. */ ?>
      <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode">
        <svg class="theme-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="theme-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
      </button>
    </div>
  </header>

  <div class="mobile-overlay"></div>

  <div class="layout">

    <aside class="sidebar" aria-label="Account sidebar">
      <div class="sidebar-brand">
        <div class="sidebar-logo-circle">
          <img src="../images/sawa_v2.svg" alt="Sawa">
        </div>
        <span>Sawa</span>
        <button class="sidebar-toggle" aria-label="Collapse sidebar" title="Collapse sidebar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
      </div>

      <!-- User profile card (auth only). Tapping the card opens the Profile
           sub-page. Tapping the small caret on the right opens an account
           switcher panel below (showing the current account + "Add an
           account" link). Multi-account switching needs backend support;
           additional accounts will be listed inside the panel once a
           /php/auth/sessions.php endpoint exists. -->
      <div class="sidebar-user-wrap auth-only">
        <button type="button" class="sidebar-user" data-section="profile" aria-label="Go to my profile">
          <?php /* Was hardcoded to the placeholder SVG and the literal word
                   "User", so a signed-in member saw a stranger's chip in their
                   own sidebar while the header above it showed their real name
                   and photo. Both values are already resolved at the top of
                   this file. */ ?>
          <img class="sidebar-user-avatar" src="<?= $avatarUrl ?>" alt="" id="sidebar-user-avatar">
          <span class="sidebar-user-info">
            <strong class="sidebar-user-name" id="sidebar-user-name"><?= $displayName ?></strong>
            <small class="sidebar-user-role"></small>
          </span>
        </button>
        <button type="button" class="sidebar-account-toggle" id="sidebar-account-toggle" aria-expanded="false" aria-controls="sidebar-account-panel" aria-label="Switch account">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
        </button>

        <div class="sidebar-account-panel" id="sidebar-account-panel" hidden>
          <!-- Current (active) account. -->
          <div class="sidebar-account-row is-current" aria-current="true">
            <img class="sidebar-account-avatar" src="<?= $avatarUrl ?>" alt="">
            <span class="sidebar-account-info">
              <strong><?= $displayName ?></strong>
              <small>Active now</small>
            </span>
            <svg class="sidebar-account-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <!-- Other accounts signed in on this device — POST switches the active one. -->
          <?php foreach ($otherAccounts as $acc):
            $accName = htmlspecialchars((string) $acc['name'], ENT_QUOTES, 'UTF-8');
            $accAvatar = !empty($acc['avatar'])
                ? htmlspecialchars(Upload::publicUrl((string) $acc['avatar']), ENT_QUOTES, 'UTF-8')
                : '../images/user-profile.svg';
            $accRoleLabel = match ($acc['role']) {
                'organisation' => 'Organization',
                'beneficiary'  => 'Recipient',
                'admin'        => 'Admin',
                default        => 'Donor',
            };
          ?>
          <form action="../php/auth/switch-account.php" method="POST" class="sidebar-account-switch-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="account_id" value="<?= (int) $acc['id'] ?>">
            <button type="submit" class="sidebar-account-row" aria-label="Switch to <?= $accName ?>">
              <img class="sidebar-account-avatar" src="<?= $accAvatar ?>" alt="">
              <span class="sidebar-account-info">
                <strong><?= $accName ?></strong>
                <small><?= $accRoleLabel ?> · Switch</small>
              </span>
            </button>
          </form>
          <?php endforeach; ?>
          <a class="sidebar-account-add" href="login.php?add=1">
            <span class="sidebar-account-add-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </span>
            Add an account
          </a>
        </div>
      </div>
      <button class="sidebar-item active" data-section="dashboard" aria-current="page">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span>Dashboard</span>
      </button>
      <button class="sidebar-item" data-section="discover">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <span>Campaigns</span>
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
      <!-- Single "Help" item shared with the desktop top nav (also labeled
           "Help" there). Routes to guide.html — no separate Guide entry. -->
      <a class="sidebar-item" href="guide.html">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22a10 10 0 100-20 10 10 0 000 20z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
        <span>Help</span>
      </a>
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

        <!-- Logged-in only: Settings (theme + language live inside it now). -->
        <button class="sidebar-item auth-only" type="button" data-section="settings">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          <span>Settings</span>
        </button>
        <?php /* A real POST form, not a link: logout.php now requires POST +
                 CSRF so an offsite <img src> cannot sign the user out. The
                 form is display:contents (see .logout-form) so the button
                 stays a direct flex child of the sidebar and nothing moves. */ ?>
        <form action="../php/auth/logout.php" method="POST" class="logout-form auth-only">
          <?= Csrf::field() ?>
          <button type="submit" class="sidebar-item logout">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span>Logout</span>
          </button>
        </form>

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

    <main class="main-content" id="main">

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
                <strong class="stat-tile-value" id="hero-raised"><?= $heroRaisedLabel ?></strong>
                <span class="hero-stat-label">Total raised on Sawa</span>
              </div>
              <div class="hero-stat">
                <span class="hero-stat-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <strong class="stat-tile-value" id="hero-helped"><?= number_format((int)$platformStats['donors']) ?></strong>
                <span class="hero-stat-label"><?= (int)$platformStats['donors'] === 1 ? 'Verified donor' : 'Verified donors' ?></span>
              </div>
              <div class="hero-stat">
                <span class="hero-stat-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </span>
                <strong class="stat-tile-value" id="hero-active"><?= (int)$platformStats['active'] ?></strong>
                <span class="hero-stat-label"><?= (int)$platformStats['active'] === 1 ? 'Active campaign now' : 'Active campaigns now' ?></span>
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
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="search" id="dash-search-input" aria-label="Search campaigns" placeholder="Search campaigns…" autocomplete="off">
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
                <!-- Jumps to Activity & Bills section (which lists the full
                     notification + donation history). Uses data-section so the
                     existing catch-all click handler handles section switching. -->
                <a href="#" class="dash-notif-foot" data-section="activity">View all notifications</a>
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

        <!-- Quick actions grid removed for AUTH users — duplicated the bottom bar and sidebar.
             Restored a guest-only action band below: 3 cards driving sign-up / browse / learn-more,
             so the area below the guest hero doesn't read as empty space. -->
        <div class="guest-actions guest-only" aria-label="Guest quick actions">
          <a href="signup.php" class="guest-action-card guest-action-primary">
            <span class="guest-action-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M16 11h6"/></svg>
            </span>
            <strong>Sign up free</strong>
            <p>Join in under a minute &mdash; unlock lower fees and wallet support.</p>
          </a>
          <button type="button" class="guest-action-card" data-jump="discover">
            <span class="guest-action-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
            <strong>Browse campaigns</strong>
            <p>Verified Lebanese families and organizations across all categories.</p>
          </button>
          <a href="about-us.php" class="guest-action-card">
            <span class="guest-action-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </span>
            <strong>How Sawa works</strong>
            <p>Transparent platform fee, real-time tracking, and verified organizations.</p>
          </a>
        </div>

        <!-- 3 stat tiles — DONOR variant (real numbers from donations table) -->
        <?php
          $donorHasHistory = $donorTotals['total'] > 0;
          $deltaPct = $donorTotals['delta_pct'];
          $famDelta = (int) $donorTotals['families_delta'];
          $monthDeltaClass = $deltaPct === null ? '' : ($deltaPct >= 0 ? 'delta-up' : 'delta-down');
          $famDeltaClass = $famDelta === 0 ? '' : ($famDelta > 0 ? 'delta-up' : 'delta-down');
        ?>
        <div class="dash-stats donor-only">
          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">Total Donated</span>
              <span class="stat-tile-icon stat-icon-trend">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="dash-donated">$<?= number_format((float) $donorTotals['total'], 2) ?></strong>
            <span class="stat-tile-delta"><?= $donorHasHistory ? 'Lifetime contribution' : 'Make your first donation to get started' ?></span>
          </div>

          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">Families Helped</span>
              <span class="stat-tile-icon stat-icon-users">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="dash-helped"><?= (int) $donorTotals['families'] ?></strong>
            <?php if ($famDelta !== 0): ?>
              <span class="stat-tile-delta <?= $famDeltaClass ?>"><?= ($famDelta > 0 ? '+' : '') . $famDelta ?> vs last month</span>
            <?php else: ?>
              <span class="stat-tile-delta"><?= $donorTotals['families'] > 0 ? 'Unique families supported' : 'No families supported yet' ?></span>
            <?php endif; ?>
          </div>

          <div class="stat-tile">
            <div class="stat-tile-head">
              <span class="stat-tile-label">This Month</span>
              <span class="stat-tile-icon stat-icon-cal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </span>
            </div>
            <strong class="stat-tile-value" id="dash-month">$<?= number_format((float) $donorTotals['this_month'], 2) ?></strong>
            <?php if ($deltaPct !== null): ?>
              <span class="stat-tile-delta <?= $monthDeltaClass ?>"><?= ($deltaPct >= 0 ? '+' : '') . $deltaPct ?>% vs last month</span>
            <?php else: ?>
              <span class="stat-tile-delta"><?= $donorTotals['this_month'] > 0 ? 'Your first month here' : 'No donations this month' ?></span>
            <?php endif; ?>
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
              <li class="recent-donation-empty">
                <strong>No donations yet</strong>
                <p>Once you support a family or NGO, your history &amp; receipts will land here.</p>
                <ol class="how-mini">
                  <li><span>1</span> Browse verified campaigns</li>
                  <li><span>2</span> Donate securely — see the fee upfront</li>
                  <li><span>3</span> Track impact and download your receipt</li>
                </ol>
                <button type="button" class="btn btn-primary" onclick="document.querySelector('[data-section=&quot;discover&quot;]').click()">Browse campaigns</button>
              </li>
              <?php else: foreach ($recentDonations as $don): include $partial . 'recent-donation.php'; endforeach; endif; ?>
            </ul>
          </div>

          <!-- Ending Soon: shown for donors + guests. Header matches the data
               (campaigns ending within 7 days), instead of the older "Urgent"
               label which contradicted the mostly-Medium priority tags. -->
          <div class="dash-card donor-only guest-show">
            <div class="dash-card-head">
              <h3>Ending Soon</h3>
              <a class="dash-card-link" onclick="document.querySelector('[data-section=&quot;discover&quot;]').click(); return false;" href="#">View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            </div>
            <div class="urgent-list" id="urgent-list">
              <?php if (!$urgentCampaigns): ?>
              <p class="empty-inline">No campaigns are wrapping up in the next week.</p>
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

        <!-- Why Sawa — three real trust pillars instead of fake testimonials.
             Swap this block for a testimonials carousel once real quotes are
             collected (and consent signed). -->
        <div class="trust-strip guest-only">
          <div class="trust-strip-head">
            <h3>Why donors choose Sawa</h3>
            <p>Three commitments we made from day one — the way we work isn't marketing copy, it's the code.</p>
          </div>
          <div class="testimonials-grid">
            <article class="testimonial-card">
              <span class="testimonial-avatar testimonial-avatar--icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
              </span>
              <strong class="testimonial-title">Every NGO reviewed manually</strong>
              <p>Organizations submit registration documents. Nothing goes live until an admin approves it.</p>
            </article>
            <article class="testimonial-card">
              <span class="testimonial-avatar testimonial-avatar--icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              </span>
              <strong class="testimonial-title">Fees shown before you pay</strong>
              <p>5% for members, 10% for guests. The exact amount appears on the confirm screen — no surprises after checkout.</p>
            </article>
            <article class="testimonial-card">
              <span class="testimonial-avatar testimonial-avatar--icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </span>
              <strong class="testimonial-title">Direct — no middlemen</strong>
              <p>Donations move from your wallet to the campaign owner. Every step is recorded in a downloadable receipt.</p>
            </article>
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
            <?php /* Real donations, newest first. Names are resolved in
                     DonationService::recentPlatformActivity() so anonymous and
                     guest donors are never attributed on this public page. */ ?>
            <ul class="activity-feed" id="activity-feed">
              <?php if (!$liveActivity): ?>
              <li class="activity-row">
                <div class="activity-text">
                  <span class="activity-meta">No donations yet — be the first to give.</span>
                </div>
              </li>
              <?php else: foreach ($liveActivity as $act):
                $isAnon = $act['anonymous'] || $act['label'] === 'Guest donor';
                $initial = mb_strtoupper(mb_substr($act['label'], 0, 1));
              ?>
              <li class="activity-row">
                <span class="activity-avatar<?= $isAnon ? ' activity-avatar-anon' : '' ?>"><?= $isAnon ? '?' : htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                <div class="activity-text">
                  <strong><?= htmlspecialchars($act['label'], ENT_QUOTES, 'UTF-8') ?></strong> donated <strong class="activity-amount">$<?= number_format($act['amount']) ?></strong>
                  <span class="activity-meta">to <?= htmlspecialchars($act['campaign'], ENT_QUOTES, 'UTF-8') ?> &middot; <time datetime="<?= htmlspecialchars($act['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(Format::timeAgo($act['created_at']), ENT_QUOTES, 'UTF-8') ?></time></span>
                </div>
              </li>
              <?php endforeach; endif; ?>
          </div>

          <!-- Guest-only marketing/trust card. The auth dashboard uses the
               "How Sawa works" guide further below instead of this About-style copy. -->
          <div class="dash-card trust-card guest-only">
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

        <!-- "How Sawa works" cards live on the dedicated /pages/guide.html page now.
             Removed from the dashboard so the home view stays focused on the user's
             actual activity (stats + recent donations + urgent campaigns). -->
      </section>

      <section id="discover" class="section">
        <div class="section-header">
          <h2>Discover Campaigns</h2>
          <p>Browse active campaigns and make a donation.</p>
        </div>

        <!-- Filter toolbar: search + sort + (mobile-only) filter trigger.
             On mobile, .discover-filters below becomes a bottom-sheet — no
             horizontal-scrolling chip row. Desktop keeps the inline toolbar. -->
        <div class="discover-toolbar">
          <label class="discover-search-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="discover-search" aria-label="Search campaigns by name, keyword, or category" placeholder="Search by name, keyword, or category…" autocomplete="off">
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
          <!-- Mobile-only trigger that opens the filter bottom-sheet. -->
          <button type="button" class="discover-filter-trigger" id="discover-filter-trigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="discover-filters-sheet">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
            <span>Filters</span>
            <span class="discover-filter-trigger-count" id="discover-filter-trigger-count" hidden>0</span>
          </button>
        </div>

        <!-- Backdrop is shown only when the bottom-sheet is open on mobile.
             Tap to close. Desktop never reaches this state. -->
        <div class="discover-filters-backdrop" id="discover-filters-backdrop" hidden></div>
        <div class="discover-filters" id="discover-filters-sheet">
          <!-- Sheet header is mobile-only; desktop hides via CSS. -->
          <div class="discover-filters-sheet-head">
            <h3>Filter campaigns</h3>
            <button type="button" class="discover-filters-sheet-close" id="discover-filter-close" aria-label="Close filters">&times;</button>
          </div>
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
            <p class="empty-state">No active campaigns yet. Check back soon.</p>
          <?php else: foreach ($discoverCampaigns as $camp): include $partial . 'campaign-card.php'; endforeach; endif; ?>
        </div>
      </section>

      <section id="profile" class="section" data-back="dashboard">
        <!-- X / Twitter-style profile header: banner + avatar + identity + Edit
             button. Click Edit → the section toggles `.is-editing` and the form
             inside .profile-card becomes interactive. Save submits to the
             existing PHP endpoint; Cancel reverts. -->
        <div class="profile-banner card profile-hero">
          <?php /* Falls back to the gradient in css/userhome.css when the user
                   has not uploaded one. */ ?>
          <div class="profile-banner-image" aria-hidden="true"<?= $bannerStyle ?>></div>

          <button type="button" class="profile-edit-toggle" id="profile-edit-toggle" data-section="profile-edit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <span>Edit profile</span>
          </button>

          <div class="profile-identity">
            <div class="profile-avatar-frame">
              <img src="<?= $avatarUrl ?>" alt="" id="profile-view-avatar">
            </div>
            <h2 class="profile-view-name" id="profile-view-name"><?= $displayName ?></h2>
            <span class="profile-role-badge" aria-label="Account role"></span>
            <?php /* Kept on one line on purpose: this element is
                     white-space: pre-wrap, so a newline and the source
                     indentation would render as a literal gap before the
                     text. */ ?>
            <p class="profile-view-bio" id="profile-view-bio"><?= $bioValue !== '' ? $bioValue : '<em class="profile-view-bio-empty">No bio yet — tap Edit profile to add one.</em>' ?></p>
          </div>
        </div>

        <!-- Profile dashboard summary cards. All numbers come from PHP variables
             (will populate once real data exists). -->
        <div class="profile-summary-grid">
          <div class="profile-summary-card">
            <span class="profile-summary-label">Account status</span>
            <strong class="profile-summary-value profile-summary-status">
              <span class="profile-status-dot" aria-hidden="true"></span> Active
            </strong>
            <small>Member since <?= htmlspecialchars($memberSince, ENT_QUOTES, 'UTF-8') ?></small>
          </div>
          <div class="profile-summary-card">
            <span class="profile-summary-label">Wallet balance</span>
            <strong class="profile-summary-value">$<?= $walletBalance ?></strong>
            <small>Available for donations</small>
          </div>
          <div class="profile-summary-card">
            <span class="profile-summary-label">Total donated</span>
            <strong class="profile-summary-value">$<?= number_format($activityTotalPaid, 2) ?></strong>
            <small>Across all campaigns</small>
          </div>
          <div class="profile-summary-card">
            <span class="profile-summary-label">Campaigns created</span>
            <strong class="profile-summary-value"><?= count($myCampaigns) ?></strong>
            <small>Including drafts</small>
          </div>
          <div class="profile-summary-card">
            <span class="profile-summary-label">Receipts on file</span>
            <strong class="profile-summary-value"><?= $receiptCount ?></strong>
            <small>Tax-ready PDFs</small>
          </div>
          <div class="profile-summary-card">
            <span class="profile-summary-label">Unread notifications</span>
            <strong class="profile-summary-value"><?= count($notifications) ?></strong>
            <small>From campaigns &amp; admin</small>
          </div>
        </div>

        <!-- Edit-mode panel removed from #profile. The form moved to its own
             #profile-edit sub-page (defined below) so editing feels like a
             distinct screen per the X / Twitter pattern. -->


        <!-- Quick actions + security placeholder — frontend-only for now. -->
        <div class="profile-extra-grid">
          <div class="card profile-extra-card">
            <h4>Quick actions</h4>
            <div class="profile-extra-actions">
              <button type="button" class="btn btn-primary" data-section="discover">Browse campaigns</button>
              <button type="button" class="btn btn-outline" data-section="wallet">Top up wallet</button>
              <button type="button" class="btn btn-outline" data-section="activity">View receipts</button>
              <button type="button" class="btn btn-outline" data-section="campaign-new">Create campaign</button>
            </div>
          </div>

          <div class="card profile-extra-card">
            <h4>Account &amp; security</h4>
            <ul class="profile-security-list">
              <li><span>Email verified</span><span class="profile-pill profile-pill-ok">Verified</span></li>
              <li><span>Two-factor auth</span><span class="profile-pill profile-pill-soon">Coming soon</span></li>
              <li><span>Password</span><span class="profile-pill profile-pill-soon">Change soon</span></li>
              <li><span>Connected devices</span><span class="profile-pill profile-pill-soon">Coming soon</span></li>
            </ul>
            <?php /* Wire to /php/users/security.php once sessions + 2FA tables land; keep this layout. */ ?>
            <p class="profile-extra-note">
              Account security upgrades &mdash; including two-factor authentication and device management &mdash; are rolling out soon.
            </p>
          </div>

          <div class="card profile-extra-card profile-extra-card-wide">
            <h4>Recent activity</h4>
            <?php if (!$activityRows): ?>
              <p class="profile-empty">No activity yet — your latest donations and wallet top-ups will appear here.</p>
            <?php else: ?>
              <ul class="profile-activity-list">
                <?php foreach (array_slice($activityRows, 0, 4) as $row): ?>
                  <li>
                    <strong><?= htmlspecialchars((string) ($row['campaign_title'] ?? 'Donation'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span>$<?= number_format((float) ($row['total_charged'] ?? 0), 2) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- Profile EDIT sub-page. Standalone section; the back arrow returns to
           the Profile view. Save submits to the existing PHP endpoint, which
           already redirects with ?section=profile so the user lands back on
           the Profile view. No fake submission — pure PHP POST. -->
      <section id="profile-edit" class="section" data-back="profile" data-title="Edit profile">
        <div class="card profile-edit-card">
          <form action="../php/users/update-profile.php" method="POST" enctype="multipart/form-data" class="profile-edit-form" id="profile-edit-form">
            <?= Csrf::field() ?>

            <div class="profile-edit-avatar">
              <label class="avatar-upload" for="avatar-input">
                <img src="<?= $avatarUrl ?>" alt="" id="avatar-preview">
                <span class="avatar-overlay" aria-hidden="true">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                  <span>Change photo</span>
                </span>
                <input type="file" name="profile_image" id="avatar-input" accept="image/*" hidden>
              </label>
              <span class="profile-role-badge" aria-label="Account role"></span>
              <?php /* A <label> rather than a <button>: it opens the hidden
                       file input natively, so this needs no JavaScript to work.
                       The form is already enctype="multipart/form-data". */ ?>
              <label class="profile-banner-edit-btn" for="banner-upload">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>
                Change banner
              </label>
              <input type="file" name="banner_image" id="banner-upload" accept="image/png,image/jpeg,image/webp" hidden>
            </div>

            <div class="profile-edit-fields">
              <label for="profile-name">Full Name</label>
              <input type="text" name="full_name" id="profile-name" placeholder="Your name" maxlength="50" value="<?= $displayName ?>">

              <label for="profile-bio">Bio</label>
              <textarea name="bio" id="profile-bio" placeholder="Tell us about yourself..." maxlength="250"><?= $bioValue ?></textarea>

              <div class="profile-edit-actions">
                <button type="button" class="btn btn-outline" data-back-to="profile">Cancel</button>
                <button type="submit" class="btn btn-primary">Save changes</button>
              </div>
            </div>
          </form>
        </div>
      </section>

      <section id="wallet" class="section" data-back="dashboard" data-title="My Wallet">
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
              <input type="number" name="amount" id="top-up-amount" aria-label="Top-up amount in dollars" placeholder="Or enter amount ($)" min="1" step="1" required>
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
                Amount to withdraw ($)
                <input type="number" name="cashout_amount" min="1" step="1" placeholder="50">
              </label>
              <label>
                Destination
                <input type="text" name="cashout_destination" placeholder="+961 Whish number or payout reference">
              </label>
            </div>

            <div class="cashout-note">
              <strong>Processing time:</strong> cash-out and transfer requests usually take <strong>2&ndash;3 business days</strong>. The amount you enter is what leaves your wallet &mdash; the <strong>5% Sawa fee comes out of it</strong>, so a $100 withdrawal pays out $95. You&rsquo;ll get a status update as soon as your payout is processed.
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

      <section id="activity" class="section" data-back="dashboard" data-title="Activity &amp; Bills">
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
              <!-- Each tab declares the row predicate via data-filter:
                     all      → show every row
                     paid     → bill_id !== 'PENDING'
                     pending  → bill_id === 'PENDING'
                   Fees tab is intentionally omitted: it would need a
                   data-bill-fee attribute on the row to be meaningful,
                   and that lives in php/partials/activity-row.php (backend zone). -->
              <div class="activity-filter-tabs" role="tablist" aria-label="Activity filters">
                <button type="button" class="active" data-filter="all">All</button>
                <button type="button" data-filter="paid">Paid</button>
                <button type="button" data-filter="pending">Pending</button>
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
                <span>The final payment reference and provider fee are confirmed once your payment provider returns the receipt.</span>
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

      <section id="campaign-new" class="section" data-back="dashboard" data-title="Create a Campaign">
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

      <section id="campaigns" class="section" data-back="dashboard" data-title="My Campaigns">
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

      <section id="messages" class="section" data-back="dashboard" data-title="Messages">
        <div class="section-header">
          <h2>Messages</h2>
          <p>Chat directly with donors, recipients, and verified organizations.</p>
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
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="search" aria-label="Search chats" placeholder="Search chats">
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

            <div class="chat-body" data-thread-id="<?= $activeThreadId > 0 ? (int) $activeThreadId : '' ?>">
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
              <div class="chat-bubble <?= $bubbleClass ?>" data-msg-id="<?= (int) $msg['id'] ?>">
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
              <input type="text" name="message" aria-label="Write a message" placeholder="Write a message..." autocomplete="off">
              <button type="submit" class="btn btn-primary chat-send-btn">Send</button>
            </form>
          </div>
        </div>
      </section>

      <?php /* Settings — X-style full-screen takeover via data-back; language row waits for i18n.
               The theme row was removed with the other per-page toggles: the
               single global control now lives on the landing page. */ ?>
      <section id="settings" class="section" data-back="dashboard" data-title="Settings">
        <div class="settings-list">
          <?php /* Language row — wire to /php/users/language.php when the strings catalog exists. */ ?>
          <div class="settings-row is-disabled" aria-disabled="true">
            <span class="settings-row-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </span>
            <span class="settings-row-body">
              <strong>Language</strong>
              <small>Switch between English and Arabic</small>
            </span>
            <span class="settings-row-soon">Coming soon</span>
          </div>

          <!-- Add an account — kicks off the multi-account flow. Backend has to
               honor ?add=1 by NOT logging the current session out, just adding
               the new account to a multi-session cookie. UI is real (no fake
               account list); switching between accounts happens in the sidebar
               user-card panel once the backend exposes the session list. -->
          <a class="settings-row" href="login.php?add=1">
            <span class="settings-row-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </span>
            <span class="settings-row-body">
              <strong>Add an account</strong>
              <small>Sign in to another Sawa account and switch between them</small>
            </span>
            <svg class="settings-row-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
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
          <input type="number" name="amount" id="modal-amount" aria-label="Donation amount in dollars" placeholder="Or enter amount" min="1" step="1">

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
          <?php /* Wallet-pay disabled by design — enable when the wallet-pay endpoint + provider integration are ready. */ ?>
          <label class="payment-method-option is-coming-soon auth-only" aria-disabled="true">
            <input type="radio" name="payment_method_choice" value="wallet" disabled>
            <span class="payment-method-icon payment-method-icon--wallet" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </span>
            <span class="payment-method-body">
              <strong>Sawa Wallet</strong>
              <small>Pay from your Sawa balance — instant, members only.</small>
              <span class="payment-method-fee payment-method-fee--soon">Coming soon</span>
              <span class="payment-method-extra">
                <span class="pm-extra-row"><span class="pm-extra-label">Status</span><span>Wallet payments are launching soon &mdash; we&rsquo;ll notify you the moment they go live.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Source</span><span>Will use your Sawa wallet balance only.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Speed</span><span>Instant — no provider redirect.</span></span>
                <span class="pm-extra-row"><span class="pm-extra-label">Why cheaper</span><span>Members will pay 5% on wallet donations (vs 10% via Whish / Visa).</span></span>
              </span>
            </span>
          </label>
          <!-- Guest-only "Sign up to unlock" Wallet promo. Fills the 3rd column
               for guests (since the real Wallet card is auth-only) and nudges
               signup with a real value prop (5% vs 10% fee). Links to signup.php
               in a new tab so the user doesn't lose the donate flow. -->
          <a class="payment-method-option is-locked guest-only" href="signup.php" target="_blank" rel="noopener">
            <span class="payment-method-icon payment-method-icon--wallet" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </span>
            <span class="payment-method-body">
              <strong>Sawa Wallet</strong>
              <small>Sign up to unlock</small>
              <span class="payment-method-fee payment-method-fee--save">Pay 5% instead of 10%</span>
            </span>
            <span class="payment-method-lock" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
          </a>
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
        <?php /* Seed rows — PHP loop replaces these with real donations per campaign when data lands. */ ?>
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

        <!-- Creator row — Twitter/X style. Avatar + name is one clickable
             target (opens creator profile). Message + Report sit as
             companion action pills on the right. -->
        <div class="cm-creator">
          <button type="button" class="cm-creator-link" id="cm-creator-link" aria-label="View creator profile">
            <span class="cm-creator-avatar" id="cm-creator-avatar" aria-hidden="true">VO</span>
            <span class="cm-creator-meta">
              <small>Created by</small>
              <strong id="cm-creator-name">Verified organization
                <span class="cm-verified" id="cm-verified" title="Verified creator" aria-label="Verified">
                  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0l3 3 4 1 1 4 3 3-3 3-1 4-4 1-3 3-3-3-4-1-1-4-3-3 3-3 1-4 4-1z"/></svg>
                  <svg class="cm-verified-tick" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
              </strong>
            </span>
          </button>
          <div class="cm-creator-actions">
            <a class="cm-action-btn auth-only" id="cm-message-btn" href="#" aria-label="Message the campaign creator">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
              <span>Message</span>
            </a>
            <button type="button" class="cm-action-btn cm-action-btn-danger" id="cm-report-creator-btn" aria-label="Report creator">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="22" x2="4" y2="15"/><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/></svg>
              <span>Report</span>
            </button>
          </div>
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
                <img class="cm-comment-form-avatar" src="<?= $avatarUrl ?>" alt="" aria-hidden="true">
                <textarea name="body" id="cm-comment-input" aria-label="Add a comment" placeholder="Add a comment — be kind." maxlength="500" rows="2" required></textarea>
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

  <!-- ─── Report modal — supports BOTH campaign + user reports.
       JS sets data-mode="campaign" or "user", which swaps the title and reason
       list. Submits via fetch to /php/engagement/report.php with { type,
       target_id, reason, message }. Admin reviews in admin.php. -->
  <div class="report-overlay" id="report-overlay" hidden>
    <div class="report-modal" id="report-modal" role="dialog" aria-modal="true" aria-labelledby="report-title" data-mode="campaign">
      <button type="button" class="report-close" id="report-close" aria-label="Close">&times;</button>
      <h3 id="report-title">Report this campaign</h3>
      <p class="report-sub">
        Help us keep Sawa safe. An admin will review your report.
        <strong id="report-target-name"></strong>
      </p>

      <!-- Segmented control: lets the user switch between reporting the
           campaign itself or its creator (fake account, impersonation, etc). -->
      <div class="report-segmented" role="tablist" aria-label="Report target">
        <button type="button" class="report-seg-btn is-active" data-report-mode="campaign" role="tab" aria-selected="true">Campaign</button>
        <button type="button" class="report-seg-btn" data-report-mode="user" role="tab" aria-selected="false">User</button>
      </div>

      <form id="report-form" class="report-form" action="../php/engagement/report.php" method="POST" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="target_id" id="report-target-id" value="">
        <input type="hidden" name="type" id="report-type" value="campaign">

        <label for="report-reason">Reason</label>
        <select id="report-reason" name="reason" required>
          <option value="">Select a reason…</option>
        </select>

        <label for="report-message">Message <small>(optional)</small></label>
        <textarea id="report-message" name="message" rows="4" maxlength="500"
                  placeholder="Tell us what's wrong…"></textarea>

        <div class="report-actions">
          <button type="button" class="btn btn-outline" id="report-cancel">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit report</button>
        </div>

        <p class="report-success" id="report-success" hidden>
          Thanks — your report was received. An admin will review it shortly.
        </p>
      </form>
    </div>
  </div>

  <!-- Bottom nav (mobile only) — 5 slots: Home · Campaigns · [+ Create] · Wallet · Profile.
       Center FAB = primary action (Create Campaign). Donations item was removed
       because it pointed at "Bills" and confused users. -->
  <nav class="bottom-nav auth-only" id="bottom-nav" aria-label="Primary mobile navigation">
    <button class="bottom-nav-item active" data-section="dashboard" aria-label="Home" aria-current="page">
      <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Home</span>
    </button>
    <button class="bottom-nav-item" data-section="discover" aria-label="Campaigns">
      <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <span>Campaigns</span>
    </button>
    <button class="bottom-nav-item bottom-nav-fab" data-section="campaign-new" aria-label="Create new campaign">
      <svg fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
    </button>
    <button class="bottom-nav-item" data-section="wallet" aria-label="Wallet">
      <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
      <span>Wallet</span>
    </button>
    <button class="bottom-nav-item" data-section="profile" aria-label="Profile">
      <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profile</span>
    </button>
  </nav>

  <!-- Removed: #auth-overlay (sign-in nudge modal). Its only trigger was the
       bottom-nav guest-restriction handler, but the bottom-nav is auth-only.
       Effectively unreachable; HTML + CSS + JS all removed in Task 1. -->

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
        <a href="about-us.php">About us</a>
        <a href="about-us.php#how-to-donate">How to donate</a>
        <a href="signup.php">Become a creator</a>
        <a href="about-us.php#faq">FAQs</a>
      </div>

      <div class="site-footer-col">
        <h4>Trust &amp; safety</h4>
        <a href="about-us.php#trust">How verification works</a>
        <a href="about-us.php#faq">Terms &amp; privacy</a>
        <a href="guide.html">User guide</a>
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

  <script src="<?= asset('js/userhome.js') ?>"></script>
</body>
</html>
