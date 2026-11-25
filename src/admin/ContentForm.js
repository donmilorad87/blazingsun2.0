import { h, uid } from './dom.js';
import { FieldInput } from './FieldInput.js';
import { emptyValues, isTranslated } from './model.js';
import { ApiError } from './Api.js';

/**
 * Language tabs for a form: shows the inputs of one language at a time (fields that aren't translated stay visible)
 * and counts, per language, the required texts that are still empty.
 */
class LanguageTabs {
	constructor( form, languages, main ) {
		this.form = form;
		this.languages = languages;
		this.current = main;
		this.buttons = new Map();
		this.element = h( 'div', { class: 'tabs', role: 'tablist', 'aria-label': 'Language to edit' },
			languages.map( ( language ) => {
				const button = h( 'button', { type: 'button', class: 'tabs__tab', role: 'tab', data: { lang: language.iso2 }, onClick: () => this.select( language.iso2 ) },
					`${ language.name }${ language.iso2 === main ? ' (main)' : '' }`,
					h( 'span', { class: 'tabs__badge', hidden: true } ),
				);
				this.buttons.set( language.iso2, button );
				return button;
			} ) );
		form.addEventListener( 'input', () => this.count() );
	}

	select( lang ) {
		this.current = lang;
		for ( const [ iso2, button ] of this.buttons ) {
			button.setAttribute( 'aria-selected', String( iso2 === lang ) );
			button.classList.toggle( 'is-active', iso2 === lang );
		}
		this.apply();
	}

	/** Shows only the current language's inputs (call again after adding items). */
	apply() {
		for ( const box of this.form.querySelectorAll( '.field__lang' ) ) {
			box.hidden = box.dataset.lang !== this.current;
		}
		this.count();
	}

	count() {
		for ( const [ iso2, button ] of this.buttons ) {
			const empty = [ ...this.form.querySelectorAll( `[data-required-translation][data-lang="${ iso2 }"]` ) ].filter( ( input ) => ! input.value.trim() ).length;
			const badge = button.querySelector( '.tabs__badge' );
			badge.hidden = empty === 0;
			badge.textContent = String( empty );
			badge.title = `${ empty } required text${ empty === 1 ? '' : 's' } not translated (the main language is shown instead)`;
		}
	}
}

/** One group of repeatable items (cards) with add, move up/down and remove. */
class ItemList {
	constructor( name, group, items, context, onChange ) {
		this.name = name;
		this.group = group;
		this.context = context;
		this.onChange = onChange;
		this.cards = [];
		this.list = h( 'ol', { class: 'items' } );
		this.error = h( 'p', { class: 'field__error', hidden: true } );
		this.addButton = h( 'button', { type: 'button', class: 'btn', onClick: () => this.add( emptyValues( group.fields ), null, true ) }, `+ Add to ${ group.label }` );
		this.element = h( 'fieldset', { class: 'group' },
			h( 'legend', { class: 'group__title', text: group.label } ),
			this.list, this.error, this.addButton );
		items.forEach( ( item ) => this.add( item.values, item.id, false ) );
		this.refresh();
	}

	add( values, id, focus ) {
		if ( this.cards.length >= this.group.max ) {
			return;
		}
		const inputs = this.group.fields.map( ( field ) => markRequired( new FieldInput( field, values[ field.name ], this.context ) ) );
		const card = { id, inputs };
		const title = h( 'h3', { class: 'item__title' } );
		const actions = h( 'div', { class: 'item__actions' },
			h( 'button', { type: 'button', class: 'btn btn--small', 'aria-label': 'Move up', title: 'Move up', onClick: () => this.move( card, -1 ) }, '↑' ),
			h( 'button', { type: 'button', class: 'btn btn--small', 'aria-label': 'Move down', title: 'Move down', onClick: () => this.move( card, 1 ) }, '↓' ),
			h( 'button', { type: 'button', class: 'btn btn--small btn--danger', onClick: () => this.remove( card ) }, 'Remove' ),
		);
		card.title = title;
		card.element = h( 'li', { class: 'item' }, h( 'div', { class: 'item__head' }, title, actions ), inputs.map( ( input ) => input.element ) );
		card.element.addEventListener( 'input', () => this.titleOf( card ) );
		this.cards.push( card );
		this.list.append( card.element );
		this.refresh();
		this.onChange();
		if ( focus ) {
			card.element.querySelector( '.field__lang:not([hidden]) .field__input, .field__input' )?.focus();
		}
	}

	move( card, delta ) {
		const index = this.cards.indexOf( card );
		const target = index + delta;
		if ( target < 0 || target >= this.cards.length ) {
			return;
		}
		this.cards.splice( index, 1 );
		this.cards.splice( target, 0, card );
		this.list.replaceChildren( ...this.cards.map( ( item ) => item.element ) );
		this.refresh();
		this.onChange();
		card.element.querySelector( `[aria-label="${ delta < 0 ? 'Move up' : 'Move down' }"]` )?.focus();
	}

	remove( card ) {
		this.cards.splice( this.cards.indexOf( card ), 1 );
		card.element.remove();
		this.refresh();
		this.onChange();
	}

	refresh() {
		this.cards.forEach( ( card, index ) => {
			card.index = index;
			this.titleOf( card );
		} );
		this.addButton.disabled = this.cards.length >= this.group.max;
	}

	/** "2 · BonusFinder": position plus the item's name (or title, label…) in the main language. */
	titleOf( card ) {
		const byName = ( name ) => card.inputs.find( ( input ) => input.field.name === name );
		const first = [ 'name', 'title', 'label', 'value', 'text' ].map( byName ).find( Boolean );
		const value = first?.value();
		const text = typeof value === 'object' ? value?.[ this.context.main ] : value;
		card.title.textContent = `${ card.index + 1 } · ${ text?.replace( /<\/?(em|b)>/g, '' ) || 'New item' }`;
	}

	payload() {
		return this.cards.map( ( card ) => ( {
			...( card.id ? { id: card.id } : {} ),
			values: Object.fromEntries( card.inputs.map( ( input ) => [ input.field.name, input.value() ] ) ),
		} ) );
	}

	showErrors( errors ) {
		const prefix = `groups.${ this.name }`;
		this.error.hidden = ! errors[ prefix ];
		this.error.textContent = errors[ prefix ] ?? '';
		return this.cards.flatMap( ( card, index ) => card.inputs.flatMap( ( input ) => input.showErrors( errors, `${ prefix }.${ index }` ) ) );
	}
}

/** Marks the inputs whose translation is required, so the language tabs can count the empty ones. */
function markRequired( input ) {
	if ( input.field.required && isTranslated( input.field ) ) {
		for ( const element of input.inputs.values() ) {
			element.dataset.requiredTranslation = '';
		}
	}
	return input;
}

/**
 * A form of fields plus item groups, saved in one request. onSubmit(payload) returns a promise; a 422 ApiError
 * marks every invalid field and switches to the first language that has an error.
 * payload: { values: { field: value }, groups: { name: [ { id?, values } ] } }
 */
export class ContentForm {
	constructor( { title, intro = '', fields, values = {}, groups = [], context, submitLabel = 'Save', onSubmit, extra = null } ) {
		this.fields = fields.map( ( field ) => markRequired( new FieldInput( field, values[ field.name ], context ) ) );
		this.status = h( 'p', { class: 'form__status', role: 'status', 'aria-live': 'polite' } );
		this.form = h( 'form', { class: 'form', noValidate: true, 'aria-labelledby': uid( 'title' ) } );
		const heading = h( 'h2', { id: this.form.getAttribute( 'aria-labelledby' ), class: 'form__title', text: title } );
		const translated = [ ...fields, ...groups.flatMap( ( group ) => group.fields ) ].some( isTranslated );
		this.tabs = translated ? new LanguageTabs( this.form, context.languages, context.main ) : null;
		this.lists = groups.map( ( group ) => new ItemList( group.name, group, group.items, context, () => this.tabs?.apply() ) );
		this.submit = h( 'button', { type: 'submit', class: 'btn btn--primary', text: submitLabel } );
		this.form.append( ...[
			heading,
			intro ? h( 'p', { class: 'form__intro', text: intro } ) : null,
			this.tabs?.element,
			this.fields.length ? h( 'div', { class: 'form__fields' }, this.fields.map( ( input ) => input.element ) ) : null,
			extra,
			this.lists.map( ( list ) => list.element ),
			h( 'div', { class: 'form__actions' }, this.submit, this.status ),
		].flat().filter( Boolean ) );
		this.form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			this.save( onSubmit );
		} );
		this.tabs?.select( context.main );
		this.element = this.form;
	}

	payload() {
		return {
			values: Object.fromEntries( this.fields.map( ( input ) => [ input.field.name, input.value() ] ) ),
			groups: Object.fromEntries( this.lists.map( ( list ) => [ list.name, list.payload() ] ) ),
		};
	}

	async save( onSubmit ) {
		this.submit.disabled = true;
		this.setStatus( 'Saving…' );
		this.showErrors( {} );
		try {
			await onSubmit( this.payload() );
			this.setStatus( 'Saved. The site is updated.' );
		} catch ( error ) {
			if ( error instanceof ApiError && error.status === 422 ) {
				this.showErrors( error.fields );
			}
			this.setStatus( error.message, true );
		} finally {
			this.submit.disabled = false;
		}
	}

	showErrors( errors ) {
		const failed = [
			...this.fields.flatMap( ( input ) => input.showErrors( errors, 'values' ) ),
			...this.lists.flatMap( ( list ) => list.showErrors( errors ) ),
		];
		const lang = failed.find( Boolean );
		if ( lang && this.tabs && lang !== this.tabs.current ) {
			this.tabs.select( lang );
		}
		this.form.querySelector( '[aria-invalid]' )?.focus();
	}

	setStatus( message, isError = false ) {
		this.status.textContent = message;
		this.status.classList.toggle( 'is-error', isError );
	}
}
