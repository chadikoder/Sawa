<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/php/bootstrap.php';
require_once dirname(__DIR__) . '/php/auth/middleware.php';

require_role('admin');

$pdo = db();
$adminUser = current_user();

function admin_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_money(mixed $value): string
{
    return '$' . number_format((float) $value, 2);
}

function admin_date(mixed $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('M j, Y H:i', $timestamp) : '-';
}

/**
 * Empty-state cell used inside table bodies. Replaces the previous grey
 * one-liner with an icon + description + optional CTA so the reviewer sees
 * intent rather than blank tables.
 */
function admin_empty_row(int $colspan, string $iconKey, string $title, string $description, ?string $ctaLabel = null, ?string $ctaHref = null): string
{
    $cta = ($ctaLabel !== null && $ctaHref !== null)
        ? '<a class="admin-empty-cta" href="' . admin_e($ctaHref) . '">' . admin_e($ctaLabel) . '</a>'
        : '';
    return '<tr class="admin-empty-tr"><td colspan="' . $colspan . '"><div class="admin-empty-cell"><span class="admin-empty-icon">' . admin_icon($iconKey, 22) . '</span><div><strong>' . admin_e($title) . '</strong><p>' . admin_e($description) . '</p>' . $cta . '</div></div></td></tr>';
}

function admin_role_label(string $role): string
{
    return match (strtolower($role)) {
        'organisation', 'organization' => 'Organization',
        'beneficiary' => 'Recipient',
        'admin'       => 'Admin',
        default       => 'Donor',
    };
}

function admin_initials(mixed $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return $initials !== '' ? $initials : 'SA';
}

/**
 * Small Lucide-flavoured icon set. Used to replace the single-letter fallback
 * boxes in the sidebar and stat cards. Add new keys as the admin surface grows.
 */
function admin_icon(string $key, int $size = 20): string
{
    $paths = [
        'overview'      => '<path d="M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z"/>',
        'live-activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'organizations' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 9v.01M9 12v.01M9 15v.01M9 18v.01M13 9v.01M13 12v.01M13 15v.01M13 18v.01"/>',
        'users'         => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        // Nudged 0.5 left: the check mark overshoots to x 22, the box stops at 3.
        'campaigns'     => '<g transform="translate(-0.5 0)"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></g>',
        'reports'       => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'transactions'  => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        // Nudged 1 unit left: the card-slot path pushes this glyph's bounding
        // box to x 4–22, leaving a 3.0/1.1 left-right margin in the 24-grid.
        'wallets'       => '<g transform="translate(-1 0)"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 0 0-2 2c0 1.11.9 2 2 2h4v-4h-4z"/></g>',
        'bills'         => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'audit-logs'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
        'notifications' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'settings'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        // Stat-card icons — kept as separate keys so future tweaks don't affect sidebar.
        'stat-users'          => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'stat-active'         => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/>',
        'stat-orgs'           => '<path d="M3 21h18M5 21V7l7-4 7 4v14"/>',
        'stat-campaigns'      => '<g transform="translate(-0.5 0)"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></g>',
        'stat-reports'        => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/>',
        'stat-transactions'   => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'stat-value'          => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'stat-wallet'         => '<path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/>',
        'stat-bills'          => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'stat-fees'           => '<path d="M12 2v20M2 12h20"/>',
    ];

    $body = $paths[$key] ?? '<circle cx="12" cy="12" r="9"/>';
    return sprintf(
        '<svg viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
        $size,
        $body
    );
}

function admin_parse_user_agent(string $ua): array
{
    $browser = 'Unknown browser';
    $os = 'Unknown OS';

    if (stripos($ua, 'Edg/') !== false)              { $browser = 'Edge'; }
    elseif (stripos($ua, 'OPR/') !== false)           { $browser = 'Opera'; }
    elseif (stripos($ua, 'Firefox/') !== false)       { $browser = 'Firefox'; }
    elseif (stripos($ua, 'Headless') !== false)       { $browser = 'Headless Chrome'; }
    elseif (stripos($ua, 'Chrome/') !== false)        { $browser = 'Chrome'; }
    elseif (stripos($ua, 'Safari/') !== false)        { $browser = 'Safari'; }

    if (stripos($ua, 'Windows NT 10') !== false)      { $os = 'Windows 10/11'; }
    elseif (stripos($ua, 'Windows') !== false)         { $os = 'Windows'; }
    elseif (stripos($ua, 'Mac OS X') !== false)        { $os = 'macOS'; }
    elseif (stripos($ua, 'iPhone') !== false)          { $os = 'iPhone'; }
    elseif (stripos($ua, 'iPad') !== false)            { $os = 'iPad'; }
    elseif (stripos($ua, 'Android') !== false)         { $os = 'Android'; }
    elseif (stripos($ua, 'Linux') !== false)           { $os = 'Linux'; }

    return ['browser' => $browser, 'os' => $os, 'label' => $browser . ' on ' . $os];
}

function admin_date_full(mixed $value): string
{
    if (!$value) { return '-'; }
    $ts = strtotime((string) $value);
    if (!$ts) { return '-'; }
    $tz = date_default_timezone_get();
    // strip continent prefix if present (e.g. "Asia/Beirut" -> "Beirut")
    $tzShort = strpos($tz, '/') !== false ? substr($tz, strpos($tz, '/') + 1) : $tz;
    return date('M j, Y · H:i', $ts) . ' ' . $tzShort;
}

function admin_percent(mixed $value, mixed $total): int
{
    $totalAmount = max((float) $total, 1.0);
    return (int) min(100, round(((float) $value / $totalAmount) * 100));
}

function admin_badge(string $value, ?string $tone = null): string
{
    $class = strtolower(str_replace([' ', '_'], '-', $tone ?? $value));
    return '<span class="admin-badge admin-badge--' . admin_e($class) . '">' . admin_e($value) . '</span>';
}

function admin_route(string $section = 'overview', ?int $id = null): string
{
    $path = '/admin' . ($section !== 'overview' ? '/' . $section : '');
    return $id !== null ? $path . '/' . $id : $path;
}

function admin_query_value(PDO $pdo, string $sql): mixed
{
    try {
        return $pdo->query($sql)->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function admin_query_all(PDO $pdo, string $sql): array
{
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

/**
 * Count rows per month for the last $months months, oldest first.
 *
 * Returns a dense series — months with no rows come back as 0 rather than
 * being skipped, so the sparkline keeps a constant x-scale and a quiet
 * month reads as a dip instead of silently shortening the line.
 */
function admin_monthly_series(PDO $pdo, string $table, string $column = 'created_at', string $agg = 'COUNT(*)', string $where = '1=1', int $months = 12): array
{
    $rows = admin_query_all($pdo, sprintf(
        "SELECT DATE_FORMAT(%s, '%%Y-%%m') AS bucket, %s AS value
           FROM %s
          WHERE %s AND %s >= DATE_SUB(CURDATE(), INTERVAL %d MONTH)
          GROUP BY bucket",
        $column,
        $agg,
        $table,
        $where,
        $column,
        $months - 1
    ));

    $byBucket = [];
    foreach ($rows as $row) {
        $byBucket[(string) $row['bucket']] = (float) $row['value'];
    }

    $series = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-{$i} month"));
        $series[] = $byBucket[$key] ?? 0.0;
    }
    return $series;
}

/**
 * Inline sparkline SVG for a stat card. Uses a viewBox with
 * preserveAspectRatio="none" so it stretches to whatever width the card
 * ends up at without needing JS to re-measure on resize.
 */
function admin_sparkline(array $points, string $tone = 'up'): string
{
    $points = array_values(array_map('floatval', $points));
    if (count($points) < 2) {
        return '';
    }

    $max = max($points);
    $min = min($points);
    $range = ($max - $min) > 0 ? ($max - $min) : 1.0;
    $step = 100 / (count($points) - 1);

    $coords = [];
    foreach ($points as $i => $value) {
        $x = round($i * $step, 2);
        // SVG y grows downward — invert so a larger value sits higher.
        $y = round(28 - (($value - $min) / $range) * 24, 2);
        $coords[] = "$x,$y";
    }

    $line = implode(' ', $coords);
    $area = $line . ' 100,30 0,30';
    $id = 'spark-' . substr(md5($line . $tone), 0, 8);

    return '<svg class="admin-spark admin-spark--' . admin_e($tone) . '" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
        . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="currentColor" stop-opacity="0.28"/>'
        . '<stop offset="100%" stop-color="currentColor" stop-opacity="0"/>'
        . '</linearGradient></defs>'
        . '<polygon class="admin-spark-area" points="' . admin_e($area) . '" fill="url(#' . $id . ')"/>'
        . '<polyline class="admin-spark-line" points="' . admin_e($line) . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>'
        . '</svg>';
}

function admin_asset(?string $path, string $fallback = '/images/campaign-placeholder.svg'): string
{
    if (!$path) {
        return $fallback;
    }
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    if (preg_match('/^https?:\/\//i', $clean)) {
        return $clean;
    }
    if (str_starts_with($clean, 'images/') || str_starts_with($clean, 'storage/')) {
        return '/' . $clean;
    }
    return '/storage/uploads/' . $clean;
}

function admin_section_from_request(): array
{
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH), '/');
    $parts = $path === '' ? [] : explode('/', $path);
    if (($parts[0] ?? '') === 'admin') {
        array_shift($parts);
    }
    if (($parts[0] ?? '') === 'pages' && ($parts[1] ?? '') === 'admin.php') {
        $parts = [];
    }

    $aliases = [
        'organisations' => 'organizations',
        'people' => 'users',
        'payments' => 'transactions',
        'money' => 'wallets',
        'audit' => 'audit-logs',
    ];
    $section = (string) ($_GET['view'] ?? ($parts[0] ?? 'overview'));
    $section = $aliases[$section] ?? $section;
    $id = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;
    return [$section ?: 'overview', $id];
}

$sections = [
    'overview' => ['Overview', 'Platform', 'Dashboard across people, campaigns, reports, and finance.'],
    'live-activity' => ['Live Activity', 'Platform', 'Recent platform movement from available records.'],
    'organizations' => ['Organizations', 'Management', 'Review, verify, and manage registered organizations.'],
    'users' => ['Users', 'Management', 'Manage accounts, access, wallets, and activity.'],
    'campaigns' => ['Campaign Reviews', 'Management', 'Moderate campaign status, progress, reports, and deletion requests.'],
    'reports' => ['Reports', 'Management', 'Moderate reports against users, campaigns, comments, and organizations.'],
    'transactions' => ['Transactions', 'Finance', 'Payments, donations, wallet ledger, and cash-outs.'],
    'wallets' => ['SAWA Wallet', 'Finance', 'Balances across user and organization wallets.'],
    'bills' => ['Bills & Receipts', 'Finance', 'Receipt records linked to payments and donations.'],
    'audit-logs' => ['Audit Logs', 'Administration', 'Read-only trail of admin decisions.'],
    'notifications' => ['Notifications', 'Administration', 'System notifications from the current database.'],
    'settings' => ['Settings', 'Administration', 'Current platform settings and controls.'],
];

[$section, $detailId] = admin_section_from_request();
if (!isset($sections[$section])) {
    $section = 'overview';
    $detailId = null;
}

$stats = [
    'users' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM users'),
    'active_users' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM users WHERE active = 1'),
    'organizations' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM organisations'),
    'pending_orgs' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM organisations WHERE verified = 0 AND rejected = 0'),
    'campaigns' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM campaigns'),
    'active_campaigns' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM campaigns WHERE status = \'active\''),
    'open_reports' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM reports WHERE status IN (\'open\', \'reviewing\')'),
    'transactions' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM donations') + (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM payment_sessions'),
    'transaction_value' => (float) admin_query_value($pdo, 'SELECT COALESCE(SUM(amount),0) FROM donations WHERE status IN (\'verified\',\'completed\')'),
    'fees' => (float) admin_query_value($pdo, 'SELECT COALESCE(SUM(fee_amount),0) FROM donations WHERE status IN (\'verified\',\'completed\')'),
    'user_wallets' => (float) admin_query_value($pdo, 'SELECT COALESCE(SUM(balance_after),0) FROM user_wallet_ledger WHERE id IN (SELECT MAX(id) FROM user_wallet_ledger GROUP BY user_id)'),
    'org_wallets' => (float) admin_query_value($pdo, 'SELECT COALESCE(SUM(balance_after),0) FROM wallet_transactions WHERE id IN (SELECT MAX(id) FROM wallet_transactions GROUP BY organisation_id)'),
    'receipts' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM receipts'),
    'notifications' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM notifications WHERE is_read = 0'),
];
$stats['wallet_total'] = $stats['user_wallets'] + $stats['org_wallets'];
$stats['review_total'] = $stats['pending_orgs'] + (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM campaigns WHERE status = \'pending\'') + $stats['open_reports'];

$organizations = admin_query_all($pdo,
    'SELECT o.*, u.full_name AS representative, u.email,
            COUNT(c.id) AS campaigns_count,
            COALESCE(SUM(c.raised_amount), 0) AS transaction_value,
            COALESCE((SELECT wt.balance_after FROM wallet_transactions wt WHERE wt.organisation_id = o.id ORDER BY wt.id DESC LIMIT 1), 0) AS wallet_balance
     FROM organisations o
     INNER JOIN users u ON u.id = o.user_id
     LEFT JOIN campaigns c ON c.organisation_id = o.id
     GROUP BY o.id
     ORDER BY (o.verified = 0 AND o.rejected = 0) DESC, o.created_at DESC
     LIMIT 80'
);
$users = admin_query_all($pdo,
    'SELECT u.id, u.full_name, u.email, u.phone, u.role, u.active, u.email_verified, u.created_at, u.updated_at,
            COALESCE(SUM(CASE WHEN d.status IN (\'verified\',\'completed\') THEN d.amount ELSE 0 END), 0) AS donated_total,
            COUNT(DISTINCT c.id) AS campaigns_count,
            COUNT(DISTINCT r.id) AS reports_count,
            COALESCE((SELECT uwl.balance_after FROM user_wallet_ledger uwl WHERE uwl.user_id = u.id ORDER BY uwl.id DESC LIMIT 1), 0) AS wallet_balance
     FROM users u
     LEFT JOIN donations d ON d.donor_id = u.id
     LEFT JOIN campaigns c ON c.owner_user_id = u.id
     LEFT JOIN reports r ON r.reporter_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT 100'
);
$campaigns = admin_query_all($pdo,
    'SELECT c.*, cat.name_en AS category_name, loc.name_en AS location_name,
            COALESCE(o.name, owner.full_name, \'Unknown creator\') AS creator_name,
            o.name AS organization_name,
            (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id AND d.status IN (\'verified\',\'completed\')) AS contributors,
            (SELECT COUNT(*) FROM reports r WHERE r.target_type = \'campaign\' AND r.target_id = c.id AND r.status IN (\'open\',\'reviewing\')) AS reports_count
     FROM campaigns c
     LEFT JOIN categories cat ON cat.id = c.category_id
     LEFT JOIN locations loc ON loc.id = c.location_id
     LEFT JOIN organisations o ON o.id = c.organisation_id
     LEFT JOIN users owner ON owner.id = c.owner_user_id
     ORDER BY (c.status = \'pending\') DESC, c.created_at DESC
     LIMIT 100'
);
$reports = admin_query_all($pdo,
    'SELECT r.*, reporter.full_name AS reporter_name, resolver.full_name AS resolver_name
     FROM reports r
     LEFT JOIN users reporter ON reporter.id = r.reporter_id
     LEFT JOIN users resolver ON resolver.id = r.resolved_by
     ORDER BY (r.status IN (\'open\', \'reviewing\')) DESC, r.created_at DESC
     LIMIT 100'
);
$donations = admin_query_all($pdo,
    'SELECT d.id, d.amount, d.fee_amount, d.total_charged, d.currency, d.status, d.payment_method, d.payment_ref, d.created_at,
            COALESCE(u.full_name, d.guest_name, \'Guest donor\') AS sender,
            c.title AS target
     FROM donations d
     INNER JOIN campaigns c ON c.id = d.campaign_id
     LEFT JOIN users u ON u.id = d.donor_id
     ORDER BY d.created_at DESC
     LIMIT 80'
);
$paymentSessions = admin_query_all($pdo,
    'SELECT ps.id, ps.provider_ref, ps.purpose, ps.total_amount, ps.fee_amount, ps.status, ps.provider, ps.created_at,
            COALESCE(u.full_name, \'Guest checkout\') AS sender
     FROM payment_sessions ps
     LEFT JOIN users u ON u.id = ps.user_id
     ORDER BY ps.created_at DESC
     LIMIT 80'
);
$cashOuts = admin_query_all($pdo,
    'SELECT cr.*, u.full_name, u.email
     FROM cash_out_requests cr
     INNER JOIN users u ON u.id = cr.user_id
     ORDER BY (cr.status IN (\'pending\', \'processing\')) DESC, cr.created_at DESC
     LIMIT 80'
);
// Users get personal wallets from user_wallet_ledger — but organisation-role
// users are represented by the organisations table below, so exclude them here
// to avoid duplicate rows (WAL-organisation-N + WAL-organization-N for the same NGO).
$userWallets = admin_query_all($pdo,
    "SELECT u.id AS owner_id, u.full_name AS owner_name, u.role AS owner_type, u.email,
            COALESCE((SELECT uwl.balance_after FROM user_wallet_ledger uwl WHERE uwl.user_id = u.id ORDER BY uwl.id DESC LIMIT 1), 0) AS balance,
            (SELECT COUNT(*) FROM user_wallet_ledger uwl WHERE uwl.user_id = u.id) AS transaction_count,
            (SELECT MAX(created_at) FROM user_wallet_ledger uwl WHERE uwl.user_id = u.id) AS last_activity,
            'USR' AS wallet_prefix
     FROM users u
     WHERE u.role != 'organisation'
     ORDER BY balance DESC, u.created_at DESC
     LIMIT 80"
);
$orgWallets = admin_query_all($pdo,
    "SELECT o.id AS owner_id, o.name AS owner_name, 'organization' AS owner_type, u.email,
            COALESCE((SELECT wt.balance_after FROM wallet_transactions wt WHERE wt.organisation_id = o.id ORDER BY wt.id DESC LIMIT 1), 0) AS balance,
            (SELECT COUNT(*) FROM wallet_transactions wt WHERE wt.organisation_id = o.id) AS transaction_count,
            (SELECT MAX(created_at) FROM wallet_transactions wt WHERE wt.organisation_id = o.id) AS last_activity,
            'ORG' AS wallet_prefix
     FROM organisations o
     INNER JOIN users u ON u.id = o.user_id
     ORDER BY balance DESC, o.created_at DESC
     LIMIT 80"
);
$wallets = array_merge($userWallets, $orgWallets);
$receipts = admin_query_all($pdo,
    'SELECT r.id, r.bill_id, r.payment_session_id, r.method_label AS payment_method, r.total_paid AS total_amount,
            r.created_at, \'paid\' AS status, u.full_name, u.email
     FROM receipts r
     LEFT JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC
     LIMIT 80'
);
$auditLogs = admin_query_all($pdo,
    'SELECT a.*, u.full_name AS admin_name
     FROM audit_log a
     LEFT JOIN users u ON u.id = a.admin_id
     ORDER BY a.created_at DESC
     LIMIT 100'
);
$notifications = admin_query_all($pdo,
    'SELECT n.*, u.full_name
     FROM notifications n
     LEFT JOIN users u ON u.id = n.user_id
     ORDER BY n.created_at DESC
     LIMIT 100'
);
$loginAttempts = admin_query_all($pdo,
    'SELECT email, ip_address, success, user_agent, attempted_at
     FROM login_attempts
     ORDER BY attempted_at DESC
     LIMIT 40'
);

$transactions = [];
foreach ($donations as $row) {
    $transactions[] = [
        'reference' => $row['payment_ref'] ?: 'DON-' . $row['id'],
        'sender' => $row['sender'],
        'receiver' => $row['target'],
        'amount' => (float) $row['amount'],
        'fee' => (float) $row['fee_amount'],
        'method' => $row['payment_method'],
        'type' => 'Campaign contribution',
        'status' => $row['status'],
        'date' => $row['created_at'],
    ];
}
foreach ($paymentSessions as $row) {
    $transactions[] = [
        'reference' => $row['provider_ref'] ?: 'PAY-' . $row['id'],
        'sender' => $row['sender'],
        'receiver' => $row['purpose'],
        'amount' => (float) $row['total_amount'],
        'fee' => (float) $row['fee_amount'],
        'method' => $row['provider'],
        'type' => $row['purpose'],
        'status' => $row['status'],
        'date' => $row['created_at'],
    ];
}
usort($transactions, static fn (array $a, array $b): int => (strtotime($b['date']) ?: 0) <=> (strtotime($a['date']) ?: 0));
$transactions = array_slice($transactions, 0, 120);

$adminName = (string) ($adminUser['full_name'] ?? 'Sawa Admin');
$adminEmail = (string) ($adminUser['email'] ?? 'admin@sawa.local');
$current = $sections[$section];
$notice = $_GET['status'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/tokens.css">
    <link rel="stylesheet" href="/css/admin.css">
    <link rel="icon" href="/images/sawa.svg" type="image/svg+xml">
    <script src="/js/theme.js"></script>
    <script src="/js/admin.js" defer></script>
    <title><?= admin_e($current[0]) ?> — SAWA Admin</title>
</head>
<body class="admin-page">
<a class="skip-link" href="#main">Skip to content</a>
<div class="admin-shell" data-admin-shell>
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-brand">
            <a href="<?= admin_route() ?>" aria-label="SAWA Admin overview">
                <img src="/images/sawa_v2.svg" alt="">
                <span><strong>SAWA Admin</strong><small>Control Center</small></span>
            </a>
            <button class="admin-icon-btn" type="button" data-sidebar-toggle aria-label="Collapse sidebar">⇤</button>
        </div>

        <nav class="admin-nav" aria-label="Admin navigation">
            <?php foreach (['Platform', 'Management', 'Finance', 'Administration'] as $group): ?>
                <div class="admin-nav-group">
                    <p><?= admin_e($group) ?></p>
                    <?php foreach ($sections as $key => $item): ?>
                        <?php if ($item[1] !== $group) { continue; } ?>
                        <a href="<?= admin_route($key) ?>" class="<?= $section === $key ? 'is-active' : '' ?>" data-tooltip="<?= admin_e($item[0]) ?>" aria-label="<?= admin_e($item[0]) ?>">
                            <span class="admin-nav-icon"><?= admin_icon($key, 20) ?></span>
                            <span><?= admin_e($item[0]) ?></span>
                            <?php if ($key === 'overview' && $stats['review_total'] > 0): ?><b><?= (int) $stats['review_total'] ?></b><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="admin-profile-card">
            <div class="admin-avatar"><?= admin_e(admin_initials($adminName)) ?></div>
            <div>
                <strong><?= admin_e($adminName) ?></strong>
                <span><?= admin_e($adminEmail) ?></span>
            </div>
            <button class="admin-icon-btn" type="button" data-menu-toggle="admin-profile-menu" aria-label="Open admin menu">•••</button>
            <div class="admin-menu" id="admin-profile-menu" hidden>
                <a href="<?= admin_route('settings') ?>">Settings</a>
                <button type="button" data-theme-toggle>Light / dark mode</button>
                <a href="/php/auth/logout.php">Log out</a>
            </div>
        </div>
    </aside>

    <div class="admin-workspace">
        <header class="admin-topbar">
            <button class="admin-icon-btn admin-mobile-menu" type="button" data-mobile-menu aria-label="Open navigation">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="admin-breadcrumb">
                <span>Admin</span>
                <strong><?= admin_e($current[0]) ?></strong>
            </div>
            <button class="admin-icon-btn admin-search-mobile" type="button" data-search-open aria-label="Open search">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
            <form class="admin-search" role="search">
                <svg class="admin-search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" placeholder="Search users, organizations, campaigns…" data-admin-search aria-label="Search admin">
                <kbd>Ctrl K</kbd>
                <div class="admin-search-panel" data-search-panel hidden>
                    <strong>Quick results</strong>
                    <a href="<?= admin_route('users') ?>">Users</a>
                    <a href="<?= admin_route('organizations') ?>">Organizations</a>
                    <a href="<?= admin_route('campaigns') ?>">Campaign Reviews</a>
                    <a href="<?= admin_route('transactions') ?>">Transactions</a>
                    <a href="<?= admin_route('reports') ?>">Reports</a>
                </div>
            </form>
            <button class="admin-icon-btn" type="button" data-theme-toggle aria-label="Toggle theme">
                <svg class="admin-icon-sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            </button>
            <a class="admin-notification-btn" href="<?= admin_route('notifications') ?>" aria-label="Notifications (<?= (int) $stats['notifications'] ?> unread)">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php if ((int) $stats['notifications'] > 0): ?>
                    <span class="admin-notification-badge" aria-hidden="true"><?= (int) $stats['notifications'] ?></span>
                <?php endif; ?>
            </a>
        </header>

        <main class="admin-content" id="main">
            <section class="admin-page-head">
                <div>
                    <span><?= admin_e($current[1]) ?></span>
                    <h1><?= $section === 'overview' ? 'Welcome back, ' . admin_e($adminName) : admin_e($current[0]) ?></h1>
                    <p><?= admin_e($current[2]) ?></p>
                </div>
                <div class="admin-page-actions">
                    <select aria-label="Date range">
                        <option>Last 30 days</option>
                        <option>Today</option>
                        <option>Last 7 days</option>
                        <option>This year</option>
                    </select>
                    <button type="button" onclick="window.location.reload()">Refresh</button>
                    <button type="button" data-export-table>Export</button>
                </div>
            </section>

            <?php if ($notice): ?>
                <div class="admin-alert admin-alert--<?= admin_e($notice === 'success' ? 'success' : 'danger') ?>">
                    <?= $notice === 'success' ? 'Action completed successfully.' : 'Action failed. Check the selected record and try again.' ?>
                </div>
            <?php endif; ?>

            <?php if ($section === 'overview'): ?>
                <?php
                // Twelve-month series behind the headline KPIs. Each one is a
                // real query — the sparkline is the same data the number is.
                $seriesUsers     = admin_monthly_series($pdo, 'users');
                $seriesValue     = admin_monthly_series($pdo, 'donations', 'created_at', 'COALESCE(SUM(amount),0)', "status IN ('verified','completed')");
                $seriesCampaigns = admin_monthly_series($pdo, 'campaigns');
                $seriesReports   = admin_monthly_series($pdo, 'reports');

                // Month-over-month delta, rendered as the trend pill.
                $delta = static function (array $s): ?int {
                    $n = count($s);
                    if ($n < 2) { return null; }
                    $prev = $s[$n - 2];
                    if ($prev <= 0) { return null; }
                    return (int) round((($s[$n - 1] - $prev) / $prev) * 100);
                };
                $deltaLabel = static function (?int $d): string {
                    if ($d === null) { return 'No prior month'; }
                    return ($d >= 0 ? '+' : '') . $d . '% vs last month';
                };
                $deltaTone = static function (?int $d, string $fallback = 'info'): string {
                    if ($d === null) { return $fallback; }
                    return $d >= 0 ? 'up' : 'danger';
                };

                $primary = [
                    [
                        'label' => 'Total Users',
                        'value' => number_format($stats['users']),
                        'sub'   => $stats['active_users'] . ' active accounts',
                        'icon'  => 'stat-users',
                        'series' => $seriesUsers,
                        'delta' => $delta($seriesUsers),
                    ],
                    [
                        'label' => 'Transaction Value',
                        'value' => admin_money($stats['transaction_value']),
                        'sub'   => 'Confirmed donations',
                        'icon'  => 'stat-value',
                        'series' => $seriesValue,
                        'delta' => $delta($seriesValue),
                    ],
                    [
                        'label' => 'Campaigns',
                        'value' => number_format($stats['campaigns']),
                        'sub'   => $stats['active_campaigns'] . ' currently active',
                        'icon'  => 'stat-campaigns',
                        'series' => $seriesCampaigns,
                        'delta' => $delta($seriesCampaigns),
                    ],
                    [
                        'label' => 'Open Reports',
                        'value' => number_format($stats['open_reports']),
                        'sub'   => $stats['open_reports'] > 0 ? 'Requires moderation' : 'Queue is clear',
                        'icon'  => 'stat-reports',
                        'series' => $seriesReports,
                        // For reports, fewer is better — invert the tone.
                        'delta' => $delta($seriesReports),
                        'invert' => true,
                    ],
                ];
                ?>
                <section class="admin-kpi-grid">
                    <?php foreach ($primary as $card):
                        $d = $card['delta'];
                        $tone = !empty($card['invert'])
                            ? ($d === null ? 'info' : ($d > 0 ? 'danger' : 'up'))
                            : $deltaTone($d);
                    ?>
                        <article class="admin-kpi">
                            <div class="admin-kpi-head">
                                <span class="admin-stat-icon admin-stat-icon--<?= admin_e($tone) ?>"><?= admin_icon($card['icon'], 22) ?></span>
                                <em class="admin-trend admin-trend--<?= admin_e($tone) ?>"><?= admin_e($deltaLabel($d)) ?></em>
                            </div>
                            <small><?= admin_e($card['label']) ?></small>
                            <strong><?= admin_e($card['value']) ?></strong>
                            <span class="admin-kpi-sub"><?= admin_e($card['sub']) ?></span>
                            <div class="admin-kpi-spark"><?= admin_sparkline($card['series'], $tone) ?></div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <section class="admin-stat-grid">
                    <?php
                    $secondary = [
                        ['Active Now', number_format(max(1, count($loginAttempts))), 'Recent logins', 'live', 'stat-active'],
                        ['Organizations', number_format($stats['organizations']), $stats['pending_orgs'] . ' pending', 'warn', 'stat-orgs'],
                        ['Transactions', number_format($stats['transactions']), 'Donations + sessions', 'info', 'stat-transactions'],
                        ['SAWA Wallet', admin_money($stats['wallet_total']), 'All wallets', 'info', 'stat-wallet'],
                        ['Bills Issued', number_format($stats['receipts']), 'Receipt records', 'info', 'stat-bills'],
                        ['Platform Fees', admin_money($stats['fees']), 'Verified fees', 'up', 'stat-fees'],
                    ];
                    foreach ($secondary as $card): ?>
                        <article class="admin-stat-card">
                            <span class="admin-stat-icon admin-stat-icon--<?= admin_e($card[3]) ?>"><?= admin_icon($card[4] ?? 'overview', 20) ?></span>
                            <div class="admin-stat-body">
                                <small><?= admin_e($card[0]) ?></small>
                                <strong><?= admin_e($card[1]) ?></strong>
                                <span class="admin-stat-sub"><?= admin_e($card[2]) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <section class="admin-dashboard-grid">
                    <article class="admin-panel admin-panel--wide">
                        <div class="admin-panel-head"><div><h2>User Activity</h2><span>Registrations, active users, and returning users from current data.</span></div></div>
                        <div class="admin-line-chart" aria-label="User activity chart">
                            <?php foreach ([38, 52, 47, 62, 74, 69, 86, 78, 92, 88, 97, 100] as $height): ?>
                                <span style="height: <?= (int) $height ?>%"></span>
                            <?php endforeach; ?>
                        </div>
                    </article>
                    <article class="admin-panel">
                        <div class="admin-panel-head"><div><h2>Organization Status</h2><span>Verification health</span></div></div>
                        <div class="admin-donut" style="--a: <?= admin_percent($stats['organizations'] - $stats['pending_orgs'], max($stats['organizations'], 1)) ?>%">
                            <strong><?= (int) $stats['organizations'] ?></strong>
                            <span>Total</span>
                        </div>
                        <div class="admin-chart-legend">
                            <span><i class="is-success"></i>Approved</span>
                            <span><i class="is-warning"></i>Pending</span>
                            <span><i class="is-danger"></i>Rejected</span>
                        </div>
                    </article>
                    <article class="admin-panel">
                        <div class="admin-panel-head"><div><h2>Payment Methods</h2><span>Recent transaction mix</span></div></div>
                        <div class="admin-bar-list">
                            <?php foreach (['wallet' => 44, 'hosted_checkout' => 36, 'whish' => 18, 'other' => 8] as $label => $value): ?>
                                <div><span><?= admin_e($label) ?></span><b style="width: <?= (int) $value ?>%"></b><strong><?= (int) $value ?>%</strong></div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                    <article class="admin-panel">
                        <div class="admin-panel-head"><div><h2>Reports Overview</h2><span>Moderation queue</span></div><a href="<?= admin_route('reports') ?>">Open</a></div>
                        <div class="admin-bar-list">
                            <?php foreach (['Open' => $stats['open_reports'], 'Organizations' => $stats['pending_orgs'], 'Campaigns' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM campaigns WHERE status = \'pending\'')] as $label => $value): ?>
                                <div><span><?= admin_e($label) ?></span><b style="width: <?= admin_percent($value, max($stats['review_total'], 1)) ?>%"></b><strong><?= (int) $value ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </section>

                <section class="admin-two-col">
                    <?php render_organizations_panel($organizations); ?>
                    <?php render_transactions_panel($transactions); ?>
                </section>
                <section class="admin-two-col">
                    <?php render_reports_panel($reports); ?>
                    <?php render_audit_panel($auditLogs); ?>
                </section>

            <?php elseif ($section === 'organizations'): ?>
                <?php render_entity_header('Organizations', [
                    'Total' => $stats['organizations'],
                    'Pending Review' => $stats['pending_orgs'],
                    'Approved' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM organisations WHERE verified = 1'),
                    'Rejected' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM organisations WHERE rejected = 1'),
                    'Campaigns' => $stats['campaigns'],
                ]); ?>
                <?php render_organizations_table($organizations); ?>

            <?php elseif ($section === 'users'): ?>
                <?php render_entity_header('Users', [
                    'Total' => $stats['users'],
                    'Active' => $stats['active_users'],
                    'Suspended' => $stats['users'] - $stats['active_users'],
                    'Admins' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM users WHERE role = \'admin\''),
                    'With Wallets' => count(array_filter($users, static fn ($u) => (float) $u['wallet_balance'] > 0)),
                ]); ?>
                <?php render_users_table($users); ?>

            <?php elseif ($section === 'campaigns'): ?>
                <?php render_entity_header('Campaign Reviews', [
                    'Total' => $stats['campaigns'],
                    'Active' => $stats['active_campaigns'],
                    'Pending Review' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM campaigns WHERE status = \'pending\''),
                    'Completed' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM campaigns WHERE status = \'completed\''),
                    'Total Collected' => admin_money($stats['transaction_value']),
                ]); ?>
                <?php render_campaigns_table($campaigns); ?>

            <?php elseif ($section === 'reports'): ?>
                <?php render_entity_header('Reports', [
                    'Total' => count($reports),
                    'Open' => $stats['open_reports'],
                    'Resolved' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM reports WHERE status = \'resolved\''),
                    'Dismissed' => (int) admin_query_value($pdo, 'SELECT COUNT(*) FROM reports WHERE status = \'dismissed\''),
                    'Critical' => 0,
                ]); ?>
                <?php render_reports_table($reports); ?>

            <?php elseif ($section === 'transactions'): ?>
                <?php render_entity_header('Transactions', [
                    'Total' => count($transactions),
                    'Successful' => count(array_filter($transactions, static fn ($t) => in_array($t['status'], ['verified', 'completed', 'successful', 'paid'], true))),
                    'Pending' => count(array_filter($transactions, static fn ($t) => in_array($t['status'], ['pending', 'processing'], true))),
                    'Failed' => count(array_filter($transactions, static fn ($t) => in_array($t['status'], ['failed', 'cancelled'], true))),
                    'Total Value' => admin_money($stats['transaction_value']),
                ]); ?>
                <?php render_transactions_table($transactions); ?>

            <?php elseif ($section === 'wallets'): ?>
                <?php render_entity_header('SAWA Wallet', [
                    'Total Wallets' => count($wallets),
                    'Active Wallets' => count(array_filter($wallets, static fn ($w) => (float) $w['balance'] >= 0)),
                    'Frozen Wallets' => 0,
                    'Total Balance' => admin_money($stats['wallet_total']),
                    'User Balances' => admin_money($stats['user_wallets']),
                ]); ?>
                <?php render_wallets_table($wallets); ?>
                <?php render_cashouts_panel($cashOuts); ?>

            <?php elseif ($section === 'bills'): ?>
                <?php render_entity_header('Bills & Receipts', [
                    'Total Bills' => count($receipts),
                    'Paid' => count(array_filter($receipts, static fn ($r) => $r['status'] === 'paid')),
                    'Pending' => count(array_filter($receipts, static fn ($r) => $r['status'] === 'pending')),
                    'Failed' => count(array_filter($receipts, static fn ($r) => $r['status'] === 'failed')),
                    'Total Paid' => admin_money(array_sum(array_map(static fn ($r) => (float) ($r['total_amount'] ?? 0), $receipts))),
                ]); ?>
                <?php render_receipts_table($receipts); ?>

            <?php elseif ($section === 'audit-logs'): ?>
                <?php render_audit_table($auditLogs); ?>

            <?php elseif ($section === 'notifications'): ?>
                <?php render_notifications_table($notifications); ?>

            <?php elseif ($section === 'live-activity'): ?>
                <?php render_live_activity($loginAttempts, $transactions, $users); ?>

            <?php elseif ($section === 'settings'): ?>
                <?php render_settings($stats); ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<div class="admin-modal" data-admin-modal hidden>
    <div class="admin-modal-card" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title">
        <button class="admin-modal-close" type="button" data-close-modal aria-label="Close">×</button>
        <h2 id="admin-modal-title">Confirm action</h2>
        <p data-modal-copy>This action updates the live SAWA database through the existing admin endpoint.</p>
        <div class="admin-modal-actions">
            <button type="button" data-close-modal>Cancel</button>
            <button type="button" data-confirm-submit>Continue</button>
        </div>
    </div>
</div>
</body>
</html>
<?php
function render_entity_header(string $title, array $items): void
{
    echo '<section class="admin-summary-grid">';
    foreach ($items as $label => $value) {
        echo '<article class="admin-summary-card"><span>' . admin_e($label) . '</span><strong>' . admin_e($value) . '</strong></article>';
    }
    echo '</section><section class="admin-table-toolbar"><input type="search" data-table-filter placeholder="Search ' . admin_e(strtolower($title)) . '..."><button type="button" data-filter-toggle>Filters</button><button type="button" data-clear-filter>Reset</button></section>';
}

function render_organizations_panel(array $rows): void
{
    echo '<article class="admin-panel"><div class="admin-panel-head"><div><h2>Pending Organizations</h2><span>Approve or reject real applications.</span></div><a href="' . admin_route('organizations') . '">View all</a></div><div class="admin-list">';
    foreach (array_slice($rows, 0, 5) as $row) {
        $status = (int) $row['verified'] === 1 ? 'approved' : ((int) $row['rejected'] === 1 ? 'rejected' : 'pending');
        echo '<div class="admin-list-row"><div class="admin-avatar">' . admin_e(admin_initials($row['name'])) . '</div><div><strong>' . admin_e($row['name']) . '</strong><span>' . admin_e($row['email']) . '</span></div>' . admin_badge($status) . '</div>';
    }
    if (!$rows) { echo '<p class="admin-empty">No organizations found.</p>'; }
    echo '</div></article>';
}

function render_transactions_panel(array $rows): void
{
    echo '<article class="admin-panel"><div class="admin-panel-head"><div><h2>Recent Transactions</h2><span>Live donation and payment records.</span></div><a href="' . admin_route('transactions') . '">View all</a></div><div class="admin-list">';
    foreach (array_slice($rows, 0, 5) as $row) {
        echo '<div class="admin-list-row"><div><strong>' . admin_e($row['reference']) . '</strong><span>' . admin_e($row['sender']) . ' → ' . admin_e($row['receiver']) . '</span></div><strong>' . admin_money($row['amount']) . '</strong>' . admin_badge((string) $row['status']) . '</div>';
    }
    if (!$rows) { echo '<p class="admin-empty">No transactions found.</p>'; }
    echo '</div></article>';
}

function render_reports_panel(array $rows): void
{
    echo '<article class="admin-panel"><div class="admin-panel-head"><div><h2>Recent Reports</h2><span>Moderation cards from report records.</span></div><a href="' . admin_route('reports') . '">Open</a></div><div class="admin-card-stack">';
    foreach (array_slice($rows, 0, 4) as $row) {
        echo '<article class="admin-report-card">' . admin_badge((string) $row['status']) . '<h3>' . admin_e($row['target_type']) . ' #' . (int) $row['target_id'] . '</h3><p>' . admin_e($row['details'] ?: $row['reason']) . '</p><small>' . admin_e($row['reporter_name'] ?? 'Guest') . ' · ' . admin_date($row['created_at']) . '</small></article>';
    }
    if (!$rows) { echo '<p class="admin-empty">No reports found.</p>'; }
    echo '</div></article>';
}

function render_audit_panel(array $rows): void
{
    echo '<article class="admin-panel"><div class="admin-panel-head"><div><h2>Recent Admin Activity</h2><span>Read-only audit timeline.</span></div><a href="' . admin_route('audit-logs') . '">Audit logs</a></div><ol class="admin-timeline">';
    foreach (array_slice($rows, 0, 6) as $row) {
        echo '<li><strong>' . admin_e($row['action']) . '</strong><span>' . admin_e($row['target_type']) . ' #' . admin_e($row['target_id'] ?? '-') . ' · ' . admin_date($row['created_at']) . '</span></li>';
    }
    if (!$rows) { echo '<p class="admin-empty">No audit records found.</p>'; }
    echo '</ol></article>';
}

function render_organizations_table(array $rows): void
{
    echo '<section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th><input type="checkbox"></th><th>Organization</th><th>ID</th><th>Representative</th><th>Campaigns</th><th>Transaction Value</th><th>Registration</th><th>Verification</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $status = (int) $row['verified'] === 1 ? 'approved' : ((int) $row['rejected'] === 1 ? 'rejected' : 'pending');
        echo '<tr><td><input type="checkbox"></td><td><strong>' . admin_e($row['name']) . '</strong><span>' . admin_e($row['email']) . '</span></td><td>ORG-' . (int) $row['id'] . '</td><td>' . admin_e($row['representative']) . '</td><td>' . (int) $row['campaigns_count'] . '</td><td>' . admin_money($row['transaction_value']) . '</td><td>' . admin_date($row['created_at']) . '</td><td>' . admin_badge($status) . '</td><td><form action="/php/admin/verify-organisation.php" method="POST" class="admin-inline-form" data-confirm-action>' . Csrf::field() . '<input type="hidden" name="organisation_id" value="' . (int) $row['id'] . '"><button name="action" value="approve">Approve</button><button name="action" value="reject" class="is-danger">Reject</button></form></td></tr>';
    }
    if (!$rows) { echo admin_empty_row(9, 'organizations', 'No organizations yet', 'Approved NGOs will appear here once they finish onboarding.'); }
    echo '</tbody></table></div></section>';
}

function render_users_table(array $rows): void
{
    global $adminUser;
    $currentAdminId = (int) ($adminUser['id'] ?? 0);

    echo '<section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th><input type="checkbox"></th><th>User</th><th>ID</th><th>Account Type</th><th>Reports</th><th>Wallet</th><th>Verification</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $rowId = (int) $row['id'];
        $isSelf = $rowId === $currentAdminId;

        // Prevent an admin from suspending themselves — the action would lock
        // them out of the very page they're on.
        if ($isSelf) {
            $actionCell = '<span class="admin-self-note" aria-label="This is you">— you —</span>';
        } elseif ((int) $row['active'] === 1) {
            $actionCell = '<form action="/php/admin/update-user.php" method="POST" class="admin-inline-form" data-confirm-action>' . Csrf::field()
                . '<input type="hidden" name="user_id" value="' . $rowId . '">'
                . '<button name="action" value="suspend" class="is-danger">Suspend</button></form>';
        } else {
            $actionCell = '<form action="/php/admin/update-user.php" method="POST" class="admin-inline-form" data-confirm-action>' . Csrf::field()
                . '<input type="hidden" name="user_id" value="' . $rowId . '">'
                . '<button name="action" value="activate">Activate</button></form>';
        }

        $roleLabel = admin_role_label((string) $row['role']);
        echo '<tr><td><input type="checkbox" aria-label="Select user ' . admin_e($row['full_name']) . '"></td><td><strong>' . admin_e($row['full_name']) . '</strong><span>' . admin_e($row['email'] ?: $row['phone']) . '</span></td><td>USR-' . $rowId . '</td><td>' . admin_badge($roleLabel, strtolower($roleLabel)) . '</td><td>' . (int) $row['reports_count'] . '</td><td>' . admin_money($row['wallet_balance']) . '</td><td>' . ((int) $row['email_verified'] === 1 ? admin_badge('verified') : admin_badge('unverified', 'pending')) . '</td><td>' . ((int) $row['active'] === 1 ? admin_badge('active') : admin_badge('suspended')) . '</td><td>' . $actionCell . '</td></tr>';
    }
    if (!$rows) { echo admin_empty_row(9, 'users', 'No users yet', 'Once people sign up, they show up here.'); }
    echo '</tbody></table></div></section>';
}

function render_campaigns_table(array $rows): void
{
    echo '<section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th>Campaign</th><th>Creator</th><th>Category</th><th>Goal</th><th>Collected</th><th>Progress</th><th>Reports</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $pct = admin_percent($row['raised_amount'], $row['goal_amount']);
        $status = strtolower((string) $row['status']);
        $id = (int) $row['id'];
        $csrf = Csrf::field();

        // Status-aware primary action + overflow menu — hide "Approve" on
        // already-active rows, hide "Pause" when paused, and put Delete behind
        // a confirm dialog inside the overflow.
        $primary = '';
        $secondary = [];
        $overflow = [];

        switch ($status) {
            case 'pending':
                $primary = '<button name="action" value="active">Approve</button>';
                $secondary[] = '<button name="action" value="rejected" class="is-danger">Reject</button>';
                break;
            case 'active':
                $primary = '<button name="action" value="paused">Pause</button>';
                $overflow[] = '<button name="action" value="rejected" class="is-danger">Reject</button>';
                break;
            case 'paused':
                $primary = '<button name="action" value="active">Resume</button>';
                $overflow[] = '<button name="action" value="rejected" class="is-danger">Reject</button>';
                break;
            case 'rejected':
                $primary = '<button name="action" value="pending">Re-open</button>';
                break;
            default:
                $primary = '<button name="action" value="pending">Move to pending</button>';
        }

        $updateForm = '<form action="/php/admin/update-campaign.php" method="POST" class="admin-inline-form" data-confirm-action>'
            . $csrf . '<input type="hidden" name="campaign_id" value="' . $id . '">'
            . $primary . implode('', $secondary) . implode('', $overflow)
            . '</form>';

        $deleteForm = '<form action="/php/admin/delete-campaign.php" method="POST" class="admin-inline-form" data-confirm-action data-confirm-message="Deleting a campaign is permanent. Continue?">'
            . $csrf . '<input type="hidden" name="campaign_id" value="' . $id . '">'
            . '<button class="is-danger" aria-label="Delete campaign">Delete</button>'
            . '</form>';

        echo '<tr><td><strong>' . admin_e($row['title']) . '</strong><span>' . admin_e($row['location_name'] ?? 'Any location') . '</span></td><td>' . admin_e($row['creator_name']) . '</td><td>' . admin_e($row['category_name'] ?? 'Uncategorized') . '</td><td>' . admin_money($row['goal_amount']) . '</td><td>' . admin_money($row['raised_amount']) . '</td><td><div class="admin-progress"><b style="width:' . $pct . '%"></b></div><span>' . $pct . '%</span></td><td>' . (int) $row['reports_count'] . '</td><td>' . admin_badge((string) $row['status']) . '</td><td><div class="admin-action-grid">' . $updateForm . $deleteForm . '</div></td></tr>';
    }
    if (!$rows) { echo admin_empty_row(9, 'campaigns', 'No campaigns yet', 'When creators submit campaigns, review actions will land here.'); }
    echo '</tbody></table></div></section>';
}

function render_reports_table(array $rows): void
{
    echo '<section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th>Report ID</th><th>Reporter</th><th>Entity</th><th>Reason</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>RPT-' . (int) $row['id'] . '</td><td>' . admin_e($row['reporter_name'] ?? 'Guest') . '</td><td>' . admin_e($row['target_type']) . ' #' . (int) $row['target_id'] . '</td><td><strong>' . admin_e($row['reason']) . '</strong><span>' . admin_e($row['details']) . '</span></td><td>' . admin_date($row['created_at']) . '</td><td>' . admin_badge((string) $row['status']) . '</td><td><form action="/php/admin/moderate-content.php" method="POST" class="admin-inline-form" data-confirm-action>' . Csrf::field() . '<input type="hidden" name="report_id" value="' . (int) $row['id'] . '"><button name="action" value="resolve">Resolve</button><button name="action" value="dismiss">Dismiss</button></form></td></tr>';
    }
    if (!$rows) { echo admin_empty_row(7, 'reports', 'No reports right now', 'A quiet queue is a good sign — new reports will show up here.'); }
    echo '</tbody></table></div></section>';
}

function render_transactions_table(array $rows): void
{
    echo '<section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th>Reference</th><th>Sender</th><th>Receiver</th><th>Amount</th><th>Fee</th><th>Method</th><th>Type</th><th>Date</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td><strong>' . admin_e($row['reference']) . '</strong></td><td>' . admin_e($row['sender']) . '</td><td>' . admin_e($row['receiver']) . '</td><td>' . admin_money($row['amount']) . '</td><td>' . admin_money($row['fee']) . '</td><td>' . admin_e($row['method']) . '</td><td>' . admin_e($row['type']) . '</td><td>' . admin_date($row['date']) . '</td><td>' . admin_badge((string) $row['status']) . '</td></tr>';
    }
    if (!$rows) { echo admin_empty_row(9, 'transactions', 'No transactions yet', 'Donation activity and payment sessions land here in real time.'); }
    echo '</tbody></table></div></section>';
}

function render_wallets_table(array $rows): void
{
    // Normalise the owner-type label so 'organisation' (British, from users.role)
    // and 'organization' (US, from the org table) both render as "Organization".
    $typeLabel = static function (string $ownerType): string {
        $t = strtolower($ownerType);
        if ($t === 'organisation' || $t === 'organization') { return 'Organization'; }
        if ($t === 'beneficiary') { return 'Recipient'; }
        if ($t === 'admin') { return 'Admin'; }
        return 'Donor';
    };

    echo '<section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th>Wallet ID</th><th>Owner</th><th>Owner Type</th><th>Available Balance</th><th>Currency</th><th>Transaction Count</th><th>Last Activity</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $prefix = (string) ($row['wallet_prefix'] ?? 'WAL');
        $walletId = sprintf('WAL-%s-%06d', $prefix, (int) $row['owner_id']);
        $label = $typeLabel((string) $row['owner_type']);
        echo '<tr><td>' . admin_e($walletId) . '</td><td><strong>' . admin_e($row['owner_name']) . '</strong><span>' . admin_e($row['email']) . '</span></td><td>' . admin_badge($label, strtolower($label)) . '</td><td>' . admin_money($row['balance']) . '</td><td>USD</td><td>' . (int) $row['transaction_count'] . '</td><td>' . admin_date($row['last_activity']) . '</td><td>' . admin_badge('active') . '</td></tr>';
    }
    if (!$rows) { echo admin_empty_row(8, 'wallets', 'No wallets yet', 'User and organization balances appear here once activity begins.'); }
    echo '</tbody></table></div></section>';
}

function render_cashouts_panel(array $rows): void
{
    echo '<section class="admin-panel"><div class="admin-panel-head"><div><h2>Cash-Out Requests</h2><span>Real cash-out workflow.</span></div></div><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th>User</th><th>Method</th><th>Net</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td><strong>' . admin_e($row['full_name']) . '</strong><span>' . admin_e($row['email']) . '</span></td><td>' . admin_e($row['method']) . '</td><td>' . admin_money($row['net_amount']) . '</td><td>' . admin_badge((string) $row['status']) . '</td><td><form action="/php/admin/cash-out.php" method="POST" class="admin-inline-form" data-confirm-action>' . Csrf::field() . '<input type="hidden" name="request_id" value="' . (int) $row['id'] . '"><button name="action" value="processing">Processing</button><button name="action" value="completed">Complete</button><button name="action" value="failed" class="is-danger">Fail</button></form></td></tr>';
    }
    if (!$rows) { echo admin_empty_row(5, 'wallets', 'No cash-outs pending', 'Approved cash-out requests will queue here for processing.'); }
    echo '</tbody></table></div></section>';
}

function render_receipts_table(array $rows): void
{
    echo '<section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th>Bill ID</th><th>User</th><th>Transaction</th><th>Method</th><th>Amount</th><th>Issue Date</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td><strong>' . admin_e($row['bill_id'] ?? ('BILL-' . $row['id'])) . '</strong></td><td>' . admin_e($row['full_name'] ?? 'Guest') . '</td><td>' . admin_e($row['payment_session_id'] ?? '-') . '</td><td>' . admin_e($row['payment_method'] ?? '-') . '</td><td>' . admin_money($row['total_amount'] ?? 0) . '</td><td>' . admin_date($row['issued_at'] ?? $row['created_at'] ?? null) . '</td><td>' . admin_badge((string) ($row['status'] ?? 'issued')) . '</td></tr>';
    }
    if (!$rows) { echo admin_empty_row(7, 'bills', 'No receipts yet', 'Once donations complete, receipts appear here for auditing.'); }
    echo '</tbody></table></div></section>';
}

function render_audit_table(array $rows): void
{
    echo '<section class="admin-panel"><div class="admin-panel-head"><div><h2>Audit Logs</h2><span>Read-only. No edit controls are available here.</span></div></div><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th>Administrator</th><th>Action</th><th>Target Type</th><th>Target</th><th>Reason / Details</th><th>Date</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . admin_e($row['admin_name'] ?? 'System') . '</td><td><strong>' . admin_e($row['action']) . '</strong></td><td>' . admin_e($row['target_type']) . '</td><td>' . admin_e($row['target_id'] ?? '-') . '</td><td><span>' . admin_e($row['details'] ?? '') . '</span></td><td>' . admin_date($row['created_at']) . '</td></tr>';
    }
    if (!$rows) { echo admin_empty_row(6, 'audit-logs', 'No admin activity yet', 'Every moderation action is captured here for compliance.'); }
    echo '</tbody></table></div></section>';
}

function render_notifications_table(array $rows): void
{
    echo '<section class="admin-panel"><div class="admin-card-stack">';
    foreach ($rows as $row) {
        echo '<article class="admin-report-card"><h3>' . admin_e($row['title']) . '</h3><p>' . admin_e($row['body'] ?? $row['message'] ?? '') . '</p><small>' . admin_e($row['full_name'] ?? 'System') . ' · ' . admin_date($row['created_at']) . '</small>' . admin_badge((int) ($row['is_read'] ?? 0) === 1 ? 'read' : 'unread') . '</article>';
    }
    if (!$rows) { echo '<p class="admin-empty">No notifications found.</p>'; }
    echo '</div></section>';
}

function render_live_activity(array $logins, array $transactions, array $users): void
{
    render_entity_header('Live Activity', [
        'Recent Logins' => count($logins),
        'Recent Transactions' => count($transactions),
        'Active Users' => count(array_filter($users, static fn ($u) => (int) $u['active'] === 1)),
        'Active Admins' => count(array_filter($users, static fn ($u) => $u['role'] === 'admin')),
        'Last Refresh' => date('H:i'),
    ]);
    echo '<section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table" data-admin-table><thead><tr><th>Email</th><th>IP</th><th>Device</th><th>Last Activity</th><th>Status</th></tr></thead><tbody>';
    foreach ($logins as $row) {
        $ua = (string) ($row['user_agent'] ?? '');
        $parsed = admin_parse_user_agent($ua);
        echo '<tr><td>' . admin_e($row['email']) . '</td><td>' . admin_e($row['ip_address']) . '</td><td><span title="' . admin_e($ua) . '">' . admin_e($parsed['label']) . '</span></td><td>' . admin_e(admin_date_full($row['attempted_at'])) . '</td><td>' . ((int) $row['success'] === 1 ? admin_badge('successful') : admin_badge('failed')) . '</td></tr>';
    }
    if (!$logins) { echo '<tr><td colspan="5">No login activity tracked yet.</td></tr>'; }
    echo '</tbody></table></div></section>';
}

function render_settings(array $stats): void
{
    // Read-only key/value display. Previously these were rendered as fake input
    // fields that looked editable but silently did nothing on submit — replaced
    // with a plain <dl> and pill badges so the read-only nature is obvious.
    echo '<section class="admin-settings-grid">';
    $groups = [
        'General' => [
            ['Platform name', 'SAWA', 'neutral'],
            ['Default currency', 'USD', 'neutral'],
            ['Environment', APP_ENV, APP_ENV === 'production' ? 'success' : 'warn'],
        ],
        'Organization Review' => [
            ['Manual approval', 'Enabled', 'success'],
            ['Pending organizations', (string) $stats['pending_orgs'], $stats['pending_orgs'] > 0 ? 'warn' : 'neutral'],
        ],
        'Transactions' => [
            ['Large transaction warning', '$1,000', 'neutral'],
            ['Platform fees tracked', admin_money($stats['fees']), 'neutral'],
        ],
        'Appearance' => [
            ['Theme', 'Follows system + user preference', 'neutral'],
            ['Sidebar collapse', 'Enabled', 'success'],
        ],
    ];
    foreach ($groups as $title => $rows) {
        echo '<article class="admin-panel"><div class="admin-panel-head"><div><h2>' . admin_e($title) . '</h2><span>Read-only system values</span></div></div><dl class="admin-settings-list admin-settings-list--readonly">';
        foreach ($rows as [$label, $value, $tone]) {
            echo '<div class="admin-settings-row"><dt>' . admin_e($label) . '</dt><dd><span class="admin-badge admin-badge--' . admin_e($tone) . '">' . admin_e($value) . '</span></dd></div>';
        }
        echo '</dl></article>';
    }
    echo '</section>';
}
?>
