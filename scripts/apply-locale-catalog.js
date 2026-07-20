#!/usr/bin/env node
/**
 * Apply translated msgstr values from scripts/translations/<locale>.json to PO files.
 * Supports single-line and multiline msgid/msgstr entries.
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
 * @param {string} value
 * @return {string}
 */
function unescapePo(value) {
	return value
		.replace(/\\n/g, '\n')
		.replace(/\\"/g, '"')
		.replace(/\\\\/g, '\\');
}

/**
 * Format msgstr for PO output (single line, or "" + continuation lines for multiline).
 *
 * @param {string} value
 * @return {string}
 */
function formatMsgstr(value) {
	const escaped = poEscape(value);
	if (!escaped.includes('\\n')) {
		return `msgstr "${escaped}"`;
	}

	const parts = escaped.split('\\n');
	const lines = ['msgstr ""'];
	parts.forEach((part, index) => {
		const isLast = index === parts.length - 1;
		if (isLast && '' === part) {
			return;
		}
		lines.push(`"${part}${isLast ? '' : '\\n'}"`);
	});
	return lines.join('\n');
}

/**
 * @param {string} poPath
 * @param {Record<string, string>} catalog
 * @return {number}
 */
function applyCatalog(poPath, catalog) {
	const content = fs.readFileSync(poPath, 'utf8').replace(/^\uFEFF/, '');
	const lines = content.split(/\r?\n/);
	/** @type {string[]} */
	const out = [];
	let applied = 0;

	let i = 0;
	while (i < lines.length) {
		const line = lines[i];

		if (!line.startsWith('msgid ')) {
			out.push(line);
			i += 1;
			continue;
		}

		/** @type {string[]} */
		const msgidLines = [line];
		i += 1;
		while (i < lines.length && /^\s*"/.test(lines[i])) {
			msgidLines.push(lines[i]);
			i += 1;
		}

		/** @type {string[]} */
		const msgstrLines = [];
		if (i < lines.length && lines[i].startsWith('msgstr ')) {
			msgstrLines.push(lines[i]);
			i += 1;
			while (i < lines.length && /^\s*"/.test(lines[i])) {
				msgstrLines.push(lines[i]);
				i += 1;
			}
		}

		let msgidRaw = '';
		msgidLines.forEach((l, idx) => {
			if (0 === idx) {
				msgidRaw += l.slice(6).trim().replace(/^"/, '').replace(/"$/, '');
			} else {
				msgidRaw += l.trim().slice(1, -1);
			}
		});
		const msgid = unescapePo(msgidRaw);

		const translation =
			'' !== msgid && Object.prototype.hasOwnProperty.call(catalog, msgid)
				? catalog[msgid]
				: undefined;

		out.push(...msgidLines);

		if (
			'string' === typeof translation &&
			'' !== translation &&
			msgstrLines.length > 0
		) {
			out.push(...formatMsgstr(translation).split('\n'));
			applied += 1;
		} else if (msgstrLines.length > 0) {
			out.push(...msgstrLines);
		}
	}

	fs.writeFileSync(poPath, `${out.join('\n')}`, 'utf8');
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

		const catalog = JSON.parse(
			fs.readFileSync(catalogPath, 'utf8').replace(/^\uFEFF/, '')
		);
		const applied = applyCatalog(poPath, catalog);
		total += applied;
		console.log(`Applied ${applied} translation(s) to ${path.basename(poPath)}`);
	});

	console.log(`Total translations applied: ${total}`);
}

main();
