#!/usr/bin/env node
/**
 * Apply translated msgstr values from scripts/translations/<locale>.json to PO files.
 */

const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');

const config = loadConfig();
const slug = String(config.slug);
const domain = String(config.textDomain || slug);
const languagesDir = path.join(rootDir, 'languages');
const translationsDir = path.join(rootDir, 'scripts', 'translations');

/**
 * @param {string} value
 * @return {string}
 */
function poEscape(value) {
	return value
		.replace(/\\/g, '\\\\')
		.replace(/"/g, '\\"')
		.replace(/\n/g, '\\n');
}

/**
 * @param {string} poPath
 * @param {Record<string, string>} catalog
 * @return {number}
 */
function applyCatalog(poPath, catalog) {
	let content = fs.readFileSync(poPath, 'utf8');
	let applied = 0;

	content = content.replace(
		/(^|\n)(msgid "((?:\\.|[^"\\])*)"\nmsgstr ")(.*?)(")/gm,
		(match, prefix, head, msgidRaw, msgstrRaw, tail) => {
			const msgid = msgidRaw
				.replace(/\\n/g, '\n')
				.replace(/\\"/g, '"')
				.replace(/\\\\/g, '\\');

			if (!Object.prototype.hasOwnProperty.call(catalog, msgid)) {
				return match;
			}

			const translation = catalog[msgid];
			if ('string' !== typeof translation || '' === translation) {
				return match;
			}

			applied += 1;
			return `${prefix}${head}${poEscape(translation)}${tail}`;
		}
	);

	fs.writeFileSync(poPath, content, 'utf8');
	return applied;
}

function main() {
	if (!fs.existsSync(translationsDir)) {
		console.error(`Missing translations directory: ${translationsDir}`);
		process.exit(1);
	}

	const locales = Array.isArray(config.locales) ? config.locales : [];
	let total = 0;

	locales.forEach((locale) => {
		const catalogPath = path.join(translationsDir, `${locale}.json`);
		const poPath = path.join(languagesDir, `${domain}-${locale}.po`);

		if (!fs.existsSync(catalogPath)) {
			console.warn(`Skip ${locale}: catalog not found (${catalogPath})`);
			return;
		}
		if (!fs.existsSync(poPath)) {
			console.warn(`Skip ${locale}: PO not found (${poPath})`);
			return;
		}

		const catalog = JSON.parse(fs.readFileSync(catalogPath, 'utf8').replace(/^\uFEFF/, ''));
		const applied = applyCatalog(poPath, catalog);
		total += applied;
		console.log(`Applied ${applied} translation(s) to ${path.basename(poPath)}`);
	});

	console.log(`Total translations applied: ${total}`);
}

main();
