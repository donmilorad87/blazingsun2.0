import { PreferenceStore } from './PreferenceStore.js';

const STORAGE_KEY = 'blazing-theme';

/** Runs around a theme change; the default does nothing (instant switch). */
const NO_TRANSITION = {
	beforeChange: async () => {},
	afterChange: async () => {},
};

/**
 * Toggles the dark "White Moon" / light "Blazing Sun" theme via the `light` class on <html>
 * (both themes' logo, name and orb are in the HTML; CSS shows the right ones) and announces changes
 * with a `themechange` event ({ detail: { isLight } }). A transition (e.g. the orb rising out and
 * dropping back in) can wrap the switch; clicks during a running switch are ignored.
 */
export class ThemeSwitcher {
	constructor( root = document.documentElement, preferences = new PreferenceStore(), transition = NO_TRANSITION ) {
		this.root = root;
		this.preferences = preferences;
		this.transition = transition;
		this.toggle = root.querySelector( '.theme-toggle' );
		this.switching = false;
	}

	init() {
		this.apply( this.preferences.read( STORAGE_KEY ) === 'light' );
		this.toggle.addEventListener( 'click', () => this.switchTheme() );
	}

	get isLight() {
		return this.root.classList.contains( 'light' );
	}

	async switchTheme() {
		if ( this.switching ) {
			return;
		}
		this.switching = true;
		try {
			const isLight = ! this.isLight;
			await this.transition.beforeChange();
			this.preferences.write( STORAGE_KEY, isLight ? 'light' : 'dark' );
			this.apply( isLight );
			await this.transition.afterChange();
		} finally {
			this.switching = false;
		}
	}

	apply( isLight ) {
		this.root.classList.toggle( 'light', isLight );
		this.toggle.setAttribute( 'aria-pressed', String( isLight ) );
		document.dispatchEvent( new CustomEvent( 'themechange', { detail: { isLight } } ) );
	}
}
