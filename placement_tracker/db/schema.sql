-- ==========================================================================
--  Placement Application Tracker - schema.sql
--
--  Creates the databases, the `users` table and the `applications` table.
--  Run once as the MySQL root user:
--
--      mysql -u root -p < db\schema.sql
--
--  The script is idempotent: it uses CREATE DATABASE / CREATE TABLE IF NOT
--  EXISTS, so running it again never drops data. It creates BOTH
--  `placement_tracker` (used by the site) and `placement_tracker_test`
--  (used by the PHPUnit route tests) so one command sets everything up.
--
--  Already have a database from an earlier version? Use db\migrate.php
--  instead - it adds the new columns without touching existing rows.
-- ==========================================================================

CREATE DATABASE IF NOT EXISTS placement_tracker
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS placement_tracker_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ------------------------------------------------------------- main database
USE placement_tracker;

-- The people who can sign in. Passwords are stored only as a
-- password_hash() digest - never in plain text.
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME     NULL,

  -- utf8mb4_unicode_ci is case-insensitive, so "Bob@x.com" and
  -- "bob@x.com" cannot both be registered.
  UNIQUE KEY uq_users_email    (email),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The tokens behind "remember me". Only the hash of the secret half is
-- stored, so a database dump cannot be used to sign in.
CREATE TABLE IF NOT EXISTS remember_tokens (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  selector       CHAR(32)     NOT NULL,
  validator_hash CHAR(64)     NOT NULL,
  expires_at     DATETIME     NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_remember_selector (selector),
  KEY idx_remember_user (user_id),
  CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  company     VARCHAR(100)  NOT NULL,
  role        VARCHAR(100)  NOT NULL,
  status      VARCHAR(20)   NOT NULL,
  applied_on  DATE          NULL,
  location    VARCHAR(120)  NULL,
  salary      VARCHAR(50)   NULL,
  job_url     VARCHAR(500)  NULL,
  notes       TEXT          NULL,
  -- 1 for the generated demonstration records, 0 for the ones a person
  -- entered. See db\seed_sample.php.
  is_sample   TINYINT(1)    NOT NULL DEFAULT 0,
  -- A stable id for a generated record. It makes the sample seeder safe to
  -- re-run: the same row is always recognised and never inserted twice.
  fingerprint CHAR(40)      NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- The CHECK constraints are the last line of defence. The application
  -- validates the same rules in PHP first so the user gets a friendly
  -- message instead of a database error.
  CONSTRAINT ck_app_company CHECK (CHAR_LENGTH(company) BETWEEN 2 AND 100),
  CONSTRAINT ck_app_role    CHECK (CHAR_LENGTH(role)    BETWEEN 2 AND 100),
  CONSTRAINT ck_app_status  CHECK (status IN
                        ('Wishlist', 'Applied', 'Interview', 'Offer', 'Rejected')),
  -- A date is required for every status except Wishlist.
  CONSTRAINT ck_app_applied CHECK (status = 'Wishlist' OR applied_on IS NOT NULL),
  CONSTRAINT ck_app_url     CHECK (job_url IS NULL OR job_url LIKE 'http://%'
                        OR job_url LIKE 'https://%'),
  CONSTRAINT ck_app_notes   CHECK (notes IS NULL OR CHAR_LENGTH(notes) <= 1000),
  CONSTRAINT ck_app_place   CHECK (location IS NULL OR CHAR_LENGTH(location) <= 120),
  CONSTRAINT ck_app_salary  CHECK (salary IS NULL OR CHAR_LENGTH(salary) <= 50),
  CONSTRAINT ck_app_sample  CHECK (is_sample IN (0, 1)),

  -- The dashboard lists the newest applications first.
  KEY idx_app_created_at (created_at DESC),
  -- Every query is scoped to the signed-in person.
  KEY idx_app_user       (user_id, applied_on),
  -- A generated row always has a fingerprint, a hand-entered one never
  -- does, so the UNIQUE index cannot reject a person's own records.
  UNIQUE KEY uq_app_fingerprint (fingerprint),

  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------- test database
-- Same shape, so the route tests exercise the real schema.
USE placement_tracker_test;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME     NULL,
  UNIQUE KEY uq_users_email    (email),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  selector       CHAR(32)     NOT NULL,
  validator_hash CHAR(64)     NOT NULL,
  expires_at     DATETIME     NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_remember_selector (selector),
  KEY idx_remember_user (user_id),
  CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  company     VARCHAR(100)  NOT NULL,
  role        VARCHAR(100)  NOT NULL,
  status      VARCHAR(20)   NOT NULL,
  applied_on  DATE          NULL,
  location    VARCHAR(120)  NULL,
  salary      VARCHAR(50)   NULL,
  job_url     VARCHAR(500)  NULL,
  notes       TEXT          NULL,
  is_sample   TINYINT(1)    NOT NULL DEFAULT 0,
  fingerprint CHAR(40)      NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT ck_app_company CHECK (CHAR_LENGTH(company) BETWEEN 2 AND 100),
  CONSTRAINT ck_app_role    CHECK (CHAR_LENGTH(role)    BETWEEN 2 AND 100),
  CONSTRAINT ck_app_status  CHECK (status IN
                        ('Wishlist', 'Applied', 'Interview', 'Offer', 'Rejected')),
  CONSTRAINT ck_app_applied CHECK (status = 'Wishlist' OR applied_on IS NOT NULL),
  CONSTRAINT ck_app_url     CHECK (job_url IS NULL OR job_url LIKE 'http://%'
                        OR job_url LIKE 'https://%'),
  CONSTRAINT ck_app_notes   CHECK (notes IS NULL OR CHAR_LENGTH(notes) <= 1000),
  CONSTRAINT ck_app_place   CHECK (location IS NULL OR CHAR_LENGTH(location) <= 120),
  CONSTRAINT ck_app_salary  CHECK (salary IS NULL OR CHAR_LENGTH(salary) <= 50),
  CONSTRAINT ck_app_sample  CHECK (is_sample IN (0, 1)),

  KEY idx_app_created_at (created_at DESC),
  KEY idx_app_user       (user_id, applied_on),
  UNIQUE KEY uq_app_fingerprint (fingerprint),
  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
