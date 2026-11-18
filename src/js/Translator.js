import { TRANSLATIONS, DEFAULT_LANGUAGE } from './translations.js';

/** Swaps text of [data-i18n] (textContent) and [data-i18n-html] (trusted static markup) elements. */
export class Translator {
	constructor( root = document.documentElement ) {
		this.root = root;
	}

	static isSupported( lang ) {
		return Object.hasOwn( TRANSLATIONS, lang );
	}

	apply( lang ) {
		const strings = TRANSLATIONS[ Translator.isSupported( lang ) ? lang : DEFAULT_LANGUAGE ];
		this.root.lang = lang;
		this.root.querySelectorAll( '[data-i18n]' ).forEach( ( el ) => {
			el.textContent = strings[ el.dataset.i18n ];
		} );
		this.root.querySelectorAll( '[data-i18n-html]' ).forEach( ( el ) => {
			el.innerHTML = strings[ el.dataset.i18nHtml ];
		} );
	}
}
