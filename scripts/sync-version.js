const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');

const config = loadConfig();
const packagePath = path.join(rootDir, 'package.json');
const pluginFilePath = path.join(rootDir, `${config.slug}.php`);
const readmeMdPath = path.join(rootDir, 'README.md');
const readmeTxtPath = path.join(rootDir, 'readme.txt');

const packageData = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
const version = packageData.version;
const versionConstant = String(config.versionConstant);

function updatePluginMainFile() {
	let content = fs.readFileSync(pluginFilePath, 'utf8');
	content = content.replace(/Version:\s*[0-9]+\.[0-9]+\.[0-9]+/, `Version: ${version}`);
	content = content.replace(
		new RegExp(`define\\(\\s*'${versionConstant}',\\s*'[^']*'\\s*\\);`),
		`define( '${versionConstant}', '${version}' );`
	);
	fs.writeFileSync(pluginFilePath, content, 'utf8');
}

function updateReadmeStableTag(readmePath, markdown) {
	if (!fs.existsSync(readmePath)) {
		return;
	}

	let content = fs.readFileSync(readmePath, 'utf8');
	if (markdown) {
		content = content.replace(/\*\*Stable tag:\*\*\s*[0-9]+\.[0-9]+\.[0-9]+/, `**Stable tag:** ${version}`);
	} else {
		content = content.replace(/Stable tag:\s*[0-9]+\.[0-9]+\.[0-9]+/, `Stable tag: ${version}`);
	}
	fs.writeFileSync(readmePath, content, 'utf8');
}

updatePluginMainFile();
updateReadmeStableTag(readmeMdPath, true);
updateReadmeStableTag(readmeTxtPath, false);

console.log(`Version synchronized to ${version}.`);
