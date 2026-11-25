import { defineConfig, devices } from '@playwright/test';

const PORT = 4173;
const isCI = Boolean( process.env.CI );

// E2E tests run against the production build (`npm run build` first), served like production by
// scripts/e2e-server.sh: PHP renders the page and API from a disposable, freshly seeded database.
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
		{ name: 'desktop', testIgnore: 'admin/**', use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'mobile', testIgnore: 'admin/**', use: { ...devices[ 'Pixel 7' ] } },
		// The admin tests change the content, so they run only after the public page tests have finished.
		{ name: 'admin', testMatch: 'admin/**/*.spec.js', dependencies: [ 'desktop', 'mobile' ], use: { ...devices[ 'Desktop Chrome' ] } },
	],
	webServer: {
		command: 'scripts/e2e-server.sh',
		url: `http://127.0.0.1:${ PORT }/api/health`,
		reuseExistingServer: ! isCI,
		timeout: 30_000,
	},
} );
