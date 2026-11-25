/** An API error with the server's message and, for 422, the field errors ({ "values.title.en": "Required" }). */
export class ApiError extends Error {
	constructor( status, message, fields = {} ) {
		super( message );
		this.status = status;
		this.fields = fields;
	}
}

/** The admin portal's calls to /api/admin. Sends the CSRF token (from login/session) with every change. */
export class Api {
	constructor( base = '/api/admin', fetchImpl = window.fetch.bind( window ) ) {
		this.base = base;
		this.fetch = fetchImpl;
		this.csrf = '';
	}

	async request( method, path, body ) {
		const headers = { Accept: 'application/json' };
		if ( method !== 'GET' ) {
			headers[ 'X-CSRF-Token' ] = this.csrf;
		}
		if ( body !== undefined && ! ( body instanceof FormData ) ) {
			headers[ 'Content-Type' ] = 'application/json';
		}
		const response = await this.fetch( `${ this.base }${ path }`, {
			method,
			headers,
			credentials: 'same-origin',
			body: body === undefined || body instanceof FormData ? body : JSON.stringify( body ),
		} );
		const data = await response.json().catch( () => ( {} ) );
		if ( ! response.ok ) {
			throw new ApiError( response.status, data.error || `Request failed (HTTP ${ response.status })`, data.fields || {} );
		}
		return data;
	}

	async session() {
		const session = await this.request( 'GET', '/session' );
		this.csrf = session.csrf;
		return session;
	}

	async login( username, password ) {
		const session = await this.request( 'POST', '/login', { username, password } );
		this.csrf = session.csrf;
		return session;
	}

	logout() {
		return this.request( 'POST', '/logout' );
	}

	content() {
		return this.request( 'GET', '/content' );
	}

	saveSection( payload ) {
		return this.request( 'POST', '/section', payload );
	}

	saveSettings( scope, values ) {
		return this.request( 'POST', '/settings', { scope, values } );
	}

	saveMenu( items ) {
		return this.request( 'POST', '/menu', { items } );
	}

	saveLanguage( language ) {
		return this.request( 'POST', '/languages', language );
	}

	deleteLanguage( iso2 ) {
		return this.request( 'POST', '/languages/delete', { iso2 } );
	}

	setMainLanguage( iso2 ) {
		return this.request( 'POST', '/languages/main', { iso2 } );
	}

	upload( file, purpose ) {
		const form = new FormData();
		form.append( 'file', file );
		form.append( 'purpose', purpose );
		return this.request( 'POST', '/media', form );
	}
}
