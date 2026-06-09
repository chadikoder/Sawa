<?php
declare(strict_types=1);

final class WalletService
{
    public static function balance(int $userId): float
    {
        $stmt = db()->prepare(
            'SELECT balance_after FROM user_wallet_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? (float) $row['balance_after'] : 0.0;
    }

    public static function credit(int $userId, float $amount, string $type, ?string $desc = null, ?string $paymentRef = null, ?int $donationId = null): float
    {
        $newBal = self::balance($userId) + $amount;
        db()->prepare(
            'INSERT INTO user_wallet_ledger (user_id, type, amount, balance_after, related_donation_id, description, payment_ref)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $type, $amount, $newBal, $donationId, $desc, $paymentRef]);
        return $newBal;
    }

    public static function debit(int $userId, float $amount, string $type, ?string $desc = null, ?int $donationId = null): float
    {
        $bal = self::balance($userId);
        if ($amount > $bal) {
            throw new RuntimeException('insufficient_balance');
        }
        $newBal = $bal - $amount;
        db()->prepare(
            'INSERT INTO user_wallet_ledger (user_id, type, amount, balance_after, related_donation_id, description)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $type, $amount, $newBal, $donationId, $desc]);
        return $newBal;
    }

    /** @return list<array<string, mixed>> */
    public static function transactions(int $userId, int $limit = 50): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM user_wallet_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function orgBalance(int $organisationId): float
    {
        $stmt = db()->prepare(
            'SELECT balance_after FROM wallet_transactions WHERE organisation_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$organisationId]);
        $row = $stmt->fetch();
        return $row ? (float) $row['balance_after'] : 0.0;
    }

    public static function creditOrganisation(int $orgId, float $amount, int $donationId, int $adminId, string $desc): void
    {
        $bal = self::orgBalance($orgId) + $amount;
        db()->prepare(
            'INSERT INTO wallet_transactions (organisation_id, type, amount, balance_after, related_donation_id, description, created_by)
             VALUES (?, \'credit\', ?, ?, ?, ?, ?)'
        )->execute([$orgId, $amount, $bal, $donationId, $desc, $adminId]);
    }
}
