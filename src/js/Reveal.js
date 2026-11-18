const STAGGER_MS = 90;
const MAX_STAGGER_STEPS = 4;

/**
 * Staggered rise-in of panels as they scroll into view. Panels are only hidden once this runs
 * (html.reveal-ready), so the page is fully visible without JavaScript or with reduced motion.
 */
export class Reveal {
	constructor( root = document.documentElement, selector = '.panel' ) {
		this.root = root;
		this.selector = selector;
	}

	init() {
		if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		const observer = new IntersectionObserver( ( entries ) => {
			entries.filter( ( entry ) => entry.isIntersecting ).forEach( ( entry ) => {
				entry.target.classList.add( 'is-revealed' );
				observer.unobserve( entry.target );
			} );
		}, { rootMargin: '0px 0px -8% 0px' } );

		this.root.querySelectorAll( this.selector ).forEach( ( panel ) => {
			// Siblings in the same grid rise one after another.
			const index = [ ...panel.parentElement.children ].indexOf( panel ) % MAX_STAGGER_STEPS;
			panel.style.setProperty( '--reveal-delay', `${ index * STAGGER_MS }ms` );
			observer.observe( panel );
		} );
		this.root.classList.add( 'reveal-ready' );
	}
}
