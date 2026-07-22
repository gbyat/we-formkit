#!/usr/bin/env node

/**
 * Convert Sighthound JSON output to SARIF 2.1.0 for GitHub Code Scanning.
 *
 * Sighthound 1.0 supports text | json | csv only — not native SARIF.
 * Passing --output-format sarif falls back to text (banner starts with "sighthound"),
 * which breaks github/codeql-action/upload-sarif.
 *
 * Usage:
 *   node scripts/sighthound-json-to-sarif.js input.json output.sarif
 *   sighthound -o json includes | node scripts/sighthound-json-to-sarif.js - out.sarif
 */

const fs = require('fs');
const path = require('path');

/**
 * @param {string} severity
 * @return {'error'|'warning'|'note'|'none'}
 */
function levelFromSeverity(severity) {
	const s = String(severity || '').toLowerCase();
	if (s === 'critical' || s === 'high') {
		return 'error';
	}
	if (s === 'medium' || s === 'moderate') {
		return 'warning';
	}
	if (s === 'low' || s === 'info' || s === 'informational') {
		return 'note';
	}
	return 'warning';
}

/**
 * @param {string} filePath
 * @return {string}
 */
function toUri(filePath) {
	return String(filePath || '')
		.replace(/\\/g, '/')
		.replace(/^\.\//, '');
}

/**
 * @param {unknown} raw
 * @return {object[]}
 */
function asFindings(raw) {
	if (Array.isArray(raw)) {
		return raw.filter((item) => item && typeof item === 'object');
	}
	if (raw && typeof raw === 'object' && Array.isArray(/** @type {{findings?: unknown}} */ (raw).findings)) {
		return /** @type {{findings: object[]}} */ (raw).findings;
	}
	return [];
}

/**
 * @param {object[]} findings
 * @return {object}
 */
function toSarif(findings) {
	/** @type {Map<string, object>} */
	const rules = new Map();
	/** @type {object[]} */
	const results = [];

	findings.forEach((finding, index) => {
		const f = /** @type {Record<string, unknown>} */ (finding);
		const ruleId =
			String(f.cwe_id || f.rule_id || f.finding_type || 'sighthound-finding')
				.trim()
				.replace(/\s+/g, '-')
				.toLowerCase() || `sighthound-${index + 1}`;
		const name = String(f.finding_type || ruleId);
		const description = String(f.description || name);

		if (!rules.has(ruleId)) {
			rules.set(ruleId, {
				id: ruleId,
				name,
				shortDescription: { text: name },
				fullDescription: { text: description },
				helpUri: 'https://corgea.com/sighthound',
				properties: {
					tags: Array.isArray(f.tags) ? f.tags.map(String) : [],
					precision: String(f.confidence || 'medium').toLowerCase(),
				},
			});
		}

		const startLine = Math.max(1, parseInt(String(f.line || 1), 10) || 1);
		const startColumn = Math.max(1, parseInt(String(f.column || 1), 10) || 1);
		const endLine = Math.max(startLine, parseInt(String(f.end_line || startLine), 10) || startLine);
		const endColumn = Math.max(1, parseInt(String(f.end_column || startColumn), 10) || startColumn);
		const messageBits = [name];
		if (description && description !== name) {
			messageBits.push(description);
		}
		if (f.snippet) {
			messageBits.push(String(f.snippet).slice(0, 400));
		}

		results.push({
			ruleId,
			level: levelFromSeverity(String(f.severity || '')),
			message: { text: messageBits.join('\n\n') },
			locations: [
				{
					physicalLocation: {
						artifactLocation: { uri: toUri(String(f.file || '')) },
						region: {
							startLine,
							startColumn,
							endLine,
							endColumn,
						},
					},
				},
			],
		});
	});

	return {
		$schema: 'https://json.schemastore.org/sarif-2.1.0.json',
		version: '2.1.0',
		runs: [
			{
				tool: {
					driver: {
						name: 'Sighthound',
						informationUri: 'https://corgea.com/sighthound',
						rules: Array.from(rules.values()),
					},
				},
				results,
			},
		],
	};
}

function main() {
	const inputPath = process.argv[2];
	const outputPath = process.argv[3];

	if (!inputPath || !outputPath) {
		console.error('Usage: node scripts/sighthound-json-to-sarif.js <input.json|-> <output.sarif>');
		process.exit(2);
	}

	const rawText =
		'-' === inputPath
			? fs.readFileSync(0, 'utf8')
			: fs.readFileSync(path.resolve(inputPath), 'utf8');

	let parsed;
	try {
		parsed = JSON.parse(rawText);
	} catch (error) {
		console.error(`Invalid Sighthound JSON: ${error instanceof Error ? error.message : String(error)}`);
		process.exit(1);
	}

	const sarif = toSarif(asFindings(parsed));
	fs.writeFileSync(path.resolve(outputPath), `${JSON.stringify(sarif, null, 2)}\n`, 'utf8');
	console.log(`Wrote ${outputPath} (${sarif.runs[0].results.length} result(s))`);
}

main();
