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
                    $creatorId,
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

    /**
     * Platform-wide recent donations for the guest "Live activity" feed.
     *
     * Anonymous donations and guests are deliberately not attributed: the feed
     * is public, so it must never leak a donor's name or email. Callers get a
     * pre-resolved display label instead of the raw donor row.
     *
     * @return list<array{label: string, amount: float, campaign: string, created_at: string, anonymous: bool}>
     */
    public static function recentPlatformActivity(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $rows = db()->query(
            'SELECT d.amount, d.anonymous, d.donor_id, d.created_at,
                    u.full_name AS donor_name, c.title AS campaign_title
             FROM donations d
             INNER JOIN campaigns c ON c.id = d.campaign_id
             LEFT  JOIN users u ON u.id = d.donor_id
             WHERE d.status IN (\'verified\', \'completed\')
             ORDER BY d.created_at DESC
             LIMIT ' . $limit
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $anon = (int) $r['anonymous'] === 1;
            if ($anon || $r['donor_id'] === null) {
                $label = $anon ? 'Anonymous' : 'Guest donor';
            } else {
                // First name only — enough to feel human without publishing
                // a full identity on a page anyone can load.
                $label = explode(' ', trim((string) $r['donor_name']))[0] ?: 'Sawa donor';
            }
            $out[] = [
                'label' => $label,
                'amount' => (float) $r['amount'],
                'campaign' => (string) $r['campaign_title'],
                'created_at' => (string) $r['created_at'],
                'anonymous' => $anon,
            ];
        }
        return $out;
    }

    /**
     * Real donor totals used on the donor dashboard tiles. Returns lifetime
     * numbers plus this/last-month buckets so the UI can show a truthful delta
     * (or omit it when there's nothing to compare against).
     *
     * @return array{
     *   total: float,
     *   families: int,
     *   this_month: float,
     *   last_month: float,
     *   delta_pct: ?int,
     *   families_delta: int
     * }
     */
    public static function donorTotals(int $userId): array
    {
        $pdo = db();
        $totals = ['total' => 0.0, 'families' => 0, 'this_month' => 0.0, 'last_month' => 0.0];

        $sumStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total,
                    COUNT(DISTINCT c.owner_user_id) AS families
             FROM donations d
             INNER JOIN campaigns c ON c.id = d.campaign_id
             WHERE d.donor_id = ? AND d.status IN ('verified','completed')"
        );
        $sumStmt->execute([$userId]);
        if ($row = $sumStmt->fetch()) {
            $totals['total'] = (float) $row['total'];
            $totals['families'] = (int) $row['families'];
        }

        // php/config/db.php builds a hardcoded mysql: DSN with emulated prepares
        // off, so an earlier strftime() variant here could never prepare — it
        // failed on every call and always fell through to this query.
        $monthStmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN DATE_FORMAT(d.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') THEN d.amount ELSE 0 END), 0) AS this_month,
                COALESCE(SUM(CASE WHEN DATE_FORMAT(d.created_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m') THEN d.amount ELSE 0 END), 0) AS last_month,
                COUNT(DISTINCT CASE WHEN DATE_FORMAT(d.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') THEN c.owner_user_id END) AS families_this,
                COUNT(DISTINCT CASE WHEN DATE_FORMAT(d.created_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m') THEN c.owner_user_id END) AS families_last
             FROM donations d
             INNER JOIN campaigns c ON c.id = d.campaign_id
             WHERE d.donor_id = ? AND d.status IN ('verified','completed')"
        );
        $monthStmt->execute([$userId]);
        $row = $monthStmt->fetch();

        $thisMonth = (float) ($row['this_month'] ?? 0);
        $lastMonth = (float) ($row['last_month'] ?? 0);
        $familiesThis = (int) ($row['families_this'] ?? 0);
        $familiesLast = (int) ($row['families_last'] ?? 0);

        $deltaPct = null;
        if ($lastMonth > 0) {
            $deltaPct = (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100);
        }

        return [
            'total' => $totals['total'],
            'families' => $totals['families'],
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'delta_pct' => $deltaPct,
            'families_delta' => $familiesThis - $familiesLast,
        ];
    }
}
