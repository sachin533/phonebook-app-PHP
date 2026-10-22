-- ==========================================================================
--  Placement Application Tracker - verify.sql
--
--  Read-only proof of what is actually in the database. Run it any time:
--
--      mysql -u tracker_app -p placement_tracker < db\verify.sql
--
--  The third query MUST return an empty result set. If it returns rows, a
--  record has a status other than Wishlist and no applied_on date, which
--  the CHECK constraint ck_app_applied is supposed to make impossible.
-- ==========================================================================

USE placement_tracker;

-- How many rows are stored, and how they are spread across the statuses.
SELECT COUNT(*) AS total FROM applications;

SELECT status, COUNT(*) AS n FROM applications
  GROUP BY status
  ORDER BY n DESC, status;

-- Integrity check: must be empty.
SELECT id, company, status FROM applications
  WHERE status <> 'Wishlist' AND applied_on IS NULL;

-- The five most recently created applications.
SELECT id, company, role, status, applied_on, created_at
  FROM applications
  ORDER BY created_at DESC, id DESC
  LIMIT 5;

-- The accounts, without the password hashes.
SELECT id, name, email, username, created_at, last_login_at
  FROM users
  ORDER BY id;

-- How the rows are split between the hand written ones and the generated
-- sample data.
SELECT is_sample, COUNT(*) AS n FROM applications GROUP BY is_sample;

-- The table shape, including the enforced CHECK constraints.
SHOW CREATE TABLE applications;
