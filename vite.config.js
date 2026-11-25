import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const { version } = JSON.parse( readFileSync( new URL( './package.json', import.meta.url ), 'utf8' ) );
// Version + commit, so every deploy (not only version bumps) gets a fresh offline cache.
const buildId = `${ version }-${ ( process.env.GITHUB_SHA || 'local' ).slice( 0, 7 ) }`;

/** Stamps the build id into dist/sw.js so every deploy invalidates the offline cache. */
function stampServiceWorkerVersion() {
	return {
		name: 'stamp-service-worker-version',
		apply: 'build',
		closeBundle() {
			const file = resolve( 'dist/sw.js' );
			const source = readFileSync( file, 'utf8' );
			if ( ! source.includes( '__APP_VERSION__' ) ) {
				throw new Error( 'dist/sw.js has no __APP_VERSION__ placeholder to stamp' );
			}
			writeFileSync( file, source.replaceAll( '__APP_VERSION__', buildId ) );
		},
	};
}

/**
 * While `npm run dev` runs, tells the PHP page renderer to load assets from this dev server (hot reload):
 * it writes the server URL to server/storage/hot and removes the file when the server stops.
 */
function phpHotFile() {
	const file = resolve( 'server/storage/hot' );
	return {
		name: 'php-hot-file',
		apply: 'serve',
		configureServer( server ) {
			server.httpServer?.once( 'listening', () => {
				mkdirSync( resolve( 'server/storage' ), { recursive: true } );
				writeFileSync( file, `http://localhost:${ server.config.server.port }` );
			} );
			const remove = () => rmSync( file, { force: true } );
			server.httpServer?.once( 'close', remove );
			process.once( 'SIGINT', () => {
				remove();
				process.exit( 0 );
			} );
		},
	};
}

// The page HTML comes from PHP (server/templates/page.php); in dev, Vite forwards it and the API to Docker nginx.
const backend = `http://127.0.0.1:${ process.env.BLAZINGSUN_HTTP_PORT || 80 }`;

export default defineConfig( {
	build: {
		outDir: 'dist',
		emptyOutDir: true,
		// The sun GIF must keep its URL (it is swapped at runtime), so never inline images.
		assetsInlineLimit: 0,
		// The PHP page renderer reads the hashed CSS/JS file names from here.
		manifest: 'asset-manifest.json',
		rollupOptions: {
			input: { main: 'src/main.js', admin: 'adminportal/index.html' },
		},
	},
	plugins: [ stampServiceWorkerVersion(), phpHotFile() ],
	server: {
		// `npm run docker:up` serves the PHP API and the rendered page through nginx.
		proxy: {
			'/api': backend,
			'/uploads': backend,
			'^/([a-z]{2}/?)?$': backend,
		},
	},
} );
