import { test, expect } from '@playwright/test';

// Records every class change on <html> from the very first moment, plus every image request.
const watch = async ( page, { theme } = {} ) => {
	page.imageRequests = [];
	page.on( 'request', ( request ) => {
		if ( request.url().includes( '/images/responsive/' ) ) {
			page.imageRequests.push( new URL( request.url() ).pathname );
		}
	} );
	await page.addInitScript( ( savedTheme ) => {
		if ( savedTheme ) {
			window.localStorage.setItem( 'blazing-theme', savedTheme );
		}
		// Init scripts run before <html> exists, so observe the document and pick out <html> class changes.
		window.__htmlClasses = [];
		new MutationObserver( ( records ) => records
			.filter( ( record ) => record.target === document.documentElement )
			.forEach( () => window.__htmlClasses.push( document.documentElement.className ) ) )
			.observe( document, { attributes: true, attributeFilter: [ 'class' ], subtree: true } );
	}, theme );
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: 1 } } ) );
};

const visibleOrb = ( page ) => page.locator( '.theme-orb:visible' );

// ThemeOrb reports <html data-orb="idle"> once the drop-in animation has really finished.
const settled = ( page ) => page.waitForFunction( () => document.documentElement.dataset.orb === 'idle' );

test( 'light theme: the moon is never shown or downloaded, the sun is there from the start', async ( { page } ) => {
	await watch( page, { theme: 'light' } );
	await page.goto( '/' );
	await settled( page );

	await expect( visibleOrb( page ) ).toHaveAttribute( 'data-theme-only', 'light' );
	await expect( page.locator( '.brand__name:visible' ) ).toHaveText( 'Blazing Sun' );
	expect( page.imageRequests.filter( ( path ) => path.includes( '/moon-' ) ), 'moon files requested in light mode' ).toEqual( [] );
	// The brand name was correct from the first parse: no JS text swap (no element ever had "White Moon" visible).
	expect( await page.evaluate( () => window.__htmlClasses[ 0 ] ?? document.documentElement.className ) ).toContain( 'light' );
} );

test( 'dark theme: the sun is not downloaded until the theme is switched', async ( { page } ) => {
	await watch( page );
	await page.goto( '/' );
	await settled( page );
	expect( page.imageRequests.filter( ( path ) => path.includes( '/sun-' ) ) ).toEqual( [] );

	await page.getByRole( 'button', { name: 'Toggle between Blazing Sun and White Moon' } ).click();
	await expect( visibleOrb( page ) ).toHaveAttribute( 'data-theme-only', 'light' );
	await expect.poll( () => page.imageRequests.some( ( path ) => path.includes( '/sun-' ) ) ).toBe( true );
} );

test( 'the orb stays hidden until it is in its final place (no jump)', async ( { page, isMobile } ) => {
	await watch( page );
	await page.goto( '/' );
	await settled( page );

	const history = await page.evaluate( () => window.__htmlClasses );
	const firstVisible = history.find( ( classes ) => ! classes.split( ' ' ).includes( 'orb-pending' ) );
	expect( history.some( ( classes ) => classes.includes( 'orb-pending' ) ) || firstVisible ).toBeTruthy();
	if ( ! isMobile ) {
		expect( firstVisible.split( ' ' ), 'became visible before being placed' ).toContain( 'orb-placed' );
	}
} );

test( 'the orb drops in from the top with an animation, then rests at its normal opacity', async ( { page } ) => {
	await watch( page );
	await page.goto( '/' );
	const animationName = await visibleOrb( page ).evaluate( async ( img ) => {
		for ( let i = 0; i < 100 && ! img.getAnimations().length; i++ ) {
			await new Promise( ( resolve ) => setTimeout( resolve, 30 ) );
		}
		return img.getAnimations()[ 0 ]?.animationName;
	} );
	expect( animationName ).toBe( 'orb-arrive' );

	await settled( page );
	const opacity = Number( await visibleOrb( page ).evaluate( ( img ) => getComputedStyle( img ).opacity ) );
	expect( opacity ).toBeGreaterThan( 0.5 );
	expect( opacity ).toBeLessThan( 0.8 );
} );

test( 'switching theme: the moon rises out, THEN the theme changes, THEN the sun drops in (and back)', async ( { page } ) => {
	await watch( page );
	await page.goto( '/' );
	await settled( page );
	const toggle = page.getByRole( 'button', { name: 'Toggle between Blazing Sun and White Moon' } );

	// Record, inside the page, what happens in which order (listeners registered once; the log is reset per round).
	await page.evaluate( () => {
		window.__steps = [];
		const log = ( step ) => window.__steps.push( step );
		// Capture phase: logged before the orb's own handlers react (they start the theme switch).
		document.addEventListener( 'animationstart', ( event ) => log( `start:${ event.animationName }:${ event.target.dataset.themeOnly }` ), true );
		document.addEventListener( 'animationend', ( event ) => log( `end:${ event.animationName }:${ event.target.dataset.themeOnly }` ), true );
		document.addEventListener( 'themechange', ( event ) => log( `theme:${ event.detail.isLight ? 'light' : 'dark' }` ) );
	} );

	for ( const [ leaving, arriving, lightAfter ] of [ [ 'dark', 'light', true ], [ 'light', 'dark', false ] ] ) {
		await page.evaluate( () => {
			window.__steps = [];
		} );
		await toggle.click();
		await expect( page.locator( 'html' ) ).toHaveClass( lightAfter ? /\blight\b/ : /^(?!.*\blight\b)/ );
		await settled( page );

		const steps = await page.evaluate( () => window.__steps.filter( ( step ) => ! step.includes( 'undefined' ) ) );
		expect( steps ).toEqual( [
			`start:orb-leave:${ leaving }`,
			`end:orb-leave:${ leaving }`,
			`theme:${ arriving }`,
			`start:orb-arrive:${ arriving }`,
			`end:orb-arrive:${ arriving }`,
		] );
		await expect( visibleOrb( page ) ).toHaveAttribute( 'data-theme-only', arriving );
	}
} );

test( 'clicks during the sun/moon transition are ignored (no half-switched state)', async ( { page } ) => {
	await watch( page );
	await page.goto( '/' );
	await settled( page );
	const toggle = page.getByRole( 'button', { name: 'Toggle between Blazing Sun and White Moon' } );
	await toggle.click();
	await toggle.click();
	await toggle.click();
	await settled( page );
	await expect( page.locator( 'html' ) ).toHaveClass( /\blight\b/ );
	await expect( toggle ).toHaveAttribute( 'aria-pressed', 'true' );
} );

test( 'reduced motion: no drop-in animation, the orb simply appears in place', async ( { page } ) => {
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	await watch( page );
	await page.goto( '/' );
	await settled( page );
	await expect( visibleOrb( page ) ).toBeVisible();
	expect( await visibleOrb( page ).evaluate( ( img ) => img.getAnimations().length ) ).toBe( 0 );
} );

test( 'desktop: the orb flies straight to its final spot: target fixed before it starts, no overshoot, no correction', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'centring on the hero spot is desktop only' );
	await watch( page );
	// Sample the orb's centre every frame from the moment it starts animating.
	await page.addInitScript( () => {
		window.__orbPath = [];
		document.addEventListener( 'animationstart', ( event ) => {
			if ( event.animationName !== 'orb-arrive' ) {
				return;
			}
			const target = { x: document.documentElement.style.getPropertyValue( '--orb-x' ), y: document.documentElement.style.getPropertyValue( '--orb-y' ) };
			window.__orbTargetAtStart = target;
			const sample = () => {
				const rect = event.target.getBoundingClientRect();
				window.__orbPath.push( { x: rect.left + ( rect.width / 2 ), y: rect.top + ( rect.height / 2 ), w: rect.width } );
				if ( event.target.getAnimations().length ) {
					requestAnimationFrame( sample );
				}
			};
			requestAnimationFrame( sample );
		}, true );
	} );
	await page.goto( '/' );
	await settled( page );
	await page.waitForTimeout( 800 ); // fonts, late layout: nothing may move the orb after it landed

	const { path, startTarget, finalTarget, rest } = await page.evaluate( () => {
		const orb = [ ...document.querySelectorAll( '.theme-orb' ) ].find( ( img ) => img.checkVisibility() );
		const rect = orb.getBoundingClientRect();
		return {
			path: window.__orbPath,
			startTarget: window.__orbTargetAtStart,
			finalTarget: { x: document.documentElement.style.getPropertyValue( '--orb-x' ), y: document.documentElement.style.getPropertyValue( '--orb-y' ) },
			rest: { x: rect.left + ( rect.width / 2 ), y: rect.top + ( rect.height / 2 ) },
		};
	} );

	expect( startTarget, 'the spot was re-measured after the orb started flying' ).toEqual( finalTarget );
	expect( path.length ).toBeGreaterThan( 5 );
	for ( const point of path ) {
		expect( Math.abs( point.x - rest.x ), 'drifts sideways' ).toBeLessThanOrEqual( 1 );
		expect( point.y, 'goes past its final spot (overshoot)' ).toBeLessThanOrEqual( rest.y + 1 );
	}
	for ( let i = 1; i < path.length; i++ ) {
		expect( path[ i ].y, 'moves back up (correction)' ).toBeGreaterThanOrEqual( path[ i - 1 ].y - 1 );
	}
} );
