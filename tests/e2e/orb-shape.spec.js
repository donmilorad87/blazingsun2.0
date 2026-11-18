import { test, expect } from '@playwright/test';

// Measured from the source images (alpha, median over 360 directions so the sun's flares do not count):
const SUN_DISC = 0.697; // sun body diameter / image width
const MOON_DISC = 0.793; // moon disc diameter / image width
const GLOW_INSET = 50; // px: the glow circle's edge sits this far inside the disc

test.beforeEach( async ( { page } ) => {
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: 1 } } ) );
	await page.goto( '/' );
	await page.waitForFunction( () => document.documentElement.dataset.orb === 'idle' );
} );

const imageWidth = ( page, theme ) => page.locator( `.theme-orb[data-theme-only="${ theme }"] img` ).evaluate( ( img ) => img.getBoundingClientRect().width );
const toggleTheme = async ( page ) => {
	await page.getByRole( 'button', { name: 'Toggle between Blazing Sun and White Moon' } ).click();
	await page.waitForFunction( () => document.documentElement.classList.contains( 'light' ) && document.documentElement.dataset.orb === 'idle' );
};

test( 'the moon disc has the same diameter as the sun body (not its flares)', async ( { page } ) => {
	const moonDisc = ( await imageWidth( page, 'dark' ) ) * MOON_DISC;
	await toggleTheme( page );
	const sunDisc = ( await imageWidth( page, 'light' ) ) * SUN_DISC;
	expect( Math.abs( moonDisc - sunDisc ) ).toBeLessThanOrEqual( 2 );
} );

test( 'the glow comes from behind the disc: a disc-sized circle behind the image, nothing on the image itself', async ( { page } ) => {
	for ( const [ theme, colour ] of [ [ 'dark', 'rgb(255, 255, 255)' ], [ 'light', 'rgb(255, 254, 240)' ] ] ) {
		if ( theme === 'light' ) {
			await toggleTheme( page );
		}
		const glow = await page.locator( `.theme-orb[data-theme-only="${ theme }"]` ).evaluate( ( orb ) => {
			const before = getComputedStyle( orb, '::before' );
			const img = getComputedStyle( orb.querySelector( 'img' ) );
			return { diameter: parseFloat( before.width ), shadow: before.boxShadow, zIndex: before.zIndex, imgShadow: img.boxShadow, imgFilter: img.filter, orbWidth: orb.getBoundingClientRect().width };
		} );
		expect( glow.shadow.startsWith( colour ), `${ theme } glow colour` ).toBe( true );
		expect( glow.zIndex ).toBe( '-1' );
		expect( Math.abs( glow.diameter - ( ( glow.orbWidth * SUN_DISC ) - ( 2 * GLOW_INSET ) ) ) ).toBeLessThanOrEqual( 2 );
		expect( glow.diameter ).toBeLessThan( glow.orbWidth );
		expect( glow.imgShadow ).toBe( 'none' );
		expect( glow.imgFilter ).toBe( 'none' );
	}
} );

test( 'desktop: the hero glow spot is the size of the disc, smaller than the images', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'desktop layout only' );
	const spot = await page.locator( '.hero__glow' ).boundingBox();
	const orb = await page.locator( '.theme-orb:visible' ).boundingBox();
	expect( Math.abs( spot.width - spot.height ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( spot.width - ( orb.width * SUN_DISC ) ) ).toBeLessThanOrEqual( 2 );
} );

test( 'the glow is clearly visible: the ring just outside the disc is much brighter than the sky further out', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'measured on the desktop-size orb' );
	const sharp = ( await import( 'sharp' ) ).default;
	const orb = page.locator( '.theme-orb[data-theme-only="dark"]' );
	const box = await orb.boundingBox();
	const png = await orb.screenshot( { scale: 'css' } );
	const { data, info } = await sharp( png ).removeAlpha().raw().toBuffer( { resolveWithObject: true } );

	const discRadius = ( box.width * SUN_DISC ) / 2;
	const meanLuminance = ( fromRadius, toRadius ) => {
		let sum = 0;
		let count = 0;
		for ( let angle = 0; angle < 360; angle += 3 ) {
			for ( let r = fromRadius; r <= toRadius; r += 2 ) {
				const x = Math.round( ( info.width / 2 ) + ( r * Math.cos( angle * Math.PI / 180 ) ) );
				const y = Math.round( ( info.height / 2 ) + ( r * Math.sin( angle * Math.PI / 180 ) ) );
				if ( x < 0 || y < 0 || x >= info.width || y >= info.height ) {
					continue;
				}
				const i = ( ( y * info.width ) + x ) * info.channels;
				sum += ( 0.2126 * data[ i ] ) + ( 0.7152 * data[ i + 1 ] ) + ( 0.0722 * data[ i + 2 ] );
				count++;
			}
		}
		return sum / count;
	};
	const glowRing = meanLuminance( discRadius + 6, discRadius + 30 );
	const farSky = meanLuminance( ( box.width / 2 ) - 12, ( box.width / 2 ) - 2 );
	expect( glowRing - farSky, `glow ring ${ glowRing.toFixed( 0 ) } vs sky ${ farSky.toFixed( 0 ) }` ).toBeGreaterThanOrEqual( 45 );
	expect( glowRing, 'glow must not blow out to solid white' ).toBeLessThan( 235 );
} );
