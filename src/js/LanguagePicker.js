import { DEFAULT_LANGUAGE, LANGUAGES } from './translations.js';
import { Translator } from './Translator.js';
import { PreferenceStore } from './PreferenceStore.js';

const STORAGE_KEY = 'blazing-language';

/** Flag dropdown in the header that switches the page language. */
export class LanguagePicker {
	constructor( container, translator = new Translator(), preferences = new PreferenceStore() ) {
		this.container = container;
		this.translator = translator;
		this.preferences = preferences;
		this.current = container.querySelector( '.lang-picker__current' );
		this.menu = container.querySelector( '.lang-picker__menu' );
		this.options = [ ...this.menu.querySelectorAll( 'button[data-lang]' ) ];
	}

	init() {
		const saved = this.preferences.read( STORAGE_KEY );
		const lang = Translator.isSupported( saved ) ? saved : DEFAULT_LANGUAGE;
		if ( lang !== DEFAULT_LANGUAGE ) {
			this.translator.apply( lang );
		}
		this.markActive( lang );

		this.current.addEventListener( 'click', () => this.setOpen( this.menu.hidden ) );
		this.options.forEach( ( option ) => option.addEventListener( 'click', () => this.select( option.dataset.lang ) ) );
		document.addEventListener( 'click', ( event ) => {
			if ( ! this.container.contains( event.target ) ) {
				this.setOpen( false );
			}
		} );
		this.container.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' && ! this.menu.hidden ) {
				this.setOpen( false );
				this.current.focus();
			}
		} );
	}

	setOpen( isOpen ) {
		this.menu.hidden = ! isOpen;
		this.current.setAttribute( 'aria-expanded', String( isOpen ) );
	}

	select( lang ) {
		this.translator.apply( lang );
		this.preferences.write( STORAGE_KEY, lang );
		this.markActive( lang );
		this.setOpen( false );
	}

	markActive( lang ) {
		this.options.forEach( ( option ) => {
			const isActive = option.dataset.lang === lang;
			option.classList.toggle( 'is-active', isActive );
			option.toggleAttribute( 'aria-current', isActive );
			if ( isActive ) {
				this.current.querySelector( 'img' ).src = option.querySelector( 'img' ).src;
			}
		} );
		this.current.setAttribute( 'aria-label', LANGUAGES[ lang ] );
	}
}
