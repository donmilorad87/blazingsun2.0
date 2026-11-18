const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

/**
 * Full-viewport animated canvas that only renders while active.
 * Subclasses implement populate() (on resize) and draw(time).
 */
export class CanvasLayer {
	constructor( canvas, maxPixelRatio ) {
		this.canvas = canvas;
		this.ctx = canvas.getContext( '2d' );
		this.maxPixelRatio = maxPixelRatio;
		this.width = 0;
		this.height = 0;
		this.frameId = 0;
		this.active = false;
		this.tick = ( time ) => {
			this.render( time );
			this.frameId = window.requestAnimationFrame( this.tick );
		};
	}

	init() {
		this.resize();
		window.addEventListener( 'resize', () => this.resize(), { passive: true } );
	}

	resize() {
		const ratio = Math.min( window.devicePixelRatio || 1, this.maxPixelRatio );
		this.width = window.innerWidth;
		this.height = window.innerHeight;
		this.canvas.width = Math.floor( this.width * ratio );
		this.canvas.height = Math.floor( this.height * ratio );
		this.ctx.setTransform( ratio, 0, 0, ratio, 0, 0 );
		this.populate();
		if ( ! this.frameId && this.active ) {
			this.render( performance.now() );
		}
	}

	setActive( isActive ) {
		this.active = isActive;
		window.cancelAnimationFrame( this.frameId );
		this.frameId = 0;
		if ( ! isActive ) {
			return;
		}
		if ( reducedMotion.matches ) {
			this.render( 0 );
			return;
		}
		this.frameId = window.requestAnimationFrame( this.tick );
	}

	render( time ) {
		this.ctx.clearRect( 0, 0, this.width, this.height );
		this.draw( time );
	}

	populate() {}

	draw() {}
}
