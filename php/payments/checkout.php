<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    Response::abort(404);
}

$session = PaymentService::findByToken($token);
if (!$session || $session['status'] !== 'pending') {
    Response::redirectStatus('pages/userhome.php', 'payment_failed');
}

$providerLabel = match ($session['provider']) {
    'whish'           => 'Whish Money',
    'hosted_checkout' => 'Visa / Mastercard',
    default           => 'Sawa Checkout',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/tokens.css">
    <link rel="stylesheet" href="../../css/login.css">
    <title>Secure Checkout — Sawa</title>
</head>
<body>
<div class="login-page">
  <div class="login-card" style="margin:3rem auto;max-width:420px;">
    <h1>Secure checkout</h1>
    <p class="auth-sub">Provider: <?= htmlspecialchars($providerLabel, ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Total: $<?= number_format((float) $session['total_amount'], 2) ?></strong></p>
    <p style="font-size:0.9rem;color:var(--color-text-muted);">This is Sawa's development checkout. In production, you would be redirected to <?= htmlspecialchars($providerLabel, ENT_QUOTES, 'UTF-8') ?>.</p>
    <form action="callback.php" method="POST" style="margin-top:1.5rem;">
      <?= Csrf::field() ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="confirm">
      <input type="submit" class="login_form" value="Confirm payment" style="width:100%;padding:0.9rem;background:var(--color-primary);color:#fff;border:none;border-radius:10px;cursor:pointer;">
    </form>
    <form action="callback.php" method="POST" style="margin-top:0.75rem;">
      <?= Csrf::field() ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="cancel">
      <button type="submit" style="width:100%;padding:0.75rem;background:transparent;border:1px solid var(--color-border);border-radius:10px;cursor:pointer;">Cancel</button>
    </form>
  </div>
</div>
</body>
</html>
