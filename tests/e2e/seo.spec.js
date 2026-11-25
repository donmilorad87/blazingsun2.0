import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { HtmlValidate } from 'html-validate';

// The same rules as `npm run lint:html` (.htmlvalidate.json), applied to the PHP-rendered pages.
const htmlRules = JSON.parse( readFileSync( new URL( '../../.htmlvalidate.json', import.meta.url ), 'utf8' ) );

const LANGUAGES = [ [ '/', 'en' ], [ '/sr/', 'sr' ], [ '/fr/', 'fr' ] ];

test( 'every language page is valid HTML', async ( { request } ) => {
	const validator = new HtmlValidate( htmlRules );
	for ( const [ path ] of LANGUAGES ) {
		const report = await validator.validateString( await ( await request.get( path ) ).text() );
		const messages = report.results.flatMap( ( result ) => result.messages.map( ( message ) => `${ message.line }:${ message.column } ${ message.ruleId } ${ message.message }` ) );
		expect( messages, path ).toEqual( [] );
	}
} );

test( 'each language has its own URL, lang attribute, canonical and the full set of hreflang alternates', async ( { page, baseURL } ) => {
	for ( const [ path, lang ] of LANGUAGES ) {
		await page.goto( path );
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'lang', lang );
		await expect( page.locator( 'link[rel="canonical"]' ) ).toHaveAttribute( 'href', `${ baseURL }${ path }` );
		const alternates = await page.locator( 'link[rel="alternate"][hreflang]' ).evaluateAll( ( links ) => Object.fromEntries( links.map( ( link ) => [ link.hreflang, link.getAttribute( 'href' ) ] ) ) );
		expect( alternates ).toEqual( { sr: `${ baseURL }/sr/`, en: `${ baseURL }/`, fr: `${ baseURL }/fr/`, 'x-default': `${ baseURL }/` } );
	}
} );

test( 'the main language has no second URL, and unknown languages are 404', async ( { request } ) => {
	const redirect = await request.get( '/en/', { maxRedirects: 0 } );
	expect( redirect.status() ).toBe( 301 );
	expect( redirect.headers().location ).toBe( '/' );
	expect( ( await request.get( '/de/' ) ).status() ).toBe( 404 );
} );

test( 'the JSON-LD graph links the page, website, company (a LocalBusiness) and person', async ( { page } ) => {
	await page.goto( '/sr/' );
	const graph = JSON.parse( await page.locator( 'script[type="application/ld+json"]' ).textContent() )[ '@graph' ];
	const byType = Object.fromEntries( graph.map( ( node ) => [ node[ '@type' ], node ] ) );
	const ids = new Set( graph.map( ( node ) => node[ '@id' ] ) );

	expect( Object.keys( byType ) ).toEqual( [ 'Person', 'ProfessionalService', 'WebSite', 'WebPage' ] );
	expect( byType.ProfessionalService.founder[ '@id' ] ).toBe( byType.Person[ '@id' ] );
	expect( byType.Person.worksFor[ '@id' ] ).toBe( byType.ProfessionalService[ '@id' ] );
	expect( byType.WebSite.publisher[ '@id' ] ).toBe( byType.ProfessionalService[ '@id' ] );
	expect( byType.WebPage.isPartOf[ '@id' ] ).toBe( byType.WebSite[ '@id' ] );
	expect( byType.WebPage.inLanguage ).toBe( 'sr' );
	// Every reference points at a node in the graph.
	const references = JSON.stringify( graph ).match( /\{"@id":"[^"]+"\}/g ).map( ( ref ) => JSON.parse( ref )[ '@id' ] );
	expect( references.filter( ( id ) => ! ids.has( id ) ) ).toEqual( [] );
} );
