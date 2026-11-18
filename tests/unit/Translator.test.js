import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Translator } from '../../src/js/Translator.js';

test( 'supports the three site languages', () => {
	for ( const lang of [ 'en', 'sr', 'fr' ] ) {
		assert.equal( Translator.isSupported( lang ), true, lang );
	}
} );

test( 'rejects unknown, empty and prototype-polluting language values (from localStorage)', () => {
	for ( const lang of [ 'de', '', null, undefined, '__proto__', 'constructor', 'toString' ] ) {
		assert.equal( Translator.isSupported( lang ), false, String( lang ) );
	}
} );
