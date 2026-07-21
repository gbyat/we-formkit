/**
 * Compile .l10n.php translation files from PO catalogs (WordPress 6.5+).
 *
 * Core prefers .l10n.php over .mo when both exist (OPcache-friendly).
 * @see https://make.wordpress.org/core/2024/02/27/i18n-improvements-6-5-performant-translations/
 * @see https://github.com/swissspidy/performant-translations
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
	runWp(['i18n', 'make-php', languagesDir]);
	console.log(`PHP translation files (.l10n.php) updated in: ${languagesDir}`);
} catch (error) {
	console.error('WP-CLI PHP translation build failed.');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
