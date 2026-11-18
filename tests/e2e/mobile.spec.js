import { test, expect } from '@playwright/test';

test( 'mobile layout hides the centre nav and keeps the header controls', async ( { page, isMobile } ) => {
	test.skip( ! isMobile, 'mobile-only layout check' );
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: 1 } } ) );
	await page.goto( '/' );
	await expect( page.locator( '.site-nav__links' ) ).toBeHidden();
	await expect( page.locator( '.theme-toggle' ) ).toBeVisible();
	await expect( page.locator( '.lang-picker__current' ) ).toBeVisible();
} );
