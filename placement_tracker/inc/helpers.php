<?php
/**
 * inc/helpers.php - shared helpers for every page.
 *
 * Output encoding, sessions, CSRF tokens, flash messages, redirects and
 * the "find one application" query. Include this file after inc/db.php.
 */
declare(strict_types=1);

// One session per request. The session holds the CSRF token and the flash
// message that survives a redirect.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '',
    ]);
    session_start();
}

/**
 * Escape a value for HTML output.
 *
 * EVERY dynamic value printed into a page must pass through this function.
 * Without it a company called "<script>" would execute in the browser.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Send a 404 page and stop.
 */
function not_found(): never
{
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

/**
 * Send a 403 page and stop.
 */
function forbidden(): never
{
    http_response_code(403);
    require __DIR__ . '/../403.php';
    exit;
}

/**
 * Send a 405 page and stop.
 */
function method_not_allowed(): never
{
    http_response_code(405);
    header('Allow: POST');
    require __DIR__ . '/../405.php';
    exit;
}

/**
 * Load one application by its id, scoped to the logged-in user.
 *
 * The id is validated as a positive integer before it ever reaches the
 * query, and the query itself is prepared, so a crafted id cannot change
 * the meaning of the SQL. A row that belongs to someone else is reported
 * as a 404, exactly like a row that does not exist.
 *
 * @return array<string,mixed> The row.
 */
function find_application(string $rawId): array
{
    $user = current_user();
    $id   = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false || $user === null) {
        not_found();
    }
    $stmt = db()->prepare('SELECT * FROM applications WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, (int) $user['id']]);
    $row = $stmt->fetch();
    if ($row === false) {
        not_found();
    }
    return $row;
}

// --------------------------------------------------------------------------
//  CSRF protection
// --------------------------------------------------------------------------

/**
 * The CSRF token for this session. Generated once, then reused.
 */
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

/**
 * The hidden form field that carries the token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Reject a POST that does not carry a valid token.
 *
 * A browser can be made to post a form to this site from another site.
 * The token proves the request came from a form this site rendered.
 */
function require_csrf(): void
{
    $expected = $_SESSION['csrf'] ?? '';
    $given    = (string) ($_POST['csrf'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        forbidden();
    }
}

// --------------------------------------------------------------------------
//  Flash messages and redirects
// --------------------------------------------------------------------------

/**
 * Store a one-shot message that survives the next redirect.
 *
 * The type ('success' or 'error') picks the matching alert style.
 */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

/**
 * Read and clear the pending flash message.
 *
 * @return array{message:string,type:string} Empty when there is nothing to show.
 */
function take_flash(): array
{
    $flash = $_SESSION['flash'] ?? ['message' => '', 'type' => 'success'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Redirect with a 302 and stop.
 *
 * After a successful write the browser is sent to a fresh URL, so a refresh
 * cannot repeat the write (POST/Redirect/GET).
 */
function redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}

// --------------------------------------------------------------------------
//  Presentation helpers
// --------------------------------------------------------------------------

/**
 * The CSS class for a status badge.
 */
function status_badge_class(string $status): string
{
    return match ($status) {
        'Applied'   => 'badge badge-applied',
        'Interview' => 'badge badge-interview',
        'Offer'     => 'badge badge-offer',
        'Rejected'  => 'badge badge-rejected',
        default     => 'badge badge-wishlist',
    };
}

/**
 * Format a stored date.
 *
 * @param string|null $date     A `Y-m-d` value from the database.
 * @param bool        $withYear Include the year.
 */
function fmt_date(?string $date, bool $withYear = false): string
{
    if ($date === null || $date === '') {
        return '-';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '-';
    }
    return date($withYear ? 'j M Y' : 'j M', $ts);
}

/**
 * Format a stored timestamp.
 */
function fmt_stamp(?string $stamp): string
{
    if ($stamp === null || $stamp === '') {
        return '-';
    }
    $ts = strtotime($stamp);
    return $ts === false ? '-' : date('j M Y, H:i', $ts);
}

/**
 * Initials for the avatar chip: "Shubham Aran" -> "SA".
 */
function initials(string $name): string
{
    $parts  = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $letters = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));
    return $letters !== '' ? $letters : 'U';
}
