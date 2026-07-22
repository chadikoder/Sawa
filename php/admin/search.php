<?php
declare(strict_types=1);

/**
 * Admin search, across the records an admin actually needs to find.
 *
 * The top bar promised "Search users, organizations, campaigns…" and delivered
 * neither: it hid rows of whatever table happened to be on screen, so on the
 * Overview — which has no table — typing did nothing at all, and searching for
 * a user while looking at Transactions found nothing no matter what you typed.
 * Below it sat a "Quick results" panel of five fixed links to the sections,
 * which were the same five links regardless of the query.
 *
 * This searches the database instead, and returns rows that link to where the
 * record can be acted on.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_role('admin');

$term = trim((string) ($_GET['q'] ?? ''));

// Two characters is the shortest query worth running: a single letter matches
// most of the table and the admin has almost certainly not finished typing.
if (mb_strlen($term) < 2) {
    json_response(['ok' => true, 'term' => $term, 'groups' => []]);
}

// LIKE wildcards are escaped so a name containing % or _ searches for those
// characters rather than turning into a wildcard of its own.
$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
$pdo = db();

/** Runs one search and shapes its rows for the panel. */
$search = static function (string $sql, callable $map) use ($pdo, $like): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$like, $like]);
        return array_map($map, $stmt->fetchAll());
    } catch (Throwable) {
        // A search box must never take the page down. An unavailable group
        // returns empty and the others still render.
        return [];
    }
};

$base = rtrim(BASE_PATH, '/') . '/admin';

$groups = [];

$users = $search(
    'SELECT id, full_name, email, role FROM users
      WHERE full_name LIKE ? OR email LIKE ?
      ORDER BY full_name LIMIT 5',
    static fn (array $r): array => [
        'title' => (string) $r['full_name'],
        'meta'  => $r['email'] . ' · ' . ucfirst((string) $r['role']),
        'href'  => $base . '/users',
    ]
);
if ($users) {
    $groups[] = ['label' => 'Users', 'items' => $users];
}

$orgs = $search(
    'SELECT o.id, o.name, u.email, o.verified, o.rejected
       FROM organisations o
       LEFT JOIN users u ON u.id = o.user_id
      WHERE o.name LIKE ? OR u.email LIKE ?
      ORDER BY o.name LIMIT 5',
    static function (array $r): array {
        $state = (int) $r['verified'] === 1
            ? 'Approved'
            : ((int) $r['rejected'] === 1 ? 'Rejected' : 'Pending review');
        return [
            'title' => (string) $r['name'],
            'meta'  => trim(((string) ($r['email'] ?? '')) . ' · ' . $state, ' ·'),
            'href'  => rtrim(BASE_PATH, '/') . '/admin/organizations',
        ];
    }
);
if ($orgs) {
    $groups[] = ['label' => 'Organizations', 'items' => $orgs];
}

$campaigns = $search(
    'SELECT id, title, status, raised_amount, goal_amount FROM campaigns
      WHERE title LIKE ? OR description LIKE ?
      ORDER BY created_at DESC LIMIT 5',
    static fn (array $r): array => [
        'title' => (string) $r['title'],
        'meta'  => ucfirst((string) $r['status'])
                   . ' · $' . number_format((float) $r['raised_amount'])
                   . ' of $' . number_format((float) $r['goal_amount']),
        'href'  => rtrim(BASE_PATH, '/') . '/admin/campaigns',
    ]
);
if ($campaigns) {
    $groups[] = ['label' => 'Campaigns', 'items' => $campaigns];
}

// Bill ids are what an admin is given when someone queries a payment, so they
// are worth matching directly rather than only through the donor's name.
$receipts = $search(
    'SELECT bill_id, recipient_label, total_paid FROM receipts
      WHERE bill_id LIKE ? OR recipient_label LIKE ?
      ORDER BY created_at DESC LIMIT 5',
    static fn (array $r): array => [
        'title' => (string) $r['bill_id'],
        'meta'  => $r['recipient_label'] . ' · $' . number_format((float) $r['total_paid'], 2),
        'href'  => rtrim(BASE_PATH, '/') . '/admin/bills',
    ]
);
if ($receipts) {
    $groups[] = ['label' => 'Bills & Receipts', 'items' => $receipts];
}

json_response(['ok' => true, 'term' => $term, 'groups' => $groups]);
