<?php
declare(strict_types=1);

final class DonationService
{
    public static function feeRate(bool $isGuest, string $paymentMethod): float
    {
        if ($paymentMethod === 'wallet' && !$isGuest) {
            return FEE_RATE_MEMBER_WALLET;
        }
        return $isGuest ? FEE_RATE_GUEST : FEE_RATE_DIRECT;
    }

    /** @return array{donation: float, fee: float, total: float} */
    public static function breakdown(float $amount, bool $isGuest, string $paymentMethod): array
    {
        $rate = self::feeRate($isGuest, $paymentMethod);
        $fee = round($amount * $rate, 2);
        return ['donation' => $amount, 'fee' => $fee, 'total' => round($amount + $fee, 2)];
    }

    public static function recordStatus(int $donationId, ?string $from, string $to, ?int $userId = null, ?string $notes = null): void
    {
        db()->prepare(
            'INSERT INTO donation_status_history (donation_id, from_status, to_status, changed_by, notes)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$donationId, $from, $to, $userId, $notes]);
    }

    public static function completeDonation(int $donationId, ?string $paymentRef = null): void
    {
        $pdo = db();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare('SELECT * FROM donations WHERE id = ? FOR UPDATE');
            $stmt->execute([$donationId]);
            $don = $stmt->fetch();
            if (!$don || !in_array($don['status'], ['pending'], true)) {
                throw new RuntimeException('invalid_donation');
            }

            $pdo->prepare(
                'UPDATE donations SET status = \'verified\', payment_ref = ?, verified_at = NOW() WHERE id = ?'
            )->execute([$paymentRef, $donationId]);

            self::recordStatus($donationId, 'pending', 'verified', null, 'Payment confirmed');

            $pdo->prepare(
                'UPDATE campaigns SET raised_amount = raised_amount + ? WHERE id = ?'
            )->execute([(float) $don['amount'], (int) $don['campaign_id']]);

            $camp = CampaignService::find((int) $don['campaign_id']);
            if ($camp && !empty($camp['organisation_id'])) {
                $creatorId = $don['donor_id'] ? (int) $don['donor_id'] : null;
                WalletService::creditOrganisation(
                    (int) $camp['organisation_id'],
                    (float) $don['amount'],
                    $donationId,
                    $creatorId ?? 0,
                    'Donation #' . $donationId
                );
            }

            if ($ownTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function forCampaign(int $campaignId): array
    {
        $stmt = db()->prepare(
            'SELECT d.*, u.full_name AS donor_name
             FROM donations d
             LEFT JOIN users u ON u.id = d.donor_id
             WHERE d.campaign_id = ? AND d.status IN (\'verified\',\'completed\')
             ORDER BY d.created_at DESC'
        );
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function activityForUser(?int $userId): array
    {
        if ($userId === null) {
            return [];
        }
        $stmt = db()->prepare(
            'SELECT d.*, c.title AS campaign_title, r.bill_id, r.id AS receipt_id,
                    r.method_label, r.total_paid, r.provider_ref
             FROM donations d
             INNER JOIN campaigns c ON c.id = d.campaign_id
             LEFT JOIN receipts r ON r.donation_id = d.id
             WHERE d.donor_id = ?
             ORDER BY d.created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function recentForDonor(int $userId, int $limit = 4): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = db()->prepare(
            'SELECT d.*, c.title AS campaign_title, u.full_name AS donor_name
             FROM donations d
             INNER JOIN campaigns c ON c.id = d.campaign_id
             LEFT JOIN users u ON u.id = d.donor_id
             WHERE d.donor_id = ?
             ORDER BY d.created_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function recentForOrganisation(int $orgUserId, int $limit = 4): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = db()->prepare(
            'SELECT d.*, c.title AS campaign_title, u.full_name AS donor_name
             FROM donations d
             INNER JOIN campaigns c ON c.id = d.campaign_id
             INNER JOIN organisations o ON o.id = c.organisation_id AND o.user_id = ?
             LEFT JOIN users u ON u.id = d.donor_id
             WHERE d.status IN (\'verified\', \'completed\')
             ORDER BY d.created_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$orgUserId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function recentDonorsForOwner(int $ownerUserId, int $limit = 4): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = db()->prepare(
            'SELECT d.*, u.full_name AS donor_name
             FROM donations d
             INNER JOIN campaigns c ON c.id = d.campaign_id AND c.owner_user_id = ?
             LEFT JOIN users u ON u.id = d.donor_id
             WHERE d.status IN (\'verified\', \'completed\')
             ORDER BY d.created_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$ownerUserId]);
        return $stmt->fetchAll();
    }
}
