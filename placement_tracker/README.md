# Placement Application Tracker (PHP & MySQL)

A complete, professional web application for tracking job and internship
applications, built with **plain PHP 8** (no framework), **PDO / MySQL 8**,
and a hand-written design system. It has a signed-in dashboard, a real
search, server-side pagination, and 500+ generated sample records to work
with.

Everything is visible: routes and HTTP methods, query strings vs form data,
server-side validation, POST/Redirect/GET, status codes, output encoding
against XSS, prepared statements against SQL injection, CSRF tokens,
sessions, password hashing, and real schema design with enforced constraints.

---

## 1. What you need

| Requirement | Used on this machine | Check |
|---|---|---|
| PHP 8.1+ | **8.3.33** (winget) | `php -v` |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `curl` | `php -m` |
| MySQL Server | **8.0.43** (service `MySQL80`) | `mysql --version` |
| PHPUnit 10 or 11 | **11.5.57** (PHAR) | `php phpunit.phar --version` |
| PowerShell 5+ | built in | used by `db\setup.ps1` and the test scripts |

`pdo_mysql` and `mbstring` are **not** enabled in a fresh PHP install. If
`php -m` does not list them, open `php.ini` (next to `php.exe`) and
uncomment:

```ini
extension=pdo_mysql
extension=mbstring
```

---

## 2. Project structure

```
placement_tracker/
├── dashboard.php          the overview: stat tiles, donut chart, recent list
├── applications.php        the list: search, filters, sorting, pagination
├── index.php               the old address of the list (redirects to it)
├── new.php                 the create form
├── view.php                the details page
├── edit.php                the edit form
├── delete.php              the delete endpoint (POST only)
├── demo-data.php           loads the sample records into your account
├── companies.php           the companies you have applied to
├── statistics.php          the numbers: status split, trend, top roles
├── profile.php             the account, the password, the devices
├── login.php               sign in
├── register.php            sign up
├── logout.php              sign out
├── 404.php  403.php  405.php   the error pages (self contained)
├── config.example.php      copy to config.php (config.php is git-ignored)
├── start.ps1               starts MySQL, the web server and the tunnel
├── tunnel.ps1              puts the site on the internet with Cloudflare
├── run-tests.ps1           runs every test suite and prints the totals
├── phpunit.xml
├── inc/
│   ├── db.php              config(), db() -> one shared PDO, the 500 page
│   ├── helpers.php         e(), CSRF, flash, redirect, find_application()
│   ├── auth.php            sign in, sign out, the throttle, remember me
│   ├── validate.php        the validation rules, for the form and the sign up
│   ├── list.php            the search, the filters, the sorting, the paging
│   ├── stats.php           the grouped queries for the overview pages
│   ├── seeder.php          the generator for the demonstration records
│   ├── table.php           the table and the pagination bar
│   ├── charts.php          the donut and the bar chart, drawn as SVG
│   ├── form.php            the shared create / edit form
│   ├── icons.php           the inline SVG icons
│   ├── header.php          the app shell and the sign in layout
│   └── footer.php
├── static/
│   ├── styles.css          the whole design system
│   └── app.js              the search, the menus, the confirm dialog
├── api/
│   └── applications.php    the JSON endpoint behind the search box
├── db/
│   ├── schema.sql          the databases and the tables
│   ├── users.sql           the least-privilege MySQL account
│   ├── seed.sql            the five demonstration rows
│   ├── seed_sample.php     500+ generated sample records
│   ├── create_user.php     creates an account from the command line
│   ├── migrate.php         brings an older database up to date
│   ├── verify.sql          read-only proof of what is in the table
│   └── setup.ps1           one-command setup for Windows
├── tests/
│   ├── bootstrap.php
│   ├── HttpTestCase.php    the shared base class for the HTTP tests
│   ├── ValidationTest.php  39 tests, no database and no server
│   ├── LayoutTest.php       3 tests, the app shell layout
│   ├── AuthTest.php        13 tests, signing in and signing out
│   ├── RoutesTest.php      10 tests, the pages and the CRUD journey
│   ├── ListTest.php        26 tests, the search and the pagination
│   ├── e2e.ps1             the whole journey, checked with curl
│   ├── smoke.ps1           the status code checks, no PHPUnit needed
│   ├── smoke.sh            the same checks for Git Bash / WSL
│   └── e2e/                the Playwright browser tests
│       ├── specs/          auth, crud, search-filter, pagination
│       ├── user.php        makes and seeds the test account
│       └── playwright.config.js
└── README.md
```

---

## 3. Setup

### 3.1 One command

From the project root:

```powershell
powershell -ExecutionPolicy Bypass -File db\setup.ps1
```

It asks for the MySQL root password (it is never stored) and then:

1. creates `placement_tracker` and `placement_tracker_test` with the
   `applications` and `users` tables,
2. creates the least-privilege `tracker_app` MySQL account,
3. runs `db\migrate.php` so an older database is brought up to date without
   losing a single row,
4. seeds the five demonstration rows,
5. asks for the details of the first application account and creates it.

### 3.2 By hand

```powershell
# as the MySQL root user
mysql -u root -p < db\schema.sql
mysql -u root -p < db\users.sql
mysql -u root -p placement_tracker < db\seed.sql

# an existing database: add the new columns without losing anything
$env:DB_USER = 'root'
$env:DB_PASS = '<your root password>'
php db\migrate.php --all
```

`db\users.sql` holds the password for `tracker_app`. It must match
`DB_PASS` in `config.php`.

### 3.3 The configuration

```powershell
copy config.example.php config.php
```

```php
return [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'placement_tracker',
    'DB_USER' => 'tracker_app',
    'DB_PASS' => 'the password you used in users.sql',
];
```

`config.php` is listed in `.gitignore`; only `config.example.php` is
committed. An environment variable of the same name always wins over the
file, which is how the test suite is pointed at the test database:

```powershell
$env:DB_NAME = 'placement_tracker_test'
```

### 3.4 The first account

There is **no default account and no default password** anywhere in the
code. Create the first account either through the sign up page
(`/register.php`) or from the command line:

```powershell
php db\create_user.php
```

The first account also takes over the applications that were in the
database before anybody could sign in, so nothing is lost.

---

## 4. Run it

### The short version

```powershell
cd placement_tracker
powershell -ExecutionPolicy Bypass -File start.ps1
```

One command. It makes sure MySQL is running, starts the web server if it is
not, and opens a Cloudflare Tunnel so the site can be reached from anywhere.
The public address is printed and written to `.tunnel-url.txt`. Press
**Ctrl + C** to stop it.

Without the tunnel (local only):

```powershell
powershell -ExecutionPolicy Bypass -File start.ps1 -NoTunnel
```

### By hand

```powershell
cd placement_tracker
php -S localhost:8000
```

Open **http://localhost:8000/** and sign in.

Under Apache / XAMPP instead: put the project in `htdocs`, start Apache and
MySQL, and open `http://localhost/placement_tracker/`. Keep `inc/`, `db/`,
`tests/` and `config.php` out of reach - move them above the document root
or add `Require all denied` rules. Never deploy with `php -S`.

---

## 4b. Put it on the internet with a Cloudflare Tunnel

`cloudflared` gives the local site a public address, so it can be opened
from a phone or from another machine.

```powershell
cd placement_tracker
powershell -ExecutionPolicy Bypass -File tunnel.ps1
```

The script starts the web server if it is not running, opens the tunnel and
prints the public address:

```
  Your site is live at:

      https://aquarium-spoke-elizabeth-burton.trycloudflare.com

  Pointing at  http://localhost:8000
```

Press **Ctrl + C** to stop both. The address changes every time the tunnel
restarts.

| Option | What it does |
|---|---|
| `-Port 8080` | tunnel a different local port |
| `-NoBrowser` | do not open the browser automatically |
| `-NoStartServer` | tunnel an already running server, do not start one |
| `-Named <name>` | run a **named** tunnel with your own address |

**A quick tunnel needs no account**, but it has no uptime guarantee and the
address is random. For a stable address on your own domain you need a
Cloudflare account:

```powershell
cloudflared tunnel login
cloudflared tunnel create placement-tracker
cloudflared tunnel route dns placement-tracker tracker.example.com

powershell -ExecutionPolicy Bypass -File tunnel.ps1 -Named placement-tracker
```

`cloudflared` is already installed on this machine (2026.10.1). To install
it on another one: `winget install --id Cloudflare.cloudflared`.

> A tunnel publishes the site to the whole internet. The sign in page still
> protects the data, but check the password of the account you use before
> sharing a link.

---

## 5. The sample data

The tracker ships with five hand written rows. To fill it with realistic
demonstration data:

```powershell
php db\seed_sample.php --user=ada@example.com --total=523
```

**A new account starts empty** - every query is scoped to the signed in
person, so nobody sees anybody else's records. There are two ways to fill it:

* the **sign up page** has a *"Start with 500+ sample records"* box, ticked
  by default, and
* an empty list offers a **"Load 500+ sample records"** button
  (`demo-data.php`), which is also on the dashboard.

Both do the same thing, and both are safe to press again.

* The rows are **demonstration data**. The company names are invented, the
  job links point at `example.com`, and every row is marked with
  `is_sample = 1` and carries a **Sample** badge in the interface. Nothing
  claims that a real vacancy exists.
* The seeder is **safe to run again**. Every generated row has a stable
  fingerprint and the insert uses `INSERT IGNORE` against a unique index, so
  a second run adds nothing:

  ```
  Already have: 523 row(s)
  Adding      : 0 row(s)
  Nothing to add - the database already has the requested number of rows.
  ```

  Raising `--total` adds only the difference.
* The status mix follows a real application funnel: about 38% applied, 15%
  interview, 5% offer, 20% rejected and 23% wishlist.

> **Two seeders, one table.** `db\seed_sample.php` is the one that belongs
> to this project. A second, larger seed file (`E:\CyberF\DAY 5\db\seed.sql`)
> uses real company names and is also idempotent - it deletes the rows it
> owns (`is_sample = 1`) before inserting them again. Both are safe to run
> more than once, and both mark their rows as sample data.

---

## 6. The tests

### 6.1 PHPUnit

```powershell
# the pure validation rules - no database, no server
php phpunit.phar --testsuite unit

# the rest - they need a server
$env:APP_BASE_URL = 'http://127.0.0.1:8099'
$env:DB_NAME      = 'placement_tracker_test'
php phpunit.phar
```

```
OK (95 tests, 423 assertions)
```

| Suite | Tests | What it covers |
|---|---|---|
| `unit` | 42 | every validation rule, the registration rules, the sign in rules, the app shell layout |
| `auth` | 13 | guests are kept out, signing in, signing out, CSRF, the password hash, one person cannot read another person's record |
| `routes` | 10 | the status codes, the pages, the full create / read / update / delete journey, the XSS check, the JSON endpoint |
| `list` | 30 | the search by company, role, status, location and notes, the filters, the combinations, the sorting, the pagination |

> The HTTP tests write records and accounts, so they refuse to run against
> a database whose name does not end in `_test`. If you point
> `DB_NAME` at `placement_tracker`, the suite stops with an explanation
> instead of touching your real data.

### 6.2 Playwright end to end tests

These drive a real browser against the running application, the way a person
would: they sign in, add an application, change it, delete it, search,
filter, page through the list and sign out.

```powershell
cd tests\e2e
npm install                      # once
npx playwright install chromium  # once
npx playwright test
```

They run against **their own server** on port 8210 and against the **test
database**, so they never touch the real site or the real data.
`tests\e2e\README.md` has the details.

### 6.3 The end to end script

```powershell
powershell -File tests\e2e.ps1
```

It signs in, walks every page, searches, filters, pages through the list
and creates, changes and deletes a record. **60 checks, all passing.**

### 6.4 The smoke script

```powershell
powershell -File tests\smoke.ps1
```

The fallback for a machine without PHPUnit (a `curl` version for Git Bash is
in `tests\smoke.sh`):

```
== a guest is sent to the login page ==
GET  /dashboard.php                     expected 302 got 302 PASS
...
== the signed in pages ==
GET  /applications.php                  expected 200 got 200 PASS
GET  "/view.php?id=999999"              expected 404 got 404 PASS
GET  /delete.php                        expected 405 got 405 PASS
POST /delete.php                        expected 403 got 403 PASS
```

### 6.5 Everything at once

```powershell
powershell -ExecutionPolicy Bypass -File run-tests.ps1
```

It starts the servers the tests need, runs all four suites and prints the
totals:

```
== PHPUnit ==
  phpunit                  95 passed    0 failed   OK

== Playwright ==
  playwright               48 passed    0 failed   OK

== Smoke and end to end scripts ==
  smoke.ps1                25 passed    0 failed   OK
  e2e.ps1                  60 passed    0 failed   OK

  TOTAL: 228 passed, 0 failed
```

The script refuses to run against a database whose name does not end in
`_test`, so it can never touch the real data.

---

## 7. The pages

| Page | What it is |
|---|---|
| `/dashboard.php` | Six stat tiles, the status donut, the applications per month, the recent list |
| `/applications.php` | The list: search, filters, sortable columns, numbered pagination |
| `/companies.php` | Every company you have applied to, with how many applications each has |
| `/statistics.php` | The status split, the conversion rate, the monthly trend, the top roles and companies |
| `/profile.php` | The account, the change password form, the saved devices |
| `/new.php` `/view.php` `/edit.php` | Create, read and update |
| `/delete.php` | Delete, POST only |
| `/login.php` `/register.php` `/logout.php` | The account |
| `/api/applications.php` | The JSON the search box talks to |

`/index.php` still works: it redirects to `/applications.php`.

---

## 8. The route contract

| Method | Page | Result |
|---|---|---|
| `GET` | `/dashboard.php` | 200, the overview |
| `GET` | `/applications.php` | 200, the list |
| `GET` | `/new.php` | 200, the form |
| `POST` | `/new.php` | 302 to the new record, or **422** with the errors |
| `GET` | `/view.php?id=<id>` | 200 details, or **404** |
| `GET` | `/edit.php?id=<id>` | 200 pre-filled form, or **404** |
| `POST` | `/edit.php?id=<id>` | 302 to the details, or **422** with the errors |
| `POST` | `/delete.php` | 302 to the list |
| `GET` | `/delete.php` | **405** with an `Allow: POST` header |
| `POST` | any form without a token | **403** |

A guest who asks for a protected page is redirected to `/login.php` and
lands back where they were heading after signing in.

---

## 9. The search and the pagination

The search runs **in the database**. The browser never receives more than
one page of rows, so 523 records cost the same as 10.

* It looks in the company, the role, the status, the location, the notes and
  the job link.
* It is case insensitive and matches part of a word.
* `%` and `_` in the search are treated as ordinary characters, so a search
  for `50%` looks for that text instead of matching everything.
* It combines with the status, company and date filters.
* It searches while you type, with a 300 ms debounce, through
  `/api/applications.php`. The same PHP renders the rows for the page and
  for the endpoint, so the two can never drift apart.
* The URL always shows the search, so a result can be bookmarked or shared:
  `/applications.php?page=2&q=google`.

The pagination shows the real total:

```
Showing 1 to 10 of 523 results
```

with numbered buttons, an ellipsis for the gap, Previous and Next that are
disabled at the ends, and a page size selector (10, 20 or 50). The search,
the filters and the sorting are kept in every link.

---

## 10. The data model

```sql
CREATE TABLE applications (
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
  KEY idx_app_user (user_id, applied_on),
  UNIQUE KEY uq_app_fingerprint (fingerprint)
);
```

| Field | Rule |
|---|---|
| `id` | Generated by the database. Never sent by the browser. |
| `user_id` | The owner. Every query is scoped to it. |
| `company` | Required, 2-100 characters |
| `role` | Required, 2-100 characters |
| `status` | One of `Wishlist`, `Applied`, `Interview`, `Offer`, `Rejected` |
| `applied_on` | Required unless the status is `Wishlist` |
| `location` | Optional, up to 120 characters |
| `salary` | Optional, up to 50 characters |
| `job_url` | Optional. Must start with `http://` or `https://` |
| `notes` | Optional, at most 1,000 characters |
| `is_sample` | 1 for the generated demonstration rows |
| `fingerprint` | A stable id for a generated row, so the seeder never duplicates |

The same rules exist twice: once in `inc\validate.php`, so the user gets a
readable message, and once as `CHECK` constraints, so bad data cannot get
in even if a future code path forgets to validate.

---

## 11. Security

| Risk | Defence | Where |
|---|---|---|
| SQL injection | Prepared statements with bound values, `EMULATE_PREPARES = false` | `inc\db.php`, `inc\list.php` |
| XSS | `e()` around every dynamic value | `inc\helpers.php` |
| CSRF | A per-session random token, compared with `hash_equals` | `inc\helpers.php` |
| Password theft | `password_hash()` / `password_verify()`, never stored in plain text | `inc\auth.php` |
| Password guessing | A session throttle: 8 failures in 10 minutes | `inc\auth.php` |
| Session fixation | The session id is regenerated on sign in | `inc\auth.php` |
| Stolen cookies | `HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS | `inc\helpers.php` |
| Stolen remember tokens | Only a hash of the secret half is stored, and the token is single use | `inc\auth.php` |
| Over-privileged database access | The app account has only `SELECT, INSERT, UPDATE, DELETE` | `db\users.sql` |
| Reading somebody else's records | Every query is scoped to `user_id` | `inc\list.php`, `inc\helpers.php` |
| Open redirect | Only a local path is accepted after signing in | `login.php` |

---

## 12. Google sign in

There is **no Google sign in button**, on purpose. A button that does not
work is worse than no button, and a fake one that accepts anything would be
a security hole.

To add real Google sign in you would need:

1. **A Google Cloud project** and the **Google People API** (or the
   OpenID Connect endpoints) enabled.
2. **OAuth 2.0 client credentials** from
   [Google Cloud Console -> APIs & Services -> Credentials](https://console.cloud.google.com/apis/credentials):
   * a **Client ID**, whose **Authorized JavaScript origins** lists your
     origin, for example `http://localhost:8000`,
   * a **Client Secret**.
3. **A redirect URI** registered with Google, for example
   `http://localhost:8000/auth/google/callback`.
4. **The keys in the configuration**, never in a public page:

   ```php
   return [
       // ...
       'GOOGLE_CLIENT_ID'     => 'xxxxx.apps.googleusercontent.com',
       'GOOGLE_CLIENT_SECRET' => 'xxxxx',
       'GOOGLE_REDIRECT_URI'  => 'http://localhost:8000/auth/google/callback',
   ];
   ```

5. **A callback page** (`auth/google/callback.php`) that exchanges the
   `code` for an `access_token`, asks Google for the profile, and then
   signs the person in with the same `login_user()` the form uses - linking
   to an existing account by email, or creating one.
6. **A `google_id` column** on `users`, with a unique index, so the account
   can be found again.

Two libraries do the OAuth dance for you and neither needs a database table
of its own: **Google's own `google/apiclient-services`** (`Composer require
google/apiclient-services`) or **PHPSocialite** (`Composer require
laravel/socialite`, which also gives GitHub, Microsoft and Facebook sign in
in the same shape).

Until those credentials exist, the sign in page shows only the email
address / username form.

---

## 13. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| The page is half empty, or the sidebar is there but the content is missing | The browser is using an old `styles.css` | Hard refresh with **Ctrl + F5** (or Ctrl + Shift + R) |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong password, or `tracker_app` was never created | Run `db\setup.ps1`, check `DB_PASS` in `config.php` |
| `Unknown database 'placement_tracker'` | `db\schema.sql` was never run | Run `db\setup.ps1` |
| `Base class or interface "PDO" not found` | `pdo_mysql` is not enabled | Uncomment `extension=pdo_mysql` in `php.ini` |
| `Call to undefined function mb_strlen()` | `mbstring` is not enabled | Uncomment `extension=mbstring` in `php.ini` |
| A 500 page instead of the dashboard | The MySQL service is stopped | `Start-Service MySQL80` |
| `The account cannot create tables` from the migration | The migration has to run as the root user | `$env:DB_USER='root'; $env:DB_PASS='<root password>'` |
| The route tests cannot connect | No server on the test port | Start `php -S 127.0.0.1:8099` and set `APP_BASE_URL` |
| The list is empty | Signed in with a different account | Every query is scoped to the account |

---

## 14. What to be able to explain

* **Browser and HTTP** - what happens after entering the local URL; why
  create and delete use POST; what a redirect is and why one follows a
  save; what a 404 means and how `http_response_code()` sets it.
* **PHP and the web server** - how `php -S` decides which file runs; the
  difference between `$_GET` and `$_POST`; why a header and a footer are
  shared with `require`; why every value must go through `e()`.
* **Database and PDO** - what one row represents; why every record needs a
  primary key; why prepared statements are safer than concatenated SQL;
  what survives a server restart and why.
* **The full request flow** - trace a form submission from the browser to
  the database and back; trace the dashboard data from the database to the
  HTML; say where the input is validated and what the CHECK constraints
  add.
* **Accounts** - why the password is hashed and never stored; why the
  session id is regenerated on sign in; what the throttle is for; why the
  search and the pagination have to happen in the database.
