import { test, expect } from '@playwright/test';

test.beforeEach( async ( { page } ) => {
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: 1 } } ) );
} );

const centreOf = ( locator ) => locator.evaluate( ( el ) => {
	const rect = el.getBoundingClientRect();
	return { x: rect.left + ( rect.width / 2 ), y: rect.top + ( rect.height / 2 ) };
} );

test( 'desktop: the sun/moon is centred on the hero glow spot and stays fixed while scrolling', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'desktop layout only' );
	await page.goto( '/' );
	const orb = page.locator( '.theme-orb' );
	await expect( orb ).toHaveCSS( 'position', 'fixed' );

	const spot = await centreOf( page.locator( '.hero__glow' ) );
	const atTop = await centreOf( orb );
	expect( Math.abs( atTop.x - spot.x ) ).toBeLessThanOrEqual( 2 );
	expect( Math.abs( atTop.y - spot.y ) ).toBeLessThanOrEqual( 2 );

	await page.mouse.wheel( 0, 1200 );
	await expect.poll( () => page.evaluate( () => window.scrollY ) ).toBeGreaterThan( 500 );
	const afterScroll = await centreOf( orb );
	expect( afterScroll ).toEqual( atTop );
} );

test( 'desktop: the orb follows the glow spot when the window is resized', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'desktop layout only' );
	await page.goto( '/' );
	await page.setViewportSize( { width: 1100, height: 800 } );

	await expect.poll( async () => {
		const spot = await centreOf( page.locator( '.hero__glow' ) );
		const orb = await centreOf( page.locator( '.theme-orb' ) );
		return Math.round( Math.abs( orb.x - spot.x ) + Math.abs( orb.y - spot.y ) );
	} ).toBeLessThanOrEqual( 3 );
} );

test( 'the hero glow spot has no background blob or blur any more', async ( { page } ) => {
	await page.goto( '/' );
	const after = await page.locator( '.hero__glow' ).evaluate( ( el ) => {
		const style = getComputedStyle( el, '::after' );
		return { background: style.backgroundColor, filter: style.filter };
	} );
	expect( after.filter ).toBe( 'none' );
	expect( after.background ).toBe( 'rgba(0, 0, 0, 0)' );
} );

test( 'moon glows pure white, sun glows the palest yellow', async ( { page } ) => {
	await page.goto( '/' );
	const orb = page.locator( '.theme-orb' );
	await expect( orb ).toHaveCSS( 'filter', /drop-shadow\(rgb\(255, 255, 255\)/ );

	await page.getByRole( 'button', { name: 'Toggle between Blazing Sun and White Moon' } ).click();
	await expect( orb ).toHaveCSS( 'filter', /drop-shadow\(rgb\(255, 254, 240\)/ );
} );

test( 'cards, header, footer and language menu share the same light glass', async ( { page } ) => {
	await page.goto( '/' );
	await page.locator( '.lang-picker__current' ).click();
	const glass = await page.evaluate( () => [ '.site-header::before', '.site-footer', '.lang-picker__menu', '.card', '.stat', '.job', '.quote', '.about__copy', '.project', '.cv-card', '.contact__box' ]
		.map( ( selector ) => {
			const [ element, pseudo ] = selector.split( '::' );
			const style = getComputedStyle( document.querySelector( element ), pseudo ? `::${ pseudo }` : null );
			return { selector, background: style.backgroundColor, blur: style.backdropFilter };
		} ) );

	const [ first ] = glass;
	for ( const surface of glass ) {
		expect( surface, surface.selector ).toEqual( { ...first, selector: surface.selector } );
	}
	const alpha = Number( first.background.match( /rgba\(.*,\s*([\d.]+)\)/ )[ 1 ] );
	expect( alpha, 'very transparent' ).toBeLessThanOrEqual( 0.06 );
	const blurPx = Number( first.blur.match( /blur\(([\d.]+)px\)/ )[ 1 ] );
	expect( blurPx, 'only a little blurry' ).toBeLessThanOrEqual( 8 );
} );

test( '<picture> wrappers do not shift icons (logo flush left, toggle icon centred)', async ( { page } ) => {
	await page.goto( '/' );
	const layout = await page.evaluate( () => {
		const box = ( selector ) => document.querySelector( selector ).getBoundingClientRect();
		const brand = box( '.brand' );
		const logo = box( '.brand__logo' );
		const toggle = box( '.theme-toggle' );
		const icon = box( '.theme-toggle img' );
		return {
			logoOffset: Math.round( ( logo.left + ( logo.width / 2 ) ) - ( brand.left + 22.5 ) ),
			iconOffset: Math.round( ( icon.left + ( icon.width / 2 ) ) - ( toggle.left + ( toggle.width / 2 ) ) ),
		};
	} );
	expect( layout ).toEqual( { logoOffset: 0, iconOffset: 0 } );
} );

test( 'language menu really blurs the page: no ancestor is a backdrop root (header glass lives on ::before)', async ( { page } ) => {
	await page.goto( '/' );
	const ancestorsWithBackdrop = await page.locator( '.lang-picker__menu' ).evaluate( ( menu ) => {
		const found = [];
		for ( let el = menu.parentElement; el; el = el.parentElement ) {
			const style = getComputedStyle( el );
			if ( style.backdropFilter !== 'none' || style.filter !== 'none' ) {
				found.push( el.className || el.tagName );
			}
		}
		return found;
	} );
	expect( ancestorsWithBackdrop ).toEqual( [] );
} );

test( 'timeline dots sit exactly on the centre of the line', async ( { page } ) => {
	await page.goto( '/' );
	const offsets = await page.evaluate( () => {
		const timeline = document.querySelector( '.timeline' );
		const line = getComputedStyle( timeline, '::before' );
		const centre = timeline.getBoundingClientRect().left + parseFloat( line.left ) + ( parseFloat( line.width ) / 2 );
		return [ ...document.querySelectorAll( '.job__date' ) ].map( ( date ) => {
			const dot = getComputedStyle( date, '::before' );
			return Math.abs( date.getBoundingClientRect().left + parseFloat( dot.left ) + ( parseFloat( dot.width ) / 2 ) - centre );
		} );
	} );
	expect( offsets.length ).toBe( 5 );
	for ( const offset of offsets ) {
		expect( offset ).toBeLessThanOrEqual( 0.25 );
	}
} );

test( 'the quote uses the normal (non-italic) sans font', async ( { page } ) => {
	await page.goto( '/' );
	const quote = page.locator( '.quote' );
	await expect( quote ).toHaveCSS( 'font-style', 'normal' );
	expect( await quote.evaluate( ( el ) => getComputedStyle( el ).fontFamily ) ).not.toContain( 'Instrument Serif' );
} );

test( 'there is generous space below the hero heading', async ( { page } ) => {
	await page.goto( '/' );
	const gap = await page.evaluate( () => document.querySelector( '.hero__lead' ).getBoundingClientRect().top - document.querySelector( '.hero h1' ).getBoundingClientRect().bottom );
	expect( gap ).toBeGreaterThanOrEqual( 40 );
} );

test( 'the about text sits in a glass card like the quote, with matching height', async ( { page, isMobile } ) => {
	await page.goto( '/' );
	const copy = page.locator( '.about__copy' );
	await expect( copy ).toHaveClass( /\bpanel\b/ );
	if ( isMobile ) {
		return; // stacked on phones, so heights naturally differ
	}
	const [ quoteBox, copyBox ] = await Promise.all( [ page.locator( '.quote' ).boundingBox(), copy.boundingBox() ] );
	expect( Math.abs( quoteBox.height - copyBox.height ) ).toBeLessThanOrEqual( 1 );
} );

test( 'light theme: clouds drift in front of the sun, but behind the page content', async ( { page } ) => {
	await page.goto( '/' );
	await page.getByRole( 'button', { name: 'Toggle between Blazing Sun and White Moon' } ).click();
	const z = await page.evaluate( () => {
		const zIndex = ( selector ) => Number( getComputedStyle( document.querySelector( selector ) ).zIndex );
		return { sun: zIndex( '.theme-orb' ), clouds: zIndex( '.sky--clouds' ), content: zIndex( 'main' ), header: zIndex( '.site-header' ) };
	} );
	expect( z.clouds ).toBeGreaterThan( z.sun );
	expect( z.content ).toBeGreaterThan( z.clouds );
	expect( z.header ).toBeGreaterThan( z.clouds );
} );
