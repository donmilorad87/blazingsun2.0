import { h, uid } from './dom.js';
import { isTranslated, wrapSelection } from './model.js';

// Translated fields that get a multi-line textarea instead of a one-line input.
const LONG_FIELDS = new Set( [ 'description', 'text', 'quote', 'company_description', 'meta_description' ] );

/**
 * One form field built from its definition. Translated fields get one input per language (only the language
 * chosen in the form's tabs is visible); empty translations show the main language as a placeholder, which is
 * exactly what the page falls back to. value() returns { iso2: text } or a string, as the API expects.
 */
export class FieldInput {
	/**
	 * @param {object} field     definition { name, label, type, required, options? }
	 * @param {*}      value     current value
	 * @param {object} context   { languages, main, media, api, onMedia, onStatus }
	 */
	constructor( field, value, context ) {
		this.field = field;
		this.context = context;
		this.inputs = new Map(); // lang ('' when untranslated) → input element
		this.element = h( 'div', { class: `field field--${ field.type }`, data: { field: field.name } } );
		if ( isTranslated( field ) ) {
			this.buildTranslated( value ?? {} );
		} else if ( field.type === 'image' ) {
			this.buildImage( value ?? '' );
		} else {
			this.buildSingle( value ?? '' );
		}
	}

	buildTranslated( values ) {
		const { languages, main } = this.context;
		for ( const language of languages ) {
			const id = uid();
			const isMain = language.iso2 === main;
			const input = this.control( id, values[ language.iso2 ] ?? '' );
			input.dataset.lang = language.iso2;
			if ( ! isMain ) {
				input.placeholder = values[ main ] ? `Empty: shows “${ values[ main ] }”` : 'Empty: shows the main language';
			}
			input.required = isMain && this.field.required;
			this.inputs.set( language.iso2, input );
			this.element.append( h( 'div', { class: 'field__lang', data: { lang: language.iso2 } },
				this.label( id, `${ this.field.label } · ${ language.name }${ isMain ? ' (main)' : '' }`, isMain && this.field.required ),
				this.field.type === 'rich' ? this.richToolbar( input ) : null,
				input,
				h( 'p', { class: 'field__error', hidden: true } ),
			) );
		}
	}

	buildSingle( value ) {
		const id = uid();
		let input;
		if ( this.field.type === 'select' ) {
			input = h( 'select', { id, class: 'field__input' }, this.field.options.map( ( option ) => h( 'option', { value: option, text: option } ) ) );
			input.value = value || this.field.options[ 0 ];
		} else {
			input = h( 'input', { id, class: 'field__input', type: 'text', value, inputMode: this.field.type === 'url' ? 'url' : 'text', autocomplete: 'off' } );
		}
		input.required = this.field.required;
		this.inputs.set( '', input );
		this.element.append( this.label( id, this.field.label, this.field.required ), input, h( 'p', { class: 'field__error', hidden: true } ) );
	}

	buildImage( value ) {
		const id = uid();
		this.imageId = value;
		this.preview = h( 'img', { class: 'field__preview', alt: '', width: 72, height: 72 } );
		this.library = h( 'div', { class: 'field__library', role: 'listbox', 'aria-label': `Uploaded images for ${ this.field.label }` } );
		const upload = h( 'input', { id, type: 'file', accept: 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml', class: 'field__file', onChange: () => this.upload( upload ) } );
		this.inputs.set( '', upload );
		this.element.append(
			h( 'span', { class: 'field__label' }, this.field.label, this.field.required ? h( 'span', { class: 'field__required', 'aria-hidden': 'true', text: ' *' } ) : null ),
			h( 'div', { class: 'field__image' },
				this.preview,
				h( 'div', {},
					upload,
					h( 'label', { class: 'btn btn--small', for: id, text: 'Upload new image…' } ),
					h( 'details', { class: 'field__choose' }, h( 'summary', { text: 'Choose an uploaded image' } ), this.library ),
				),
			),
			h( 'p', { class: 'field__error', hidden: true } ),
		);
		this.renderImage();
	}

	renderImage() {
		const media = this.context.media[ this.imageId ];
		this.preview.hidden = ! media;
		if ( media ) {
			this.preview.src = media.url;
			this.preview.alt = `Current: image #${ media.id } (${ media.width }×${ media.height })`;
		}
		this.library.replaceChildren( ...Object.values( this.context.media ).map( ( item ) => h( 'button', {
			type: 'button',
			class: `field__thumb${ String( item.id ) === String( this.imageId ) ? ' is-active' : '' }`,
			role: 'option',
			'aria-selected': String( String( item.id ) === String( this.imageId ) ),
			'aria-label': `Image #${ item.id }, ${ item.width }×${ item.height }`,
			onClick: () => {
				this.imageId = String( item.id );
				this.element.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				this.renderImage();
			},
		}, h( 'img', { src: item.url, alt: '', loading: 'lazy', width: 48, height: 48 } ) ) ) );
	}

	async upload( input ) {
		const file = input.files?.[ 0 ];
		if ( ! file ) {
			return;
		}
		this.context.onStatus( `Uploading ${ file.name }…` );
		try {
			const media = await this.context.api.upload( file, 'image' );
			this.context.onMedia( media );
			this.imageId = String( media.id );
			this.element.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			this.renderImage();
			this.context.onStatus( 'Image uploaded. Save to use it on the site.' );
		} catch ( error ) {
			this.context.onStatus( error.message, true );
		} finally {
			input.value = '';
		}
	}

	control( id, value ) {
		const long = this.field.type === 'rich' || LONG_FIELDS.has( this.field.name );
		return long
			? h( 'textarea', { id, class: 'field__input', rows: 3, value } )
			: h( 'input', { id, class: 'field__input', type: 'text', value, autocomplete: 'off' } );
	}

	label( id, text, required ) {
		return h( 'label', { class: 'field__label', for: id }, text, required ? h( 'span', { class: 'field__required', 'aria-hidden': 'true', text: ' *' } ) : null );
	}

	/** Italic / bold buttons: the only markup the page allows (<em>, <b>). */
	richToolbar( input ) {
		const wrap = ( tag ) => {
			input.value = wrapSelection( input.value, input.selectionStart, input.selectionEnd, tag );
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			input.focus();
		};
		return h( 'div', { class: 'field__toolbar', role: 'toolbar', 'aria-label': 'Formatting' },
			h( 'button', { type: 'button', class: 'btn btn--small', onClick: () => wrap( 'em' ), 'aria-label': 'Italic (gold accent)' }, h( 'em', { text: 'I' } ) ),
			h( 'button', { type: 'button', class: 'btn btn--small', onClick: () => wrap( 'b' ), 'aria-label': 'Bold' }, h( 'b', { text: 'B' } ) ),
		);
	}

	value() {
		if ( this.field.type === 'image' ) {
			return this.imageId ? String( this.imageId ) : '';
		}
		if ( isTranslated( this.field ) ) {
			return Object.fromEntries( [ ...this.inputs ].map( ( [ lang, input ] ) => [ lang, input.value ] ) );
		}
		return this.inputs.get( '' ).value;
	}

	/** Shows the server's errors for this field ("{prefix}.{name}" or "{prefix}.{name}.{iso2}"); returns the languages with errors. */
	showErrors( errors, prefix ) {
		const failed = [];
		for ( const [ lang, input ] of this.inputs ) {
			const message = errors[ lang ? `${ prefix }.${ this.field.name }.${ lang }` : `${ prefix }.${ this.field.name }` ] ?? null;
			const box = lang ? input.closest( '.field__lang' ) : this.element;
			const error = box.querySelector( '.field__error' );
			error.hidden = ! message;
			error.textContent = message ?? '';
			input.toggleAttribute( 'aria-invalid', Boolean( message ) );
			if ( message ) {
				error.id ||= uid( 'err' );
				input.setAttribute( 'aria-describedby', error.id );
				failed.push( lang );
			} else {
				input.removeAttribute( 'aria-describedby' );
			}
		}
		return failed;
	}
}
