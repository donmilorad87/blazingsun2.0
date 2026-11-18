const ENDPOINT = '/api/visits';

/**
 * Counts this page load via the PHP API and writes the all-time total into a hidden footer element
 * ([data-visit-counter]), visible only in DevTools. Failures are logged and never affect the page.
 */
export class VisitCounter {
	constructor( element, fetchImpl = window.fetch.bind( window ) ) {
		this.element = element;
		this.fetch = fetchImpl;
	}

	async record() {
		try {
			// POST: counts a visit, and the service worker never caches non-GET requests.
			const response = await this.fetch( ENDPOINT, { method: 'POST', headers: { Accept: 'application/json' } } );
			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status }` );
			}
			this.show( ( await response.json() ).total );
		} catch ( error ) {
			console.warn( '[VisitCounter] could not record visit', error );
		}
	}

	show( total ) {
		if ( ! Number.isSafeInteger( total ) || total < 0 ) {
			throw new TypeError( `unexpected total: ${ String( total ) }` );
		}
		this.element.dataset.visits = String( total );
		this.element.textContent = String( total );
	}
}
