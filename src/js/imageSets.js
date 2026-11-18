// Single source of truth for responsive images: used by the page (ThemeSwitcher, LanguagePicker),
// by scripts/build-images.mjs (which generates the files) and by the tests.

const BASE = '/images/responsive';
const ORB_WIDTHS = [ 24, 48, 72, 90, 135, 240, 330, 426 ]; // toggle 24px, logo 45px, orb ≤ 500px; source is 426px
const FLAG_WIDTHS = [ 24, 48, 72 ]; // flags are shown at 22–24px (1×–3× screens)

/**
 * @typedef {object} ImageSet
 * @property {string} name           file prefix in /images/responsive
 * @property {string} source         original file (images-src/, not deployed)
 * @property {number} sourceWidth    widths never exceed this (no upscaling)
 * @property {number[]} widths       generated widths, ascending
 * @property {string} webp           modern format
 * @property {string} fallback       format for browsers without WebP
 * @property {number[]} [fallbackWidths] widths generated in the fallback format (default: all)
 * @property {boolean} [animated]
 */

/** @type {Record<string, ImageSet>} */
export const IMAGE_SETS = {
	moon: { name: 'moon', source: 'images-src/moon.png', sourceWidth: 426, widths: ORB_WIDTHS, webp: 'webp', fallback: 'png' },
	// Animated: WebP keeps the animation at a fraction of the GIF size; the original GIF stays as the only fallback.
	sun: { name: 'sun', source: 'images-src/sun.gif', sourceWidth: 426, widths: ORB_WIDTHS, webp: 'webp', fallback: 'gif', fallbackWidths: [ 426 ], animated: true },
	flagRs: { name: 'flag-rs', source: 'images-src/flags/rs.svg', sourceWidth: 1200, widths: FLAG_WIDTHS, webp: 'webp', fallback: 'png' },
	flagGb: { name: 'flag-gb', source: 'images-src/flags/gb.svg', sourceWidth: 1200, widths: FLAG_WIDTHS, webp: 'webp', fallback: 'png' },
	flagFr: { name: 'flag-fr', source: 'images-src/flags/fr.svg', sourceWidth: 1200, widths: FLAG_WIDTHS, webp: 'webp', fallback: 'png' },
};

/** `sizes` for each slot, matching the CSS widths. */
export const SIZES = {
	orb: '(max-width: 700px) min(330px, 72vw), min(500px, 40vw)',
	logo: '45px',
	toggle: '24px',
	flag: '24px',
};

/** Widths generated for a given format of a set. */
export function widthsFor( set, format ) {
	return format === set.fallback && set.fallbackWidths ? set.fallbackWidths : set.widths;
}

/** `srcset` attribute value, e.g. "/images/responsive/moon-24.webp 24w, …". */
export function srcset( set, format ) {
	return widthsFor( set, format ).map( ( width ) => `${ BASE }/${ set.name }-${ width }.${ format } ${ width }w` ).join( ', ' );
}

/** Plain `src` for the <img>: the largest fallback file. */
export function fallbackSrc( set ) {
	return `${ BASE }/${ set.name }-${ widthsFor( set, set.fallback ).at( -1 ) }.${ set.fallback }`;
}

/** File path of one generated variant, relative to the project root. */
export function outputPath( set, width, format ) {
	return `public${ BASE }/${ set.name }-${ width }.${ format }`;
}
