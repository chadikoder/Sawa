<?php
declare(strict_types=1);

final class PaymentService
{
    public static function createSession(
        string $purpose,
        float $amount,
        float $fee,
        string $provider,
        ?int $userId,
        ?int $donationId
    ): array {
        $token = bin2hex(random_bytes(32));
        $total = round($amount + $fee, 2);
        db()->prepare(
            'INSERT INTO payment_sessions (user_id, donation_id, purpose, amount, fee_amount, total_amount, provider, return_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $donationId, $purpose, $amount, $fee, $total, $provider, $token]);

        return [
            'id'    => (int) db()->lastInsertId(),
            'token' => $token,
            'total' => $total,
        ];
    }

    public static function findByToken(string $token): ?array
    {
        $stmt = db()->prepare('SELECT * FROM payment_sessions WHERE return_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function confirm(string $token, string $providerRef): void
    {
        $session = self::findByToken($token);
        if (!$session || $session['status'] !== 'pending') {
            throw new RuntimeException('invalid_session');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE payment_sessions SET status = \'confirmed\', provider_ref = ?, completed_at = NOW() WHERE id = ?'
            )->execute([$providerRef, $session['id']]);

            if ($session['purpose'] === 'wallet_topup' && $session['user_id']) {
                WalletService::credit(
                    (int) $session['user_id'],
                    (float) $session['amount'],
                    'topup',
                    'Wallet top-up',
                    $providerRef
                );
            } elseif ($session['purpose'] === 'donation' && $session['donation_id']) {
                DonationService::completeDonation((int) $session['donation_id'], $providerRef);
                ReceiptService::createForDonation((int) $session['donation_id']);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function fail(string $token): void
    {
        db()->prepare(
            'UPDATE payment_sessions SET status = \'failed\', completed_at = NOW() WHERE return_token = ?'
        )->execute([$token]);
        $session = self::findByToken($token);
        if ($session && $session['donation_id']) {
            db()->prepare('UPDATE donations SET status = \'failed\' WHERE id = ?')
                ->execute([$session['donation_id']]);
        }
    }
}
