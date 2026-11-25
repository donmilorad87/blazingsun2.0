// Replaced with "<package version>-<commit>" at build time (vite.config.js), so each deploy gets a fresh cache.
const CACHE_VERSION = '__APP_VERSION__';

/**
 * Offline support: pages are network-first (fresh content, cache as fallback);
 * assets are stale-while-revalidate (Vite file names are content-hashed).
 */
class OfflineCache {
	constructor( name, appShell ) {
		this.name = name;
		this.appShell = appShell;
	}

	listen( scope ) {
		scope.addEventListener( 'install', ( event ) => event.waitUntil( this.install( scope ) ) );
		scope.addEventListener( 'activate', ( event ) => event.waitUntil( this.activate( scope ) ) );
		scope.addEventListener( 'fetch', ( event ) => this.onFetch( event, scope ) );
	}

	async install( scope ) {
		const cache = await caches.open( this.name );
		await cache.addAll( this.appShell );
		await scope.skipWaiting();
	}

	async activate( scope ) {
		const keys = await caches.keys();
		await Promise.all( keys.filter( ( key ) => key !== this.name ).map( ( key ) => caches.delete( key ) ) );
		await scope.clients.claim();
	}

	onFetch( event, scope ) {
		const { request } = event;
		if ( request.method !== 'GET' || new URL( request.url ).origin !== scope.location.origin ) {
			return;
		}
		event.respondWith( request.mode === 'navigate' ? this.networkFirst( request ) : this.staleWhileRevalidate( request ) );
	}

	async networkFirst( request ) {
		try {
			const response = await fetch( request );
			this.store( request, response );
			return response;
		} catch ( error ) {
			console.warn( '[OfflineCache] offline, serving cached page', error );
			// Each language has its own URL (/, /sr/, …); fall back to the main page if this one was never cached.
			return ( await caches.match( request ) ) || caches.match( '/' );
		}
	}

	async staleWhileRevalidate( request ) {
		const cached = await caches.match( request );
		const network = fetch( request ).then( ( response ) => {
			this.store( request, response );
			return response;
		} );
		return cached || network;
	}

	store( request, response ) {
		if ( ! response.ok ) {
			return;
		}
		const copy = response.clone();
		caches.open( this.name )
			.then( ( cache ) => cache.put( request, copy ) )
			.catch( ( error ) => console.warn( '[OfflineCache] cache write failed', error ) );
	}
}

new OfflineCache( `blazing-sun-v${ CACHE_VERSION }`, [ '/', '/manifest.json', '/favicon.png' ] ).listen( self );
