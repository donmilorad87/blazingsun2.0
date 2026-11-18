const SHOW_AFTER_SCREENS = 0.6; // appears once you have scrolled 60% of a screen
const REDUCED_MOTION = window.matchMedia( '(prefers-reduced-motion: reduce)' );

/**
 * Fixed glass button that scrolls back to the top. It appears after scrolling down, and its ring
 * (--progress 0…1) shows how far down the page you are. Focus moves to `focusTarget` after scrolling,
 * so keyboard users are not left on a button that has just disappeared.
 */
export class BackToTop {
	constructor( button, focusTarget ) {
		this.button = button;
		this.focusTarget = focusTarget;
		this.frame = 0;
	}

	init() {
		const queue = () => {
			if ( ! this.frame ) {
				this.frame = window.requestAnimationFrame( () => this.update() );
			}
		};
		window.addEventListener( 'scroll', queue, { passive: true } );
		window.addEventListener( 'resize', queue, { passive: true } );
		this.button.addEventListener( 'click', () => this.scrollToTop() );
		this.update();
	}

	update() {
		this.frame = 0;
		const scrollable = document.documentElement.scrollHeight - window.innerHeight;
		const progress = scrollable > 0 ? Math.min( window.scrollY / scrollable, 1 ) : 0;
		this.button.style.setProperty( '--progress', progress.toFixed( 4 ) );
		this.button.classList.toggle( 'is-visible', window.scrollY > window.innerHeight * SHOW_AFTER_SCREENS );
	}

	scrollToTop() {
		window.scrollTo( { top: 0, behavior: REDUCED_MOTION.matches ? 'instant' : 'smooth' } );
		this.focusTarget.focus( { preventScroll: true } );
	}
}
