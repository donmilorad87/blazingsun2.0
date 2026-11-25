import { h, uid } from './dom.js';
import { ContentForm } from './ContentForm.js';
import { ApiError } from './Api.js';
import { schemaGraph } from './model.js';

/** Editor for one page section: its own fields plus its item groups. */
export function sectionScreen( app, slug ) {
	const definition = app.data.definitions.sections[ slug ];
	const section = app.data.sections[ slug ];
	return new ContentForm( {
		title: definition.label,
		intro: definition.nav ? 'The section number in “before title” is added automatically from the page order.' : 'The home banner is not in the main menu.',
		fields: definition.fields.filter( ( field ) => field.name !== 'nav_label' ),
		values: section.values,
		groups: Object.entries( definition.groups ).map( ( [ name, group ] ) => ( { name, ...group, items: section.groups[ name ] } ) ),
		context: app.context(),
		onSubmit: ( payload ) => app.save( () => app.api.saveSection( {
			section: slug,
			values: { ...payload.values, ...( definition.nav ? { nav_label: section.values.nav_label } : {} ) },
			groups: payload.groups,
		} ) ),
	} ).element;
}

/** Footer, header images/names or UI labels: one settings scope. */
export function settingsScreen( app, scope, title, intro ) {
	return new ContentForm( {
		title,
		intro,
		fields: app.data.definitions.settings[ scope ],
		values: app.data.settings[ scope ],
		context: app.context(),
		onSubmit: ( payload ) => app.save( () => app.api.saveSettings( scope, payload.values ) ),
	} ).element;
}

/** Main menu: which sections are shown, and each one's label in every language. */
export function menuScreen( app ) {
	const sections = Object.entries( app.data.definitions.sections ).filter( ( [ , section ] ) => section.nav );
	const checkboxes = new Map( sections.map( ( [ slug, section ] ) => {
		const id = uid();
		const box = h( 'input', { id, type: 'checkbox', checked: app.data.sections[ slug ].in_nav } );
		return [ slug, { box, row: h( 'div', { class: 'check' }, box, h( 'label', { for: id, text: section.label } ) ) } ];
	} ) );
	const visibility = h( 'fieldset', { class: 'group' },
		h( 'legend', { class: 'group__title', text: 'Shown in the main menu' } ),
		[ ...checkboxes.values() ].map( ( { row } ) => row ) );

	return new ContentForm( {
		title: 'Main menu',
		intro: 'Menu labels for every language. Unchecked sections stay on the page but are not linked from the menu.',
		fields: sections.map( ( [ slug, section ] ) => ( { name: slug, label: section.label, type: 'text', required: true } ) ),
		values: Object.fromEntries( sections.map( ( [ slug ] ) => [ slug, app.data.sections[ slug ].values.nav_label ] ) ),
		context: app.context(),
		extra: visibility,
		submitLabel: 'Save menu',
		onSubmit: async ( payload ) => {
			const items = Object.fromEntries( sections.map( ( [ slug ] ) => [ slug, { in_nav: checkboxes.get( slug ).box.checked, label: payload.values[ slug ] } ] ) );
			try {
				await app.save( () => app.api.saveMenu( items ) );
			} catch ( error ) {
				// The API reports "items.{slug}.nav_label.{iso2}"; the form's fields are "values.{slug}.{iso2}".
				if ( error instanceof ApiError ) {
					error.fields = Object.fromEntries( Object.entries( error.fields ).map( ( [ path, message ] ) => [ path.replace( /^items\.([a-z-]+)\.nav_label/, 'values.$1' ), message ] ) );
				}
				throw error;
			}
		},
	} ).element;
}

/** Languages: list (make main, delete), add/edit form with an SVG flag, and the UI labels in every language. */
export function languagesScreen( app ) {
	const { languages, media } = app.data;
	const status = h( 'p', { class: 'form__status', role: 'status', 'aria-live': 'polite' } );
	const report = ( error ) => {
		status.textContent = error.status === 422 ? Object.values( error.fields ).join( ' ' ) || error.message : error.message;
		status.classList.add( 'is-error' );
	};
	const editor = languageForm( app );

	const rows = languages.map( ( language ) => h( 'li', { class: 'language' },
		media[ language.flag ] ? h( 'img', { class: 'language__flag', src: media[ language.flag ].url, alt: '', width: 32, height: 21 } ) : h( 'span', { class: 'language__flag' } ),
		h( 'div', { class: 'language__name' },
			h( 'strong', { text: language.name } ),
			h( 'span', { class: 'language__codes', text: `${ language.iso2 } · ${ language.iso3 }` } ),
			language.is_main ? h( 'span', { class: 'badge', text: 'Main language' } ) : null,
			! language.is_main && language.missing > 0 ? h( 'span', { class: 'badge badge--warn', text: `${ language.missing } required texts not translated` } ) : null,
		),
		h( 'div', { class: 'language__actions' },
			h( 'button', { type: 'button', class: 'btn btn--small', onClick: () => editor.edit( language ) }, 'Edit' ),
			language.is_main ? null : h( 'button', { type: 'button', class: 'btn btn--small', onClick: () => app.save( () => app.api.setMainLanguage( language.iso2 ) ).catch( report ) }, 'Make main' ),
			language.is_main ? null : h( 'button', {
				type: 'button',
				class: 'btn btn--small btn--danger',
				onClick: () => {
					if ( window.confirm( `Delete ${ language.name }? All its translations are deleted too.` ) ) {
						app.save( () => app.api.deleteLanguage( language.iso2 ) ).catch( report );
					}
				},
			}, 'Delete' ),
		),
	) );

	return h( 'div', { class: 'stack' },
		h( 'section', { class: 'form', 'aria-labelledby': 'languages-title' },
			h( 'h2', { id: 'languages-title', class: 'form__title', text: 'Languages' } ),
			h( 'p', { class: 'form__intro', text: 'The main language is shown at / and is the fallback for every text that is not translated. The others are at /{iso2}/.' } ),
			h( 'ul', { class: 'languages' }, rows ),
			status,
		),
		editor.element,
		settingsScreen( app, 'labels', 'Labels', 'Texts that are not part of a section: page title, meta description and screen-reader labels.' ),
	);
}

function languageForm( app ) {
	const ids = { iso2: uid(), iso3: uid(), name: uid(), flag: uid() };
	const inputs = {
		iso2: h( 'input', { id: ids.iso2, class: 'field__input', maxLength: 2, pattern: '[a-z]{2}', required: true, autocomplete: 'off', placeholder: 'de' } ),
		iso3: h( 'input', { id: ids.iso3, class: 'field__input', maxLength: 3, pattern: '[a-z]{3}', required: true, autocomplete: 'off', placeholder: 'deu' } ),
		name: h( 'input', { id: ids.name, class: 'field__input', maxLength: 64, required: true, autocomplete: 'off', placeholder: 'Deutsch' } ),
		flag: h( 'input', { id: ids.flag, class: 'field__file', type: 'file', accept: 'image/svg+xml,.svg' } ),
	};
	const errors = Object.fromEntries( Object.keys( inputs ).map( ( key ) => [ key, h( 'p', { class: 'field__error', hidden: true, id: uid( 'err' ) } ) ] ) );
	const title = h( 'h2', { class: 'form__title', id: uid( 'title' ), text: 'Add a language' } );
	const flagPreview = h( 'img', { class: 'language__flag', alt: '', width: 32, height: 21, hidden: true } );
	const status = h( 'p', { class: 'form__status', role: 'status', 'aria-live': 'polite' } );
	const cancel = h( 'button', { type: 'button', class: 'btn', hidden: true, text: 'Cancel' } );
	let flagId = null;

	const field = ( key, label, hint ) => h( 'div', { class: 'field' },
		h( 'label', { class: 'field__label', for: ids[ key ] }, label, h( 'span', { class: 'field__required', 'aria-hidden': 'true', text: ' *' } ) ),
		hint ? h( 'p', { class: 'field__hint', text: hint } ) : null,
		key === 'flag' ? h( 'div', { class: 'field__image' }, flagPreview, inputs.flag ) : inputs[ key ],
		errors[ key ] );

	const reset = () => {
		form.reset();
		flagId = null;
		flagPreview.hidden = true;
		inputs.iso2.readOnly = false;
		title.textContent = 'Add a language';
		cancel.hidden = true;
	};

	const form = h( 'form', { class: 'form', noValidate: true, 'aria-labelledby': title.id },
		title,
		h( 'div', { class: 'form__fields form__fields--3' },
			field( 'iso2', 'ISO 639-1 code', 'Two letters, also the URL: /de/' ),
			field( 'iso3', 'ISO 639-2 code', 'Three letters' ),
			field( 'name', 'Name', 'In the language itself, e.g. Deutsch' ),
		),
		field( 'flag', 'Flag (SVG)', 'Cleaned on upload: scripts, links and external styles are removed.' ),
		h( 'div', { class: 'form__actions' }, h( 'button', { type: 'submit', class: 'btn btn--primary', text: 'Save language' } ), cancel, status ),
	);

	inputs.flag.addEventListener( 'change', async () => {
		const file = inputs.flag.files?.[ 0 ];
		if ( ! file ) {
			return;
		}
		status.textContent = 'Uploading flag…';
		try {
			const media = await app.api.upload( file, 'flag' );
			app.data.media[ media.id ] = media;
			flagId = media.id;
			flagPreview.src = media.url;
			flagPreview.hidden = false;
			status.textContent = 'Flag uploaded.';
			errors.flag.hidden = true;
		} catch ( error ) {
			errors.flag.textContent = error.message;
			errors.flag.hidden = false;
			status.textContent = '';
		}
	} );
	cancel.addEventListener( 'click', reset );
	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		Object.values( errors ).forEach( ( error ) => ( error.hidden = true ) );
		Object.values( inputs ).forEach( ( input ) => input.removeAttribute( 'aria-invalid' ) );
		try {
			await app.save( () => app.api.saveLanguage( { iso2: inputs.iso2.value.trim(), iso3: inputs.iso3.value.trim(), name: inputs.name.value.trim(), flag: flagId } ) );
		} catch ( error ) {
			for ( const [ key, message ] of Object.entries( error.fields ?? {} ) ) {
				if ( errors[ key ] ) {
					errors[ key ].textContent = message;
					errors[ key ].hidden = false;
					inputs[ key ].setAttribute( 'aria-invalid', 'true' );
					inputs[ key ].setAttribute( 'aria-describedby', errors[ key ].id );
				}
			}
			status.textContent = error.message;
			status.classList.add( 'is-error' );
			form.querySelector( '[aria-invalid]' )?.focus();
		}
	} );

	return {
		element: form,
		edit( language ) {
			reset();
			inputs.iso2.value = language.iso2;
			inputs.iso2.readOnly = true;
			inputs.iso3.value = language.iso3;
			inputs.name.value = language.name;
			flagId = language.flag;
			const flag = app.data.media[ language.flag ];
			flagPreview.hidden = ! flag;
			if ( flag ) {
				flagPreview.src = flag.url;
			}
			title.textContent = `Edit ${ language.name }`;
			cancel.hidden = false;
			inputs.name.focus();
		},
	};
}

const SVG = 'http://www.w3.org/2000/svg';

function svg( tag, attrs, text ) {
	const element = document.createElementNS( SVG, tag );
	Object.entries( attrs ).forEach( ( [ name, value ] ) => element.setAttribute( name, String( value ) ) );
	if ( text ) {
		element.textContent = text;
	}
	return element;
}

/** Read-only diagram of the page's JSON-LD graph (nodes on a circle, arrows for @id links) and the raw JSON. */
export function schemaScreen( app ) {
	const { nodes, edges } = schemaGraph( app.data.schema );
	const size = 640;
	const center = size / 2;
	const radius = 220;
	const position = new Map( nodes.map( ( node, index ) => {
		const angle = ( ( 2 * Math.PI * index ) / nodes.length ) - ( Math.PI / 2 );
		return [ node.id, { x: center + ( radius * Math.cos( angle ) ), y: center + ( radius * Math.sin( angle ) ) } ];
	} ) );

	const diagram = svg( 'svg', { viewBox: `0 0 ${ size } ${ size }`, class: 'schema__diagram', role: 'img', 'aria-labelledby': 'schema-desc' } );
	diagram.append( svg( 'desc', { id: 'schema-desc' }, `Schema graph: ${ edges.map( ( edge ) => `${ typeOf( nodes, edge.from ) } ${ edge.label } ${ typeOf( nodes, edge.to ) }` ).join( '; ' ) }.` ) );
	const defs = svg( 'defs', {} );
	const marker = svg( 'marker', { id: 'arrow', viewBox: '0 0 10 10', refX: 10, refY: 5, markerWidth: 8, markerHeight: 8, orient: 'auto-start-reverse' } );
	marker.append( svg( 'path', { d: 'M0 0L10 5L0 10z', class: 'schema__arrow' } ) );
	defs.append( marker );
	diagram.append( defs );

	edges.forEach( ( edge, index ) => {
		const from = position.get( edge.from );
		const to = position.get( edge.to );
		// Two links between the same pair (worksFor / founder) bend to opposite sides so both stay readable.
		const bend = edges.some( ( other, otherIndex ) => otherIndex !== index && other.from === edge.to && other.to === edge.from ) ? 40 : 0;
		const mid = { x: ( ( from.x + to.x ) / 2 ) + ( bend * ( ( to.y - from.y ) / radius ) / 2 ), y: ( ( from.y + to.y ) / 2 ) - ( bend * ( ( to.x - from.x ) / radius ) / 2 ) };
		const end = shorten( mid, to, 70 );
		diagram.append(
			svg( 'path', { d: `M${ shorten( mid, from, 70 ).x } ${ shorten( mid, from, 70 ).y } Q${ mid.x } ${ mid.y } ${ end.x } ${ end.y }`, class: 'schema__edge', 'marker-end': 'url(#arrow)' } ),
			svg( 'text', { x: mid.x, y: mid.y, class: 'schema__label', 'text-anchor': 'middle' }, edge.label ),
		);
	} );
	nodes.forEach( ( node ) => {
		const { x, y } = position.get( node.id );
		const group = svg( 'g', { class: 'schema__node' } );
		group.append(
			svg( 'rect', { x: x - 80, y: y - 30, width: 160, height: 60, rx: 12 } ),
			svg( 'text', { x, y: y - 6, 'text-anchor': 'middle', class: 'schema__type' }, node.type ),
			svg( 'text', { x, y: y + 16, 'text-anchor': 'middle', class: 'schema__name' }, truncate( node.label, 24 ) ),
		);
		diagram.append( group );
	} );

	return h( 'section', { class: 'form', 'aria-labelledby': 'schema-title' },
		h( 'h2', { id: 'schema-title', class: 'form__title', text: 'Schema graph' } ),
		h( 'p', { class: 'form__intro', text: 'The JSON-LD in the <head> of the main-language page. The Person node is fixed; the company is a LocalBusiness (ProfessionalService) founded by the person, publishing the website.' } ),
		diagram,
		h( 'details', { class: 'schema__json' }, h( 'summary', { text: 'Raw JSON-LD' } ), h( 'pre', {}, h( 'code', { text: JSON.stringify( app.data.schema, null, 2 ) } ) ) ),
	);
}

function typeOf( nodes, id ) {
	return nodes.find( ( node ) => node.id === id )?.type ?? id;
}

/** The point `distance` before `to` on the line from `from` (so arrows stop at the node box). */
function shorten( from, to, distance ) {
	const length = Math.hypot( to.x - from.x, to.y - from.y ) || 1;
	return { x: to.x - ( ( ( to.x - from.x ) / length ) * distance ), y: to.y - ( ( ( to.y - from.y ) / length ) * distance ) };
}

function truncate( text, max ) {
	return text.length > max ? `${ text.slice( 0, max - 1 ) }…` : text;
}
