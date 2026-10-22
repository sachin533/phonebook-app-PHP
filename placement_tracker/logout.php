<?php
/**
 * logout.php - sign out.
 *
 * A GET link is accepted for convenience, but a POST is the safe way to
 * sign out, so a link in an email cannot sign somebody out by accident.
 * Either way the session and the remember token are destroyed.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';

// A POST must carry the token, so a cross site page cannot sign somebody out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}

logout_user();

// Start a fresh session so the "you have signed out" message can be shown.
session_start();
flash('You have been signed out.');
redirect('/login.php');
