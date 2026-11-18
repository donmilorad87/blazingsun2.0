import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PreferenceStore } from '../../src/js/PreferenceStore.js';

class MemoryStorage {
	constructor() {
		this.items = new Map();
	}

	getItem( key ) {
		return this.items.has( key ) ? this.items.get( key ) : null;
	}

	setItem( key, value ) {
		this.items.set( key, String( value ) );
	}
}

class BlockedStorage {
	getItem() {
		throw new Error( 'SecurityError: storage blocked' );
	}

	setItem() {
		throw new Error( 'QuotaExceededError' );
	}
}

test( 'writes and reads back a preference', () => {
	const store = new PreferenceStore( new MemoryStorage() );
	store.write( 'blazing-theme', 'light' );
	assert.equal( store.read( 'blazing-theme' ), 'light' );
} );

test( 'returns null for a preference that was never saved', () => {
	const store = new PreferenceStore( new MemoryStorage() );
	assert.equal( store.read( 'blazing-language' ), null );
} );

test( 'blocked storage: read returns null and logs a warning instead of throwing', ( t ) => {
	const warn = t.mock.method( console, 'warn', () => {} );
	const store = new PreferenceStore( new BlockedStorage() );
	assert.equal( store.read( 'blazing-theme' ), null );
	assert.equal( warn.mock.callCount(), 1 );
} );

test( 'blocked storage: write does not throw and logs a warning', ( t ) => {
	const warn = t.mock.method( console, 'warn', () => {} );
	const store = new PreferenceStore( new BlockedStorage() );
	assert.doesNotThrow( () => store.write( 'blazing-theme', 'dark' ) );
	assert.equal( warn.mock.callCount(), 1 );
} );
