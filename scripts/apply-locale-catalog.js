#!/usr/bin/env node
/**
 * Apply translated msgstr values from scripts/translations/<locale>.json to PO files.
 *
 * Handles escaped quotes inside msgid/msgstr (e.g. HTML href=\"%s\").
 */

const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');
const { readLf, writeLf } = require('./process');

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
 * @param {string} value
 * @return {string}
 */
function poUnescape(value) {
	return value
		.replace(/\\n/g, '\n')
		.replace(/\\"/g, '"')
		.replace(/\\\\/g, '\\');
}

/**
 * @param {string} poPath
 * @param {Record<string, string>} catalog
 * @return {number}
 */
function applyCatalog(poPath, catalog) {
	let content = readLf(poPath);
	let applied = 0;

	// Match single-line msgid + msgstr pairs; allow escaped quotes inside both.
	content = content.replace(
		/(^|\n)(msgid "((?:\\.|[^"\\])*)"\nmsgstr ")((?:\\.|[^"\\])*)(")/gm,
		(match, prefix, head, msgidRaw, _msgstrRaw, tail) => {
			const msgid = poUnescape(msgidRaw);

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

	writeLf(poPath, content);
	return applied;
}

function main() {
	if (!fs.existsSync(translationsDir)) {
		console.log('No scripts/translations directory; skipping catalog apply.');
		return;
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

		const catalog = JSON.parse(readLf(catalogPath));
		const applied = applyCatalog(poPath, catalog);
		total += applied;
		console.log(`Applied ${applied} translation(s) to ${path.basename(poPath)}`);
	});

	console.log(`Total translations applied: ${total}`);
}

main();
