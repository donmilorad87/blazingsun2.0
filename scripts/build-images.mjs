// Generates the responsive image variants listed in src/js/imageSets.js into public/images/responsive/.
// Run after adding or changing a source image in images-src/:  npm run images
import { mkdir, stat } from 'node:fs/promises';
import { dirname } from 'node:path';
import sharp from 'sharp';
import { IMAGE_SETS, widthsFor, outputPath } from '../src/js/imageSets.js';

const ENCODERS = {
	webp: ( image ) => image.webp( { quality: 82, alphaQuality: 90, effort: 6 } ),
	png: ( image ) => image.png( { compressionLevel: 9, palette: true, quality: 90 } ),
	gif: ( image ) => image.gif( { effort: 7 } ),
};

class ResponsiveImageBuilder {
	constructor( sets ) {
		this.sets = sets;
		this.written = [];
	}

	async buildAll() {
		for ( const set of Object.values( this.sets ) ) {
			for ( const format of [ set.webp, set.fallback ] ) {
				for ( const width of widthsFor( set, format ) ) {
					await this.buildOne( set, width, format );
				}
			}
		}
		return this.written;
	}

	async buildOne( set, width, format ) {
		if ( width > set.sourceWidth ) {
			throw new Error( `${ set.name }: ${ width }px would upscale the ${ set.sourceWidth }px source` );
		}
		const file = outputPath( set, width, format );
		await mkdir( dirname( file ), { recursive: true } );
		// SVGs are rasterised at a high density so small flags stay crisp; GIFs keep every animation frame.
		const input = sharp( set.source, { animated: Boolean( set.animated ), density: set.source.endsWith( '.svg' ) ? 600 : 72 } );
		await ENCODERS[ format ]( input.resize( { width, withoutEnlargement: true } ) ).toFile( file );
		this.written.push( { file, bytes: ( await stat( file ) ).size } );
	}
}

const written = await new ResponsiveImageBuilder( IMAGE_SETS ).buildAll();
const report = written.map( ( { file, bytes } ) => `${ file.padEnd( 48 ) } ${ ( bytes / 1024 ).toFixed( 1 ).padStart( 8 ) } KB` );
process.stdout.write( `${ report.join( '\n' ) }\n${ written.length } files\n` );
