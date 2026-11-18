const DESKTOP = window.matchMedia( '(min-width: 851px)' );
const REDUCED_MOTION = window.matchMedia( '(prefers-reduced-motion: reduce)' );
const LOAD_TIMEOUT_MS = 3000; // show the orb even if its image is slow
const LEAVE_TIMEOUT_MS = 2500; // safety nets only (animations are 0.6s / 1.4s; a busy main thread can delay end events)
const ARRIVE_TIMEOUT_MS = 3500;

const wait = ( ms ) => new Promise( ( resolve ) => window.setTimeout( resolve, ms ) );

/**
 * The fixed sun/moon (.theme-orb wrapper with its image and glow, one per theme; CSS shows the current theme's).
 * - Placement: on desktop it is centred on the hero's glow spot (.hero__glow), via --orb-x/--orb-y on <html>.
 * - Entrance: hidden (html.orb-pending, set in <head>) until placed and loaded, then drops in from the top.
 * - Theme switch: leave() lets the current orb rise out; after the switch arrive() drops the new one in.
 * State for CSS/tests: <html data-orb="arriving|idle|leaving">. Both methods resolve when their animation has finished.
 */
export class ThemeOrb {
	constructor( root, spot ) {
		this.root = root;
		this.spot = spot;
	}

	init() {
		const place = () => this.place();
		new ResizeObserver( place ).observe( this.spot );
		window.addEventListener( 'resize', place, { passive: true } );
		DESKTOP.addEventListener( 'change', place );
		place();
		return this.arrive();
	}

	place() {
		if ( ! DESKTOP.matches ) {
			this.root.classList.remove( 'orb-placed' );
			return;
		}
		const rect = this.spot.getBoundingClientRect();
		this.root.style.setProperty( '--orb-x', `${ rect.left + ( rect.width / 2 ) }px` );
		this.root.style.setProperty( '--orb-y', `${ rect.top + window.scrollY + ( rect.height / 2 ) }px` );
		this.root.classList.add( 'orb-placed' );
	}

	/**
	 * Drops the current theme's orb in from the top once its image and the web fonts are ready.
	 * Fonts change the hero's height (and so the spot), so the spot is measured only after they load:
	 * the orb flies straight to its final place instead of landing and then being corrected.
	 */
	async arrive() {
		const orb = this.visibleOrb();
		await Promise.race( [
			Promise.all( [
				document.fonts.ready,
				orb?.querySelector( 'img' ).decode().catch( ( error ) => console.warn( '[ThemeOrb] image could not be decoded; showing it anyway', error ) ),
			] ),
			wait( LOAD_TIMEOUT_MS ),
		] );
		this.place();
		this.root.dataset.orb = 'arriving';
		this.root.classList.remove( 'orb-pending', 'orb-arrived', 'orb-leaving' );
		void this.root.offsetWidth; // restart the CSS animation when the class is re-added
		await this.animate( orb, 'orb-arrived', ARRIVE_TIMEOUT_MS );
		this.root.dataset.orb = 'idle';
	}

	/** Lets the current orb rise out of the top of the page; resolves when it is gone. */
	async leave() {
		this.root.dataset.orb = 'leaving';
		this.root.classList.remove( 'orb-arrived' );
		await this.animate( this.visibleOrb(), 'orb-leaving', LEAVE_TIMEOUT_MS );
		this.root.classList.remove( 'orb-leaving' );
		this.root.classList.add( 'orb-pending' );
	}

	/** Adds the class that starts an orb animation and resolves when it ends (immediately with reduced motion). */
	async animate( orb, className, timeoutMs ) {
		const animates = orb && ! REDUCED_MOTION.matches;
		const ended = animates
			? new Promise( ( resolve ) => orb.addEventListener( 'animationend', resolve, { once: true } ) )
			: Promise.resolve();
		this.root.classList.add( className );
		await Promise.race( [ ended, wait( timeoutMs ) ] );
	}

	visibleOrb() {
		return [ ...this.root.querySelectorAll( '.theme-orb' ) ].find( ( orb ) => orb.checkVisibility() );
	}
}
