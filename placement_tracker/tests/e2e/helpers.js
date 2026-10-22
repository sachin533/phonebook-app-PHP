// @ts-check
const { expect } = require('@playwright/test');

/** The account the tests sign in with. It is made by global-setup.js. */
const ACCOUNT = {
  email: 'playwright@example.com',
  username: 'playwright',
  password: 'Playwright-Test-9',
};

/** A unique marker, so each test works on its own records. */
function uniqueName(prefix) {
  return `${prefix} ${Date.now()}-${Math.floor(Math.random() * 100000)}`;
}

/**
 * Sign in through the real login form.
 */
async function signIn(page) {
  await page.goto('/login.php');
  await page.fill('#identifier', ACCOUNT.email);
  await page.fill('#password', ACCOUNT.password);
  await page.click('.auth-card button[type="submit"]');
  await page.waitForURL('**/dashboard.php');
  await expect(page.locator('.sidebar-user-name')).toHaveText('Playwright Tester');
}

/**
 * The application rows that are on the page right now.
 */
async function visibleCompanies(page) {
  return page.locator('.data-table tbody .company-name').allTextContents();
}

/**
 * Wait until the search has been sent and the table has been replaced.
 */
async function waitForSearch(page) {
  await expect(page.locator('[data-results-loading]')).not.toHaveClass(/on/);
  await page.waitForTimeout(400);
}

/**
 * Fill in the application form and save it.
 */
async function addApplication(page, fields) {
  await page.goto('/new.php');
  await page.fill('#company', fields.company);
  await page.fill('#role', fields.role);
  await page.fill('#applied_on', fields.appliedOn || '2026-09-16');
  await page.selectOption('#status', fields.status || 'Applied');
  if (fields.location) await page.fill('#location', fields.location);
  if (fields.salary) await page.fill('#salary', fields.salary);
  if (fields.jobUrl) await page.fill('#job_url', fields.jobUrl);
  if (fields.notes) await page.fill('#notes', fields.notes);

  await page.click('.form-card button[type="submit"]');
  await page.waitForURL('**/view.php?id=*');

  const id = new URL(page.url()).searchParams.get('id');
  return id;
}

module.exports = { ACCOUNT, uniqueName, signIn, visibleCompanies, waitForSearch, addApplication };
