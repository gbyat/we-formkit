/**
 * Validate release tag against plugin.config.json and version files.
 *
 * Usage:
 *   node scripts/validate-release.js <version>
 *
 * Intended for GitHub Actions release workflow only.
 */

const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');

const version = process.argv[2];

if (!version) {
	console.error('Version argument required.');
	process.exit(1);
}

const config = loadConfig();
const packagePath = path.join(rootDir, 'package.json');
const pluginFilePath = path.join(rootDir, `${config.slug}.php`);
const changelogPath = path.join(rootDir, 'CHANGELOG.md');
const versionConstant = String(config.versionConstant);

const pkg = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
if (pkg.version !== version) {
	console.error(`package.json version (${pkg.version}) does not match tag (${version})`);
	process.exit(1);
}

const plugin = fs.readFileSync(pluginFilePath, 'utf8');
if (!plugin.includes(`Version: ${version}`)) {
	console.error('Plugin header version does not match tag');
	process.exit(1);
}

if (!plugin.includes(`${versionConstant}', '${version}`)) {
	console.error(`${versionConstant} constant does not match tag`);
	process.exit(1);
}

if (!fs.existsSync(changelogPath) || !new RegExp(`^## \\[${version.replace(/\./g, '\\.')}\\] - `, 'm').test(fs.readFileSync(changelogPath, 'utf8'))) {
	console.error(`Missing CHANGELOG entry for version ${version}`);
	process.exit(1);
}

console.log(`Release validation passed for v${version}.`);
