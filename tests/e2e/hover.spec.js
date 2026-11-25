import { test, expect } from '@playwright/test';

test.beforeEach( async ( { page } ) => {
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: 1 } } ) );
	await page.goto( '/' );
	await page.waitForFunction( () => document.documentElement.dataset.orb === 'idle' );
} );

const look = ( locator ) => locator.evaluate( ( el ) => {
	const style = getComputedStyle( el );
	const rect = el.getBoundingClientRect();
	return {
		background: style.backgroundColor,
		borderColor: style.borderTopColor,
		borderWidth: style.borderTopWidth,
		blur: style.backdropFilter,
		box: [ rect.left, rect.top, rect.width, rect.height ].map( Math.round ),
	};
} );

// Hover glass: wait until the CSS transition has finished (two identical reads 120ms apart). Hovering can
// scroll the page smoothly first, so a fixed delay is not enough.
const hovered = async ( page, locator ) => {
	await locator.hover();
	let previous = null;
	for ( let i = 0; i < 30; i++ ) {
		const current = await look( locator );
		const isHovered = await locator.evaluate( ( el ) => el.matches( ':hover' ) );
		if ( isHovered && previous && JSON.stringify( current ) === JSON.stringify( previous ) ) {
			return current;
		}
		previous = isHovered ? current : null;
		if ( ! isHovered ) {
			await locator.hover(); // the page was still scrolling under the pointer
		}
		await page.waitForTimeout( 120 );
	}
	return previous;
};

test( 'nav items get the glass look on hover without moving or changing border colour', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'the nav links are hidden on phones' );
	const link = page.locator( '.site-nav__links a' ).nth( 1 );
	const before = await look( link );
	const after = await hovered( page, link );

	expect( before.background ).toBe( 'rgba(0, 0, 0, 0)' );
	expect( after.background ).not.toBe( 'rgba(0, 0, 0, 0)' );
	expect( after.blur ).toMatch( /blur\(/ );
	expect( after.borderColor ).not.toBe( 'rgba(0, 0, 0, 0)' );
	expect( after.borderWidth ).toBe( before.borderWidth );
	expect( after.box, 'moved or resized on hover' ).toEqual( before.box );
} );

test( 'language menu items get the same glass hover, without moving', async ( { page } ) => {
	await page.locator( '.lang-picker__current' ).click();
	const item = page.getByRole( 'link', { name: 'Français' } );
	const before = await look( item );
	const after = await hovered( page, item );

	expect( after.background ).not.toBe( before.background );
	expect( after.blur ).toMatch( /blur\(/ );
	expect( after.borderColor ).not.toBe( 'rgba(0, 0, 0, 0)' );
	expect( after.box ).toEqual( before.box );

	const navLinkHover = await page.evaluate( () => getComputedStyle( document.documentElement ).getPropertyValue( '--glass-bg-hover' ).trim() );
	expect( navLinkHover ).not.toBe( '' );
} );

test( 'ghost button hover keeps its border colour', async ( { page } ) => {
	const ghost = page.locator( '.hero .btn--ghost' );
	const before = await look( ghost );
	const after = await hovered( page, ghost );

	expect( after.borderColor ).toBe( before.borderColor );
	expect( after.background ).not.toBe( before.background );
	expect( after.blur ).toMatch( /blur\(/ );
} );

test( 'all three hover states use the same glass', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'the nav links are hidden on phones' );
	const nav = await hovered( page, page.locator( '.site-nav__links a' ).first() );
	const ghost = await hovered( page, page.locator( '.hero .btn--ghost' ) );
	await page.locator( '.lang-picker__current' ).click();
	const item = await hovered( page, page.getByRole( 'link', { name: 'Srpski' } ) );

	for ( const state of [ ghost, item ] ) {
		expect( state.background ).toBe( nav.background );
		expect( state.borderColor ).toBe( nav.borderColor );
		expect( state.blur ).toBe( nav.blur );
	}
} );

test( 'hover only adds background and border: the nav text colour stays the same', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'the nav links are hidden on phones' );
	const link = page.locator( '.site-nav__links a' ).nth( 2 );
	const before = await link.evaluate( ( el ) => getComputedStyle( el ).color );
	await link.hover();
	await page.waitForTimeout( 450 );
	expect( await link.evaluate( ( el ) => getComputedStyle( el ).color ) ).toBe( before );
} );

test( 'language dropdown: hovered and active items have the same rounded shape as the nav hover', async ( { page, isMobile } ) => {
	await page.locator( '.lang-picker__current' ).click();
	const item = page.getByRole( 'link', { name: 'Français' } );
	await item.hover();
	await page.waitForTimeout( 450 );
	const radius = ( locator ) => locator.evaluate( ( el ) => getComputedStyle( el ).borderRadius );
	const hovered = await radius( item );
	expect( await radius( page.locator( '.lang-picker__menu a.is-active' ) ) ).toBe( hovered );
	if ( ! isMobile ) {
		expect( await radius( page.locator( '.site-nav__links a' ).first() ) ).toBe( hovered );
	}
	// The dropdown's corners are concentric with its pill rows: item radius (half the row height) + padding.
	const geometry = await page.locator( '.lang-picker__menu' ).evaluate( ( menu ) => {
		const row = menu.querySelector( 'a' ).getBoundingClientRect().height;
		const style = getComputedStyle( menu );
		return { menuRadius: parseFloat( style.borderRadius ), expected: ( row / 2 ) + parseFloat( style.paddingTop ) };
	} );
	expect( Math.abs( geometry.menuRadius - geometry.expected ) ).toBeLessThanOrEqual( 1 );
} );

test( 'contact links get the same hover glass, keeping their border (and text) colour', async ( { page, isMobile } ) => {
	// The hover-glass background as the browser resolves it (from the --glass-bg-hover token).
	const hoverGlass = await page.evaluate( () => {
		const probe = document.createElement( 'div' );
		probe.style.backgroundColor = 'var(--glass-bg-hover)';
		document.body.append( probe );
		const value = getComputedStyle( probe ).backgroundColor;
		probe.remove();
		return value;
	} );
	const link = page.locator( '.contact__link' ).first();
	await page.locator( '#contact .panel' ).evaluate( ( panel ) => panel.classList.add( 'is-revealed' ) );
	await link.scrollIntoViewIfNeeded();
	const before = await link.evaluate( ( el ) => ( { border: getComputedStyle( el ).borderTopColor, text: getComputedStyle( el ).color } ) );

	await link.hover();
	await expect.poll( () => link.evaluate( ( el ) => getComputedStyle( el ).backgroundColor ) ).toBe( hoverGlass );
	await expect( link ).toHaveCSS( 'border-top-color', before.border );
	await expect( link ).toHaveCSS( 'color', before.text );
	await expect( link ).toHaveCSS( 'backdrop-filter', /blur\(/ );

	if ( ! isMobile ) {
		const nav = page.locator( '.site-nav__links a' ).first();
		await nav.hover();
		await expect.poll( () => nav.evaluate( ( el ) => getComputedStyle( el ).backgroundColor ) ).toBe( hoverGlass );
	}
} );
