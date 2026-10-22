// @ts-check
const { test, expect } = require('@playwright/test');
const { signIn, visibleCompanies } = require('../helpers');
const { clearRecords, seedRecords } = require('../db-tools');

test.describe('The numbered pagination', () => {
  // 25 records, so three pages of ten.
  test.beforeEach(async ({ page }) => {
    await signIn(page);
    seedRecords(25, { prefix: 'Paged Co', role: 'Pagination Tester' });
  });

  test.afterEach(() => {
    clearRecords();
  });

  test('the first page shows ten rows and says what it is showing', async ({ page }) => {
    await page.goto('/applications.php');

    expect((await visibleCompanies(page)).length).toBe(10);
    await expect(page.locator('.pagination-info')).toContainText('Showing');
    await expect(page.locator('.pagination-info')).toContainText('of');
    await expect(page.locator('.pagination .current')).toHaveText('1');
  });

  test('the page numbers are links', async ({ page }) => {
    await page.goto('/applications.php');

    const numbers = await page.locator('.pagination li a').allTextContents();
    expect(numbers).toContain('2');
    expect(numbers).toContain('3');
  });

  test('the second page shows the next ten rows', async ({ page }) => {
    await page.goto('/applications.php?page=1');
    const first = await visibleCompanies(page);

    await page.goto('/applications.php?page=2');
    const second = await visibleCompanies(page);

    expect(second.length).toBe(10);
    expect(first).not.toEqual(second);
    await expect(page.locator('.pagination .current')).toHaveText('2');
  });

  test('the last page shows the remaining rows', async ({ page }) => {
    await page.goto('/applications.php?per_page=10&page=3');

    const rows = await visibleCompanies(page);
    expect(rows.length).toBe(5);
    await expect(page.locator('.pagination .current')).toHaveText('3');
  });

  test('the Next and Previous buttons move through the list', async ({ page }) => {
    await page.goto('/applications.php');

    await page.click('.pagination a[rel="next"]');
    await expect(page.locator('.pagination .current')).toHaveText('2');

    await page.click('.pagination a[rel="prev"]');
    await expect(page.locator('.pagination .current')).toHaveText('1');
  });

  test('Previous is disabled on the first page', async ({ page }) => {
    await page.goto('/applications.php');

    await expect(page.locator('.pagination .arrow.disabled')).toHaveCount(1);
    await expect(page.locator('.pagination a[rel="prev"]')).toHaveCount(0);
  });

  test('Next is disabled on the last page', async ({ page }) => {
    await page.goto('/applications.php?per_page=10&page=3');

    await expect(page.locator('.pagination .arrow.disabled')).toHaveCount(1);
    await expect(page.locator('.pagination a[rel="next"]')).toHaveCount(0);
  });

  test('a page number past the end shows the last page', async ({ page }) => {
    await page.goto('/applications.php?per_page=10&page=9999');

    await expect(page.locator('.pagination .current')).toHaveText('3');
    expect((await visibleCompanies(page)).length).toBe(5);
  });

  test('a page number below one shows the first page', async ({ page }) => {
    await page.goto('/applications.php?per_page=10&page=-5');

    await expect(page.locator('.pagination .current')).toHaveText('1');
  });

  test('the page size can be changed to twenty', async ({ page }) => {
    await page.goto('/applications.php');

    await page.selectOption('[data-per-page]', '20');
    // The change reloads the page, so wait for the new address.
    await page.waitForURL(/per_page=20/);

    expect((await visibleCompanies(page)).length).toBe(20);
  });

  test('the search and the page number work together', async ({ page }) => {
    await page.goto('/applications.php?q=Paged');
    await expect(page.locator('.pagination-info')).toContainText('Paged');

    await page.goto('/applications.php?q=Paged&page=2');
    await expect(page.locator('.pagination-info')).toContainText('Paged');
  });

  test('the search and the filters are kept in the page links', async ({ page }) => {
    await page.goto('/applications.php?q=Paged&status=Applied');

    const href = await page.locator('.pagination li a').first().getAttribute('href');
    expect(href).toContain('q=Paged');
    expect(href).toContain('status=Applied');
  });

  test('a page can be opened straight from the address', async ({ page }) => {
    const response = await page.goto('/applications.php?page=2&per_page=10');

    expect(response.status()).toBe(200);
    await expect(page.locator('.pagination .current')).toHaveText('2');
  });

  test('the pages show different records', async ({ page }) => {
    await page.goto('/applications.php?page=1&per_page=10&sort=company&dir=asc');
    const first = await visibleCompanies(page);

    await page.goto('/applications.php?page=2&per_page=10&sort=company&dir=asc');
    const second = await visibleCompanies(page);

    const overlap = first.filter((c) => second.includes(c));
    expect(overlap).toEqual([]);
  });
});
