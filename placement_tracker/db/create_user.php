<?php
/**
 * db/create_user.php - create an account from the command line.
 *
 * This is the safe way to create the first user: there is no default
 * account and no built in password anywhere in the code. The password is
 * asked for and then hashed with password_hash().
 *
 *     php db\create_user.php
 *
 * or, for a scripted setup:
 *
 *     php db\create_user.php --name="Ada" --email="ada@example.com" `
 *                             --username="ada" --password="S3cret-Pass-9"
 *
 * The first account to be created also takes over the applications that
 * were in the database before anybody could sign in.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/validate.php';

/** Read a --key=value argument. */
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-zA-Z0-9_-]+)(?:=(.*))?$/', $arg, $m)) {
        $options[$m[1]] = $m[2] ?? '';
    }
}

/**
 * Ask the question and return the answer.
 */
function ask(string $question, string $default = ''): string
{
    $suffix = $default === '' ? '' : " [$default]";
    echo $question . $suffix . ': ';

    if (function_exists('readline')) {
        $answer = readline('');
    } else {
        $answer = fgets(STDIN);
    }
    $answer = trim((string) $answer);

    return $answer === '' ? $default : $answer;
}

$name     = trim((string) ($options['name'] ?? ''));
$email    = strtolower(trim((string) ($options['email'] ?? '')));
$username = trim((string) ($options['username'] ?? ''));
$password = (string) ($options['password'] ?? '');

if ($name === '') {
    $name = ask('Full name');
}
if ($email === '') {
    $email = strtolower(ask('Email address'));
}
if ($username === '') {
    $username = ask('Username');
}
if ($password === '') {
    echo "Password (it will be shown while you type): ";
    if (function_exists('readline')) {
        $password = (string) readline('');
    } else {
        $password = (string) fgets(STDIN);
    }
    $password = trim($password);
    echo "\n";
}

[$data, $errors] = validate_registration([
    'name'            => $name,
    'email'           => $email,
    'username'        => $username,
    'password'        => $password,
    'password_confirm' => $password,
]);

if ($errors) {
    echo "\nThe details were not accepted:\n";
    foreach ($errors as $field => $message) {
        echo "  - $field: $message\n";
    }
    exit(1);
}

$id = register_user($data['name'], $data['email'], $data['username'], $password);

// How many records the new account now owns.
$stmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE user_id = ?');
$stmt->execute([$id]);
$count = (int) $stmt->fetchColumn();

echo "\nAccount created.\n";
echo "  id       : $id\n";
echo "  name     : {$data['name']}\n";
echo "  email    : {$data['email']}\n";
echo "  username : {$data['username']}\n";
echo "  records  : $count application(s)\n";
echo "\nYou can now sign in at /login.php\n";
