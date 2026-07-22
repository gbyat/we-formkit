/**
 * Create missing PO files from the POT template for configured locales.
 */

const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');

const config = loadConfig();
const languagesDir = path.join(rootDir, 'languages');
const slug = String(config.slug);
const potFile = path.join(languagesDir, `${slug}.pot`);
const locales = Array.isArray(config.locales) ? config.locales : ['de_DE'];

if (!fs.existsSync(potFile)) {
	console.error(`POT file not found: ${potFile}`);
	console.error('Run "npm run pot" first.');
	process.exit(1);
}

let created = 0;

locales.forEach((locale) => {
	const poFile = path.join(languagesDir, `${slug}-${locale}.po`);

	if (fs.existsSync(poFile)) {
		return;
	}

	let content = fs.readFileSync(potFile, 'utf8');
	content = content.replace(
		/^"POT-Creation-Date:.*$/m,
		'"PO-Revision-Date: ' + new Date().toISOString().replace(/\.\d{3}Z$/, '+00:00') + '\\n"'
	);

	if (!content.includes(`"Language: ${locale}\\n"`)) {
		content = content.replace(
			'"Content-Transfer-Encoding: 8bit\\n"',
			'"Content-Transfer-Encoding: 8bit\\n"\n' +
				`"Language: ${locale}\\n"\n` +
				'"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n' +
				'"Language-Team: webentwicklerin, Gabriele Laesser\\n"'
		);
	}

	fs.writeFileSync(poFile, content, 'utf8');
	created += 1;
	console.log(`Created PO file: ${poFile}`);
});

if (0 === created) {
	console.log('All configured PO files already exist.');
}
