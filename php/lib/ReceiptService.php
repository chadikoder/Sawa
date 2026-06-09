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

        db()->prepare(
            'INSERT INTO receipts (bill_id, user_id, donation_id, recipient_label, method_label,
                                   subtotal, fee_amount, total_paid, provider_ref, checksum)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
        ]);

        return (int) db()->lastInsertId();
    }

    public static function findByBillId(string $billId, ?int $userId = null): ?array
    {
        $sql = 'SELECT * FROM receipts WHERE bill_id = ?';
        $params = [$billId];
        if ($userId !== null) {
            $sql .= ' AND (user_id = ? OR user_id IS NULL)';
            $params[] = $userId;
        }
        $sql .= ' LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
