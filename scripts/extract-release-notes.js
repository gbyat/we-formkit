const fs = require('fs');
const path = require('path');

/**
 * Extract release notes from CHANGELOG.md for a specific version.
 *
 * Usage:
 *   node scripts/extract-release-notes.js <version> [output-file]
 */
const version = process.argv[2];
const outputFile = process.argv[3] || 'release_notes.txt';

if (!version) {
	console.error('Version argument required.');
	console.error('Usage: node scripts/extract-release-notes.js <version> [output-file]');
	process.exit(1);
}

const changelogPath = path.join(__dirname, '..', 'CHANGELOG.md');
if (!fs.existsSync(changelogPath)) {
	console.error(`CHANGELOG.md not found at ${changelogPath}`);
	process.exit(1);
}

const changelogContent = fs.readFileSync(changelogPath, 'utf8');
const escapedVersion = version.replace(/\./g, '\\.');
const sectionPattern = new RegExp(`## \\[${escapedVersion}\\] - [0-9-]+([\\s\\S]*?)(?=## \\[|$)`);
const match = changelogContent.match(sectionPattern);

let notes = '';
if (match && match[1]) {
	notes = match[1]
		.trim()
		// Drop Keep-a-Changelog footer links such as "[1.0.0]: https://..."
		.replace(/^\[[^\]]+\]:\s*https?:\/\/\S+\s*$/gim, '')
		.trim()
		.replace(/\n{3,}/g, '\n\n');
}

if (!notes) {
	notes = [
		`## What's new in ${version}`,
		'',
		'No detailed changelog entries were found for this version in CHANGELOG.md.',
		'Add bullet points under the version heading before releasing.',
		'',
		'### Upgrade Notice',
		`- Update to ${version} when convenient.`,
	].join('\n');
}

fs.writeFileSync(outputFile, notes, 'utf8');
console.log(`Release notes extracted to ${outputFile}`);
