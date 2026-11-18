import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { IMAGE_SETS, srcset } from '../../src/js/imageSets.js';

const publicPath = ( url ) => new URL( `../../public${ url }`, import.meta.url );
const html = readFileSync( new URL( '../../index.html', import.meta.url ), 'utf8' );

test( 'every responsive file listed in imageSets.js exists in public/', () => {
	const missing = Object.values( IMAGE_SETS ).flatMap( ( set ) => [ set.webp, set.fallback ].flatMap( ( format ) =>
		srcset( set, format ).split( ', ' ).map( ( entry ) => entry.split( ' ' )[ 0 ] ) ) )
		.filter( ( url ) => ! existsSync( publicPath( url ) ) );
	assert.deepEqual( missing, [], 'run `npm run images`' );
} );

test( 'widths are ascending and never upscale past the source', () => {
	for ( const [ name, set ] of Object.entries( IMAGE_SETS ) ) {
		assert.deepEqual( [ ...set.widths ].sort( ( a, b ) => a - b ), set.widths, name );
		assert.ok( set.widths.at( -1 ) <= set.sourceWidth, `${ name } upscales` );
	}
} );

test( 'srcset lists one candidate per width with a w descriptor', () => {
	assert.equal(
		srcset( { name: 'flag-rs', widths: [ 24, 48 ] }, 'webp' ),
		'/images/responsive/flag-rs-24.webp 24w, /images/responsive/flag-rs-48.webp 48w',
	);
} );

test( 'index.html uses exactly the srcsets from imageSets.js (no hand-written drift)', () => {
	for ( const set of Object.values( IMAGE_SETS ) ) {
		const used = html.includes( `srcset="${ srcset( set, set.webp ) }"` );
		const unused = set.name === 'sun'; // only swapped in by the theme switcher
		assert.equal( used, ! unused, `${ set.name } srcset in index.html` );
	}
} );
