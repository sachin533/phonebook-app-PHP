<?php
/**
 * config.example.php - copy this file to config.php and fill it in.
 *
 *     copy config.example.php config.php
 *
 * config.php is listed in .gitignore, so real credentials never reach git.
 * An environment variable of the same name always wins over this file:
 *
 *     DB_NAME=placement_tracker_test php -S 127.0.0.1:8099
 */
declare(strict_types=1);

return [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'placement_tracker',
    'DB_USER' => 'tracker_app',
    'DB_PASS' => 'CHANGE_ME',
];
