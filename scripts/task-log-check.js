#!/usr/bin/env node
/**
 * Verify (or fix) TASK-LOG.md newest-first order under ## Ongoing Time Log.
 *
 * Usage:
 *   node scripts/task-log-check.js
 *   node scripts/task-log-check.js --fix
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '..' );
const TASK_LOG = path.join( ROOT, 'TASK-LOG.md' );
const HEADING = '## Ongoing Time Log';
const ENTRY_RE = /^- (\d{4}-\d{2}-\d{2} \d{2}:\d{2}) \|/;
const fix = process.argv.includes( '--fix' );

function parseEntries( bodyLines ) {
	const entries = [];
	const other = [];
	for ( const line of bodyLines ) {
		if ( ENTRY_RE.test( line ) ) {
			entries.push( line );
		} else if ( line.trim() !== '' ) {
			other.push( line );
		}
	}
	return { entries, other };
}

function sortNewestFirst( entries ) {
	return entries.slice().sort( ( a, b ) => {
		const ta = a.match( ENTRY_RE )[ 1 ];
		const tb = b.match( ENTRY_RE )[ 1 ];
		if ( ta !== tb ) {
			return ta < tb ? 1 : -1;
		}
		return a.localeCompare( b );
	} );
}

function findBreaks( entries ) {
	const breaks = [];
	for ( let i = 1; i < entries.length; i++ ) {
		const prev = entries[ i - 1 ].match( ENTRY_RE )[ 1 ];
		const curr = entries[ i ].match( ENTRY_RE )[ 1 ];
		if ( curr > prev ) {
			breaks.push( { index: i + 1, prev, curr } );
		}
	}
	return breaks;
}

function main() {
	if ( ! fs.existsSync( TASK_LOG ) ) {
		console.error( 'TASK-LOG.md not found.' );
		process.exit( 1 );
	}

	const raw = fs.readFileSync( TASK_LOG, 'utf8' ).replace( /\r\n/g, '\n' );
	const parts = raw.split( /^## Ongoing Time Log\s*$/m );
	if ( parts.length < 2 ) {
		console.error( 'Missing "## Ongoing Time Log" heading.' );
		process.exit( 1 );
	}

	const header = parts[ 0 ] + HEADING + '\n\n';
	const body = parts.slice( 1 ).join( HEADING );
	const { entries, other } = parseEntries( body.split( '\n' ) );
	const breaks = findBreaks( entries );
	const hasAuto = entries.some( ( line ) => line.includes( '[AUTO-WIP]' ) );

	if ( ! fix ) {
		if ( breaks.length === 0 && ! hasAuto ) {
			console.log( `OK: ${ entries.length } entries, newest-first.` );
			process.exit( 0 );
		}
		if ( breaks.length ) {
			console.error( `TASK-LOG order error: ${ breaks.length } break(s) (expected newest-first).` );
			breaks.slice( 0, 5 ).forEach( ( b ) => {
				console.error( `  line~${ b.index }: ${ b.prev } -> ${ b.curr }` );
			} );
		}
		if ( hasAuto ) {
			console.error( 'TASK-LOG still contains [AUTO-WIP] (rolling snapshot). Run with --fix to drop it, or finish the real log entry.' );
		}
		console.error( 'Fix with: npm run task-log:fix' );
		process.exit( 1 );
	}

	const cleaned = entries.filter( ( line ) => ! line.includes( '[AUTO-WIP]' ) );
	const sorted = sortNewestFirst( cleaned );
	const seen = new Set();
	const unique = [];
	for ( const line of sorted ) {
		const stamp = line.match( ENTRY_RE )[ 1 ];
		const scopeMatch = line.match( /\| Scope: ([^|]+)\|/ );
		const key = stamp + '|' + ( scopeMatch ? scopeMatch[ 1 ].trim() : line );
		if ( seen.has( key ) ) {
			continue;
		}
		seen.add( key );
		unique.push( line );
	}

	const out =
		header +
		unique.join( '\n' ) +
		'\n' +
		( other.length ? '\n' + other.join( '\n' ) + '\n' : '' );
	fs.writeFileSync( TASK_LOG, out, 'utf8' );
	console.log(
		`Fixed: ${ entries.length } → ${ unique.length } entries (newest-first).`
	);
}

main();
