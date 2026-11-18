import { test, expect } from '@playwright/test';

test.beforeEach( async ( { page } ) => {
	page.imageRequests = [];
	page.on( 'request', ( request ) => {
		if ( request.url().includes( '/images/responsive/' ) ) {
			page.imageRequests.push( new URL( request.url() ).pathname );
		}
	} );
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: 1 } } ) );
	await page.goto( '/' );
	await page.waitForLoadState( 'load' );
} );

const orbFile = ( page ) => page.locator( '.theme-orb:visible img' ).evaluate( ( img ) => new URL( img.currentSrc ).pathname );

const chosen = ( page, selector ) => page.locator( selector ).evaluate( ( img ) => ( {
	inPicture: img.parentElement.tagName === 'PICTURE',
	hasWebpSource: Boolean( img.parentElement.querySelector( 'source[type="image/webp"]' ) ),
	currentSrc: img.currentSrc,
	width: Number( img.currentSrc.match( /-(\d+)\.\w+$/ )?.[ 1 ] ),
	cssWidth: img.getBoundingClientRect().width,
	dpr: window.devicePixelRatio,
} ) );

for ( const [ name, selector ] of [
	[ 'sun/moon orb', '.theme-orb:visible img' ],
	[ 'header logo', '.brand__logo:visible' ],
	[ 'theme toggle icon', '.theme-toggle img:visible' ],
	[ 'current language flag', '.lang-picker__current img' ],
] ) {
	test( `${ name }: <picture> with WebP, and the browser downloads a size that fits the slot`, async ( { page } ) => {
		const image = await chosen( page, selector );
		expect( image.inPicture ).toBe( true );
		expect( image.hasWebpSource ).toBe( true );
		expect( image.currentSrc ).toMatch( /\/images\/responsive\/.+-\d+\.webp$/ );
		// Sharp enough: at least displayed size × DPR (unless the source itself is smaller).
		expect( image.width ).toBeGreaterThanOrEqual( Math.min( Math.ceil( image.cssWidth * image.dpr ), 426 ) );
		// Not wasteful: a right-sized file, or the larger file the orb already downloaded (browsers reuse cached candidates).
		const reusesOrbFile = new URL( image.currentSrc ).pathname === await orbFile( page );
		expect( reusesOrbFile || image.width <= Math.max( image.cssWidth * image.dpr * 2, 48 ) ).toBe( true );
	} );
}

test( 'no wasted downloads: one large orb file at most, nothing requested twice, no fallback formats', async ( { page } ) => {
	const large = page.imageRequests.filter( ( path ) => /-(240|330|426)\./.test( path ) );
	expect( large.length, large.join( ', ' ) ).toBeLessThanOrEqual( 1 );
	expect( new Set( page.imageRequests ).size ).toBe( page.imageRequests.length );
	expect( page.imageRequests.filter( ( path ) => ! path.endsWith( '.webp' ) ) ).toEqual( [] );
} );

test( 'switching theme swaps every themed picture to the sun', async ( { page } ) => {
	await page.getByRole( 'button', { name: 'Toggle between Blazing Sun and White Moon' } ).click();
	for ( const selector of [ '.theme-orb:visible img', '.brand__logo:visible', '.theme-toggle img:visible' ] ) {
		await expect.poll( async () => ( await chosen( page, selector ) ).currentSrc ).toMatch( /\/sun-\d+\.webp$/ );
	}
} );

test( 'choosing a language swaps the current flag picture', async ( { page } ) => {
	await page.locator( '.lang-picker__current' ).click();
	await page.getByRole( 'button', { name: 'Français' } ).click();
	await expect.poll( async () => ( await chosen( page, '.lang-picker__current img' ) ).currentSrc ).toMatch( /\/flag-fr-\d+\.webp$/ );
} );
