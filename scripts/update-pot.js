/**
 * Build or update the POT file (PHP-only or block plugin).
 */

const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');
const { runWp } = require('./wp-cli');

const config = loadConfig();
const languagesDir = path.join(rootDir, 'languages');
const slug = String(config.slug);
const textDomain = String(config.textDomain || slug);
const potFile = path.join(languagesDir, `${slug}.pot`);

if (!fs.existsSync(languagesDir)) {
	fs.mkdirSync(languagesDir, { recursive: true });
}

/**
 * @return {void}
 */
function runMakePotForBlocks() {
	const makePot = path.join(
		rootDir,
		'node_modules',
		'@wp-blocks',
		'make-pot',
		'lib',
		'makePot.js'
	);

	if (!fs.existsSync(makePot)) {
		throw new Error('@wp-blocks/make-pot not found. Run npm install in the plugin directory.');
	}

	const exclude = String(
		config.potExclude ||
			'**/node_modules/**,languages/**,scripts/**,**/*.min.js,build/**,dist/**,vendor/**'
	);

	const args = [
		makePot,
		rootDir,
		languagesDir,
		`--slug=${slug}`,
		`--domain=${config.potDomain || 'plugin'}`,
		`--exclude=${exclude}`,
		'--charset=utf-8',
		`--package-name=${config.name || slug}`,
		`--headers=Report-Msgid-Bugs-To:https://github.com/${config.githubRepo}/issues`,
		'--headers=Language-Team:webentwicklerin <hello@webentwicklerin.at>',
		'--headers=Last-Translator:Gabriele Laesser <hello@webentwicklerin.at>',
		'--headers=Author:webentwicklerin, Gabriele Laesser <hello@webentwicklerin.at>',
		'--headers=email:hello@webentwicklerin.at',
		'--skip-audit',
	];

	const result = spawnSync(process.execPath, args, {
		stdio: 'inherit',
		cwd: rootDir,
		env: process.env,
	});

	if (result.error) {
		throw result.error;
	}
	if (result.status !== 0) {
		throw new Error(`make-pot exited with code ${result.status}`);
	}
}

try {
	if (config.hasBlocks) {
		runMakePotForBlocks();
		console.log(`POT file updated: ${potFile}`);
	} else {
		runWp([
			'i18n',
			'make-pot',
			'.',
			potFile,
			`--domain=${textDomain}`,
			`--exclude=${config.potExclude || 'node_modules,vendor,scripts,assets/vendor'}`,
			'--skip-block-json',
		]);
		console.log(`POT file updated: ${potFile}`);
	}
} catch (error) {
	console.error('POT build failed.');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
