import { h, uid } from './dom.js';
import { Api, ApiError } from './Api.js';
import { languagesScreen, menuScreen, schemaScreen, sectionScreen, settingsScreen } from './screens.js';

/** Sidebar entries, in page order. The id is also the URL hash (#experience), so a reload keeps the screen. */
const SCREENS = [
	{ id: 'header', label: 'Header & menu' },
	{ id: 'hero', label: 'Home banner', section: 'hero' },
	{ id: 'about', label: 'Profile', section: 'about' },
	{ id: 'stack', label: 'Capabilities', section: 'stack' },
	{ id: 'experience', label: 'Experience', section: 'experience' },
	{ id: 'projects', label: 'Selected work', section: 'projects' },
	{ id: 'cv', label: 'Expanded CV', section: 'cv' },
	{ id: 'contact', label: 'Contact', section: 'contact' },
	{ id: 'footer', label: 'Footer' },
	{ id: 'languages', label: 'Languages & labels' },
	{ id: 'schema', label: 'Schema graph' },
];

/** The admin portal: login, then a sidebar of screens. Every save returns the full content, and the screen re-renders from it. */
export class AdminApp {
	constructor( doc = document, api = new Api() ) {
		this.doc = doc;
		this.api = api;
		this.main = doc.querySelector( '#admin-main' );
		this.nav = doc.querySelector( '.admin__nav' );
		this.logoutButton = doc.querySelector( '[data-logout]' );
		this.toast = doc.querySelector( '.toast' );
		this.data = null;
		this.dirty = false;
	}

	async start() {
		this.logoutButton.addEventListener( 'click', () => this.logout() );
		this.doc.defaultView.addEventListener( 'hashchange', () => this.data && this.render() );
		this.doc.defaultView.addEventListener( 'beforeunload', ( event ) => {
			if ( this.dirty ) {
				event.preventDefault();
			}
		} );
		this.main.addEventListener( 'input', () => ( this.dirty = true ) );
		try {
			await this.api.session();
			await this.load();
		} catch ( error ) {
			if ( error instanceof ApiError && error.status === 401 ) {
				this.showLogin();
				return;
			}
			this.fail( error );
		}
	}

	async load() {
		this.data = await this.api.content();
		this.dirty = false;
		this.renderNav();
		this.render();
		this.logoutButton.hidden = false;
	}

	/** Runs a save call; the response is the new content, so the current screen is rebuilt from it. */
	async save( call ) {
		try {
			this.data = await call();
		} catch ( error ) {
			if ( error instanceof ApiError && error.status === 401 ) {
				this.notify( 'Your session expired. Log in again (copy anything you typed first).', true );
				this.showLogin();
			}
			throw error;
		}
		this.dirty = false;
		this.render();
		this.notify( 'Saved. The site is updated.' );
	}

	/** Shared by every field: languages, the main language, the media library and the uploader. */
	context() {
		return {
			languages: this.data.languages,
			main: this.data.languages.find( ( language ) => language.is_main )?.iso2,
			media: this.data.media,
			api: this.api,
			onMedia: ( media ) => ( this.data.media[ media.id ] = media ),
			onStatus: ( message, isError ) => this.notify( message, isError ),
		};
	}

	current() {
		const id = this.doc.defaultView.location.hash.slice( 1 );
		return SCREENS.find( ( screen ) => screen.id === id ) ?? SCREENS[ 0 ];
	}

	renderNav() {
		this.nav.hidden = false;
		this.nav.querySelector( 'ul' ).replaceChildren( ...SCREENS.map( ( screen ) => h( 'li', {}, h( 'a', { href: `#${ screen.id }`, class: 'admin__link', text: screen.label } ) ) ) );
	}

	render() {
		const screen = this.current();
		for ( const link of this.nav.querySelectorAll( 'a' ) ) {
			const active = link.hash === `#${ screen.id }`;
			link.classList.toggle( 'is-active', active );
			link.toggleAttribute( 'aria-current', active );
			if ( active ) {
				link.setAttribute( 'aria-current', 'page' );
			}
		}
		this.main.replaceChildren( this.screen( screen ) );
		this.doc.title = `${ screen.label } | Admin portal`;
	}

	screen( screen ) {
		if ( screen.section ) {
			return sectionScreen( this, screen.section );
		}
		switch ( screen.id ) {
			case 'header':
				return h( 'div', { class: 'stack' },
					settingsScreen( this, 'header', 'Header', 'Site names and images for both themes. The icon is also the theme switch and the sun/moon in the home banner.' ),
					menuScreen( this ) );
			case 'footer':
				return settingsScreen( this, 'footer', 'Footer', 'Shown as “© {year} {name} · {company}” and “{position} · {country}”.' );
			case 'languages':
				return languagesScreen( this );
			default:
				return schemaScreen( this );
		}
	}

	showLogin() {
		this.nav.hidden = true;
		this.logoutButton.hidden = true;
		const ids = { user: uid(), password: uid() };
		const error = h( 'p', { class: 'form__status is-error', role: 'alert' } );
		const button = h( 'button', { type: 'submit', class: 'btn btn--primary', text: 'Log in' } );
		const form = h( 'form', { class: 'form login', 'aria-labelledby': 'login-title' },
			h( 'h2', { id: 'login-title', class: 'form__title', text: 'Log in' } ),
			h( 'div', { class: 'field' }, h( 'label', { class: 'field__label', for: ids.user, text: 'Username' } ), h( 'input', { id: ids.user, name: 'username', class: 'field__input', autocomplete: 'username', required: true } ) ),
			h( 'div', { class: 'field' }, h( 'label', { class: 'field__label', for: ids.password, text: 'Password' } ), h( 'input', { id: ids.password, name: 'password', type: 'password', class: 'field__input', autocomplete: 'current-password', required: true } ) ),
			h( 'div', { class: 'form__actions' }, button ),
			error,
		);
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			button.disabled = true;
			error.textContent = '';
			try {
				await this.api.login( form.elements.username.value, form.elements.password.value );
				await this.load();
			} catch ( failure ) {
				error.textContent = failure.message;
			} finally {
				button.disabled = false;
			}
		} );
		this.main.replaceChildren( form );
		form.elements.username.focus();
	}

	async logout() {
		try {
			await this.api.logout();
		} catch ( error ) {
			console.warn( '[AdminApp] logout failed', error );
		}
		this.data = null;
		this.dirty = false;
		this.showLogin();
	}

	notify( message, isError = false ) {
		this.toast.textContent = message;
		this.toast.classList.toggle( 'is-error', isError );
		this.toast.classList.add( 'is-visible' );
		clearTimeout( this.toastTimer );
		this.toastTimer = setTimeout( () => this.toast.classList.remove( 'is-visible' ), isError ? 8000 : 3500 );
	}

	fail( error ) {
		console.error( '[AdminApp]', error );
		this.main.replaceChildren( h( 'p', { class: 'form__status is-error', role: 'alert', text: `The admin portal could not load: ${ error.message }` } ) );
	}
}
