#!/usr/bin/env node
/**
 * Export msgid => msgstr pairs from an existing PO file.
 *
 * Usage:
 *   node scripts/export-po-catalog.js languages/<domain>-de_DE.po
 *   node scripts/export-po-catalog.js languages/<domain>-de_DE.po scripts/translations/de_DE.json
 *
 * Prefer writing to a file path (second argument) instead of shell redirects so
 * UTF-8 stays intact (PowerShell redirects can add a BOM or corrupt encoding).
 */

const fs = require('fs');

const poPath = process.argv[2];
const outputPath = process.argv[3];

if (!poPath || !fs.existsSync(poPath)) {
	console.error('Usage: node scripts/export-po-catalog.js <path-to-po> [output-json-path]');
	process.exit(1);
}

const content = fs.readFileSync(poPath, 'utf8').replace(/^\uFEFF/, '');
/** @type {Record<string, string>} */
const catalog = {};
let msgid = null;
let msgstr = null;
let state = null;

for (const line of content.split(/\r?\n/)) {
	if (line.startsWith('msgid ')) {
		if (msgid !== null && msgstr !== null && msgid !== '') {
			catalog[msgid] = msgstr;
		}
		msgid = line.slice(6).trim().replace(/^"/, '').replace(/"$/, '');
		msgstr = '';
		state = 'msgid';
		continue;
	}
	if (line.startsWith('msgstr ')) {
		msgstr = line.slice(7).trim().replace(/^"/, '').replace(/"$/, '');
		state = 'msgstr';
		continue;
	}
	if (/^\s*"/.test(line)) {
		const chunk = line.trim().slice(1, -1);
		if ('msgid' === state) {
			msgid += chunk;
		}
		if ('msgstr' === state) {
			msgstr += chunk;
		}
		continue;
	}
	if ('' === line.trim() && msgid !== null && msgstr !== null) {
		if (msgid !== '') {
			catalog[msgid] = msgstr;
		}
		msgid = null;
		msgstr = null;
		state = null;
	}
}

if (msgid !== null && msgstr !== null && msgid !== '') {
	catalog[msgid] = msgstr;
}

const unescape = (value) =>
	value.replace(/\\n/g, '\n').replace(/\\"/g, '"').replace(/\\\\/g, '\\');

/** @type {Record<string, string>} */
const output = {};
Object.entries(catalog).forEach(([key, value]) => {
	output[unescape(key)] = unescape(value);
});

const json = `${JSON.stringify(output, null, 2)}\n`;

if (outputPath) {
	fs.mkdirSync(require('path').dirname(outputPath), { recursive: true });
	fs.writeFileSync(outputPath, json, 'utf8');
	console.log(`Wrote ${Object.keys(output).length} entries to ${outputPath}`);
} else {
	process.stdout.write(json);
}
