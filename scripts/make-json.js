/**
 * Build hashed JS translation JSON files from existing PO files.
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
	runWp(['i18n', 'make-json', languagesDir, '--no-purge', '--extensions=js,jsx']);
	console.log(`JSON translation files updated in: ${languagesDir}`);

	const aliasScript = path.join(__dirname, 'json-handle-aliases.js');
	if (fs.existsSync(aliasScript)) {
		require('./json-handle-aliases');
	}
} catch (error) {
	console.error('WP-CLI JSON build failed.');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
