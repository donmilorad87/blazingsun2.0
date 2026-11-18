import { ThemeSwitcher } from './ThemeSwitcher.js';
import { LanguagePicker } from './LanguagePicker.js';
import { StarField } from './StarField.js';
import { CloudLayer } from './CloudLayer.js';
import { VisitCounter } from './VisitCounter.js';
import { ThemeOrb } from './ThemeOrb.js';
import { GlassSpotlight } from './GlassSpotlight.js';
import { Reveal } from './Reveal.js';
import { BackToTop } from './BackToTop.js';

/** Wires the page components together. */
export class App {
	constructor( doc = document ) {
		this.doc = doc;
		this.stars = new StarField( doc.querySelector( '.sky--stars' ) );
		this.clouds = new CloudLayer( doc.querySelector( '.sky--clouds' ) );
		this.language = new LanguagePicker( doc.querySelector( '.lang-picker' ) );
		this.orb = new ThemeOrb( doc.documentElement, doc.querySelector( '.hero__glow' ) );
		// Switching theme: the sun/moon rises out, the theme changes, the other one drops in.
		this.theme = new ThemeSwitcher( doc.documentElement, undefined, {
			beforeChange: () => this.orb.leave(),
			afterChange: () => this.orb.arrive(),
		} );
		this.spotlight = new GlassSpotlight( doc );
		this.reveal = new Reveal( doc.documentElement );
		this.backToTop = new BackToTop( doc.querySelector( '.back-to-top' ), doc.querySelector( '.brand' ) );
		this.visits = new VisitCounter( doc.querySelector( '[data-visit-counter]' ) );
	}

	start() {
		this.stars.init();
		this.clouds.init();
		// Subscribe before the theme applies its initial state, so the right sky layer starts.
		this.doc.addEventListener( 'themechange', ( event ) => this.onThemeChange( event.detail.isLight ) );
		this.theme.init();
		this.language.init();
		this.orb.init();
		this.spotlight.init();
		this.reveal.init();
		this.backToTop.init();
		this.visits.record();
		this.registerServiceWorker();
	}

	onThemeChange( isLight ) {
		this.stars.setActive( ! isLight );
		this.clouds.setActive( isLight );
	}

	registerServiceWorker() {
		if ( ! ( 'serviceWorker' in navigator ) || ! import.meta.env.PROD ) {
			return;
		}
		window.addEventListener( 'load', () => {
			navigator.serviceWorker.register( '/sw.js', { scope: '/' } )
				.catch( ( error ) => console.error( '[App] service worker registration failed', error ) );
		} );
	}
}
