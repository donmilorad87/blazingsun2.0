import { test, expect } from '@playwright/test';

test.beforeEach( async ( { page } ) => {
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: 1 } } ) );
	await page.goto( '/' );
} );

const button = ( page ) => page.locator( '.back-to-top' );
const scrollTo = ( page, y ) => page.evaluate( ( top ) => window.scrollTo( { top, behavior: 'instant' } ), y );

test( 'hidden at the top, appears after scrolling down', async ( { page } ) => {
	await expect( button( page ) ).toBeHidden();
	await scrollTo( page, 1500 );
	await expect( button( page ) ).toBeVisible();
	await scrollTo( page, 0 );
	await expect( button( page ) ).toBeHidden();
} );

test( 'click scrolls back to the top and keeps keyboard focus on the page', async ( { page } ) => {
	await scrollTo( page, 2500 );
	await button( page ).click();
	await expect.poll( () => page.evaluate( () => window.scrollY ), { timeout: 4000 } ).toBe( 0 );
	await expect( page.locator( '.brand' ) ).toBeFocused();
} );

test( 'same glass as the other elements, round, bottom-right, never over the footer', async ( { page } ) => {
	await scrollTo( page, 1500 );
	await expect( button( page ) ).toBeVisible();
	// It slides up 16px while fading in: measure once it has settled.
	await expect( button( page ) ).toHaveCSS( 'translate', '0px' );
	const look = await page.evaluate( () => {
		const style = ( selector ) => getComputedStyle( document.querySelector( selector ) );
		const rect = document.querySelector( '.back-to-top' ).getBoundingClientRect();
		const footer = document.querySelector( '.site-footer' ).getBoundingClientRect();
		return {
			sameBackground: style( '.back-to-top' ).backgroundColor === style( '.card' ).backgroundColor,
			sameBlur: style( '.back-to-top' ).backdropFilter === style( '.card' ).backdropFilter,
			round: style( '.back-to-top' ).borderRadius === '50%',
			rightSide: rect.right > window.innerWidth - 48 && rect.right <= window.innerWidth,
			aboveFooter: rect.bottom <= footer.top - 8,
		};
	} );
	expect( look ).toEqual( { sameBackground: true, sameBlur: true, round: true, rightSide: true, aboveFooter: true } );
} );

test( 'the progress ring fills as you scroll', async ( { page } ) => {
	const progress = () => page.locator( '.back-to-top' ).evaluate( ( el ) => Number( el.style.getPropertyValue( '--progress' ) ) );
	await scrollTo( page, 1500 );
	await expect.poll( progress ).toBeGreaterThan( 0 );
	const part = await progress();
	await scrollTo( page, 100000 );
	await expect.poll( progress ).toBeCloseTo( 1, 2 );
	expect( part ).toBeLessThan( 1 );
} );

test( 'label is translated', async ( { page } ) => {
	await expect( button( page ) ).toHaveAttribute( 'aria-label', 'Back to top' );
	await page.locator( '.lang-picker__current' ).click();
	await page.getByRole( 'link', { name: 'Srpski' } ).click();
	await expect( page ).toHaveURL( /\/sr\/$/ );
	await expect( button( page ) ).toHaveAttribute( 'aria-label', 'Nazad na vrh' );
} );
