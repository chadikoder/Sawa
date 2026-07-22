<?php
declare(strict_types=1);

final class ReceiptService
{
    public static function nextBillId(): string
    {
        $year = date('Y');
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM receipts WHERE bill_id LIKE ?'
        );
        $stmt->execute(["SAWA-{$year}-%"]);
        $n = (int) $stmt->fetchColumn() + 1;
        return sprintf('SAWA-%s-%04d', $year, $n);
    }

    public static function createForDonation(int $donationId): int
    {
        $stmt = db()->prepare(
            'SELECT d.*, c.title AS campaign_title
             FROM donations d
             INNER JOIN campaigns c ON c.id = d.campaign_id
             WHERE d.id = ?'
        );
        $stmt->execute([$donationId]);
        $don = $stmt->fetch();
        if (!$don) {
            throw new RuntimeException('donation_not_found');
        }

        $billId = self::nextBillId();
        $checksum = hash('sha256', $billId . '|' . $donationId . '|' . $don['total_charged'] . '|' . SESSION_SECRET);
        $method = match ($don['payment_method']) {
            'wallet'          => 'Sawa Wallet',
            'whish'           => 'Whish Money',
            'hosted_checkout' => 'Visa / Mastercard',
            default           => 'Payment',
        };

        // 32 random bytes from the CSPRNG. This is the only thing standing
        // between a guest receipt and the public, so it must not be derived
        // from the bill id, the donation id or anything else guessable.
        $accessToken = bin2hex(random_bytes(32));

        db()->prepare(
            'INSERT INTO receipts (bill_id, user_id, donation_id, recipient_label, method_label,
                                   subtotal, fee_amount, total_paid, provider_ref, checksum, access_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $billId,
            $don['donor_id'],
            $donationId,
            $don['campaign_title'],
            $method,
            $don['amount'],
            $don['fee_amount'],
            $don['total_charged'],
            $don['payment_ref'],
            substr($checksum, 0, 64),
            $accessToken,
        ]);

        return (int) db()->lastInsertId();
    }

    /**
     * Email a guest donor the capability link to their receipt.
     *
     * Only for donations with no donor_id — members reach their receipts
     * through the dashboard, authorised by session, and do not need a token
     * link. Best-effort by design: this runs after the payment has already
     * committed, so a mail failure must never surface as a failed donation.
     */
    public static function emailGuestCopy(int $donationId): void
    {
        try {
            $stmt = db()->prepare(
                'SELECT r.bill_id, r.access_token, r.total_paid, d.guest_email
                   FROM receipts r
                   INNER JOIN donations d ON d.id = r.donation_id
                  WHERE r.donation_id = ? AND d.donor_id IS NULL
                  LIMIT 1'
            );
            $stmt->execute([$donationId]);
            $row = $stmt->fetch();
            if (!$row || empty($row['guest_email']) || empty($row['access_token'])) {
                return;
            }
            Mailer::sendReceipt(
                (string) $row['guest_email'],
                (string) $row['bill_id'],
                (string) $row['access_token'],
                (float) $row['total_paid']
            );
        } catch (Throwable $e) {
            error_log('[Sawa receipt mail] ' . $e->getMessage());
        }
    }

    /** The access token for a receipt, or null. Used to build the guest link. */
    public static function accessTokenFor(int $receiptId): ?string
    {
        $stmt = db()->prepare('SELECT access_token FROM receipts WHERE id = ? LIMIT 1');
        $stmt->execute([$receiptId]);
        $token = $stmt->fetchColumn();
        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * A receipt by its capability token — the guest path, which carries no
     * session. The token is 256 bits of CSPRNG output looked up on a UNIQUE
     * index, so there is nothing to enumerate and no user to compare against.
     *
     * Callers must reject a malformed token before calling: a NULL
     * access_token (every receipt issued before this column existed) can never
     * match a bound parameter, but an empty string reaching the query would
     * still be a pointless scan.
     */
    public static function findByAccessToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $stmt = db()->prepare(
            'SELECT * FROM receipts WHERE access_token = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * A receipt, but only if it belongs to $userId.
     *
     * The owner is a required argument on purpose. This used to take a
     * nullable $userId and skip the ownership clause when it was null, so a
     * caller passing null for a signed-out visitor got *any* receipt by bill
     * id — and bill ids are sequential (SAWA-2026-0001, -0002, ...), so the
     * whole table could be walked by a stranger. The old filter also carried
     * `OR user_id IS NULL`, which handed every guest donor's receipt to any
     * signed-in account. Keep both the argument and the clause mandatory.
     */
    public static function findByBillId(string $billId, int $userId): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM receipts WHERE bill_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$billId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
