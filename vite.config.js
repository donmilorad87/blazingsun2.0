import { readFileSync, writeFileSync } from 'node:fs';
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

export default defineConfig( {
	build: {
		outDir: 'dist',
		emptyOutDir: true,
		// The sun GIF must keep its URL (it is swapped at runtime), so never inline images.
		assetsInlineLimit: 0,
	},
	plugins: [ stampServiceWorkerVersion() ],
	server: {
		// `npm run docker:up` serves the PHP API through nginx; the dev server forwards /api to it.
		proxy: { '/api': `http://127.0.0.1:${ process.env.BLAZINGSUN_HTTP_PORT || 8080 }` },
	},
} );
