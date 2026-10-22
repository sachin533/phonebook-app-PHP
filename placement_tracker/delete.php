<?php
/**
 * delete.php - remove an application.
 *
 * POST /delete.php  ->  302 back to the applications list.
 *
 * Deleting is destructive, so it only ever happens through a POST that
 * carries a valid CSRF token. A GET deletes nothing: it answers 405 with an
 * Allow: POST header, so a bookmarked or prefetched link can never remove
 * a row.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    method_not_allowed();
}

require_csrf();

$user   = current_user();
$userId = (int) $user['id'];

// The row has to belong to the signed in person.
$app = find_application((string) ($_POST['id'] ?? ''));

$stmt = db()->prepare('DELETE FROM applications WHERE id = ? AND user_id = ?');
$stmt->execute([(int) $app['id'], $userId]);

flash('Application deleted.');
redirect('/applications.php');
