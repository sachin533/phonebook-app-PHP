-- ==========================================================================
--  Placement Application Tracker - users.sql
--
--  Creates the least-privilege application account. The site NEVER connects
--  as root: it only needs to read and write rows in `applications`.
--
--  Run once as the MySQL root user:
--
--      mysql -u root -p < db\users.sql
--
--  !! CHANGE THE PASSWORD BELOW BEFORE YOU RUN THIS !!
--  The password must match DB_PASS in your config.php.
--  Generate a strong one with:
--      php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
-- ==========================================================================

CREATE USER IF NOT EXISTS 'tracker_app'@'localhost' IDENTIFIED BY 'q1iBr2npwskkWFxTAynnJtZ8';

-- Re-running the script must not leave a stale password behind.
ALTER USER 'tracker_app'@'localhost' IDENTIFIED BY 'q1iBr2npwskkWFxTAynnJtZ8';

-- SELECT / INSERT / UPDATE / DELETE only. No CREATE, no DROP, no GRANT:
-- the application user cannot alter the schema it depends on.
GRANT SELECT, INSERT, UPDATE, DELETE ON placement_tracker.*      TO 'tracker_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON placement_tracker_test.* TO 'tracker_app'@'localhost';

FLUSH PRIVILEGES;
