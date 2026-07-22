/**
 * Download wp-cli.phar into the project root for local i18n commands.
 */

const fs = require('fs');
const path = require('path');
const https = require('https');

const {
	getLocalPharPath,
	resolvePhpBinary,
	resolveWpCli,
	runWp,
} = require('./wp-cli');

const WP_CLI_PHAR_URL = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar';

/**
 * @param {string} url
 * @param {string} targetPath
 * @return {Promise<void>}
 */
function downloadFile(url, targetPath) {
	return new Promise((resolve, reject) => {
		const request = https.get(url, (response) => {
			if (response.statusCode && response.statusCode >= 300 && response.statusCode < 400 && response.headers.location) {
				downloadFile(response.headers.location, targetPath).then(resolve).catch(reject);
				return;
			}

			if (200 !== response.statusCode) {
				reject(new Error(`Download failed with HTTP ${response.statusCode}`));
				return;
			}

			const chunks = [];
			response.on('data', (chunk) => chunks.push(chunk));
			response.on('end', () => {
				fs.writeFileSync(targetPath, Buffer.concat(chunks));
				resolve();
			});
		});

		request.on('error', reject);
	});
}

async function main() {
	const existing = resolveWpCli();
	if (existing) {
		console.log('WP-CLI already available.');
		return;
	}

	const phpBinary = resolvePhpBinary();
	if (!phpBinary) {
		console.error('PHP not found. Expected a binary such as:');
		console.error('C:\\Program Files\\PHP\\php-8-5\\php.exe');
		process.exit(1);
	}

	const pharPath = getLocalPharPath();

	console.log(`Downloading WP-CLI to ${pharPath}`);
	await downloadFile(WP_CLI_PHAR_URL, pharPath);

	const verify = resolveWpCli();
	if (!verify) {
		console.error('WP-CLI download completed but verification failed.');
		process.exit(1);
	}

	console.log(`WP-CLI ready via ${verify.command}`);
	if (verify.argsPrefix.length > 0) {
		runWp(['--info']);
	}
}

main().catch((error) => {
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
});
