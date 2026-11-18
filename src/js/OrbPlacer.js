const DESKTOP = window.matchMedia( '(min-width: 851px)' );

/**
 * Keeps the fixed sun/moon (.theme-orb) centred on the hero's glow spot (.hero__glow) on desktop.
 * The orb stays position: fixed; this only decides where: the spot's centre when the page is scrolled to the top.
 * The spot moves with the hero text height and window size, so it is re-measured on resize.
 */
export class OrbPlacer {
	constructor( orb, spot ) {
		this.orb = orb;
		this.spot = spot;
	}

	init() {
		const place = () => this.place();
		new ResizeObserver( place ).observe( this.spot );
		window.addEventListener( 'resize', place, { passive: true } );
		DESKTOP.addEventListener( 'change', place );
		place();
	}

	place() {
		if ( ! DESKTOP.matches ) {
			this.orb.classList.remove( 'is-placed' );
			return;
		}
		const rect = this.spot.getBoundingClientRect();
		this.orb.style.setProperty( '--orb-x', `${ rect.left + ( rect.width / 2 ) }px` );
		this.orb.style.setProperty( '--orb-y', `${ rect.top + window.scrollY + ( rect.height / 2 ) }px` );
		this.orb.classList.add( 'is-placed' );
	}
}
