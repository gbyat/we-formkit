/**
 * Compile MO files from PO catalogs in languages/.
 */

const fs = require('fs');
const path = require('path');
const { rootDir } = require('./load-config');
const { runWp } = require('./wp-cli');

const languagesDir = path.join(rootDir, 'languages');

if (!fs.existsSync(languagesDir)) {
	fs.mkdirSync(languagesDir, { recursive: true });
}

try {
	runWp(['i18n', 'make-mo', languagesDir]);
	console.log(`MO files updated in: ${languagesDir}`);
} catch (error) {
	console.error('WP-CLI MO build failed.');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
