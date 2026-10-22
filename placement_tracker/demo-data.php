<?php
/**
 * demo-data.php - load the demonstration records into the signed in account.
 *
 * A new account starts with an empty list. This button fills it with the
 * generated sample records, so the search, the filters and the pagination
 * can be tried straight away.
 *
 * POST /demo-data.php  ->  302 back to the applications list.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/seeder.php';

require_login();

// Loading data is a change, so it needs a POST and a token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    method_not_allowed();
}
require_csrf();

$user   = current_user();
$userId = (int) $user['id'];

$total   = (int) ($_POST['total'] ?? 523);
$total   = max(0, min(5000, $total));
$result  = seed_sample_records($userId, $total);

if ($result['inserted'] > 0) {
    flash(sprintf(
        '%d sample %s added. They are marked as sample data in the list.',
        $result['inserted'],
        $result['inserted'] === 1 ? 'record was' : 'records were'
    ));
} else {
    flash('You already have ' . $result['total'] . ' records, so nothing was added.');
}

$back = (string) ($_POST['return_to'] ?? '/applications.php');
// Only a local path is allowed, so this cannot be used as an open redirect.
if (!str_starts_with($back, '/') || str_starts_with($back, '//')) {
    $back = '/applications.php';
}

redirect($back);
