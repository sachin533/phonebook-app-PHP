<?php
/**
 * 404.php - "that application does not exist".
 *
 * Reached through not_found() in inc/helpers.php, which sets the 404 status
 * code before this file is included.
 *
 * The page stands on its own: it does not need the sidebar, the session or
 * the database, so it still works when the database is down.
 */
declare(strict_types=1);

$title = 'Not found';
http_response_code(404);
if (!function_exists('e')) { require __DIR__ . '/inc/helpers.php'; }
require __DIR__ . '/inc/icons.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 &middot; Not found &middot; Placement Tracker</title>
<link rel="stylesheet" href="/static/styles.css">
</head>
<body>
<main class="container" style="padding-top:60px">
  <div class="card center-card">
    <span class="empty-icon" aria-hidden="true"><?= icon('search', 26) ?></span>
    <h1>404 &middot; Not found</h1>
    <p>
      That application does not exist. It may have been deleted, or the link
      may have a wrong id in it.
    </p>
    <a class="btn btn-primary" href="/applications.php">Back to applications</a>
  </div>
</main>
</body>
</html>
