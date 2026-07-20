<?php
declare(strict_types=1);

final class CampaignService
{
    /** @var array<string, string> */
    public const CATEGORY_SLUGS = [
        'Medical'      => 'medical',
        'Educational'  => 'educational',
        'Food'         => 'food',
        'Shelter'      => 'shelter',
        'Other'        => 'other',
    ];

    /** @var array<string, string> */
    public const LOCATION_SLUGS = [
        'Beirut'         => 'beirut',
        'Tripoli'        => 'tripoli',
        'Akkar'          => 'akkar',
        'Batroun'        => 'batroun',
        'Baalbek'        => 'baalbek',
        'South_lb'       => 'south_lb',
    ];

    public static function categorySlug(string $label): ?string
    {
        return self::CATEGORY_SLUGS[$label] ?? null;
    }

    public static function locationSlug(string $label): ?string
    {
        return self::LOCATION_SLUGS[$label] ?? null;
    }

    public static function categoryCss(string $nameEn): string
    {
        return match (strtolower($nameEn)) {
            'medical'     => 'cat-medical',
            'educational' => 'cat-edu',
            'food'        => 'cat-food',
            'shelter'     => 'cat-shelter',
            default       => 'cat-other',
        };
    }

    public static function locationLabel(string $slug): string
    {
        foreach (self::LOCATION_SLUGS as $label => $s) {
            if ($s === $slug) {
                return $label;
            }
        }
        return ucfirst(str_replace('_', ' ', $slug));
    }

    /** @return list<array<string, mixed>> */
    public static function listActive(): array
    {
        $sql = 'SELECT c.*, cat.name_en AS category_name, loc.slug AS location_slug,
                       loc.name_en AS location_name,
                       COALESCE(o.name, u.full_name) AS creator_name,
                       o.user_id AS org_user_id,
                       (SELECT COUNT(DISTINCT d.donor_id) FROM donations d
                        WHERE d.campaign_id = c.id AND d.status IN (\'verified\',\'completed\')) AS donor_count,
                       (SELECT GROUP_CONCAT(ci.image_path ORDER BY ci.sort_order SEPARATOR \'|\')
                        FROM campaign_images ci WHERE ci.campaign_id = c.id) AS image_paths
                FROM campaigns c
                LEFT JOIN categories cat ON cat.id = c.category_id
                LEFT JOIN locations loc ON loc.id = c.location_id
                LEFT JOIN organisations o ON o.id = c.organisation_id
                LEFT JOIN users u ON u.id = c.owner_user_id
                WHERE c.status = \'active\'
                ORDER BY c.created_at DESC';
        return db()->query($sql)->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function listForUser(int $userId, string $role): array
    {
        if ($role === 'organisation') {
            $sql = 'SELECT c.*, cat.name_en AS category_name, loc.slug AS location_slug,
                           loc.name_en AS location_name,
                           COALESCE(o.name, u.full_name) AS creator_name,
                           o.user_id AS org_user_id,
                           (SELECT COUNT(DISTINCT d.donor_id) FROM donations d
                            WHERE d.campaign_id = c.id AND d.status IN (\'verified\',\'completed\')) AS donor_count,
                           (SELECT GROUP_CONCAT(ci.image_path ORDER BY ci.sort_order SEPARATOR \'|\')
                            FROM campaign_images ci WHERE ci.campaign_id = c.id) AS image_paths
                    FROM campaigns c
                    INNER JOIN organisations o ON o.id = c.organisation_id AND o.user_id = ?
                    LEFT JOIN categories cat ON cat.id = c.category_id
                    LEFT JOIN locations loc ON loc.id = c.location_id
                    LEFT JOIN users u ON u.id = c.owner_user_id
                    ORDER BY c.created_at DESC';
            $stmt = db()->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        }

        $sql = 'SELECT c.*, cat.name_en AS category_name, loc.slug AS location_slug,
                       loc.name_en AS location_name,
                       COALESCE(o.name, u.full_name) AS creator_name,
                       o.user_id AS org_user_id,
                       (SELECT COUNT(DISTINCT d.donor_id) FROM donations d
                        WHERE d.campaign_id = c.id AND d.status IN (\'verified\',\'completed\')) AS donor_count,
                       (SELECT GROUP_CONCAT(ci.image_path ORDER BY ci.sort_order SEPARATOR \'|\')
                        FROM campaign_images ci WHERE ci.campaign_id = c.id) AS image_paths
                FROM campaigns c
                LEFT JOIN categories cat ON cat.id = c.category_id
                LEFT JOIN locations loc ON loc.id = c.location_id
                LEFT JOIN organisations o ON o.id = c.organisation_id
                LEFT JOIN users u ON u.id = c.owner_user_id
                WHERE c.owner_user_id = ?
                ORDER BY c.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

  /** @return ?array<string, mixed> */
    public static function find(int $id): ?array
    {
        $sql = 'SELECT c.*, cat.name_en AS category_name, loc.slug AS location_slug,
                       COALESCE(o.name, u.full_name) AS creator_name,
                       o.user_id AS org_user_id, o.verified AS org_verified
                FROM campaigns c
                LEFT JOIN categories cat ON cat.id = c.category_id
                LEFT JOIN locations loc ON loc.id = c.location_id
                LEFT JOIN organisations o ON o.id = c.organisation_id
                LEFT JOIN users u ON u.id = c.owner_user_id
                WHERE c.id = ?
                LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function isUrgent(array $camp): bool
    {
        if (empty($camp['ends_at'])) {
            return false;
        }
        $end = new DateTimeImmutable((string) $camp['ends_at']);
        return $end <= new DateTimeImmutable('+7 days');
    }

    public static function canManage(int $userId, string $role, array $camp): bool
    {
        if ($role === 'admin') {
            return true;
        }
        if (!empty($camp['owner_user_id']) && (int) $camp['owner_user_id'] === $userId) {
            return true;
        }
        if ($role === 'organisation' && !empty($camp['organisation_id'])) {
            $stmt = db()->prepare('SELECT id FROM organisations WHERE id = ? AND user_id = ?');
            $stmt->execute([(int) $camp['organisation_id'], $userId]);
            return (bool) $stmt->fetch();
        }
        return false;
    }

    public static function lookupId(string $table, string $slug): ?int
    {
        $stmt = db()->prepare("SELECT id FROM {$table} WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    /** @param list<string> $paths */
    public static function attachImages(int $campaignId, array $paths, int $coverIndex = 0): void
    {
        $pdo = db();
        foreach ($paths as $i => $path) {
            $pdo->prepare(
                'INSERT INTO campaign_images (campaign_id, image_path, sort_order) VALUES (?, ?, ?)'
            )->execute([$campaignId, $path, $i]);
            if ($i === $coverIndex) {
                $pdo->prepare('UPDATE campaigns SET cover_image = ? WHERE id = ?')
                    ->execute([$path, $campaignId]);
            }
        }
    }

    public static function stats(): array
    {
        $pdo = db();
        $raised = (float) ($pdo->query(
            'SELECT COALESCE(SUM(raised_amount),0) FROM campaigns WHERE status = \'active\''
        )->fetchColumn() ?: 0);
        $active = (int) ($pdo->query(
            'SELECT COUNT(*) FROM campaigns WHERE status = \'active\''
        )->fetchColumn() ?: 0);
        $donors = (int) ($pdo->query(
            'SELECT COUNT(DISTINCT donor_id) FROM donations WHERE status IN (\'verified\',\'completed\') AND donor_id IS NOT NULL'
        )->fetchColumn() ?: 0);
        return ['raised' => $raised, 'active' => $active, 'donors' => $donors];
    }

    /** @return list<array<string, mixed>> */
    public static function listUrgent(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $sql = 'SELECT c.*, cat.name_en AS category_name, loc.slug AS location_slug,
                       loc.name_en AS location_name,
                       COALESCE(o.name, u.full_name) AS creator_name,
                       (SELECT COUNT(DISTINCT d.donor_id) FROM donations d
                        WHERE d.campaign_id = c.id AND d.status IN (\'verified\',\'completed\')) AS donor_count
                FROM campaigns c
                LEFT JOIN categories cat ON cat.id = c.category_id
                LEFT JOIN locations loc ON loc.id = c.location_id
                LEFT JOIN organisations o ON o.id = c.organisation_id
                LEFT JOIN users u ON u.id = c.owner_user_id
                WHERE c.status = \'active\'
                  AND c.ends_at IS NOT NULL
                  AND c.ends_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                ORDER BY c.ends_at ASC
                LIMIT ' . $limit;
        return db()->query($sql)->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function listFeatured(int $limit = 3): array
    {
        $limit = max(1, min(10, $limit));
        $sql = 'SELECT c.*, cat.name_en AS category_name, loc.slug AS location_slug,
                       loc.name_en AS location_name,
                       COALESCE(o.name, u.full_name) AS creator_name,
                       (SELECT COUNT(DISTINCT d.donor_id) FROM donations d
                        WHERE d.campaign_id = c.id AND d.status IN (\'verified\',\'completed\')) AS donor_count
                FROM campaigns c
                LEFT JOIN categories cat ON cat.id = c.category_id
                LEFT JOIN locations loc ON loc.id = c.location_id
                LEFT JOIN organisations o ON o.id = c.organisation_id
                LEFT JOIN users u ON u.id = c.owner_user_id
                WHERE c.status = \'active\'
                ORDER BY c.raised_amount DESC, c.created_at DESC
                LIMIT ' . $limit;
        return db()->query($sql)->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function listTopForOrganisation(int $orgUserId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $sql = 'SELECT c.*, cat.name_en AS category_name,
                       (SELECT COUNT(DISTINCT d.donor_id) FROM donations d
                        WHERE d.campaign_id = c.id AND d.status IN (\'verified\',\'completed\')) AS donor_count
                FROM campaigns c
                INNER JOIN organisations o ON o.id = c.organisation_id AND o.user_id = ?
                LEFT JOIN categories cat ON cat.id = c.category_id
                WHERE c.status IN (\'active\', \'completed\')
                ORDER BY c.raised_amount DESC
                LIMIT ' . $limit;
        $stmt = db()->prepare($sql);
        $stmt->execute([$orgUserId]);
        return $stmt->fetchAll();
    }

    /** @return ?array<string, mixed> */
    public static function primaryForBeneficiary(int $userId): ?array
    {
        $stmt = db()->prepare(
            'SELECT c.*, cat.name_en AS category_name
             FROM campaigns c
             LEFT JOIN categories cat ON cat.id = c.category_id
             WHERE c.owner_user_id = ? AND c.status = \'active\'
             ORDER BY c.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
