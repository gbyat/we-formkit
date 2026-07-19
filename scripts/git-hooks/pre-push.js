#!/usr/bin/env node

/**
 * Git pre-push hook: run Sighthound before push.
 *
 * Install: npm run hooks:install
 * Bypass:  git push --no-verify
 */

const path = require('path');
const { spawnSync } = require('child_process');

const rootDir = path.resolve(__dirname, '..', '..');
const runner = path.join(rootDir, 'scripts', 'run-sighthound.js');

console.log('Pre-push: running Sighthound scan…');

const result = spawnSync(process.execPath, [runner], {
	cwd: rootDir,
	stdio: 'inherit',
});

if (result.error) {
	console.error(result.error.message);
	process.exit(1);
}

process.exit(typeof result.status === 'number' ? result.status : 1);
