/**
 * Merge new POT strings into existing PO files.
 */

const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');
const { runWp } = require('./wp-cli');

const config = loadConfig();
const languagesDir = path.join(rootDir, 'languages');
const slug = String(config.slug);
const potFile = path.join(languagesDir, `${slug}.pot`);

if (!fs.existsSync(potFile)) {
	console.error(`POT file not found: ${potFile}`);
	console.error('Run "npm run pot" first.');
	process.exit(1);
}

try {
	runWp(['i18n', 'update-po', potFile, languagesDir]);
	console.log(`PO files updated from: ${potFile}`);
} catch (error) {
	console.error('WP-CLI PO update failed.');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
