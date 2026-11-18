import { test, expect } from '@playwright/test';

// The PHP API is not running during E2E (Vite preview only), so it is mocked; this also keeps CI out of the real count.
test( 'counts the visit and puts the total in a hidden footer element', async ( { page } ) => {
	const calls = [];
	await page.route( '**/api/visits', async ( route ) => {
		calls.push( route.request().method() );
		await route.fulfill( { json: { total: 1234 } } );
	} );

	await page.goto( '/' );

	const counter = page.locator( '[data-visit-counter]' );
	await expect( counter ).toHaveAttribute( 'data-visits', '1234' );
	await expect( counter ).toHaveText( '1234' );
	await expect( counter ).toBeHidden();
	await expect( counter ).toHaveAttribute( 'hidden', '' );
	expect( calls ).toEqual( [ 'POST' ] );
} );

test( 'a failing API leaves the counter empty and does not break the page', async ( { page } ) => {
	const warnings = [];
	page.on( 'console', ( message ) => {
		if ( message.type() === 'warning' ) {
			warnings.push( message.text() );
		}
	} );
	await page.route( '**/api/visits', ( route ) => route.fulfill( { status: 500, json: { error: 'Internal server error' } } ) );

	await page.goto( '/' );

	await expect.poll( () => warnings.some( ( text ) => text.includes( '[VisitCounter]' ) ) ).toBe( true );
	await expect( page.locator( '[data-visit-counter]' ) ).toHaveText( '' );
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toBeVisible();
} );

test( 'malformed API responses are ignored', async ( { page } ) => {
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: '<img src=x onerror=alert(1)>' } } ) );

	await page.goto( '/' );
	await page.waitForLoadState( 'networkidle' );

	await expect( page.locator( '[data-visit-counter]' ) ).toHaveText( '' );
	await expect( page.locator( '[data-visit-counter]' ) ).not.toHaveAttribute( 'data-visits' );
} );
