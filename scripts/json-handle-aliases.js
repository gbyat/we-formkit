/**
 * Duplicate MD5 JSON files using script handle filenames.
 *
 * Configure mappings in plugin.config.json under jsonHandleAliases, for example:
 * { "build/example/index.js": "we-example-editor" }
 */

const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');

const config = loadConfig();
const languagesDir = path.join(rootDir, 'languages');
const slug = String(config.slug);
/** @type {Record<string, string>} */
const sourceToHandle = config.jsonHandleAliases || {};

if (!fs.existsSync(languagesDir) || 0 === Object.keys(sourceToHandle).length) {
	process.exit(0);
}

let created = 0;
const jsonPattern = new RegExp(
	`^${slug.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}-[a-z]{2}_[A-Z]{2}(?:_[a-z]+)?-[a-f0-9]{32}\\.json$`
);

fs.readdirSync(languagesDir)
	.filter((name) => jsonPattern.test(name))
	.forEach((filename) => {
		const match = filename.match(
			new RegExp(`^${slug.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}-([a-z]{2}_[A-Z]{2}(?:_[a-z]+)?)-[a-f0-9]{32}\\.json$`)
		);
		if (!match) {
			return;
		}

		const locale = match[1];
		const parsed = JSON.parse(fs.readFileSync(path.join(languagesDir, filename), 'utf8'));
		const source = (parsed.source || '').replace(/\\/g, '/');
		const handle = sourceToHandle[source];

		if (!handle) {
			return;
		}

		const aliasName = `${slug}-${locale}-${handle}.json`;
		fs.writeFileSync(path.join(languagesDir, aliasName), JSON.stringify(parsed));
		created += 1;
		console.log(`Created ${aliasName}`);
	});

console.log(`Handle-based JSON aliases: ${created} file(s).`);
