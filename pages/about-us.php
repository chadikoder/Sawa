<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/php/bootstrap.php';

// Real numbers, not marketing fiction. Falls back gracefully on an empty DB.
$platform = CampaignService::stats();
$pdo = db();
try {
    $orgsVerified = (int) $pdo->query("SELECT COUNT(*) FROM organisations WHERE verified = 1")->fetchColumn();
} catch (Throwable) {
    $orgsVerified = 0;
}
try {
    $totalCampaigns = (int) $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status IN ('active','completed')")->fetchColumn();
} catch (Throwable) {
    $totalCampaigns = 0;
}

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = static fn (int $n): string => number_format($n);
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sawa connects Lebanese donors with families in need transparently, with verified campaigns and clear platform fees.">
    <title>About Sawa — Direct help for Lebanese families</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

    <link rel="icon" href="../images/sawa.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../css/tokens.css">
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../css/about.css">
    <script src="../js/theme.js"></script>
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <nav class="site-nav" id="site-nav">
    <div class="nav-container">
      <a href="../index.html" class="nav-brand">
        <div class="nav-logo-circle">
          <img src="../images/sawa_v2.svg" alt="Sawa" class="nav-logo">
        </div>
        <span class="nav-brand-name">Sawa</span>
      </a>
      <div class="nav-links-wrap" id="nav-links">
        <a href="../index.html" class="nav-link">Home</a>
        <a href="#mission" class="nav-link">Mission</a>
        <a href="#how-to-donate" class="nav-link">How it works</a>
        <a href="#faq" class="nav-link">FAQs</a>
        <div class="nav-auth-desktop">
          <a href="login.php" class="nav-btn-login">Log In</a>
          <a href="signup.php" class="nav-btn-signup">Sign Up</a>
        </div>
        <div class="nav-mobile-auth">
          <a href="login.php" class="nav-btn-login">Log In</a>
          <a href="signup.php" class="nav-btn-signup">Sign Up Free</a>
        </div>
      </div>
      <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode">
        <svg class="theme-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="theme-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
      </button>
      <button class="nav-hamburger" id="nav-hamburger" aria-label="Open menu">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
    </div>
  </nav>
  <div class="nav-mobile-overlay" id="nav-mobile-overlay"></div>

  <!-- ── Hero ── -->
  <section class="about-hero" id="main">
    <div class="container about-hero-grid">
      <div class="about-hero-copy">
        <span class="about-eyebrow">Transparent giving for Lebanon</span>
        <h1>Direct help. Real impact.<br><span class="about-hero-accent">Zero intermediaries.</span></h1>
        <p>Sawa connects Lebanese families in need directly with donors and verified NGOs — campaign progress, platform fees, and payment status stay visible from start to finish.</p>
        <div class="about-hero-cta">
          <a href="signup.php" class="about-btn about-btn--primary">Get started free</a>
          <a href="userhome.php" class="about-btn about-btn--ghost">Browse campaigns</a>
        </div>
        <div class="about-hero-proof">
          <div>
            <strong><?= $e($fmt($orgsVerified)) ?></strong>
            <span><?= (int)$orgsVerified === 1 ? 'Verified NGO' : 'Verified NGOs' ?></span>
          </div>
          <div>
            <strong>5%</strong>
            <span>Member fee (10% guests)</span>
          </div>
          <div>
            <strong><?= $e($fmt($platform['active'])) ?></strong>
            <span><?= (int)$platform['active'] === 1 ? 'Active campaign' : 'Active campaigns' ?></span>
          </div>
        </div>
      </div>
      <div class="about-hero-visual" aria-hidden="true">
        <img src="../images/about-impact-photo.png" alt="">
        <div class="about-hero-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <div>
            <strong>Every donation traceable</strong>
            <span>Real-time status, downloadable receipt.</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Story ── -->
  <section id="story" class="section-white">
    <div class="container">
      <div class="about-section-head about-section-head--center">
        <span class="about-eyebrow about-eyebrow--accent">Why Sawa exists</span>
        <h2>Built in response to the Lebanese crisis.</h2>
      </div>
      <div class="about-story-grid">
        <article class="about-story-card">
          <span class="about-story-year">2019</span>
          <h3>The crisis</h3>
          <p>Lebanon's economy collapses. Families that never asked for help before struggle for medicine, food, and rent.</p>
        </article>
        <article class="about-story-card about-story-card--featured">
          <span class="about-story-year">2025</span>
          <h3>The idea</h3>
          <p>A student project asks a simple question: what if donors could reach families directly, without three layers of NGOs and fees between them?</p>
        </article>
        <article class="about-story-card">
          <span class="about-story-year">2026</span>
          <h3>Sawa launches</h3>
          <p>Verified NGOs, a transparent wallet, receipts on every donation. One clear fee, shown before you pay.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- ── Mission ── -->
  <section id="mission" class="section-light">
    <div class="container">
      <div class="mission-grid">
        <div class="mission-content">
          <span class="about-eyebrow about-eyebrow--accent">Purpose</span>
          <h2>Our mission</h2>
          <p>During Lebanon's humanitarian crisis, families struggle to access basic necessities. Sawa was created to bridge this gap — connecting those in need directly with compassionate donors and established organizations.</p>
          <p>We believe in direct connection with clear platform fees. Sawa shows the support fee <strong>before</strong> checkout and confirms the final donation and payment status after the provider redirects back.</p>
          <div class="mission-stats">
            <div class="stat">
              <div class="stat-value"><?= $e($fmt($orgsVerified)) ?></div>
              <div class="stat-label">Verified NGOs</div>
            </div>
            <div class="stat">
              <div class="stat-value">5%</div>
              <div class="stat-label">Member fee</div>
            </div>
            <div class="stat">
              <div class="stat-value"><?= $e($fmt($totalCampaigns)) ?></div>
              <div class="stat-label">Campaigns hosted</div>
            </div>
          </div>
        </div>
        <div class="mission-image">
          <svg class="about-illustration" viewBox="0 0 520 360" role="img" aria-labelledby="mission-svg-title">
            <title id="mission-svg-title">Verified donation flow illustration</title>
            <rect class="ill-bg" x="0" y="0" width="520" height="360" rx="28"/>
            <rect class="ill-card" x="56" y="70" width="172" height="210" rx="22"/>
            <rect class="ill-card" x="292" y="70" width="172" height="210" rx="22"/>
            <circle class="ill-primary" cx="142" cy="134" r="34"/>
            <path class="ill-line" d="M112 190h60M112 215h88M112 240h46"/>
            <circle class="ill-success" cx="378" cy="134" r="34"/>
            <path d="M361 134l12 12 25-29" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
            <path class="ill-arrow" d="M240 176h60"/>
            <path class="ill-arrow" d="M286 160l16 16-16 16"/>
            <path class="ill-line" d="M348 190h60M348 215h74M348 240h44"/>
          </svg>
        </div>
      </div>
    </div>
  </section>

  <!-- ── How it works ── -->
  <section id="how-to-donate" class="section-white">
    <div class="container">
      <div class="about-section-head about-section-head--center">
        <span class="about-eyebrow about-eyebrow--accent">The flow</span>
        <h2>Two sides, one connection.</h2>
        <p class="about-section-sub">Simple and transparent, with fees and payment state shown before checkout. The final result is confirmed once the payment provider redirects back.</p>
      </div>

      <div class="how-grid">
        <article class="how-card">
          <div class="how-card-head how-card-head--donor">
            <span class="how-card-role">For donors</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          </div>
          <ol class="how-steps">
            <li><span class="step-number">1</span><div><strong>Recharge your balance</strong><small>Fund your wallet once, donate many times.</small></div></li>
            <li><span class="step-number">2</span><div><strong>Browse campaigns</strong><small>Filter by category, location, or urgency.</small></div></li>
            <li><span class="step-number">3</span><div><strong>Confirm &amp; pay</strong><small>See the fee before you pay. Zero hidden cost.</small></div></li>
            <li><span class="step-number">4</span><div><strong>Track impact</strong><small>Real-time status, receipts, and updates.</small></div></li>
          </ol>
        </article>

        <div class="how-connector" aria-hidden="true">
          <svg viewBox="0 0 60 200" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M30 10v60M30 130v60" stroke-dasharray="3 4"/>
            <circle cx="30" cy="100" r="14" fill="#2563eb" stroke="none"/>
            <path d="M30 92v16M22 100h16" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
            <text x="30" y="126" font-family="Poppins" font-size="10" fill="currentColor" text-anchor="middle" font-weight="700">Campaign</text>
          </svg>
        </div>

        <article class="how-card">
          <div class="how-card-head how-card-head--recipient">
            <span class="how-card-role">For those in need</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <ol class="how-steps">
            <li><span class="step-number">1</span><div><strong>Create a campaign</strong><small>Tell your story with real photos.</small></div></li>
            <li><span class="step-number">2</span><div><strong>Get verified</strong><small>Every campaign is reviewed by our team.</small></div></li>
            <li><span class="step-number">3</span><div><strong>Receive donations</strong><small>Directly, transparently, with tracking.</small></div></li>
            <li><span class="step-number">4</span><div><strong>Post updates</strong><small>Show donors the impact of their support.</small></div></li>
          </ol>
        </article>
      </div>

      <div class="highlight-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M13 10V3L4 14h7v7l9-11h-7z"></path>
        </svg>
        <div>
          <h4>Direct connection. No middlemen.</h4>
          <p>Sawa removes third-party intermediaries while keeping platform fees visible. Donors connect with families and organizations through a transparent checkout, and the final donation status is confirmed automatically.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Trust strip: payment methods + verification ── -->
  <section id="trust" class="section-light about-trust">
    <div class="container">
      <div class="about-section-head about-section-head--center">
        <span class="about-eyebrow about-eyebrow--accent">Trust &amp; safety</span>
        <h2>Every part of Sawa is audited.</h2>
      </div>

      <div class="about-trust-grid">
        <article class="about-trust-card">
          <span class="about-trust-icon" style="background:#eff6ff; color:#2563eb;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
          </span>
          <h3>NGO verification</h3>
          <p>Every organization submits a registration document reviewed by our admins before any campaign goes live.</p>
        </article>
        <article class="about-trust-card">
          <span class="about-trust-icon" style="background:#ecfdf5; color:#059669;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <h3>Secure payments</h3>
          <p>Payments run through Whish Money and card providers. We never store your card details on our servers.</p>
        </article>
        <article class="about-trust-card">
          <span class="about-trust-icon" style="background:#fff7ed; color:#d97706;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </span>
          <h3>Downloadable receipts</h3>
          <p>Every donation generates a PDF receipt you can archive, share with your NGO, or attach to your tax records.</p>
        </article>
        <article class="about-trust-card">
          <span class="about-trust-icon" style="background:#fef2f2; color:#dc2626;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </span>
          <h3>Report &amp; review</h3>
          <p>Anyone can flag a suspicious campaign. Admin reviews are logged in a read-only audit trail.</p>
        </article>
      </div>

      <div class="about-payment-strip">
        <span class="about-payment-label">Payments powered by</span>
        <div class="about-payment-logos">
          <span class="about-payment-logo">Whish Money</span>
          <span class="about-payment-logo">Visa</span>
          <span class="about-payment-logo">Mastercard</span>
          <span class="about-payment-logo">SAWA Wallet</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ── FAQ ── -->
  <section id="faq" class="section-white">
    <div class="container">
      <div class="about-section-head about-section-head--center">
        <span class="about-eyebrow about-eyebrow--accent">Common questions</span>
        <h2>Frequently asked.</h2>
        <p class="about-section-sub">Can't find your answer? <a href="mailto:sawatogether961@gmail.com">Email us</a> — we read every message.</p>
      </div>
      <div class="faq-list">
        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            <span>Is my donation secure?</span>
            <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div class="faq-answer" hidden>
            <p>Yes. All transactions are processed through secure, verified payment gateways. We do not store your credit card information on our servers.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            <span>How do I know my donation reached the right person?</span>
            <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div class="faq-answer" hidden>
            <p>Sawa provides a transparent tracking system. You will receive updates when your donation is processed, transferred, and received by the campaign creator or organization.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            <span>Is there a minimum donation amount?</span>
            <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div class="faq-answer" hidden>
            <p>There is no minimum donation amount. Every contribution, large or small, makes a real difference to the families and organizations we support.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            <span>Can I donate without creating an account?</span>
            <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div class="faq-answer" hidden>
            <p>Yes — you can browse campaigns and make a one-time guest donation without registering. Creating a free account lets you track all your donations and receive updates from the campaigns you've supported.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            <span>How does the verification process work?</span>
            <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div class="faq-answer" hidden>
            <p>NGOs submit their registration document during signup. Our admins review each submission before the account is approved. Individual campaigns undergo a separate review before they become visible to donors.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            <span>Where does the 5% fee go?</span>
            <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div class="faq-answer" hidden>
            <p>The support fee covers payment provider costs (card processing, Whish charges) and platform hosting. It is shown to you before you confirm any payment — there are no hidden costs after checkout.</p>
          </div>
        </div>
      </div>

      <details class="about-policy">
        <summary>
          <span>Terms &amp; conditions and privacy policy</span>
          <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </summary>
        <div class="about-policy-body">
          <h4>Terms of service</h4>
          <p>By using Sawa you agree to our community guidelines. Donors must use lawfully obtained funds. Organizations and individuals requesting help must provide accurate information. We reserve the right to suspend any account found engaging in fraudulent activity.</p>
          <h4>Privacy policy</h4>
          <p>We collect only the information required to facilitate donations and verify campaigns. Personal data is never sold. For recipients, contact details are shared only with verified organizations facilitating aid.</p>
          <p><em>Full documents available on request — email <a href="mailto:sawatogether961@gmail.com">sawatogether961@gmail.com</a>.</em></p>
        </div>
      </details>
    </div>
  </section>

  <!-- ── Contact ── -->
  <section id="contact" class="section-light">
    <div class="container about-contact-grid">
      <div>
        <span class="about-eyebrow about-eyebrow--accent">Talk to us</span>
        <h2>Real people, real replies.</h2>
        <p>Ideas, bug reports, partnership requests, or a family that needs help — send us a message. We usually reply within 24 hours.</p>
      </div>
      <div class="about-contact-cards">
        <a href="mailto:sawatogether961@gmail.com" class="about-contact-card">
          <span class="about-contact-icon" style="background:#eff6ff; color:#2563eb;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </span>
          <div><strong>Email</strong><span>sawatogether961@gmail.com</span></div>
        </a>
        <a href="tel:+96171612269" class="about-contact-card">
          <span class="about-contact-icon" style="background:#ecfdf5; color:#059669;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13 1.05.37 2.08.72 3.06a2 2 0 0 1-.45 2.11L8.09 10.91a16 16 0 0 0 6 6l2.02-1.29a2 2 0 0 1 2.11-.45c.98.35 2.01.59 3.06.72A2 2 0 0 1 22 16.92z"/></svg>
          </span>
          <div><strong>Call</strong><span>+961 71 61 22 69</span></div>
        </a>
        <div class="about-contact-card about-contact-card--static">
          <span class="about-contact-icon" style="background:#fff7ed; color:#d97706;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </span>
          <div><strong>Based in</strong><span>Tripoli, Lebanon</span></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Final CTA ── -->
  <section class="cta">
    <div class="container">
      <div class="cta-content">
        <span class="about-eyebrow about-eyebrow--light">Ready?</span>
        <h2>Be part of the change.</h2>
        <p>Whether you want to donate, request help, or partner with us, Sawa is here to connect compassion with action.</p>
        <div class="cta-buttons">
          <a href="signup.php" class="btn btn-accent">Get started free</a>
          <a href="login.php" class="btn btn-outline">Log in</a>
        </div>
      </div>
    </div>
  </section>

  <footer>
    <div class="container">
      <div class="footer-grid">
        <div class="footer-section">
          <div class="footer-brand">
            <div class="footer-logo"><img src="../images/sawa_v2.svg" alt="Sawa" style="width: 2.8rem; height: 2.8rem;"></div>
            <span>Sawa</span>
          </div>
          <p class="footer-desc">Direct help for Lebanese families in need.</p>
          <div class="footer-social">
            <a href="https://www.facebook.com/sawatogether" target="_blank" rel="noopener" aria-label="Facebook">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            <a href="https://www.instagram.com/sawatogether" target="_blank" rel="noopener" aria-label="Instagram">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
            </a>
            <a href="mailto:sawatogether961@gmail.com" aria-label="Email">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.91 12 9.818l6.545-4.91 1.528-1.418C21.691 2.28 24 3.434 24 5.457z"/></svg>
            </a>
          </div>
        </div>
        <div class="footer-section">
          <h4>Contact us</h4>
          <ul>
            <li><a href="tel:+96171612269">+961 71 61 22 69</a></li>
            <li><a href="mailto:sawatogether961@gmail.com">sawatogether961@gmail.com</a></li>
            <!-- 1.4rem, not 0.875rem: the root is 62.5%, so 1rem is 10px and
                 0.875rem rendered this at 8.75px rather than the intended 14px.
                 Matches .footer-section a in css/about.css. -->
            <li><span style="opacity:0.75;font-size:1.4rem;">Lebanon — Tripoli</span></li>
          </ul>
        </div>
        <div class="footer-section">
          <h4>Discover</h4>
          <ul>
            <li><a href="#mission">Mission</a></li>
            <li><a href="#how-to-donate">How it works</a></li>
            <li><a href="#trust">Trust &amp; safety</a></li>
            <li><a href="#faq">FAQs</a></li>
          </ul>
        </div>
        <div class="footer-section">
          <h4>Get started</h4>
          <ul>
            <li><a href="../index.html">Home</a></li>
            <li><a href="userhome.php">Browse campaigns</a></li>
            <li><a href="login.php">Log in</a></li>
            <li><a href="signup.php">Sign up</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <?= $e($year) ?> Sawa. All rights reserved. &middot; Built in response to the Lebanese crisis.</p>
      </div>
    </div>
  </footer>

  <script src="../js/about.js"></script>
</body>
</html>
