<?php
/**
 * tests/bootstrap.php - loaded by PHPUnit before any test runs.
 *
 * The validation tests need nothing but the pure rules, so they run without
 * a database. The route tests drive a running server over HTTP and use
 * these helpers to talk to it.
 */
declare(strict_types=1);

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/validate.php';
require_once __DIR__ . '/../inc/list.php';
require_once __DIR__ . '/../inc/stats.php';

// The shared base class for the HTTP tests.
require_once __DIR__ . '/HttpTestCase.php';
