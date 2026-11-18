import { PreferenceStore } from './PreferenceStore.js';

const STORAGE_KEY = 'blazing-theme';

const THEMES = {
	dark: { name: 'White Moon', image: '/images/moon.png' },
	light: { name: 'Blazing Sun', image: '/images/sun.gif' },
};

/**
 * Toggles the dark "White Moon" / light "Blazing Sun" theme via the `light` class on <html>
 * and announces changes with a `themechange` event ({ detail: { isLight } }).
 */
export class ThemeSwitcher {
	constructor( root = document.documentElement, preferences = new PreferenceStore() ) {
		this.root = root;
		this.preferences = preferences;
		this.toggle = root.querySelector( '.theme-toggle' );
		this.brandLogo = root.querySelector( '.brand__logo' );
		this.brandName = root.querySelector( '.brand__name' );
		this.images = root.querySelectorAll( '[data-theme-src]' );
	}

	init() {
		this.apply( this.preferences.read( STORAGE_KEY ) === 'light' );
		this.toggle.addEventListener( 'click', () => {
			const isLight = ! this.isLight;
			this.preferences.write( STORAGE_KEY, isLight ? 'light' : 'dark' );
			this.apply( isLight );
		} );
	}

	get isLight() {
		return this.root.classList.contains( 'light' );
	}

	apply( isLight ) {
		const theme = isLight ? THEMES.light : THEMES.dark;
		this.root.classList.toggle( 'light', isLight );
		this.images.forEach( ( img ) => {
			img.src = theme.image;
		} );
		this.brandLogo.alt = `${ theme.name } logo`;
		this.brandName.textContent = theme.name;
		this.toggle.setAttribute( 'aria-pressed', String( isLight ) );
		document.dispatchEvent( new CustomEvent( 'themechange', { detail: { isLight } } ) );
	}
}
