#!/usr/bin/env node

/**
 * Install project git hooks (pre-push → Sighthound).
 *
 * Usage:
 *   node scripts/install-git-hooks.js
 *   npm run hooks:install
 */

const fs = require('fs');
const path = require('path');

const rootDir = process.cwd();
const gitDir = path.join(rootDir, '.git');
const hooksDir = path.join(gitDir, 'hooks');
const hookName = 'pre-push';
const hookPath = path.join(hooksDir, hookName);

const hookBody = `#!/bin/sh
# Installed by scripts/install-git-hooks.js
set -e
cd "$(git rev-parse --show-toplevel)"
node scripts/git-hooks/pre-push.js
`;

function main() {
	if (!fs.existsSync(gitDir)) {
		console.error('Not a git repository (.git missing).');
		process.exit(1);
	}

	fs.mkdirSync(hooksDir, { recursive: true });
	fs.writeFileSync(hookPath, hookBody.replace(/\r\n/g, '\n'), 'utf8');

	if ('win32' !== process.platform) {
		fs.chmodSync(hookPath, 0o755);
	}

	console.log(`Installed git hook: ${hookName}`);
	console.log('Runs Sighthound before push. Bypass with: git push --no-verify');
	console.log('');
	console.log('Requires Sighthound on PATH (see README → Sighthound).');
}

main();
