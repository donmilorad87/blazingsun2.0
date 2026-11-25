import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { IMAGE_SETS, srcset, widthsFor } from '../../src/js/imageSets.js';

const publicPath = ( url ) => new URL( `../../public${ url }`, import.meta.url );
const seed = JSON.parse( readFileSync( new URL( '../../server/content/seed.json', import.meta.url ), 'utf8' ) );

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

test( 'the CMS seed lists exactly the generated variants of every image set (no hand-written drift)', () => {
	for ( const set of Object.values( IMAGE_SETS ) ) {
		const media = seed.media.find( ( entry ) => entry.path === `/images/responsive/${ set.name }` );
		assert.ok( media, `${ set.name } is in server/content/seed.json` );
		assert.deepEqual( media.variants, { webp: set.widths, fallback: set.fallback, fallbackWidths: widthsFor( set, set.fallback ) }, set.name );
	}
} );
