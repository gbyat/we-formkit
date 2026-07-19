#!/usr/bin/env node
/**
 * Duplicate locale JSON files for additional script path hashes used by WordPress.
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { loadConfig, rootDir } = require('./load-config');

const config = loadConfig();
const slug = String(config.slug);
const domain = String(config.textDomain || slug);
const languagesDir = path.join(rootDir, 'languages');

const scriptPaths = [
	`${slug}/build/frontend.js`,
	`${slug}/build/index.js`,
	'build/frontend.js',
	'build/index.js',
	`plugins/${slug}/build/frontend.js`,
	`plugins/${slug}/build/index.js`,
	`/plugins/${slug}/build/frontend.js`,
	`/plugins/${slug}/build/index.js`,
];

function md5(value) {
	return crypto.createHash('md5').update(value).digest('hex');
}

/**
 * @param {string} locale
 * @return {string}
 */
function resolveSourceJson(locale) {
	const baseJson = path.join(languagesDir, `${domain}-${locale}.json`);
	if (fs.existsSync(baseJson)) {
		return fs.readFileSync(baseJson, 'utf8');
	}

	const hashedPattern = new RegExp(
		`^${domain.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}-${locale.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}-[a-f0-9]{32}\\.json$`
	);
	const hashed = fs
		.readdirSync(languagesDir)
		.filter((name) => hashedPattern.test(name))
		.sort();

	if (0 === hashed.length) {
		return '';
	}

	return fs.readFileSync(path.join(languagesDir, hashed[0]), 'utf8');
}

if (!fs.existsSync(languagesDir)) {
	process.exit(0);
}

const locales = Array.isArray(config.locales) ? config.locales : [];
let created = 0;

locales.forEach((locale) => {
	const json = resolveSourceJson(locale);
	if ('' === json) {
		console.warn(`Skip ${locale}: no JSON source found`);
		return;
	}

	const baseJson = path.join(languagesDir, `${domain}-${locale}.json`);
	fs.writeFileSync(baseJson, json);

	scriptPaths.forEach((relPath) => {
		const target = path.join(languagesDir, `${domain}-${locale}-${md5(relPath)}.json`);
		fs.writeFileSync(target, json);
		created += 1;
	});
});

console.log(`Script-path JSON duplicates: ${created} file(s).`);
