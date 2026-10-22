// @ts-check
const { test, expect } = require('@playwright/test');
const { uniqueName, signIn, addApplication } = require('../helpers');
const { clearRecords } = require('../db-tools');

test.describe('Adding, viewing, changing and deleting an application', () => {
  test.beforeEach(async ({ page }) => {
    await signIn(page);
  });

  test.afterEach(() => {
    clearRecords();
  });

  test('an application can be added and is then shown', async ({ page }) => {
    const company = uniqueName('Playwright Co');

    const id = await addApplication(page, {
      company,
      role: 'PHP Developer',
      location: 'Bengaluru, Karnataka',
      salary: '12 LPA',
      notes: 'Added by the Playwright test.',
    });

    expect(id).toBeTruthy();

    // The details page shows what was typed.
    await expect(page.locator('h1', { hasText: 'Application Details' })).toBeVisible();
    await expect(page.locator('.detail-value', { hasText: company })).toBeVisible();
    await expect(page.locator('.detail-value', { hasText: 'PHP Developer' })).toBeVisible();
    await expect(page.locator('.detail-value', { hasText: 'Bengaluru, Karnataka' })).toBeVisible();
    await expect(page.locator('.detail-value', { hasText: '12 LPA' })).toBeVisible();
    await expect(page.locator('.hero-card .badge', { hasText: 'Applied' })).toBeVisible();
  });

  test('the new application appears in the list', async ({ page }) => {
    const company = uniqueName('Listed Co');
    await addApplication(page, { company, role: 'Backend Developer' });

    await page.goto('/applications.php');
    await expect(page.locator('.company-name', { hasText: company })).toBeVisible();
  });

  test('the form refuses an empty company and keeps the other values', async ({ page }) => {
    await page.goto('/new.php');
    await page.fill('#role', 'QA Engineer');
    await page.fill('#applied_on', '2026-09-16');
    await page.click('.form-card button[type="submit"]');

    await expect(page.locator('.alert-error')).toBeVisible();
    await expect(page.locator('.alert-error')).toContainText('Company must be between 2 and 100 characters');
    // The values that were typed are still in the form.
    await expect(page.locator('#role')).toHaveValue('QA Engineer');
  });

  test('a real date is required', async ({ page }) => {
    await page.goto('/new.php');
    await page.fill('#company', uniqueName('Date Co'));
    await page.fill('#role', 'Data Analyst');
    await page.click('.form-card button[type="submit"]');

    await expect(page.locator('.alert-error')).toContainText('Applied date is required');
  });

  test('an application can be changed and the new status is kept', async ({ page }) => {
    const company = uniqueName('Editable Co');
    const id = await addApplication(page, { company, role: 'Frontend Developer' });

    await page.click('.page-head a[href*="edit.php"]');
    await expect(page).toHaveURL(new RegExp(`/edit\\.php\\?id=${id}`));

    await page.selectOption('#status', 'Interview');
    await page.fill('#salary', '18 LPA');
    await page.click('.form-card button[type="submit"]');

    await expect(page).toHaveURL(new RegExp(`/view\\.php\\?id=${id}`));
    await expect(page.locator('.hero-card .badge', { hasText: 'Interview' })).toBeVisible();
    await expect(page.locator('.detail-value', { hasText: '18 LPA' })).toBeVisible();
  });

  test('the edit form is filled in with the current values', async ({ page }) => {
    const company = uniqueName('Prefilled Co');
    const id = await addApplication(page, {
      company,
      role: 'DevOps Engineer',
      location: 'Pune, Maharashtra',
      salary: '14 LPA',
    });

    await page.goto(`/edit.php?id=${id}`);
    await expect(page.locator('#company')).toHaveValue(company);
    await expect(page.locator('#role')).toHaveValue('DevOps Engineer');
    await expect(page.locator('#location')).toHaveValue('Pune, Maharashtra');
    await expect(page.locator('#salary')).toHaveValue('14 LPA');
  });

  test('an application can be deleted after the confirmation', async ({ page }) => {
    const company = uniqueName('Deletable Co');
    const id = await addApplication(page, { company, role: 'Mobile Developer' });

    await page.goto(`/view.php?id=${id}`);
    await page.click('.danger-zone button[type="submit"]');

    // The confirmation dialog asks first.
    await expect(page.locator('#confirmDialog')).toBeVisible();
    await expect(page.locator('#confirmMessage')).toContainText('cannot be undone');

    await page.click('[data-confirm-accept]');

    // Back on the list, and the record is gone.
    await expect(page).toHaveURL(/\/applications\.php/);
    await expect(page.locator('.alert-success')).toContainText('deleted');
    await expect(page.locator('.company-name', { hasText: company })).toHaveCount(0);
  });

  test('the confirmation can be cancelled and nothing is deleted', async ({ page }) => {
    const company = uniqueName('Kept Co');
    const id = await addApplication(page, { company, role: 'Security Engineer' });

    await page.goto(`/view.php?id=${id}`);
    await page.click('.danger-zone button[type="submit"]');
    await expect(page.locator('#confirmDialog')).toBeVisible();

    await page.click('[data-confirm-cancel]');
    await expect(page.locator('#confirmDialog')).toBeHidden();

    // The record is still there.
    await page.goto(`/view.php?id=${id}`);
    await expect(page.locator('.detail-value', { hasText: company })).toBeVisible();
  });

  test('an unknown id gives the 404 page', async ({ page }) => {
    const response = await page.goto('/view.php?id=99999999');

    expect(response.status()).toBe(404);
    await expect(page.locator('h1')).toContainText('404');
  });
});
