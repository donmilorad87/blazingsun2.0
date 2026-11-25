import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'node:url';

// Runs after the public page tests (see playwright.config.js) and in order: each test changes the content.
test.describe.configure( { mode: 'serial' } );

const FLAG = fileURLToPath( new URL( '../../../images-src/flags/fr.svg', import.meta.url ) );
const LOGO = fileURLToPath( new URL( '../../../images-src/moon.png', import.meta.url ) );

async function login( page, password = 'e2e-password' ) {
	await page.goto( '/adminportal/' );
	await page.getByLabel( 'Username' ).fill( 'e2e-admin' );
	await page.getByLabel( 'Password' ).fill( password );
	await page.getByRole( 'button', { name: 'Log in' } ).click();
}

async function open( page, screen ) {
	await page.getByRole( 'navigation', { name: 'Sections' } ).getByRole( 'link', { name: screen, exact: true } ).click();
}

const saved = ( page ) => expect( page.locator( '.toast' ) ).toHaveText( 'Saved. The site is updated.' );

test( 'the API refuses anonymous and cross-site requests', async ( { request } ) => {
	expect( ( await request.get( '/api/admin/content' ) ).status() ).toBe( 401 );
	const loginResponse = await request.post( '/api/admin/login', { data: { username: 'e2e-admin', password: 'e2e-password' } } );
	expect( loginResponse.ok() ).toBe( true );
	expect( ( await request.post( '/api/admin/settings', { data: { scope: 'footer', values: {} } } ) ).status() ).toBe( 403 );
} );

test( 'login: a wrong password is rejected, the right one opens the portal', async ( { page } ) => {
	await login( page, 'wrong' );
	await expect( page.getByRole( 'alert' ) ).toHaveText( 'Wrong username or password' );
	await page.getByLabel( 'Password' ).fill( 'e2e-password' );
	await page.getByRole( 'button', { name: 'Log in' } ).click();
	await expect( page.getByRole( 'heading', { name: 'Header', exact: true } ) ).toBeVisible();
	await expect( page.locator( 'meta[name="robots"]' ) ).toHaveAttribute( 'content', 'noindex, nofollow' );
} );

test( 'editing the home banner in two languages updates both pages', async ( { page } ) => {
	await login( page );
	await open( page, 'Home banner' );
	await page.getByRole( 'textbox', { name: 'Title · English (main)', exact: true } ).fill( 'Shipping <em>fast</em> systems' );
	await page.getByRole( 'tab', { name: /Srpski/ } ).click();
	await page.getByRole( 'textbox', { name: 'Title · Srpski', exact: true } ).fill( 'Brzi sistemi' );
	await page.getByRole( 'button', { name: 'Save' } ).click();
	await saved( page );

	await page.goto( '/' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Shipping fast systems' );
	await expect( page.locator( 'h1 em' ) ).toHaveText( 'fast' );
	await page.goto( '/sr/' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Brzi sistemi' );
} );

test( 'required fields are checked and marked', async ( { page } ) => {
	await login( page );
	await open( page, 'Experience' );
	await page.getByRole( 'textbox', { name: 'Title · English (main)', exact: true } ).fill( '' );
	await page.getByRole( 'button', { name: 'Save' } ).click();

	const title = page.getByRole( 'textbox', { name: 'Title · English (main)', exact: true } );
	await expect( title ).toHaveAttribute( 'aria-invalid', '' );
	await expect( page.locator( '.field__error:visible' ).first() ).toHaveText( 'Required in the main language' );
	await page.goto( '/' );
	await expect( page.locator( '#experience h2' ) ).toHaveText( 'A decade-plus of shipping.' );
} );

test( 'items can be added, reordered and removed', async ( { page } ) => {
	await login( page );
	await open( page, 'Capabilities' );
	await page.getByRole( 'button', { name: '+ Add to Capabilities' } ).click();
	const card = page.locator( '.item' ).last();
	await card.getByRole( 'textbox', { name: 'Title · English (main)', exact: true } ).fill( 'Testing' );
	await card.getByRole( 'textbox', { name: 'Text · English (main)', exact: true } ).fill( '<b>Playwright</b> end to end' );
	await card.getByRole( 'button', { name: 'Move up' } ).click();
	await page.getByRole( 'button', { name: 'Save' } ).click();
	await saved( page );

	await page.goto( '/' );
	const titles = page.locator( '#stack .card h3' );
	await expect( titles ).toHaveCount( 7 );
	await expect( titles.nth( 5 ) ).toHaveText( 'Testing' );
	await expect( page.locator( '#stack .card b', { hasText: 'Playwright' } ) ).toBeAttached();

	await page.goto( '/adminportal/#stack' );
	await page.locator( '.item', { hasText: '6 · Testing' } ).getByRole( 'button', { name: 'Remove' } ).click();
	await page.getByRole( 'button', { name: 'Save' } ).click();
	await saved( page );
	await page.goto( '/' );
	await expect( titles ).toHaveCount( 6 );
} );

test( 'the main menu can hide a section and rename another', async ( { page } ) => {
	await login( page );
	await page.getByRole( 'checkbox', { name: 'Capabilities', exact: true } ).uncheck();
	await page.getByRole( 'textbox', { name: 'Profile · English (main)', exact: true } ).fill( 'Me' );
	await page.getByRole( 'button', { name: 'Save menu' } ).click();
	await saved( page );

	await page.goto( '/' );
	await expect( page.locator( '.site-nav__links a[href="#stack"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '.site-nav__links a[href="#about"]' ) ).toHaveText( 'Me' );
	await expect( page.locator( '#stack' ) ).toBeAttached();
} );

test( 'an uploaded logo replaces the dark-theme logo', async ( { page } ) => {
	await login( page );
	await page.locator( '[data-field="logo_dark"] input[type="file"]' ).setInputFiles( LOGO );
	await expect( page.locator( '.toast' ) ).toHaveText( 'Image uploaded. Save to use it on the site.' );
	await page.getByRole( 'button', { name: 'Save', exact: true } ).click();
	await saved( page );

	await page.goto( '/' );
	const logo = page.locator( '.brand__logo[data-theme-only="dark"]' );
	await expect( logo ).toHaveAttribute( 'src', /^\/uploads\/media\/[0-9a-f]{24}-426\.png$/ );
	const response = await page.request.get( await logo.getAttribute( 'src' ) );
	expect( response.ok() ).toBe( true );
} );

test( 'a new language gets its own page and disappears when deleted', async ( { page, request } ) => {
	await login( page );
	await open( page, 'Languages & labels' );
	await page.getByLabel( 'ISO 639-1 code' ).fill( 'de' );
	await page.getByLabel( 'ISO 639-2 code' ).fill( 'deu' );
	await page.getByLabel( 'Name' ).fill( 'Deutsch' );
	await page.getByLabel( 'Flag (SVG)' ).setInputFiles( FLAG );
	await expect( page.locator( '.form__status', { hasText: 'Flag uploaded.' } ) ).toBeVisible();
	await page.getByRole( 'button', { name: 'Save language' } ).click();
	await saved( page );

	const german = page.locator( '.language', { hasText: 'Deutsch' } );
	await expect( german ).toContainText( 'required texts not translated' );
	await page.goto( '/de/' );
	await expect( page.locator( 'html' ) ).toHaveAttribute( 'lang', 'de' );
	await expect( page.locator( 'link[hreflang="de"]' ) ).toHaveCount( 1 );
	await expect( page.locator( '#contact h2' ) ).toHaveText( 'Let’s build something sharp.' );

	await page.goto( '/adminportal/#languages' );
	page.once( 'dialog', ( dialog ) => dialog.accept() );
	await page.locator( '.language', { hasText: 'Deutsch' } ).getByRole( 'button', { name: 'Delete' } ).click();
	await saved( page );
	expect( ( await request.get( '/de/' ) ).status() ).toBe( 404 );
} );

test( 'the schema graph shows the linked nodes', async ( { page } ) => {
	await login( page );
	await open( page, 'Schema graph' );
	await expect( page.locator( '#schema-desc' ) ).toContainText( 'ProfessionalService founder Person' );
	await expect( page.locator( '.schema__node' ) ).toHaveCount( 4 );
} );
