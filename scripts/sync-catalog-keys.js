#!/usr/bin/env node
/**
 * Re-key locale catalogs so msgid keys match the current POT file exactly.
 *
 * Usage:
 *   node scripts/sync-catalog-keys.js
 *   node scripts/sync-catalog-keys.js --dry-run
 */

const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');
const { normalizeText } = require('./normalize-translation-encoding');

const config = loadConfig();
const domain = String(config.textDomain || config.slug);
const locales = Array.isArray(config.locales) ? config.locales : [];
const translationsDir = path.join(rootDir, 'scripts', 'translations');
const potPath = path.join(rootDir, 'languages', `${domain}.pot`);

const isDryRun = process.argv.includes('--dry-run');

/**
 * @param {string} filePath
 * @return {string[]}
 */
function extractPotMsgids(filePath) {
	if (!fs.existsSync(filePath)) {
		console.error(`POT file not found: ${filePath}`);
		process.exit(1);
	}

	const content = fs.readFileSync(filePath, 'utf8').replace(/^\uFEFF/, '');
	/** @type {string[]} */
	const msgids = [];
	let msgid = null;
	let state = null;

	const flushMsgid = () => {
		if (null === msgid) {
			return;
		}
		const unescaped = msgid
			.replace(/\\n/g, '\n')
			.replace(/\\"/g, '"')
			.replace(/\\\\/g, '\\');
		if ('' !== unescaped) {
			msgids.push(unescaped);
		}
		msgid = null;
		state = null;
	};

	for (const line of content.split(/\r?\n/)) {
		if (line.startsWith('msgid ')) {
			flushMsgid();
			msgid = line.slice(6).trim().replace(/^"/, '').replace(/"$/, '');
			state = 'msgid';
			continue;
		}
		if (line.startsWith('msgstr ')) {
			flushMsgid();
			state = 'msgstr';
			continue;
		}
		if (/^\s*"/.test(line) && 'msgid' === state) {
			msgid += line.trim().slice(1, -1);
			continue;
		}
		if ('' === line.trim()) {
			flushMsgid();
		}
	}
	flushMsgid();

	return msgids;
}

/**
 * @param {Record<string, string>} catalog
 * @param {string} msgid
 * @return {string|undefined}
 */
function lookupTranslation(catalog, msgid) {
	if (Object.prototype.hasOwnProperty.call(catalog, msgid)) {
		return catalog[msgid];
	}

	const normalizedMsgid = normalizeText(msgid);
	for (const [key, value] of Object.entries(catalog)) {
		if (key === msgid || normalizeText(key) === normalizedMsgid) {
			return value;
		}
	}

	return undefined;
}

/**
 * @param {string} locale
 * @param {string[]} potMsgids
 * @return {{ matched: number, missing: number, stale: number }}
 */
function syncLocaleCatalog(locale, potMsgids) {
	const catalogPath = path.join(translationsDir, `${locale}.json`);
	if (!fs.existsSync(catalogPath)) {
		console.warn(`Skip ${locale}: catalog not found (${catalogPath})`);
		return { matched: 0, missing: 0, stale: 0 };
	}

	const raw = fs.readFileSync(catalogPath, 'utf8').replace(/^\uFEFF/, '');
	/** @type {Record<string, string>} */
	const catalog = JSON.parse(raw);
	/** @type {Record<string, string>} */
	const synced = {};
	let matched = 0;
	let missing = 0;

	potMsgids.forEach((msgid) => {
		const translation = lookupTranslation(catalog, msgid);
		if ('string' === typeof translation && '' !== translation) {
			synced[msgid] = translation;
			matched += 1;
		} else {
			synced[msgid] = '';
			missing += 1;
		}
	});

	const stale = Object.keys(catalog).filter((key) => !potMsgids.includes(key)).length;

	if (!isDryRun) {
		fs.writeFileSync(catalogPath, `${JSON.stringify(synced, null, 2)}\n`, 'utf8');
	}

	console.log(
		`${locale}: ${matched} matched, ${missing} empty/missing, ${stale} stale key(s) removed${isDryRun ? ' (dry run)' : ''}.`
	);

	return { matched, missing, stale };
}

function main() {
	if (!fs.existsSync(translationsDir)) {
		console.log('No scripts/translations directory; skipping catalog key sync.');
		return;
	}

	const potMsgids = extractPotMsgids(potPath);
	let totalMatched = 0;
	let totalMissing = 0;

	locales.forEach((locale) => {
		const result = syncLocaleCatalog(locale, potMsgids);
		totalMatched += result.matched;
		totalMissing += result.missing;
	});

	console.log(
		`Catalog key sync finished. POT strings: ${potMsgids.length}, matched translations: ${totalMatched}, missing/empty: ${totalMissing}.`
	);
}

main();
