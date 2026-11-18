import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { TRANSLATIONS, DEFAULT_LANGUAGE } from '../../src/js/translations.js';

const html = readFileSync( new URL( '../../index.html', import.meta.url ), 'utf8' );
const keysInHtml = [ ...html.matchAll( /data-i18n(?:-html|-aria-label)?="([^"]+)"/g ) ].map( ( match ) => match[ 1 ] );
const languages = Object.keys( TRANSLATIONS );

test( 'index.html declares translatable elements', () => {
	assert.ok( keysInHtml.length > 50, `only ${ keysInHtml.length } keys found` );
} );

test( 'default language exists', () => {
	assert.ok( TRANSLATIONS[ DEFAULT_LANGUAGE ] );
} );

test( 'every key used in index.html is translated in every language', () => {
	for ( const lang of languages ) {
		const missing = keysInHtml.filter( ( key ) => typeof TRANSLATIONS[ lang ][ key ] !== 'string' );
		assert.deepEqual( missing, [], `${ lang } is missing keys` );
	}
} );

test( 'no language has keys that index.html does not use', () => {
	const used = new Set( keysInHtml );
	for ( const lang of languages ) {
		const unused = Object.keys( TRANSLATIONS[ lang ] ).filter( ( key ) => ! used.has( key ) );
		assert.deepEqual( unused, [], `${ lang } has unused keys` );
	}
} );

test( 'English translations match the static HTML text (SEO copy stays in sync)', () => {
	const textOf = ( key ) => {
		const label = html.match( new RegExp( `aria-label="([^"]*)"[^>]*data-i18n-aria-label="${ key.replace( /\./g, '\\.' ) }"` ) );
		if ( label ) {
			return label[ 1 ];
		}
		const match = html.match( new RegExp( `data-i18n(?:-html)?="${ key.replace( /\./g, '\\.' ) }"[^>]*>([\\s\\S]*?)</(?:span|a|div|h1|h2|h3|p|button)>` ) );
		return match ? match[ 1 ].replace( /&amp;/g, '&' ).trim() : null;
	};
	const mismatched = keysInHtml.filter( ( key ) => textOf( key ) !== TRANSLATIONS.en[ key ] );
	assert.deepEqual( mismatched, [] );
} );
