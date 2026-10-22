<?php
/**
 * 403.php - "that request was not allowed".
 *
 * Reached through forbidden() in inc/helpers.php, which sets the 403 status
 * code before this file is included. A POST with a missing or wrong CSRF
 * token ends up here, and nothing is written to the database.
 */
declare(strict_types=1);

$title = 'Forbidden';
http_response_code(403);
if (!function_exists('e')) { require __DIR__ . '/inc/helpers.php'; }
require __DIR__ . '/inc/icons.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 &middot; Forbidden &middot; Placement Tracker</title>
<link rel="stylesheet" href="/static/styles.css">
</head>
<body>
<main class="container" style="padding-top:60px">
  <div class="card center-card">
    <span class="empty-icon" aria-hidden="true"><?= icon('lock', 26) ?></span>
    <h1>403 &middot; Forbidden</h1>
    <p>
      Your form token was missing or expired, so the request was rejected and
      nothing was changed. Go back, reload the page, and try again.
    </p>
    <a class="btn btn-primary" href="/applications.php">Back to applications</a>
  </div>
</main>
</body>
</html>
