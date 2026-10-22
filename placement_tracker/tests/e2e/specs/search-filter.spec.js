// @ts-check
const { test, expect } = require('@playwright/test');
const { uniqueName, signIn, visibleCompanies, waitForSearch } = require('../helpers');
const { clearRecords, seedRecords } = require('../db-tools');

test.describe('Searching and filtering the list', () => {
  test.beforeEach(async ({ page }) => {
    await signIn(page);

    // Records that can be found by company, by role and by location.
    seedRecords(5, { prefix: 'Kestrel Systems', role: 'Zephyr Platform Engineer' });
    seedRecords(3, { prefix: 'Nightingale Data', role: 'Data Analyst', location: 'Quetzalacoatl City' });
    seedRecords(2, { prefix: 'Unrelated Systems', role: 'Support Engineer', status: 'Rejected' });
    seedRecords(2, { prefix: 'Kestrel Labs', role: 'Product Manager', status: 'Offer' });
  });

  test.afterEach(() => {
    clearRecords();
  });

  test('the search finds a company as you type', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'Nightingale');
    await waitForSearch(page);

    const companies = await visibleCompanies(page);
    expect(companies.length).toBe(3);
    expect(companies.every((c) => c.includes('Nightingale Data'))).toBe(true);
  });

  test('the search is case insensitive', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'nightingale');
    await waitForSearch(page);
    const lower = await visibleCompanies(page);

    await page.fill('#topbar-search-input', 'NIGHTINGALE');
    await waitForSearch(page);
    const upper = await visibleCompanies(page);

    expect(lower).toEqual(upper);
    expect(lower.length).toBe(3);
  });

  test('the search matches part of a word', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'estrel');
    await waitForSearch(page);

    const companies = await visibleCompanies(page);
    expect(companies.length).toBe(7);
    expect(companies.every((c) => c.includes('Kestrel'))).toBe(true);
  });

  test('the search looks in the role as well', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'Zephyr Platform');
    await waitForSearch(page);

    const companies = await visibleCompanies(page);
    expect(companies.length).toBe(5);
    expect(companies.every((c) => c.includes('Kestrel Systems'))).toBe(true);
  });

  test('the search looks in the location as well', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'Quetzalacoatl');
    await waitForSearch(page);

    const companies = await visibleCompanies(page);
    expect(companies.length).toBe(3);
  });

  test('the search looks in the status as well', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'Offer');
    await waitForSearch(page);

    const statuses = await page.locator('.data-table tbody .badge').allTextContents();
    expect(statuses.length).toBe(2);
    expect(statuses.every((s) => s.trim() === 'Offer')).toBe(true);
  });

  test('the search reports the number of results', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'Kestrel');
    await waitForSearch(page);

    await expect(page.locator('.pagination-info')).toContainText('of');
    await expect(page.locator('.pagination-info')).toContainText('results');
    await expect(page.locator('.pagination-info')).toContainText('Kestrel');
  });

  test('a search with no results says so', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'zzz-nothing-matches-this');
    await waitForSearch(page);

    await expect(page.locator('.empty-state')).toBeVisible();
    await expect(page.locator('.empty-state h3')).toContainText('No matching applications');
  });

  test('the clear button empties the search', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'Kestrel');
    await waitForSearch(page);
    expect((await visibleCompanies(page)).length).toBe(7);

    await page.click('[data-search-clear]');
    await waitForSearch(page);

    await expect(page.locator('#topbar-search-input')).toHaveValue('');
    expect((await visibleCompanies(page)).length).toBe(10);
  });

  test('the search is in the address bar, so a result can be shared', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'Nightingale');
    await waitForSearch(page);

    await expect(page).toHaveURL(/q=Nightingale/);
  });

  test('a shared search link opens the right results', async ({ page }) => {
    await page.goto('/applications.php?q=Nightingale');

    const companies = await visibleCompanies(page);
    expect(companies.length).toBe(3);
    await expect(page.locator('#topbar-search-input')).toHaveValue('Nightingale');
  });

  test('the status filter narrows the list', async ({ page }) => {
    await page.goto('/applications.php');

    await page.selectOption('#filter-status', 'Offer');
    await waitForSearch(page);

    const statuses = await page.locator('.data-table tbody .badge').allTextContents();
    expect(statuses.length).toBe(2);
    expect(statuses.every((s) => s.trim() === 'Offer')).toBe(true);
  });

  test('the company filter narrows the list', async ({ page }) => {
    await page.goto('/applications.php');

    await page.selectOption('#filter-company', 'Nightingale Data 000');
    await waitForSearch(page);

    const companies = await visibleCompanies(page);
    expect(companies.length).toBe(1);
    expect(companies[0]).toBe('Nightingale Data 000');
  });

  test('a search and a filter work together', async ({ page }) => {
    await page.goto('/applications.php');

    await page.fill('#topbar-search-input', 'Kestrel');
    await waitForSearch(page);
    await page.selectOption('#filter-status', 'Offer');
    await waitForSearch(page);

    const rows = await visibleCompanies(page);
    expect(rows.length).toBe(2);
    expect(rows.every((c) => c.includes('Kestrel Labs'))).toBe(true);

    const statuses = await page.locator('.data-table tbody .badge').allTextContents();
    expect(statuses.every((s) => s.trim() === 'Offer')).toBe(true);
  });

  test('the date filter narrows the list', async ({ page }) => {
    await page.goto('/applications.php');

    // The seeded records were applied over the last 30 days.
    await page.fill('#filter-from', '2000-01-01');
    await waitForSearch(page);
    const all = await visibleCompanies(page);
    expect(all.length).toBe(10);

    await page.fill('#filter-from', '2999-01-01');
    await waitForSearch(page);
    await expect(page.locator('.empty-state')).toBeVisible();
  });

  test('the reset link clears the filters', async ({ page }) => {
    await page.goto('/applications.php');

    await page.selectOption('#filter-status', 'Offer');
    await waitForSearch(page);
    await expect(page.locator('.filter-reset')).toBeVisible();

    await page.click('.filter-reset');
    await page.waitForLoadState('load');

    await expect(page).toHaveURL(/\/applications\.php(\?|$)/);
    expect((await visibleCompanies(page)).length).toBe(10);
  });

  test('the filters are kept when the page is reloaded', async ({ page }) => {
    await page.goto('/applications.php');

    await page.selectOption('#filter-status', 'Rejected');
    await waitForSearch(page);

    await page.reload();
    await expect(page.locator('#filter-status')).toHaveValue('Rejected');
  });
});
