# Delivery Report — Placement Application Tracker (PHP 8 / MySQL 8)

**Project:** `E:\CyberF\DAY 55\placement_tracker`
**Date:** 2026-10-21
**Stack:** PHP 8.3 (winget), MySQL 8.0.43, hand-written CSS/JS — no framework, no build step.

---

## 1. Files changed

| File | Change |
|---|---|
| `login.php` | Fixed a `$errors`-ordering bug: the throttled-login message was assigned before `$errors = []`, so it was wiped and never shown. |
| `404.php`, `403.php`, `405.php` | Fixed a fatal `Call to undefined function e()`. Each page now loads `inc/helpers.php` behind a `function_exists` guard so it works both standalone (direct URL) and in-app (when helpers are already loaded). |
| `new.php`, `edit.php` | Removed a premature `require inc/form.php` at the top of both files. `inc/form.php` *renders markup when included*, so the entire form was printed before `<!DOCTYPE>` on every new/edit request (`<!DOCTYPE>` started at byte 2937 — content before the doctype puts browsers into quirks mode). The form is now included only inside the page layout (the existing include later in each file). Verified after the fix: `<!DOCTYPE>` at byte 0-1, no `<form>` before it, prefilled edit values intact, create/update POSTs still 302. |
| `demo-data.php`, `inc/seeder.php`, `dashboard.php`, `register.php`, `inc/table.php` | "Load demo data" feature added by the concurrent session (after this report was first drafted): a POST+CSRF-protected button that fills the signed-in account with flagged sample rows so a brand-new account can try search/filters/pagination immediately. I reviewed the code (login required, POST-only → 405, missing CSRF → 403, `total` clamped 0–5000, redirect restricted to local paths) and verified it end-to-end over HTTP and in the browser. |

Everything else in the tree (applications, companies, statistics, profile, CRUD, auth, list/search/pagination layer, charts, table renderer, JSON endpoint, `db/*` scripts, `tests/*`, `README.md`) was already complete and passing; no restructuring was done.

## 2. What the application does

- **Auth** — `password_hash`/`password_verify`, sign in by email *or* username, logout, CSRF on every POST, protected routes, session-fixation defence, a login throttle (8 failures / 10 min), remember-me tokens. First account adopts any pre-auth rows so nothing is lost.
- **App shell** — fixed sidebar (Dashboard, Applications, Companies, Statistics, Profile), sticky top bar with a global search box and profile menu, responsive with a mobile sidebar toggle.
- **Dashboard** — six stat tiles, a status donut, an applications-per-month bar chart, a recent list.
- **Applications** — server-side search (company, role, status, location, notes, job link; case-insensitive, partial-word, `%`/`_` treated literally), sortable columns, filters (status, company, date range) that combine with search, numbered pagination with ellipsis, Prev/Next disabled at the ends, page-size selector (10/20/50), URL reflects every parameter so results are bookmarkable.
- **CRUD** — create / read / update / delete, all POST+redirect, 422 with field errors on invalid input, `<dialog>` confirmation on delete.
- **Sample data** — 523 generated rows, all flagged `is_sample = 1` with a stable `fingerprint` so the seeder never duplicates; original 6 hand-written rows preserved. A **"Load demo data" button** on the dashboard (POST + CSRF) can also fill any brand-new account with flagged sample rows on demand.
- **Security** — prepared statements everywhere, `e()` output encoding, `HttpOnly`/`SameSite=Lax` cookies, least-privilege `tracker_app` MySQL account (SELECT/INSERT/UPDATE/DELETE only), per-user query scoping.

## 3. How it was run and tested

**Servers used for verification** (isolated so a concurrent session's shared servers could not poison results):

```
php -S 127.0.0.1:8200 -t "E:\CyberF\DAY 55\placement_tracker"   # DB_NAME=placement_tracker_test
```

**Test results — all green:**

| Suite | Result |
|---|---|
| PHPUnit full run (unit + auth + routes + list) — final run after all file changes | **OK — 95 tests, 423 assertions** |
| Unit suite alone (`--testsuite unit`) | **OK — 42 tests, 97 assertions** |
| `tests\e2e.ps1` (10 sections) | **Passed: 60  Failed: 0** |
| `php -l` on every `.php` file (66 files) | **No syntax errors** |

The e2e run covers: guest protection, sign-in (wrong password → 422, right password → 302), dashboard rendering, the list, search by company/role/location/status/notes + case-insensitivity + empty-result message, status/company/date filters, filter+search combination, pagination (first page, past-the-end clamp, search+page, page-size 20), the JSON endpoint, all secondary pages, full create→read→update→delete, and sign-out.

**Manual verification of the delivered app** (port 8000): demo login → 302; list "Showing 1–10 of 529"; search `Zephyr` → 1 result; dashboard/statistics/companies/profile all 200 with no fatal output; the three error pages return real 404/403/405 bodies both by direct URL and through the app.

**Browser verification** (real Chromium, after the fixes above): a fresh zero-row account and an account with 50 loaded samples both render the full shell — sidebar (248px), sticky top bar, content area — in standards mode (`CSS1Compat`), with the list showing "Showing 1 to 10 of 50 results", sample badges on every row, and **zero console errors**. The reported "blank content area" could not be reproduced at any point afterwards: every page renders complete HTML for every account (including the personal account, rendered page-by-page with an error-trapping harness — no warnings, no fatals). The blank view coincided with a period when the concurrent session was actively rewriting page files, so it was a transient/mid-write state — a normal refresh (Ctrl+F5) resolves it.

**Note on test ordering:** the HTTP suites call `clearTestRecords()`, which wipes the test account — always re-seed **after** the PHPUnit run and before judging e2e row-count checks.

## 4. Database state (delivered)

- Instance: MySQL 8.0.43 on **port 3306** (the instance `config.php` points at).
- `placement_tracker`: 2 accounts —
  - `demo@tracker.local`: **529 applications** = 523 generated samples + 6 original hand-written rows (row `id=2050` verified byte-for-byte back at its original values after testing).
  - the account you created yourself (`sachinkamble`): **523 sample rows**, loaded through the "Load demo data" button; your own records are untouched by any test work.
- `placement_tracker_test`: re-seeded to **523 rows** *after* the final test run so the suites stay repeatable.

## 5. Setup / run instructions (for the next machine)

```powershell
# one-command setup (asks for the MySQL root password)
powershell -ExecutionPolicy Bypass -File db\setup.ps1

# or by hand
mysql -u root -p < db\schema.sql
mysql -u root -p < db\users.sql
mysql -u root -p placement_tracker < db\seed.sql
$env:DB_USER='root'; $env:DB_PASS='<root>'; php db\migrate.php --all   # upgrade an old DB
php db\create_user.php                                                # first account
php db\seed_sample.php --user=<email> --total=523                     # 500+ sample rows

# run
php -S localhost:8000        # open http://localhost:8000/
```

Credentials live in `config.php` (git-ignored); the app account is the least-privilege `tracker_app`.

## 6. Honest limitations

- **Concurrent session interference.** A second agent session was writing to the same project and running its own servers/tests against the shared databases throughout this work (it authored the demo-data feature and later test additions). Every test above was therefore re-run against an isolated port (8200) and an isolated database (`placement_tracker_test`), and the final numbers were taken only after its file writes had stopped, to get trustworthy results. Stray test accounts created during runs (0 rows each) were removed from the delivered database.
- **The two-MySQL-instance split** discovered mid-session (a second mysqld on port 3307) was resolved by making **port 3306 canonical** — the instance `config.php` points at and the one the delivered app reads. The app server on port 8000 was confirmed to be serving that instance.
- **No real OAuth.** The sign-in page shows only the email/username form on purpose; `README.md` §12 documents exactly what real Google sign-in would require (Cloud project, People API, client ID/secret, redirect URI, a callback page, a `google_id` column) rather than shipping a fake button.
- **`php -S` is for development only.** The README notes it should not be used in deployment; under Apache/XAMPP the `inc/`, `db/`, `tests/` and `config.php` paths must be denied.
- **Test-suite repeatability** depends on the test account having data; the HTTP suites call `clearTestRecords()`, which wipes the test account's rows by design. Re-seed with `php db\seed_sample.php --user=route.test@example.com --total=523` before a repeat run if needed.
