/**
 * Shared UTF-8 and gettext helpers for i18n scripts.
 */

const fs = require('fs');

/** Common mojibake when UTF-8 text was saved or read with the wrong encoding. */
const MOJIBAKE_REPLACEMENTS = [
	['ÔÇª', '…'],
	['ÔÇö', '—'],
	['ÔåÆ', '→'],
	['ÔÇÖ', '’'],
	['ÔÇœ', '“'],
	['ÔÇ\x9d', '”'],
	['ÔÇ\x9c', '“'],
	['Ã¤', 'ä'],
	['Ã¶', 'ö'],
	['Ã¼', 'ü'],
	['Ã„', 'Ä'],
	['Ã–', 'Ö'],
	['Ãœ', 'Ü'],
	['ÃŸ', 'ß'],
];

/**
 * @param {string} value
 * @return {string}
 */
function stripBom(value) {
	return value.replace(/^\uFEFF/, '');
}

/**
 * @param {string} filePath
 * @return {string}
 */
function readUtf8(filePath) {
	return stripBom(fs.readFileSync(filePath, 'utf8'));
}

/**
 * @param {string} filePath
 * @param {string} content
 * @return {void}
 */
function writeUtf8(filePath, content) {
	fs.writeFileSync(filePath, content, { encoding: 'utf8' });
}

/**
 * @param {string} value
 * @return {string}
 */
function fixMojibake(value) {
	let fixed = value;
	MOJIBAKE_REPLACEMENTS.forEach(([from, to]) => {
		fixed = fixed.split(from).join(to);
	});
	return fixed.normalize('NFC');
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
 * @param {string} potPath
 * @return {string[]}
 */
function extractMsgidsFromPot(potPath) {
	const content = readUtf8(potPath);
	const msgids = [];
	let msgid = '';
	let collecting = false;

	for (const line of content.split(/\r?\n/)) {
		if (line.startsWith('msgid ')) {
			msgid = line.slice(6).trim();
			if (msgid.startsWith('"') && msgid.endsWith('"')) {
				msgid = msgid.slice(1, -1);
			}
			collecting = true;
			continue;
		}
		if (line.startsWith('msgstr ') && collecting) {
			if (msgid !== '') {
				msgids.push(poUnescape(msgid));
			}
			msgid = '';
			collecting = false;
			continue;
		}
		if (collecting && /^\s*"/.test(line)) {
			msgid += line.trim().slice(1, -1);
		}
	}

	return msgids;
}

/**
 * Build a lookup map that tolerates mojibake key drift in JSON catalogs.
 *
 * @param {Record<string, string>} catalog
 * @return {Map<string, string>}
 */
function buildCatalogLookup(catalog) {
	/** @type {Map<string, string>} */
	const lookup = new Map();

	Object.entries(catalog).forEach(([key, value]) => {
		const normalizedKey = fixMojibake(key).normalize('NFC');
		lookup.set(normalizedKey, value);
	});

	return lookup;
}

/**
 * @param {Map<string, string>} lookup
 * @param {string} msgid
 * @return {string|undefined}
 */
function findCatalogTranslation(lookup, msgid) {
	const normalized = fixMojibake(msgid).normalize('NFC');
	return lookup.get(normalized);
}

module.exports = {
	MOJIBAKE_REPLACEMENTS,
	stripBom,
	readUtf8,
	writeUtf8,
	fixMojibake,
	poUnescape,
	poEscape,
	extractMsgidsFromPot,
	buildCatalogLookup,
	findCatalogTranslation,
};
