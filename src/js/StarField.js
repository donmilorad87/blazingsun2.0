import { CanvasLayer } from './CanvasLayer.js';

const STAR_COUNT = 190;

/** Twinkling stars with scroll parallax (dark theme). */
export class StarField extends CanvasLayer {
	constructor( canvas ) {
		super( canvas, 1.5 );
		this.stars = [];
	}

	populate() {
		if ( this.stars.length ) {
			return;
		}
		this.stars = Array.from( { length: STAR_COUNT }, () => ( {
			x: Math.random(),
			y: Math.random(),
			radius: 0.35 + ( Math.random() * 1.35 ),
			alpha: 0.3 + ( Math.random() * 0.7 ),
			depth: 0.025 + ( Math.random() * 0.16 ),
			twinkle: Math.random() * Math.PI * 2,
		} ) );
	}

	draw( time ) {
		const { ctx, width, height } = this;
		const seconds = time * 0.001;
		const scrollY = window.scrollY;
		for ( const star of this.stars ) {
			const y = ( ( ( star.y * height ) + ( scrollY * star.depth ) ) % ( height + 36 ) ) - 18;
			const alpha = star.alpha * ( 0.72 + ( 0.28 * Math.sin( ( seconds * 1.4 ) + star.twinkle ) ) );
			ctx.beginPath();
			ctx.fillStyle = `rgba(222, 244, 255, ${ alpha })`;
			ctx.arc( star.x * width, y, star.radius, 0, Math.PI * 2 );
			ctx.fill();
		}
	}
}
