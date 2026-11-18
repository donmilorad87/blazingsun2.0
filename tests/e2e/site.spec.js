import { test, expect } from '@playwright/test';

test.beforeEach( async ( { page } ) => {
	// The PHP API is not running during E2E; answer the visit counter call so the page behaves like production.
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: 1 } } ) );
	page.errors = [];
	page.on( 'pageerror', ( error ) => page.errors.push( error.message ) );
	page.on( 'console', ( message ) => {
		if ( message.type() === 'error' ) {
			page.errors.push( message.text() );
		}
	} );
	await page.goto( '/' );
} );

test.afterEach( async ( { page } ) => {
	expect( page.errors, 'no JavaScript errors in the console' ).toEqual( [] );
} );

test( 'renders the hero and every section', async ( { page } ) => {
	await expect( page ).toHaveTitle( 'Milorad Đuković | Blazing Sun' );
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toHaveText( 'Building digital systems' );
	for ( const id of [ 'about', 'stack', 'experience', 'projects', 'cv-highlights', 'contact' ] ) {
		await expect( page.locator( `#${ id }` ) ).toBeAttached();
	}
} );

test( 'every image loads', async ( { page } ) => {
	await page.waitForLoadState( 'load' );
	const broken = await page.locator( 'img' ).evaluateAll(
		// Only displayed images: the other theme's pictures are lazy and intentionally not loaded.
		( images ) => images.filter( ( img ) => img.checkVisibility() && ( ! img.complete || img.naturalWidth === 0 ) ).map( ( img ) => img.src ),
	);
	expect( broken ).toEqual( [] );
} );

test( 'in-page nav links point at existing sections', async ( { page } ) => {
	const targets = await page.locator( 'a[href^="#"]' ).evaluateAll( ( links ) => links.map( ( link ) => link.getAttribute( 'href' ) ) );
	expect( targets.length ).toBeGreaterThan( 0 );
	for ( const target of targets ) {
		await expect( page.locator( target ), target ).toBeAttached();
	}
} );

test( 'theme toggle switches to Blazing Sun and remembers it', async ( { page } ) => {
	const toggle = page.getByRole( 'button', { name: 'Toggle between Blazing Sun and White Moon' } );
	await expect( page.locator( '.brand__name:visible' ) ).toHaveText( 'White Moon' );
	await expect( toggle ).toHaveAttribute( 'aria-pressed', 'false' );

	await toggle.click();
	await expect( page.locator( 'html' ) ).toHaveClass( /\blight\b/ );
	await expect( page.locator( '.brand__name:visible' ) ).toHaveText( 'Blazing Sun' );
	await expect.poll( () => page.locator( '.theme-orb:visible img' ).evaluate( ( img ) => img.currentSrc ) ).toMatch( /\/sun-\d+\.webp$/ );
	await expect( page.locator( '.sky--clouds' ) ).toBeVisible();
	await expect( page.locator( '.sky--stars' ) ).toBeHidden();

	await page.reload();
	await expect( page.locator( 'html' ) ).toHaveClass( /\blight\b/ );
	await expect( toggle ).toHaveAttribute( 'aria-pressed', 'true' );
} );

test( 'language picker translates the page and remembers the choice', async ( { page } ) => {
	const current = page.locator( '.lang-picker__current' );
	await current.click();
	await expect( current ).toHaveAttribute( 'aria-expanded', 'true' );
	await page.getByRole( 'button', { name: 'Srpski' } ).click();

	await expect( page.locator( 'html' ) ).toHaveAttribute( 'lang', 'sr' );
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toHaveText( 'Gradimo digitalne sisteme' );
	await expect( page.locator( '#lang-menu' ) ).toBeHidden();

	await page.reload();
	await expect( page.locator( 'html' ) ).toHaveAttribute( 'lang', 'sr' );
	await expect( page.locator( '#contact h2' ) ).toHaveText( 'Hajde da izgradimo nešto ozbiljno.' );
} );

test( 'language menu closes with Escape and on outside click', async ( { page } ) => {
	const current = page.locator( '.lang-picker__current' );
	const menu = page.locator( '#lang-menu' );

	await current.click();
	await expect( menu ).toBeVisible();
	await page.keyboard.press( 'Escape' );
	await expect( menu ).toBeHidden();
	await expect( current ).toBeFocused();

	await current.click();
	await page.locator( 'h1' ).click();
	await expect( menu ).toBeHidden();
	await expect( current ).toHaveAttribute( 'aria-expanded', 'false' );
} );

test( 'has no horizontal scroll', async ( { page } ) => {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - window.innerWidth );
	expect( overflow ).toBeLessThanOrEqual( 0 );
} );

test( 'service worker installs and caches the app shell', async ( { page } ) => {
	const cacheNames = await page.evaluate( async () => {
		await navigator.serviceWorker.ready;
		return caches.keys();
	} );
	expect( cacheNames.some( ( name ) => name.startsWith( 'blazing-sun-v' ) ) ).toBe( true );
} );
