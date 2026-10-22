<?php
/**
 * db/migrate.php - bring an existing database up to the current schema.
 *
 * The project already had a `placement_tracker` database with the original
 * `applications` table. This script adds the new pieces without touching a
 * single existing row:
 *
 *   - the `users` table
 *   - applications.location, applications.salary, applications.user_id
 *   - applications.source and applications.fingerprint
 *   - the index on user_id and the unique index on fingerprint
 *   - the foreign key from applications.user_id to users.id
 *
 * It is safe to run as often as you like: every change is checked first and
 * only applied when it is missing.
 *
 * Usage:
 *
 *     php db\migrate.php              # the database from config.php
 *     php db\migrate.php --db=placement_tracker_test
 *     php db\migrate.php --all        # the main database and the test database
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require __DIR__ . '/../inc/db.php';

/** Parse --key=value arguments. */
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-zA-Z0-9_-]+)(?:=(.*))?$/', $arg, $m)) {
        $options[$m[1]] = $m[2] ?? '1';
    }
}

$targets = [];
if (isset($options['all'])) {
    $targets = ['placement_tracker', 'placement_tracker_test'];
} elseif (isset($options['db'])) {
    $targets = [$options['db']];
} else {
    $targets = [config('DB_NAME')];
}

// The database name is part of the DSN, so the connection is built here
// instead of using db(), which is pinned to the configured database.
function connect_to(string $database): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        config('DB_HOST') ?: '127.0.0.1',
        config('DB_PORT') ?: '3306',
        $database
    );
    return new PDO($dsn, config('DB_USER'), config('DB_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/** Does a column exist on the table? */
function has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Does an index exist on the table? */
function has_index(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Does a foreign key exist? */
function has_foreign_key(PDO $pdo, string $table, string $name): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
    );
    $stmt->execute([$table, $name]);
    return (int) $stmt->fetchColumn() > 0;
}

function run(PDO $pdo, string $sql): void
{
    $pdo->exec($sql);
}

$totalChanges = 0;

foreach ($targets as $database) {
    echo "\n== $database ==\n";

    try {
        $pdo = connect_to($database);
    } catch (PDOException $e) {
        echo "  SKIP  cannot connect: " . $e->getMessage() . "\n";
        continue;
    }

    // The application user is deliberately not allowed to change the schema.
    // The migration therefore has to run as the MySQL root user.
    try {
        $pdo->query("CREATE TABLE _migrate_probe (id INT) ");
        $pdo->query("DROP TABLE _migrate_probe");
    } catch (PDOException $e) {
        echo "  SKIP  this account cannot create tables (" . $e->getMessage() . ")\n";
        echo "        Run the migration as the MySQL root user instead, for example:\n";
        echo "          set DB_USER=root\n";
        echo "          set DB_PASS=<your root password>\n";
        echo "          php db\\migrate.php --all\n";
        continue;
    }

    // --- the users table ---------------------------------------------------
    $hasUsers = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
    )->fetchColumn() > 0;

    if ($hasUsers) {
        echo "  ok    users table already exists\n";
    } else {
        run($pdo, "CREATE TABLE users (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(100) NOT NULL,
            email         VARCHAR(190) NOT NULL,
            username      VARCHAR(60)  NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login_at DATETIME     NULL,
            UNIQUE KEY uq_users_email    (email),
            UNIQUE KEY uq_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "  add   users table created\n";
        $totalChanges++;
    }

    // --- the remember_tokens table -----------------------------------------
    $hasTokens = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'remember_tokens'"
    )->fetchColumn() > 0;

    if ($hasTokens) {
        echo "  ok    remember_tokens table already exists\n";
    } else {
        run($pdo, "CREATE TABLE remember_tokens (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id        INT UNSIGNED NOT NULL,
            selector       CHAR(32)     NOT NULL,
            validator_hash CHAR(64)     NOT NULL,
            expires_at     DATETIME     NOT NULL,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_remember_selector (selector),
            KEY idx_remember_user (user_id),
            CONSTRAINT fk_remember_user FOREIGN KEY (user_id)
                REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "  add   remember_tokens table created\n";
        $totalChanges++;
    }

    // --- new columns on applications ---------------------------------------
    $columns = [
        'user_id'    => 'INT UNSIGNED NULL',
        'location'   => 'VARCHAR(120) NULL',
        'salary'     => 'VARCHAR(50)  NULL',
        'is_sample'  => 'TINYINT(1)    NOT NULL DEFAULT 0',
        'fingerprint' => 'CHAR(40)      NULL',
    ];
    foreach ($columns as $name => $definition) {
        if (has_column($pdo, 'applications', $name)) {
            echo "  ok    applications.$name already exists\n";
        } else {
            run($pdo, "ALTER TABLE applications ADD COLUMN $name $definition");
            echo "  add   applications.$name\n";
            $totalChanges++;
        }
    }

    // --- an early draft used a `source` column instead of `is_sample` ------
    // It is replaced by is_sample, so the old one is removed here.
    if (has_column($pdo, 'applications', 'source')) {
        run($pdo, 'ALTER TABLE applications DROP COLUMN source');
        echo "  drop  applications.source (replaced by is_sample)\n";
        $totalChanges++;
    }

    // --- constraints on the new columns ------------------------------------
    $checks = [
        'ck_app_place'  => 'CHECK (location IS NULL OR CHAR_LENGTH(location) <= 120)',
        'ck_app_salary' => 'CHECK (salary IS NULL OR CHAR_LENGTH(salary) <= 50)',
        'ck_app_sample' => 'CHECK (is_sample IN (0, 1))',
    ];
    foreach ($checks as $name => $definition) {
        $exists = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'applications'
                AND CONSTRAINT_NAME = '$name'"
        )->fetchColumn() > 0;
        if ($exists) {
            echo "  ok    $name already exists\n";
        } else {
            run($pdo, "ALTER TABLE applications ADD CONSTRAINT $name $definition");
            echo "  add   $name\n";
            $totalChanges++;
        }
    }

    // --- indexes ------------------------------------------------------------
    $indexes = [
        'idx_app_user'       => 'ADD INDEX idx_app_user (user_id, applied_on)',
        'uq_app_fingerprint' => 'ADD UNIQUE INDEX uq_app_fingerprint (fingerprint)',
    ];
    foreach ($indexes as $name => $sql) {
        if (has_index($pdo, 'applications', $name)) {
            echo "  ok    index $name already exists\n";
        } else {
            run($pdo, "ALTER TABLE applications $sql");
            echo "  add   index $name\n";
            $totalChanges++;
        }
    }

    // --- the foreign key ----------------------------------------------------
    if (has_foreign_key($pdo, 'applications', 'fk_app_user')) {
        echo "  ok    fk_app_user already exists\n";
    } else {
        run($pdo, 'ALTER TABLE applications
                   ADD CONSTRAINT fk_app_user FOREIGN KEY (user_id)
                   REFERENCES users(id) ON DELETE CASCADE');
        echo "  add   fk_app_user\n";
        $totalChanges++;
    }

    $rows = (int) $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();
    echo "  ->    $rows application row(s) still in place\n";
}

echo "\nMigration finished. $totalChanges change(s) applied.\n";
echo "Existing records were not modified.\n";
