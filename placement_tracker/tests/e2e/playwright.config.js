// @ts-check
const { defineConfig, devices } = require('@playwright/test');
const path = require('path');

// The root of the PHP project, one level up from tests/e2e.
const projectRoot = path.resolve(__dirname, '..', '..');

/**
 * The tests run against a server of their own, on their own port, against
 * the test database. That keeps them away from the real site and away from
 * the real data.
 *
 *   E2E_BASE_URL   the address of the server (default: the one below)
 *   E2E_DB_PORT    the MySQL port for the test database
 *   E2E_DB_NAME    the test database
 *   PHP_BIN        the php executable
 */
const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8210';
const phpBin = process.env.PHP_BIN || 'php';

module.exports = defineConfig({
  testDir: './specs',
  outputDir: './test-results',

  // The PHP development server handles one request at a time, so the tests
  // have to run one after another.
  workers: 1,
  fullyParallel: false,

  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  timeout: 30000,
  expect: { timeout: 10000 },

  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'test-results/results.json' }],
  ],

  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    actionTimeout: 10000,
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // Start the server for the tests, unless one is already running.
  webServer: {
    command: `"${phpBin}" -S 127.0.0.1:8210 -t "${projectRoot}"`,
    url: `${baseURL}/login.php`,
    cwd: projectRoot,
    reuseExistingServer: true,
    timeout: 30000,
    env: {
      DB_PORT: process.env.E2E_DB_PORT || '3307',
      DB_NAME: process.env.E2E_DB_NAME || 'placement_tracker_test',
    },
  },

  globalSetup: require.resolve('./global-setup.js'),
});
