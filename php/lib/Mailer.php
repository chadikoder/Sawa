<?php
declare(strict_types=1);

final class Mailer
{
    public static function sendVerification(string $email, string $token): bool
    {
        $url = APP_URL . '/php/auth/verify-email.php?token=' . urlencode($token);
        $body = "Welcome to Sawa.\n\nVerify your email:\n$url\n\nThis link expires in 48 hours.";
        return send_mail($email, 'Verify your Sawa account', $body);
    }

    public static function sendPasswordReset(string $email, string $token): bool
    {
        $url = APP_URL . '/pages/reset-password.html?token=' . urlencode($token);
        $body = "Reset your Sawa password:\n$url\n\nThis link expires in 1 hour. If you did not request this, ignore this email.";
        return send_mail($email, 'Reset your Sawa password', $body);
    }

    public static function sendOrgDecision(string $email, bool $approved, ?string $reason = null): bool
    {
        if ($approved) {
            $url = APP_URL . '/pages/getverified.html';
            $body = "Your organisation has been verified on Sawa.\n\nOpen your dashboard:\n$url";
            return send_mail($email, 'Sawa — Organisation approved', $body);
        }
        $url = APP_URL . '/pages/reject.html';
        $body = "Your organisation application was not approved.\n\n";
        if ($reason) {
            $body .= "Reason: $reason\n\n";
        }
        $body .= "Details: $url";
        return send_mail($email, 'Sawa — Application update', $body);
    }
}
