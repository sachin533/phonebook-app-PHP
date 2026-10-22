// @ts-check
const { execFileSync } = require('child_process');
const path = require('path');

const projectRoot = path.resolve(__dirname, '..', '..');
const phpBin = process.env.PHP_BIN || 'php';
const script = path.join(projectRoot, 'tests', 'e2e', 'user.php');

/**
 * Run db\e2e\user.php. The tests use it to set up and clear the records
 * quickly, instead of filling a form hundreds of times.
 *
 * @param {string[]} args
 * @returns {string}
 */
function runUserScript(args) {
  return execFileSync(phpBin, [script, ...args], {
    cwd: projectRoot,
    encoding: 'utf8',
    env: {
      ...process.env,
      DB_PORT: process.env.E2E_DB_PORT || '3307',
      DB_NAME: process.env.E2E_DB_NAME || 'placement_tracker_test',
    },
  }).trim();
}

/** Remove every record that belongs to the test account. */
function clearRecords() {
  runUserScript(['clear']);
}

/**
 * Put records into the test account straight away.
 *
 * @param {number} count
 * @param {{prefix?: string, status?: string, role?: string, location?: string}} [options]
 */
function seedRecords(count, options = {}) {
  const args = ['seed', String(count)];
  for (const [key, value] of Object.entries(options)) {
    if (value !== undefined) {
      args.push(`--${key}=${value}`);
    }
  }
  runUserScript(args);
}

module.exports = { runUserScript, clearRecords, seedRecords };
