<?php
declare(strict_types=1);

final class Mailer
{
    public static function sendVerification(string $email, string $token): bool
    {
        $url = absolute_url('php/auth/verify-email.php') . '?token=' . urlencode($token);
        $body = "Welcome to Sawa.\n\nVerify your email:\n$url\n\nThis link expires in 48 hours.";
        return send_mail($email, 'Verify your Sawa account', $body);
    }

    public static function sendPasswordReset(string $email, string $token): bool
    {
        $url = absolute_url('pages/reset-password.php') . '?token=' . urlencode($token);
        $body = "Reset your Sawa password:\n$url\n\nThis link expires in 1 hour. If you did not request this, ignore this email.";
        return send_mail($email, 'Reset your Sawa password', $body);
    }

    /**
     * The guest donor's copy of their receipt. Guests have no account, so the
     * capability token in this link is the only way they can ever reach it —
     * the bill id alone is deliberately not enough.
     */
    public static function sendReceipt(string $email, string $billId, string $token, float $total): bool
    {
        $url = absolute_url('php/receipts/download.php') . '?token=' . urlencode($token);
        $body = "Thank you for your donation.\n\n"
            . "Receipt: $billId\n"
            . 'Total paid: $' . number_format($total, 2) . "\n\n"
            . "Download your receipt:\n$url\n\n"
            . "Keep this link private — anyone who has it can view this receipt.";
        return send_mail($email, 'Your Sawa receipt ' . $billId, $body);
    }

    public static function sendOrgDecision(string $email, bool $approved, ?string $reason = null): bool
    {
        if ($approved) {
            $url = absolute_url('pages/getverified.html');
            $body = "Your organisation has been verified on Sawa.\n\nOpen your dashboard:\n$url";
            return send_mail($email, 'Sawa — Organisation approved', $body);
        }
        $url = absolute_url('pages/reject.html');
        $body = "Your organisation application was not approved.\n\n";
        if ($reason) {
            $body .= "Reason: $reason\n\n";
        }
        $body .= "Details: $url";
        return send_mail($email, 'Sawa — Application update', $body);
    }
}
