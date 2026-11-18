import { srcset, fallbackSrc } from './imageSets.js';

/** Points a <picture> (WebP <source> + fallback <img>) at another image set; `sizes` stay as written in the HTML. */
export class ResponsivePicture {
	static show( picture, set ) {
		picture.querySelector( 'source[type="image/webp"]' ).srcset = srcset( set, set.webp );
		const img = picture.querySelector( 'img' );
		img.srcset = srcset( set, set.fallback );
		img.src = fallbackSrc( set );
	}
}
