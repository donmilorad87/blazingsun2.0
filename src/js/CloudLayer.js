import { CanvasLayer } from './CanvasLayer.js';

// [x offset (× cloud width), y offset, radius] of the puffs on top of each cloud base.
const PUFFS = [ [ -0.72, -4, 34 ], [ -0.42, -25, 48 ], [ -0.08, -40, 59 ], [ 0.3, -24, 46 ], [ 0.63, -3, 32 ] ];

/** Slowly drifting clouds (light theme). */
export class CloudLayer extends CanvasLayer {
	constructor( canvas ) {
		super( canvas, 2 );
		this.clouds = [];
	}

	populate() {
		const perRow = Math.max( 7, Math.min( 13, Math.floor( this.width / 150 ) ) );
		// Two staggered rows, matching the density of the original design.
		this.clouds = Array.from( { length: perRow * 2 }, ( _, i ) => ( {
			x: ( ( i % perRow ) / perRow * this.width ) + ( this.width * 0.08 ),
			y: this.height * ( 0.12 + ( Math.random() * 0.62 ) ),
			scale: 0.55 + ( Math.random() * 0.75 ),
			alpha: 0.16 + ( Math.random() * 0.2 ),
			speed: 0.006 + ( Math.random() * 0.012 ),
		} ) );
	}

	draw( time ) {
		this.clouds.forEach( ( cloud ) => this.drawCloud( cloud, time ) );
	}

	drawCloud( cloud, time ) {
		const { ctx } = this;
		const { scale } = cloud;
		const base = 170 * scale;
		const x = ( ( cloud.x + ( time * cloud.speed ) ) % ( this.width + 420 ) ) - 210;

		ctx.save();
		ctx.translate( x, cloud.y );
		ctx.globalAlpha = cloud.alpha;
		ctx.shadowColor = 'rgba(255, 255, 255, 0.28)';
		ctx.shadowBlur = 24;
		const gradient = ctx.createLinearGradient( 0, -55 * scale, 0, 55 * scale );
		gradient.addColorStop( 0, 'rgba(255, 255, 255, 0.82)' );
		gradient.addColorStop( 0.48, 'rgba(255, 255, 255, 0.48)' );
		gradient.addColorStop( 1, 'rgba(255, 255, 255, 0.04)' );
		ctx.fillStyle = gradient;
		ctx.beginPath();
		ctx.ellipse( 0, 15 * scale, base, 27 * scale, 0, 0, Math.PI * 2 );
		ctx.fill();
		PUFFS.forEach( ( [ offsetX, offsetY, radius ] ) => {
			ctx.beginPath();
			ctx.arc( offsetX * base, offsetY * scale, radius * scale, 0, Math.PI * 2 );
			ctx.fill();
		} );
		ctx.shadowBlur = 0;
		ctx.globalAlpha = cloud.alpha * 0.3;
		ctx.fillStyle = 'rgba(134, 188, 224, 0.35)';
		ctx.beginPath();
		ctx.ellipse( 0, 28 * scale, base * 0.82, 14 * scale, 0, 0, Math.PI * 2 );
		ctx.fill();
		ctx.restore();
	}
}
