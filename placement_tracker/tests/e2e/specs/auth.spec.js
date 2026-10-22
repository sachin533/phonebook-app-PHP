// @ts-check
const { test, expect } = require('@playwright/test');
const { ACCOUNT, signIn } = require('../helpers');

test.describe('Signing in and out', () => {
  test('a guest is sent to the login page', async ({ page }) => {
    await page.goto('/dashboard.php');

    await expect(page).toHaveURL(/\/login\.php/);
    await expect(page.locator('h1.auth-aside, .auth-aside h1')).toBeVisible();
    await expect(page.locator('#identifier')).toBeVisible();
  });

  test('the login page shows the sign in form', async ({ page }) => {
    await page.goto('/login.php');

    await expect(page.locator('h2', { hasText: 'Welcome back' })).toBeVisible();
    await expect(page.locator('#identifier')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toHaveText('Sign in');
  });

  test('a wrong password is refused with a message', async ({ page }) => {
    await page.goto('/login.php');
    await page.fill('#identifier', ACCOUNT.email);
    await page.fill('#password', 'this-is-not-the-password');
    await page.click('.auth-card button[type="submit"]');

    await expect(page.locator('.alert-error')).toBeVisible();
    await expect(page.locator('.alert-error')).toContainText('not right');
    await expect(page).toHaveURL(/\/login\.php/);
  });

  test('an unknown account is refused with the same message', async ({ page }) => {
    await page.goto('/login.php');
    await page.fill('#identifier', 'nobody@example.com');
    await page.fill('#password', 'whatever-9');
    await page.click('.auth-card button[type="submit"]');

    await expect(page.locator('.alert-error')).toContainText('not right');
  });

  test('the right password signs the person in', async ({ page }) => {
    await signIn(page);

    await expect(page).toHaveURL(/\/dashboard\.php/);
    await expect(page.locator('.sidebar-user-name')).toHaveText('Playwright Tester');
    await expect(page.locator('.flash-message, .alert-success')).toBeVisible();
  });

  test('the username works as well as the email', async ({ page }) => {
    await page.goto('/login.php');
    await page.fill('#identifier', ACCOUNT.username);
    await page.fill('#password', ACCOUNT.password);
    await page.click('.auth-card button[type="submit"]');

    await expect(page).toHaveURL(/\/dashboard\.php/);
  });

  test('the pages are protected once signed out', async ({ page }) => {
    await signIn(page);
    await page.goto('/applications.php');
    await expect(page).toHaveURL(/\/applications\.php/);

    // Sign out through the profile menu.
    await page.click('#userMenu [data-menu-toggle]');
    await page.click('#userMenu button[type="submit"]');
    await expect(page).toHaveURL(/\/login\.php/);

    // The page is closed again.
    await page.goto('/dashboard.php');
    await expect(page).toHaveURL(/\/login\.php/);
  });

  test('the session cookie is marked HttpOnly', async ({ page }) => {
    await signIn(page);

    const cookies = await page.context().cookies();
    const session = cookies.find((c) => c.name === 'PHPSESSID');

    expect(session, 'the session cookie should exist').toBeTruthy();
    expect(session.httpOnly).toBe(true);
    expect(session.sameSite).toBe('Lax');
  });
});
