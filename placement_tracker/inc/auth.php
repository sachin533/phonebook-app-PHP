<?php
/**
 * inc/auth.php - authentication helpers.
 *
 * Sessions hold the logged-in user id; passwords are stored as
 * password_hash() values and checked with password_verify(). A small
 * session-based throttle slows down password guessing.
 *
 * Include this file after inc/db.php and inc/helpers.php. Every page that
 * shows user data must call require_login().
 */
declare(strict_types=1);

/** Failed attempts allowed inside the throttle window. */
const LOGIN_MAX_ATTEMPTS = 8;

/** The throttle window, in seconds. */
const LOGIN_WINDOW_SECONDS = 600;

/** How long a "remember me" cookie lives, in seconds. */
const REMEMBER_LIFETIME = 2592000; // 30 days

/**
 * The logged-in user (id, name, email, created_at), or null for a guest.
 */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        $id   = $_SESSION['user_id'] ?? null;
        if ($id !== null) {
            $stmt = db()->prepare(
                'SELECT id, name, email, username, created_at, last_login_at
                   FROM users WHERE id = ?'
            );
            $stmt->execute([(int) $id]);
            $user = $stmt->fetch() ?: null;
            if ($user === null) {
                // The session points at a deleted account: treat as logged out.
                unset($_SESSION['user_id']);
            }
        } else {
            // No session, but maybe a "remember me" cookie.
            $user = login_from_remember_cookie();
        }
    }
    return $user;
}

/**
 * Send guests to the login page and remember where they wanted to go.
 */
function require_login(): void
{
    if (current_user() === null) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/applications.php';
        redirect('/login.php');
    }
}

/**
 * Send a signed in person away from the sign in and sign up pages.
 */
function require_guest(): void
{
    if (current_user() !== null) {
        redirect('/dashboard.php');
    }
}

/**
 * Log a user in with a fresh session id (prevents session fixation).
 */
function login_user(int $id, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    unset($_SESSION['login_throttle']);
    if ($remember) {
        issue_remember_cookie($id);
    }
}

/**
 * Log out: clear the session data and expire the session cookie.
 */
function logout_user(): void
{
    delete_remember_cookie();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * True while this session has too many recent failed login attempts.
 */
function login_throttled(): bool
{
    $t = $_SESSION['login_throttle'] ?? null;
    if ($t === null) {
        return false;
    }
    if (time() - $t['first'] > LOGIN_WINDOW_SECONDS) {
        unset($_SESSION['login_throttle']);
        return false;
    }
    return $t['count'] >= LOGIN_MAX_ATTEMPTS;
}

/** Count one failed attempt. */
function login_failed(): void
{
    $t = $_SESSION['login_throttle'] ?? ['first' => time(), 'count' => 0];
    if (time() - $t['first'] > LOGIN_WINDOW_SECONDS) {
        $t = ['first' => time(), 'count' => 0];
    }
    $t['count']++;
    $_SESSION['login_throttle'] = $t;
}

/**
 * Check an email address, a username or a password against the stored hash.
 *
 * The lookup is a prepared statement and the check is password_verify(),
 * so neither SQL injection nor a timing-friendly string compare is possible.
 * Failures feed the throttle.
 */
function attempt_login(string $identifier, string $password, bool $remember = false): bool
{
    if (login_throttled()) {
        return false;
    }

    $identifier = mb_strtolower(trim($identifier));

    // The person may type either their email address or their username.
    $stmt = db()->prepare(
        'SELECT id, password_hash FROM users
          WHERE email = ? OR username = ?'
    );
    $stmt->execute([$identifier, $identifier]);
    $row = $stmt->fetch();

    if ($row === false || !password_verify($password, (string) $row['password_hash'])) {
        login_failed();
        return false;
    }
    login_user((int) $row['id'], $remember);
    return true;
}

/**
 * Create an account and return its id.
 *
 * The password is hashed with password_hash() and the plain text is never
 * stored. The new account also takes over the applications that were in
 * the database before anybody could sign in, so nothing is lost.
 */
function register_user(string $name, string $email, string $username, string $password): int
{
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = db()->prepare(
        'INSERT INTO users (name, email, username, password_hash)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $username, $hash]);
    $id = (int) db()->lastInsertId();

    // Take over the records that had no owner yet.
    $stmt = db()->prepare('UPDATE applications SET user_id = ? WHERE user_id IS NULL');
    $stmt->execute([$id]);

    return $id;
}

/**
 * Is the email address or the username already taken?
 *
 * @return array<string,string> The field name and the message, or an empty
 *         array when both are free.
 */
function account_taken(string $email, string $username): array
{
    $errors = [];

    $stmt = db()->prepare('SELECT email, username FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$email, $username]);

    foreach ($stmt->fetchAll() as $row) {
        if (strcasecmp((string) $row['email'], $email) === 0) {
            $errors['email'] = 'An account with that email address already exists.';
        }
        if (strcasecmp((string) $row['username'], $username) === 0) {
            $errors['username'] = 'That username is already taken.';
        }
    }

    return $errors;
}

// --------------------------------------------------------------------------
//  "Remember me" - a long lived token, never the password
// --------------------------------------------------------------------------

/** The cookie that carries the remember token. */
function remember_cookie_name(): string
{
    return 'pt_remember';
}

/**
 * Issue a fresh remember token for the person.
 */
function issue_remember_cookie(int $userId): void
{
    $selector  = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));

    $stmt = db()->prepare(
        'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
    );
    $stmt->execute([$userId, $selector, hash('sha256', $validator), (int) (REMEMBER_LIFETIME / 86400)]);

    setcookie(remember_cookie_name(), $selector . ':' . $validator, [
        'expires'  => time() + REMEMBER_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '',
        'samesite' => 'Lax',
    ]);
}

/**
 * Remove the remember token, both from the database and from the browser.
 */
function delete_remember_cookie(): void
{
    $cookie = (string) ($_COOKIE[remember_cookie_name()] ?? '');
    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        $stmt = db()->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->execute([$selector]);
    }
    setcookie(remember_cookie_name(), '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[remember_cookie_name()]);
}

/**
 * Sign a person in from a valid remember token.
 *
 * Only the hash of the secret half is stored, so a copy of the database
 * cannot be used to sign in. The token is single use: it is replaced with
 * a new one every time it is used.
 */
function login_from_remember_cookie(): ?array
{
    $cookie = (string) ($_COOKIE[remember_cookie_name()] ?? '');
    if (!str_contains($cookie, ':')) {
        return null;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if ($selector === '' || $validator === '') {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT t.id AS token_id, t.user_id, t.validator_hash, t.expires_at,
                u.name, u.email, u.username, u.created_at, u.last_login_at
           FROM remember_tokens t
           JOIN users u ON u.id = t.user_id
          WHERE t.selector = ?'
    );
    $stmt->execute([$selector]);
    $row = $stmt->fetch();

    if ($row === false) {
        return null;
    }

    // An expired token is removed and does not sign anybody in.
    if (strtotime((string) $row['expires_at']) < time()) {
        $stmt = db()->prepare('DELETE FROM remember_tokens WHERE id = ?');
        $stmt->execute([(int) $row['token_id']]);
        return null;
    }

    // The secret half is compared as a hash, in constant time.
    if (!hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
        return null;
    }

    // The token is single use.
    $stmt = db()->prepare('DELETE FROM remember_tokens WHERE id = ?');
    $stmt->execute([(int) $row['token_id']]);
    issue_remember_cookie((int) $row['user_id']);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['user_id'];

    return [
        'id'            => (int) $row['user_id'],
        'name'          => (string) $row['name'],
        'email'         => (string) $row['email'],
        'username'      => (string) $row['username'],
        'created_at'    => (string) $row['created_at'],
        'last_login_at' => $row['last_login_at'],
    ];
}
