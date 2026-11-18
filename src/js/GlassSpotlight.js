/**
 * Moves a soft light across glass panels under the cursor by setting --spot-x / --spot-y on the hovered .panel.
 * One delegated listener for the whole page, throttled to one update per animation frame.
 */
export class GlassSpotlight {
	constructor( root = document, selector = '.panel' ) {
		this.root = root;
		this.selector = selector;
		this.pending = null;
	}

	init() {
		if ( window.matchMedia( '(hover: none)' ).matches ) {
			return; // touch screens have no cursor to follow
		}
		this.root.addEventListener( 'pointermove', ( event ) => this.queue( event ), { passive: true } );
	}

	queue( event ) {
		const panel = event.target instanceof Element ? event.target.closest( this.selector ) : null;
		if ( ! panel ) {
			return;
		}
		if ( this.pending === null ) {
			window.requestAnimationFrame( () => this.apply() );
		}
		this.pending = { panel, x: event.clientX, y: event.clientY };
	}

	apply() {
		const { panel, x, y } = this.pending;
		this.pending = null;
		const rect = panel.getBoundingClientRect();
		panel.style.setProperty( '--spot-x', `${ x - rect.left }px` );
		panel.style.setProperty( '--spot-y', `${ y - rect.top }px` );
	}
}
