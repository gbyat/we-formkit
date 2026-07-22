#!/usr/bin/env node
/**
 * Normalize translation catalog encoding and detect mojibake.
 *
 * Usage:
 *   node scripts/normalize-translation-encoding.js --write
 *   node scripts/normalize-translation-encoding.js --check
 */

const fs = require('fs');
const path = require('path');
const iconv = require('iconv-lite');
const { loadConfig, rootDir } = require('./load-config');

const config = loadConfig();
const slug = String(config.slug);
const domain = String(config.textDomain || slug);
const locales = Array.isArray(config.locales) ? config.locales : [];

const translationsDir = path.join(rootDir, 'scripts', 'translations');
const languagesDir = path.join(rootDir, 'languages');

const args = new Set(process.argv.slice(2));
const isWriteMode = args.has('--write');
const isCheckMode = args.has('--check');

/** @type {RegExp} */
const suspiciousPattern =
	/(ÔÇ|Ôå|├|┤|┬|┼|│|─|╚|╝|╔|╗|â€¦|â€™|â€œ|â€|Ã.|Â.|�)/g;

const directReplacementMap = new Map([
	['├╝', 'ü'],
	['├ñ', 'ä'],
	['├Â', 'ö'],
	['├ƒ', 'ß'],
	['├£', 'Ü'],
	['├ä', 'Ä'],
	['├û', 'Ö'],
	['ÔÇö', '—'],
	['ÔÇô', '–'],
	['ÔÇª', '…'],
	['ÔÇ×', '"'],
	['ÔÇ£', '"'],
	['ÔåÆ', '→'],
	['ÔÇ™', "'"],
]);

/**
 * @param {string} value
 * @return {number}
 */
function mojibakeScore(value) {
	let score = 0;
	const matches = value.match(suspiciousPattern);
	if (matches) {
		score += matches.length;
	}
	return score;
}

/**
 * @param {string} value
 * @param {string} sourceEncoding
 * @return {string}
 */
function decodeAsUtf8(value, sourceEncoding) {
	try {
		const encoded = iconv.encode(value, sourceEncoding);
		return iconv.decode(encoded, 'utf8');
	} catch (error) {
		return value;
	}
}

/**
 * @param {string} value
 * @return {string}
 */
function normalizeText(value) {
	if ('string' !== typeof value || 0 === mojibakeScore(value)) {
		return value;
	}

	let current = value;
	directReplacementMap.forEach((replacement, source) => {
		current = current.split(source).join(replacement);
	});

	for (let i = 0; i < 4; i += 1) {
		const candidates = [
			current,
			decodeAsUtf8(current, 'win1252'),
			decodeAsUtf8(current, 'latin1'),
			decodeAsUtf8(current, 'cp437'),
		];

		let best = current;
		let bestScore = mojibakeScore(current);

		candidates.forEach((candidate) => {
			const candidateScore = mojibakeScore(candidate);
			if (candidateScore < bestScore) {
				best = candidate;
				bestScore = candidateScore;
			}
		});

		if (best === current) {
			break;
		}
		current = best;
	}

	return current;
}

/**
 * @param {string} filePath
 * @return {number}
 */
function normalizeCatalogFile(filePath) {
	if (!fs.existsSync(filePath)) {
		return 0;
	}

	const raw = fs.readFileSync(filePath, 'utf8').replace(/^\uFEFF/, '');
	/** @type {Record<string, string>} */
	const catalog = JSON.parse(raw);
	/** @type {Record<string, string>} */
	const normalized = {};
	let changedEntries = 0;

	Object.entries(catalog).forEach(([key, value]) => {
		const nextKey = normalizeText(key);
		const nextValue = normalizeText(value);
		if (nextKey !== key || nextValue !== value) {
			changedEntries += 1;
		}
		normalized[nextKey] = nextValue;
	});

	if (changedEntries > 0) {
		fs.writeFileSync(filePath, `${JSON.stringify(normalized, null, 2)}\n`, 'utf8');
	}

	return changedEntries;
}

function collectJsonValueFindings(filePath) {
	if (!fs.existsSync(filePath)) {
		return [];
	}
	/** @type {Array<{line: number, snippet: string}>} */
	const findings = [];
	const raw = fs.readFileSync(filePath, 'utf8').replace(/^\uFEFF/, '');
	const catalog = JSON.parse(raw);

	Object.values(catalog).forEach((value) => {
		if ('string' !== typeof value) {
			return;
		}
		if (suspiciousPattern.test(value)) {
			findings.push({
				line: 0,
				snippet: value.slice(0, 180),
			});
		}
		suspiciousPattern.lastIndex = 0;
	});

	return findings;
}

function collectPoMsgstrFindings(filePath) {
	if (!fs.existsSync(filePath)) {
		return [];
	}

	const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);
	/** @type {Array<{line: number, snippet: string}>} */
	const findings = [];
	let inMsgstr = false;
	let buffer = '';
	let startLine = 0;

	const flush = () => {
		if ('' === buffer) {
			return;
		}
		if (suspiciousPattern.test(buffer)) {
			findings.push({
				line: startLine,
				snippet: buffer.slice(0, 180),
			});
		}
		suspiciousPattern.lastIndex = 0;
		buffer = '';
		startLine = 0;
	};

	lines.forEach((line, index) => {
		if (line.startsWith('msgstr ')) {
			flush();
			inMsgstr = true;
			startLine = index + 1;
			buffer = line.replace(/^msgstr\s+"/, '').replace(/"$/, '');
			return;
		}

		if (inMsgstr && /^\s*"/.test(line)) {
			buffer += line.trim().slice(1, -1);
			return;
		}

		if (inMsgstr && '' === line.trim()) {
			flush();
			inMsgstr = false;
		}
	});

	flush();
	return findings;
}

function runWriteMode() {
	let totalChanged = 0;

	locales.forEach((locale) => {
		const catalogPath = path.join(translationsDir, `${locale}.json`);
		const changed = normalizeCatalogFile(catalogPath);
		totalChanged += changed;
		console.log(`${locale}: normalized ${changed} catalog entr${1 === changed ? 'y' : 'ies'}.`);
	});

	console.log(`Encoding normalization finished. Changed entries: ${totalChanged}.`);
}

function runCheckMode() {
	/** @type {Array<string>} */
	const files = [];
	locales.forEach((locale) => {
		files.push(path.join(translationsDir, `${locale}.json`));
		files.push(path.join(languagesDir, `${domain}-${locale}.po`));
	});

	const languageJsonFiles = fs
		.readdirSync(languagesDir)
		.filter((file) => file.startsWith(`${domain}-`) && file.endsWith('.json'))
		.map((file) => path.join(languagesDir, file));
	files.push(...languageJsonFiles);

	/** @type {Array<{file: string, line: number, snippet: string}>} */
	const findings = [];
	files.forEach((filePath) => {
		const ext = path.extname(filePath).toLowerCase();
		const localFindings =
			'.po' === ext ? collectPoMsgstrFindings(filePath) : collectJsonValueFindings(filePath);
		localFindings.forEach((finding) => {
			findings.push({
				file: path.relative(rootDir, filePath),
				line: finding.line,
				snippet: finding.snippet,
			});
		});
	});

	if (0 === findings.length) {
		console.log('Encoding check passed. No suspicious mojibake sequences found.');
		return;
	}

	console.error('Encoding check failed. Suspicious mojibake sequences found:');
	findings.slice(0, 80).forEach((finding) => {
		console.error(`- ${finding.file}:${finding.line} ${finding.snippet}`);
	});
	if (findings.length > 80) {
		console.error(`... and ${findings.length - 80} more.`);
	}
	process.exit(1);
}

module.exports = {
	normalizeText,
	mojibakeScore,
	suspiciousPattern,
};

if (require.main === module) {
	if (!isWriteMode && !isCheckMode) {
		console.error('Missing mode. Use --write or --check.');
		process.exit(1);
	}
	if (isWriteMode) {
		runWriteMode();
	}
	if (isCheckMode) {
		runCheckMode();
	}
}
