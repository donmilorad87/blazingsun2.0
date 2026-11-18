/**
 * Persists small user preferences (theme, language) in localStorage.
 * localStorage can throw (private mode, blocked site data); preferences are optional, so it falls back to null.
 */
export class PreferenceStore {
	// `storage` is injectable for tests; window.localStorage is resolved lazily because touching it can throw.
	constructor( storage = null ) {
		this.storage = storage;
	}

	get backend() {
		return this.storage ?? window.localStorage;
	}

	read( key ) {
		try {
			return this.backend.getItem( key );
		} catch ( error ) {
			console.warn( `[PreferenceStore] cannot read "${ key }"`, error );
			return null;
		}
	}

	write( key, value ) {
		try {
			this.backend.setItem( key, value );
		} catch ( error ) {
			console.warn( `[PreferenceStore] cannot write "${ key }"`, error );
		}
	}
}
