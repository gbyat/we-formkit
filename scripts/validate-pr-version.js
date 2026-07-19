/**
 * Validate plugin header and version constant on pull requests.
 */

const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');

const config = loadConfig();
const pluginFilePath = path.join(rootDir, `${config.slug}.php`);
const versionConstant = String(config.versionConstant);
const content = fs.readFileSync(pluginFilePath, 'utf8');

if (!/Version:\s*\d+\.\d+\.\d+/.test(content)) {
	console.error('Missing or invalid plugin header version');
	process.exit(1);
}

if (!new RegExp(`${versionConstant}'\\s*,\\s*'\\d+\\.\\d+\\.\\d+'`).test(content)) {
	console.error(`Missing or invalid ${versionConstant} constant`);
	process.exit(1);
}

console.log('Plugin version declarations OK');
