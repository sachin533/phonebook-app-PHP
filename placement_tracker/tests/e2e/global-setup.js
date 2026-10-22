// @ts-check
const { execFileSync } = require('child_process');
const path = require('path');

const projectRoot = path.resolve(__dirname, '..', '..');
const phpBin = process.env.PHP_BIN || 'php';

/**
 * Before the first test runs, make sure the account the tests sign in with
 * exists and starts with an empty list.
 */
module.exports = async () => {
  const script = path.join(projectRoot, 'tests', 'e2e', 'user.php');

  const output = execFileSync(phpBin, [script, 'create'], {
    cwd: projectRoot,
    encoding: 'utf8',
    env: {
      ...process.env,
      DB_PORT: process.env.E2E_DB_PORT || '3307',
      DB_NAME: process.env.E2E_DB_NAME || 'placement_tracker_test',
    },
  });

  console.log(`  setup: ${output.trim()}`);
};
