<?php
/**
 * tests/e2e/user.php - manage the account and the records the Playwright
 * tests use.
 *
 * Called by the Playwright global setup, so the tests always start with a
 * known account and a known password. It is safe to run again: the account
 * is updated, not duplicated.
 *
 *     php tests\e2e\user.php create
 *     php tests\e2e\user.php delete
 *     php tests\e2e\user.php clear
 *     php tests\e2e\user.php seed 25 --prefix=Paged --status=Applied
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require __DIR__ . '/../../inc/db.php';

$action = (string) ($argv[1] ?? 'create');

$email    = 'playwright@example.com';
$username = 'playwright';
$password = 'Playwright-Test-9';
$name     = 'Playwright Tester';

/** The id of the test account, or 0 when it does not exist yet. */
function account_id(string $email): int
{
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    return $id === false ? 0 : (int) $id;
}

/** Remove every record that belongs to the test account. */
function clear_records(int $userId): void
{
    if ($userId === 0) {
        return;
    }
    $stmt = db()->prepare('DELETE FROM applications WHERE user_id = ?');
    $stmt->execute([$userId]);
}

if ($action === 'delete') {
    $id = account_id($email);
    clear_records($id);
    $stmt = db()->prepare('DELETE FROM remember_tokens WHERE user_id IN (SELECT id FROM users WHERE email = ?)');
    $stmt->execute([$email]);
    $stmt = db()->prepare('DELETE FROM users WHERE email = ?');
    $stmt->execute([$email]);
    echo "Removed $email\n";
    exit(0);
}

if ($action === 'clear') {
    clear_records(account_id($email));
    echo "Records cleared\n";
    exit(0);
}

if ($action === 'seed') {
    // php user.php seed 25 --prefix=Paged --status=Applied --role=Tester
    $count   = (int) ($argv[2] ?? 25);
    $options = [];
    foreach (array_slice($argv, 3) as $arg) {
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m)) {
            $options[$m[1]] = $m[2];
        }
    }

    $id = account_id($email);
    if ($id === 0) {
        echo "The test account does not exist. Run: php user.php create\n";
        exit(1);
    }

    $prefix   = $options['prefix'] ?? 'Seeded Co';
    $status   = $options['status'] ?? 'Applied';
    $role     = $options['role'] ?? 'Seeded Role';
    $location = $options['location'] ?? 'Bengaluru, Karnataka';

    $stmt = db()->prepare(
        'INSERT INTO applications
            (user_id, company, role, status, applied_on, location, salary, job_url, notes, is_sample)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
    );

    for ($i = 0; $i < $count; $i++) {
        $company = sprintf('%s %03d', $prefix, $i);
        $date    = date('Y-m-d', strtotime('-' . ($i % 30) . ' days'));
        $stmt->execute([
            $id, $company, $role, $status, $date, $location,
            '10 LPA', 'https://careers.example.com/seeded', 'Seeded for the Playwright tests.',
        ]);
    }

    echo "Seeded $count record(s) for $email\n";
    exit(0);
}

if ($action !== 'create') {
    exit("Usage: php user.php create|delete|clear|seed <count> [--prefix=..]\n");
}

// Start from a clean account, so a test run never sees old records.
$id = account_id($email);
clear_records($id);
$stmt = db()->prepare('DELETE FROM remember_tokens WHERE user_id IN (SELECT id FROM users WHERE email = ?)');
$stmt->execute([$email]);
$stmt = db()->prepare('DELETE FROM users WHERE email = ?');
$stmt->execute([$email]);

$stmt = db()->prepare(
    'INSERT INTO users (name, email, username, password_hash)
     VALUES (?, ?, ?, ?)'
);
$stmt->execute([$name, $email, $username, password_hash($password, PASSWORD_DEFAULT)]);

$id = (int) db()->lastInsertId();
echo "Account ready: $email (id $id)\n";
