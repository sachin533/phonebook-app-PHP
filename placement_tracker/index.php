<?php
/**
 * index.php - the old address of the applications list.
 *
 * The list lives in /applications.php. This page sends anybody who still
 * uses the old address there, so the old link keeps working.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';

$target = '/applications.php';

// The query string is kept, so a bookmarked search still works.
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

header('Location: ' . $target, true, 302);
exit;
