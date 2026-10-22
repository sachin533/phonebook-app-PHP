<?php
/**
 * inc/validate.php - server-side validation for the application form.
 *
 * The CHECK constraints in db/schema.sql are the last line of defence. This
 * layer runs first so the user gets a readable message instead of a database
 * error, and so bad data never reaches the table at all.
 */
declare(strict_types=1);

/**
 * The statuses a user may choose, in the order they appear in the form.
 */
const STATUSES = ['Wishlist', 'Applied', 'Interview', 'Offer', 'Rejected'];

/**
 * True when the value is a real calendar date written as Y-m-d.
 *
 * "2026-02-30" is rejected: PHP would silently roll it over to 2 March, and
 * the database would store a date the user never asked for.
 */
function is_iso_date(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if ($date === false) {
        return false;
    }
    $errors = DateTime::getLastErrors();
    if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return false;
    }
    // Catches a rolled-over date such as 2026-02-30 becoming 2026-03-02.
    return $date->format('Y-m-d') === $value;
}

/**
 * True when the value starts with http:// or https://.
 */
function has_http_scheme(string $value): bool
{
    return stripos($value, 'http://') === 0 || stripos($value, 'https://') === 0;
}

/**
 * Validate the submitted application.
 *
 * @param array<string,mixed> $input The raw $_POST array.
 * @return array{0: array<string,string|null>, 1: array<string,string>}
 *         A tuple of [clean values, errors]. Errors are keyed by field name
 *         and are empty when everything is valid.
 */
function validate_application(array $input): array
{
    $errors = [];

    $company   = trim((string) ($input['company'] ?? ''));
    $role      = trim((string) ($input['role'] ?? ''));
    $status    = trim((string) ($input['status'] ?? ''));
    $appliedOn = trim((string) ($input['applied_on'] ?? ''));
    $location  = trim((string) ($input['location'] ?? ''));
    $salary    = trim((string) ($input['salary'] ?? ''));
    $jobUrl    = trim((string) ($input['job_url'] ?? ''));
    $notes     = trim((string) ($input['notes'] ?? ''));

    // --- company -----------------------------------------------------------
    $length = mb_strlen($company);
    if ($length < 2 || $length > 100) {
        $errors['company'] = 'Company must be between 2 and 100 characters.';
    }

    // --- role --------------------------------------------------------------
    $length = mb_strlen($role);
    if ($length < 2 || $length > 100) {
        $errors['role'] = 'Role must be between 2 and 100 characters.';
    }

    // --- status ------------------------------------------------------------
    if (!in_array($status, STATUSES, true)) {
        $errors['status'] = 'Please choose one of the allowed statuses.';
    }

    // --- applied_on --------------------------------------------------------
    // A date is needed for every status except Wishlist.
    if ($appliedOn === '') {
        if ($status !== 'Wishlist') {
            $errors['applied_on'] = 'Applied date is required unless the status is Wishlist.';
        }
    } elseif (!is_iso_date($appliedOn)) {
        $errors['applied_on'] = 'Use a real date in yyyy-mm-dd format.';
    }

    // --- location ----------------------------------------------------------
    if (mb_strlen($location) > 120) {
        $errors['location'] = 'Location must be 120 characters or fewer.';
    }

    // --- salary ------------------------------------------------------------
    if (mb_strlen($salary) > 50) {
        $errors['salary'] = 'Salary must be 50 characters or fewer.';
    }

    // --- job_url -----------------------------------------------------------
    if ($jobUrl !== '') {
        if (mb_strlen($jobUrl) > 500) {
            $errors['job_url'] = 'Job URL must be 500 characters or fewer.';
        } elseif (!has_http_scheme($jobUrl)) {
            $errors['job_url'] = 'Job URL must start with http:// or https://.';
        }
    }

    // --- notes -------------------------------------------------------------
    if (mb_strlen($notes) > 1000) {
        $errors['notes'] = 'Notes must be 1,000 characters or fewer.';
    }

    // The values that go back into the form, so a failed save never makes
    // the user type everything again. Empty optional fields become NULL,
    // which is what the CHECK constraints expect.
    $data = [
        'company'    => $company,
        'role'       => $role,
        'status'     => $status,
        'applied_on' => $appliedOn === '' ? null : $appliedOn,
        'location'   => $location === '' ? null : $location,
        'salary'     => $salary === '' ? null : $salary,
        'job_url'    => $jobUrl === '' ? null : $jobUrl,
        'notes'      => $notes === '' ? null : $notes,
    ];

    return [$data, $errors];
}

// ==========================================================================
//  Sign in and sign up
// ==========================================================================

/**
 * The rules a password has to follow.
 *
 * Long beats complicated: eight characters with at least one letter and one
 * number is a reasonable floor for a local application.
 */
function password_problem(string $password): string
{
    if (mb_strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (mb_strlen($password) > 200) {
        return 'Password must be 200 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return 'Password must contain at least one letter.';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Password must contain at least one number.';
    }
    return '';
}

/**
 * Validate the sign up form.
 *
 * @param array<string,mixed> $input The raw $_POST array.
 * @return array{0: array<string,string>, 1: array<string,string>}
 *         A tuple of [clean values, errors].
 */
function validate_registration(array $input): array
{
    $errors = [];

    $name     = trim((string) ($input['name'] ?? ''));
    $email    = strtolower(trim((string) ($input['email'] ?? '')));
    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $confirm  = (string) ($input['password_confirm'] ?? '');

    // --- name ---------------------------------------------------------------
    if ($name === '') {
        $errors['name'] = 'Please tell us your name.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Name must be between 2 and 100 characters.';
    }

    // --- email --------------------------------------------------------------
    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That does not look like a valid email address.';
    } elseif (mb_strlen($email) > 190) {
        $errors['email'] = 'Email must be 190 characters or fewer.';
    }

    // --- username -----------------------------------------------------------
    if ($username === '') {
        $errors['username'] = 'Please choose a username.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
        $errors['username'] = 'Username must be 3 to 30 characters, using letters, numbers, dot, dash or underscore.';
    }

    // --- password -----------------------------------------------------------
    $problem = password_problem($password);
    if ($problem !== '') {
        $errors['password'] = $problem;
    } elseif (!hash_equals($password, (string) ($input['password_confirm'] ?? ''))) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    // Whether the email address or the username is already taken is
    // checked by account_taken() in inc\auth.php, because that needs the
    // database. This function stays pure so it can be tested on its own.

    $data = [
        'name'     => $name,
        'email'    => $email,
        'username' => $username,
    ];

    return [$data, $errors];
}

/**
 * Validate the sign in form.
 *
 * @param array<string,mixed> $input The raw $_POST array.
 * @return array{0: array<string,string>, 1: array<string,string>}
 */
function validate_login(array $input): array
{
    $errors = [];

    $identifier = trim((string) ($input['identifier'] ?? ''));
    $password   = (string) ($input['password'] ?? '');

    if ($identifier === '') {
        $errors['identifier'] = 'Please enter your email address or username.';
    }
    if ($password === '') {
        $errors['password'] = 'Please enter your password.';
    }

    return [['identifier' => $identifier], $errors];
}
