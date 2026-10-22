<?php
/**
 * db/seed_sample.php - fill an account with generated sample records.
 *
 * The rows are DEMONSTRATION DATA. The company names are invented, the job
 * links point at example.com and every row is marked with is_sample = 1 and
 * carries a "Sample" badge in the interface. Nothing here claims that a real
 * vacancy exists.
 *
 * Running it again never creates duplicates: every generated row has a
 * stable fingerprint and the insert uses INSERT IGNORE against a unique
 * index on that fingerprint.
 *
 * Usage:
 *
 *     php db\seed_sample.php                          # the first account
 *     php db\seed_sample.php --user=ada@example.com   # a named account
 *     php db\seed_sample.php --total=523              # the number of rows wanted
 *     php db\seed_sample.php --all                    # the same rows for every account
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/helpers.php';
require __DIR__ . '/../inc/seeder.php';

/** Parse --key=value arguments. */
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-zA-Z0-9_-]+)(?:=(.*))?$/', $arg, $m)) {
        $options[$m[1]] = $m[2] ?? '1';
    }
}

$stmt = db()->query('SELECT id, email FROM users ORDER BY id');
$users = $stmt->fetchAll();

if ($users === []) {
    echo "There is no account yet.\n";
    echo "Create one first, then run this script again:\n";
    echo "    php db\\create_user.php\n";
    echo "The generated rows can also be left without an owner: they are\n";
    echo "claimed by the first person who registers.\n";
    exit(1);
}

// --- which account the rows belong to --------------------------------------
$targets = [];

if (isset($options['user'])) {
    $needle = strtolower(trim((string) $options['user']));
    foreach ($users as $user) {
        if (strtolower((string) $user['email']) === $needle) {
            $targets[] = (int) $user['id'];
            break;
        }
    }
    if ($targets === []) {
        echo "No account with the email address '$needle'.\n";
        exit(1);
    }
} elseif (isset($options['all'])) {
    $targets = array_map(static fn (array $u): int => (int) $u['id'], $users);
} else {
    $targets = [(int) $users[0]['id']];
}

$targetTotal = (int) ($options['total'] ?? 523);

echo 'Account     : ' . ($targets === [1] ? (string) $targets[0] : implode(', ', $targets)) . "\n";
echo 'Target rows : ' . $targetTotal . "\n";
echo str_repeat('-', 62) . "\n";

$totalInserted = 0;
$totalSkipped  = 0;

foreach ($targets as $id) {
    $result = seed_sample_records($id, $targetTotal);

    echo "  account {$id}: {$result['inserted']} row(s) added";
    if ($result['skipped'] > 0) {
        echo ", {$result['skipped']} duplicate(s) skipped";
    }
    echo ", {$result['total']} row(s) in total\n";

    $totalInserted += $result['inserted'];
    $totalSkipped  += $result['skipped'];
}

echo str_repeat('-', 62) . "\n";
echo "Inserted    : $totalInserted row(s)\n";
if ($totalSkipped > 0) {
    echo "Skipped     : $totalSkipped duplicate(s) - they were already there\n";
}

echo "\nThe rows are demonstration data: the companies are invented and every\n";
echo "row is marked as a sample in the interface. Re-run this script as often\n";
echo "as you like - it will not create duplicates.\n";
