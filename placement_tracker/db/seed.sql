-- ==========================================================================
--  Placement Application Tracker - seed.sql
--
--  Five demo rows so the dashboard has something to show on first run.
--  Idempotent: the rows are inserted ONLY when the table is empty, so
--  re-running the script never duplicates or wipes your data.
--
--  Run as the MySQL root user (or as a user with INSERT rights):
--
--      mysql -u root -p placement_tracker < db\seed.sql
-- ==========================================================================

USE placement_tracker;

INSERT INTO applications (company, role, status, applied_on, job_url, notes)
SELECT s.company, s.role, s.status, s.applied_on, s.job_url, s.notes
FROM (
  SELECT 'Acme Labs'     AS company, 'Software Intern'    AS role,
         'Applied'       AS status, '2026-09-16'         AS applied_on,
         'https://acme.example/careers' AS job_url,
         'Referred by the campus placement cell. Applied through the careers page.' AS notes
  UNION ALL
  SELECT 'Northstar',    'Graduate Engineer', 'Interview', '2026-09-12',
         'https://northstar.example/jobs',
         'Online assessment done. Waiting for the interview slot confirmation.'
  UNION ALL
  SELECT 'Contoso',     'Backend Intern',    'Rejected',  '2026-09-05',
         'https://contoso.example/careers',
         'Rejected after the technical round. Keep as a reminder for next year.'
  UNION ALL
  SELECT 'Globex',      'Frontend Developer','Offer',     '2026-08-28',
         'https://globex.example/apply',
         'Offer received. Response needed before the end of the month.'
  UNION ALL
  SELECT 'Initech',     'Data Analyst',      'Wishlist',  NULL,
         'https://initech.example/careers',
         'Not applied yet - the team opens applications in November.'
) AS s
WHERE NOT EXISTS (SELECT 1 FROM applications);

SELECT COUNT(*) AS seeded_rows FROM applications;
