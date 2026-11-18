import { test, expect } from '@playwright/test';

test.beforeEach( async ( { page } ) => {
	await page.route( '**/api/visits', ( route ) => route.fulfill( { json: { total: 1 } } ) );
	await page.goto( '/' );
} );

const chooseLanguage = async ( page, name ) => {
	await page.locator( '.lang-picker__current' ).click();
	await page.getByRole( 'button', { name } ).click();
};

test( 'the expanded CV section is translated (Serbian and French)', async ( { page } ) => {
	const section = page.locator( '#cv-highlights' );

	await chooseLanguage( page, 'Srpski' );
	await expect( section.locator( 'h2' ) ).toHaveText( 'Više od tehnologija.' );
	await expect( section.locator( '.eyebrow' ) ).toHaveText( '05 / Prošireni CV' );
	await expect( section.locator( '.cv-card h3' ).first() ).toHaveText( 'AI inženjering' );
	await expect( section.locator( '.cv-card h3' ).nth( 3 ) ).toHaveText( 'Obrazovanje' );

	await chooseLanguage( page, 'Français' );
	await expect( section.locator( 'h2' ) ).toHaveText( 'Plus qu’une stack.' );
	await expect( section.locator( '.cv-card h3' ).nth( 3 ) ).toHaveText( 'Formation' );

	await chooseLanguage( page, 'English' );
	await expect( section.locator( 'h2' ) ).toHaveText( 'More than a stack.' );
} );

test( 'sections are numbered in page order', async ( { page } ) => {
	const numbers = await page.locator( 'main section .section-head .eyebrow, #contact .eyebrow' )
		.evaluateAll( ( eyebrows ) => eyebrows.map( ( el ) => el.textContent.trim().slice( 0, 2 ) ) );
	expect( numbers ).toEqual( [ '01', '02', '03', '04', '05', '06' ] );
} );

test( 'every section description sits below its title, left-aligned with it', async ( { page } ) => {
	const heads = await page.locator( '.section-head' ).evaluateAll( ( list ) => list.map( ( head ) => {
		const title = head.querySelector( 'h2' ).getBoundingClientRect();
		const text = head.querySelector( ':scope > p' ).getBoundingClientRect();
		return { below: text.top >= title.bottom, sameLeft: Math.abs( text.left - title.left ) < 1 };
	} ) );
	expect( heads.length ).toBe( 5 );
	for ( const head of heads ) {
		expect( head ).toEqual( { below: true, sameLeft: true } );
	}
} );

test( 'the hero banner reads naturally in every language', async ( { page } ) => {
	const hero = {
		kicker: page.locator( '.hero .eyebrow' ),
		title: page.locator( '.hero h1' ),
		lead: page.locator( '.hero__lead' ),
		primary: page.locator( '.hero .btn--primary' ),
		secondary: page.locator( '.hero .btn--ghost' ),
		available: page.locator( '.hero__available span' ),
	};

	await expect( hero.title ).toHaveText( 'Building digital systems' );
	await expect( hero.title.locator( 'em' ) ).toHaveText( 'digital' );

	await chooseLanguage( page, 'Srpski' );
	await expect( hero.kicker ).toHaveText( 'Senior full-stack web inženjer' );
	await expect( hero.title ).toHaveText( 'Gradimo digitalne sisteme' );
	await expect( hero.title.locator( 'em' ) ).toHaveText( 'digitalne' );
	await expect( hero.lead ).toContainText( 'više od 15 godina iskustva' );
	await expect( hero.primary ).toHaveText( 'Sarađujmo ↗' );
	await expect( hero.secondary ).toHaveText( 'Pogledajte projekte' );
	await expect( hero.available ).toHaveText( 'Iz Gornjeg Milanovca, Srbija · Otvoren za ambiciozne projekte' );

	await chooseLanguage( page, 'Français' );
	await expect( hero.kicker ).toHaveText( 'Ingénieur web full-stack senior' );
	await expect( hero.title ).toHaveText( 'Construire des systèmes numériques' );
	await expect( hero.title.locator( 'em' ) ).toHaveText( 'numériques' );
	await expect( hero.primary ).toHaveText( 'Travaillons ensemble ↗' );
	await expect( hero.secondary ).toHaveText( 'Découvrir les projets' );
} );

for ( const height of [ 900, 1200 ] ) {
	test( `desktop ${ height }px tall: the hero fills the screen between header and footer, content vertically centred (also in French)`, async ( { page, isMobile } ) => {
		test.skip( isMobile, 'desktop layout only' );
		await page.setViewportSize( { width: 1440, height } );
		const measure = () => page.evaluate( () => {
			const box = ( selector ) => document.querySelector( selector ).getBoundingClientRect();
			const header = box( '.site-header' );
			const footer = box( '.site-footer' );
			const hero = box( '.hero' );
			const grid = box( '.hero__grid' );
			return {
				heroTop: Math.round( hero.top - header.bottom ),
				heroFillsGap: Math.round( hero.height - ( window.innerHeight - header.height - footer.height ) ),
				gridOffCentre: Math.round( ( grid.top + ( grid.height / 2 ) ) - ( hero.top + ( hero.height / 2 ) ) ),
			};
		} );
		expect( await measure() ).toEqual( { heroTop: 0, heroFillsGap: 0, gridOffCentre: 0 } );

		await chooseLanguage( page, 'Français' );
		expect( await measure() ).toEqual( { heroTop: 0, heroFillsGap: 0, gridOffCentre: 0 } );
	} );
}
