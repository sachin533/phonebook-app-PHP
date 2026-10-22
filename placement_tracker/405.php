<?php
/**
 * 405.php - "this page does not accept that method".
 *
 * Reached through method_not_allowed() in inc/helpers.php, which sets the
 * 405 status code and the Allow: POST header before this file is included.
 * Visiting delete.php in the browser therefore deletes nothing.
 */
declare(strict_types=1);

$title = 'Method not allowed';
http_response_code(405);
if (!headers_sent()) {
    header('Allow: POST');
}
if (!function_exists('e')) { require __DIR__ . '/inc/helpers.php'; }
require __DIR__ . '/inc/icons.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>405 &middot; Method not allowed &middot; Placement Tracker</title>
<link rel="stylesheet" href="/static/styles.css">
</head>
<body>
<main class="container" style="padding-top:60px">
  <div class="card center-card">
    <span class="empty-icon" aria-hidden="true"><?= icon('warning', 26) ?></span>
    <h1>405 &middot; Method not allowed</h1>
    <p>
      This page only accepts POST requests, so nothing was deleted. Use the
      Delete button on the details page of the application you want to remove.
    </p>
    <a class="btn btn-primary" href="/applications.php">Back to applications</a>
  </div>
</main>
</body>
</html>
