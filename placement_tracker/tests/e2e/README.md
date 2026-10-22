# Playwright end to end tests

These tests drive a real browser against the running application, the same
way a person would: they sign in, add an application, change it, delete it,
search, filter, page through the list and sign out.

## What they need

* Node.js 18 or newer
* The Chromium build that Playwright downloads

```powershell
cd tests\e2e
npm install
npx playwright install chromium
```

## Running them

```powershell
cd tests\e2e
npx playwright test
```

The report is written to `playwright-report\index.html`; open it with:

```powershell
npm run report
```

Run one file, or one test:

```powershell
npx playwright test specs/auth.spec.js
npx playwright test -g "the search finds a company"
```

## How they are set up

* The tests run against **their own server** on port **8210**, started by
  Playwright itself, and against the **test database**
  `placement_tracker_test`. They never touch the real site or the real data.
* `global-setup.js` runs `user.php create`, which makes a known account
  (`playwright@example.com` / `Playwright-Test-9`) with an empty list.
* `user.php` can also seed records straight into the database
  (`php user.php seed 25 --prefix=Paged`), which is much faster than filling
  the form hundreds of times, and clear them again
  (`php user.php clear`).
* The tests run **one after another** (`workers: 1`), because the PHP
  development server handles one request at a time.

## The files

| File | What it covers |
|---|---|
| `specs/auth.spec.js` | signing in, the wrong password, the username, signing out, the session cookie |
| `specs/crud.spec.js` | adding, viewing, changing and deleting an application, the confirmation dialog, the validation messages |
| `specs/search-filter.spec.js` | searching by company, role, location and status, the clear button, the filters, the reset link |
| `specs/pagination.spec.js` | the numbered pages, Previous and Next, the page size, a page number past the end |
| `helpers.js` | signing in, adding a record, reading the table |
| `db-tools.js` | calls `user.php` to seed and clear records |
| `user.php` | creates, clears and seeds the test account |
