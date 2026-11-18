import { defineConfig, devices } from '@playwright/test';

const PORT = 4173;
const isCI = Boolean( process.env.CI );

// E2E tests run against the production build (`npm run build` first), served by `vite preview`.
export default defineConfig( {
	testDir: 'tests/e2e',
	fullyParallel: true,
	forbidOnly: isCI,
	retries: isCI ? 1 : 0,
	reporter: isCI
		? [ [ 'github' ], [ 'list' ], [ 'html', { open: 'never', outputFolder: 'playwright-report' } ] ]
		: [ [ 'list' ] ],
	use: {
		baseURL: `http://127.0.0.1:${ PORT }`,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'mobile', use: { ...devices[ 'Pixel 7' ] } },
	],
	webServer: {
		command: `npx vite preview --port ${ PORT } --strictPort --host 127.0.0.1`,
		url: `http://127.0.0.1:${ PORT }`,
		reuseExistingServer: ! isCI,
		timeout: 30_000,
	},
} );
