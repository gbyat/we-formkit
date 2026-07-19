#!/usr/bin/env node

/**
 * Run Corgea Sighthound (dev SAST) against plugin source.
 *
 * Dev/CI only — not shipped in the release ZIP.
 *
 * Requires the `sighthound` binary on PATH (or SIGHTHOUND_BIN).
 * Install: cargo install --git https://github.com/Corgea/Sighthound --tag 1.0
 *
 * Usage:
 *   node scripts/run-sighthound.js
 *   node scripts/run-sighthound.js --sarif
 *   node scripts/run-sighthound.js --json
 *   node scripts/run-sighthound.js includes src
 *
 * Default targets: existing of includes/, src/
 */

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const rootDir = process.cwd();
const args = process.argv.slice(2);

/**
 * @return {string[]}
 */
function defaultTargets() {
	return ['includes', 'src'].filter((relative) =>
		fs.existsSync(path.join(rootDir, relative))
	);
}

/**
 * @param {string[]} argv
 * @return {{ format: string, targets: string[] }}
 */
function parseArgs(argv) {
	let format = 'text';
	const targets = [];

	argv.forEach((arg) => {
		if (arg === '--sarif') {
			format = 'sarif';
			return;
		}
		if (arg === '--json') {
			format = 'json';
			return;
		}
		if (arg === '--csv') {
			format = 'csv';
			return;
		}
		if (arg.startsWith('-')) {
			console.error(`Unknown option: ${arg}`);
			process.exit(2);
		}
		targets.push(arg);
	});

	return {
		format,
		targets: targets.length > 0 ? targets : defaultTargets(),
	};
}

/**
 * @return {string|null}
 */
function resolveBinary() {
	if (process.env.SIGHTHOUND_BIN) {
		return process.env.SIGHTHOUND_BIN;
	}

	const probe = spawnSync('sighthound', ['--help'], {
		encoding: 'utf8',
		shell: process.platform === 'win32',
	});

	if (probe.status === 0 || (probe.stdout && probe.stdout.includes('sighthound'))) {
		return 'sighthound';
	}

	if (probe.stdout || probe.stderr) {
		const blob = `${probe.stdout || ''}${probe.stderr || ''}`;
		if (/usage|sighthound/i.test(blob)) {
			return 'sighthound';
		}
	}

	return null;
}

function printInstallHelp() {
	console.error('Sighthound is not installed (or not on PATH).');
	console.error('');
	console.error('Install (Rust 1.85+ required):');
	console.error('  cargo install --git https://github.com/Corgea/Sighthound --tag 1.0');
	console.error('');
	console.error('Or set SIGHTHOUND_BIN to the binary path.');
	console.error('Docs: https://corgea.com/sighthound');
}

function main() {
	const { format, targets } = parseArgs(args);
	const binary = resolveBinary();

	if (!binary) {
		printInstallHelp();
		process.exit(1);
	}

	const existing = targets.filter((relative) => {
		const full = path.join(rootDir, relative);
		if (!fs.existsSync(full)) {
			console.warn(`WARN: skip missing path: ${relative}`);
			return false;
		}
		return true;
	});

	if (existing.length === 0) {
		console.error('No scan targets found (expected includes/ and/or src/).');
		process.exit(1);
	}

	let exitCode = 0;

	existing.forEach((relative) => {
		const target = path.join(rootDir, relative);
		console.log(`Sighthound → ${relative} (format: ${format})`);

		const cliArgs = ['--output-format', format, target];
		const result = spawnSync(binary, cliArgs, {
			cwd: rootDir,
			encoding: 'utf8',
			shell: process.platform === 'win32',
			maxBuffer: 20 * 1024 * 1024,
		});

		const out = result.stdout || '';
		const err = result.stderr || '';

		if ('sarif' === format && existing.length > 1) {
			const safeName = relative.replace(/[\\/]/g, '-');
			const outFile = path.join(rootDir, `sighthound-${safeName}.sarif`);
			fs.writeFileSync(outFile, out, 'utf8');
			console.log(`Wrote ${path.basename(outFile)}`);
		} else if (out) {
			process.stdout.write(out);
		}

		if (err) {
			process.stderr.write(err);
		}

		if (result.error) {
			console.error(result.error.message);
			exitCode = 1;
			return;
		}

		if (typeof result.status === 'number' && result.status !== 0) {
			exitCode = result.status;
		}
	});

	process.exit(exitCode);
}

main();
