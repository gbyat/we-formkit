/**
 * Copy DataViews CSS into build/ (package marks sideEffects:false, so CSS imports are dropped).
 */
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.join( __dirname, '..' );
const src = path.join(
	root,
	'node_modules',
	'@wordpress',
	'dataviews',
	'build-style',
	'style.css'
);
const destDir = path.join( root, 'build' );
const dest = path.join( destDir, 'dataviews.css' );

if ( ! fs.existsSync( src ) ) {
	console.warn( 'copy-dataviews-style: source missing, skip:', src );
	process.exit( 0 );
}

fs.mkdirSync( destDir, { recursive: true } );
fs.copyFileSync( src, dest );

const rtlSrc = path.join( path.dirname( src ), 'style-rtl.css' );
if ( fs.existsSync( rtlSrc ) ) {
	fs.copyFileSync( rtlSrc, path.join( destDir, 'dataviews-rtl.css' ) );
}

console.log( 'Copied DataViews styles to build/' );
