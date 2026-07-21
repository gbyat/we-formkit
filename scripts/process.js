/**
 * Shared process helpers: Node-only runners (no shell / no PowerShell) and LF file I/O.
 */

const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const rootDir = path.join(__dirname, '..');

/**
 * @param {string} content
 * @return {string}
 */
function toLf(content) {
	return String(content).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
}

/**
 * @param {string} filePath
 * @param {string} content
 */
function writeLf(filePath, content) {
	fs.writeFileSync(filePath, toLf(content), { encoding: 'utf8' });
}

/**
 * @param {string} filePath
 * @return {string}
 */
function readLf(filePath) {
	return toLf(fs.readFileSync(filePath, 'utf8'));
}

/**
 * @param {string} command
 * @param {string[]} args
 * @param {{ stdio?: import('child_process').StdioOptions, encoding?: BufferEncoding, allowFailure?: boolean }} [options]
 * @return {import('child_process').SpawnSyncReturns<string|Buffer>}
 */
function run(command, args, options = {}) {
	const result = spawnSync(command, args, {
		cwd: rootDir,
		stdio: options.stdio || 'inherit',
		encoding: options.encoding,
		shell: false,
	});

	if (result.error && !options.allowFailure) {
		throw result.error;
	}

	if (result.status !== 0 && !options.allowFailure) {
		const error = new Error(
			`${command} ${args.join(' ')} exited with code ${result.status ?? 'unknown'}`
		);
		error.status = result.status;
		throw error;
	}

	return result;
}

/**
 * @param {string[]} args
 * @param {object} [options]
 */
function runGit(args, options) {
	return run('git', args, options);
}

/**
 * @param {string} scriptName Filename inside scripts/ (e.g. update-pot.js).
 * @param {string[]} [extraArgs]
 * @param {object} [options]
 */
function runNode(scriptName, extraArgs = [], options) {
	return run(process.execPath, [path.join(__dirname, scriptName), ...extraArgs], options);
}

/**
 * @param {string} version
 * @param {'patch'|'minor'|'major'} type
 * @return {string}
 */
function bumpSemver(version, type) {
	const parts = version.split('.').map((part) => parseInt(part, 10));
	if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
		throw new Error(`Invalid semver: ${version}`);
	}

	if ('major' === type) {
		return `${parts[0] + 1}.0.0`;
	}
	if ('minor' === type) {
		return `${parts[0]}.${parts[1] + 1}.0`;
	}

	return `${parts[0]}.${parts[1]}.${parts[2] + 1}`;
}

module.exports = {
	rootDir,
	toLf,
	writeLf,
	readLf,
	run,
	runGit,
	runNode,
	bumpSemver,
};
