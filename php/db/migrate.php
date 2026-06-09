<?php
declare(strict_types=1);

/**
 * CLI migration runner: php php/db/migrate.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

$order = [
    '01_auth.sql',
    '05_lookups.sql',
    '02_campaigns.sql',
    '03_donations.sql',
    '04_engagement.sql',
    '06_admin.sql',
    '07_messaging.sql',
    '08_payments.sql',
    'seed.sql',
];

$pdo = db();

foreach ($order as $file) {
    $path = __DIR__ . DIRECTORY_SEPARATOR . $file;
    if (!is_readable($path)) {
        fwrite(STDERR, "Missing: $file\n");
        exit(1);
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Cannot read: $file\n");
        exit(1);
    }

    echo "Running $file ... ";

    $statements = array_filter(
        array_map('trim', preg_split('/;\s*\n/', $sql) ?: []),
        static fn (string $s): bool => $s !== '' && !str_starts_with($s, '--')
    );

    try {
        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
        echo "OK\n";
    } catch (PDOException $e) {
        fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
        exit(1);
    }
}

$adminHash = password_hash('Admin123', PASSWORD_DEFAULT);
$pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ? AND role = ?')
    ->execute([$adminHash, 'admin@sawa.local', 'admin']);

$demoHash = password_hash('Demo123!', PASSWORD_DEFAULT);
foreach (['donor@sawa.local', 'beneficiary@sawa.local', 'org@sawa.local'] as $demoEmail) {
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?')
        ->execute([$demoHash, $demoEmail]);
}

echo "Migration complete.\n";
echo "Default admin: admin@sawa.local / Admin123 (change in production).\n";
echo "Demo accounts: donor@sawa.local, beneficiary@sawa.local, org@sawa.local / Demo123!\n";
