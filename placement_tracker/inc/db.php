<?php
/**
 * inc/db.php - configuration and the single PDO connection.
 *
 * Every page includes this file. `db()` returns one shared PDO instance for
 * the request; the connection is opened lazily, so pages that never touch the
 * database (and the unit tests) do not need a running MySQL server.
 *
 * Credentials come from environment variables first, then from config.php.
 * config.php is git-ignored - never commit real passwords.
 */
declare(strict_types=1);

/**
 * The output is buffered so that an error half way through a page can
 * still be turned into a clean error page with the right status code.
 * Without this, a page that had already started would answer 200.
 */
if (PHP_SAPI !== 'cli') {
    ob_start();
}

/**
 * Turn an uncaught error into a clean 500 page.
 *
 * Without this, a database that is down would print a stack trace to the
 * browser. The details still go to the error log, and the message is only
 * shown on screen while display_errors is on.
 */
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('Uncaught %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));

    // Throw away whatever the page had printed so far.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $showDetail = (bool) ini_get('display_errors');
    $message    = $showDetail
        ? htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        : 'The server could not complete the request. The details are in the error log.';

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>500 &middot; Server error</title>'
       . '<link rel="stylesheet" href="/static/styles.css"></head><body>'
       . '<main class="container"><div class="card center-card">'
       . '<h1>500 &middot; Server error</h1>'
       . '<p>The server could not complete the request. Make sure the MySQL service is running '
       . 'and that the credentials in config.php are correct.</p>'
       . '<p><code>' . $message . '</code></p>'
       . '<p><a class="btn btn-primary" href="/applications.php">Back to applications</a></p>'
       . '</div></main></body></html>';
});

/**
 * Read a configuration value.
 *
 * Environment variables win over config.php, which makes it easy to point
 * the test suite at a different database:
 *
 *     DB_NAME=placement_tracker_test php -S 127.0.0.1:8099
 */
function config(string $key): string
{
    static $file = null;
    if ($file === null) {
        $path = __DIR__ . '/../config.php';
        $file = is_file($path) ? require $path : [];
    }
    $env = getenv($key);
    return $env !== false ? $env : (string) ($file[$key] ?? '');
}

/**
 * The shared PDO handle.
 *
 * ERRMODE_EXCEPTION turns every database problem into a catchable exception
 * instead of a silent false. EMULATE_PREPARES=false makes MySQL do the
 * parameter binding for real, so a value can never be read as SQL.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            config('DB_HOST') ?: '127.0.0.1',
            config('DB_PORT') ?: '3306',
            config('DB_NAME')
        );
        $pdo = new PDO($dsn, config('DB_USER'), config('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
