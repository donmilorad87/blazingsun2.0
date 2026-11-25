// Pure helpers for the admin portal (no DOM), unit-tested in tests/unit/adminModel.test.js.

/** Text and rich-text fields are stored per language ({ iso2: text }); every other type is one string. */
export function isTranslated( field ) {
	return field.type === 'text' || field.type === 'rich';
}

/** Empty value for a new item: {} for translated fields, '' otherwise (a select gets its first option). */
export function emptyValues( fields ) {
	return Object.fromEntries( fields.map( ( field ) => [
		field.name,
		isTranslated( field ) ? {} : ( field.options?.[ 0 ] ?? '' ),
	] ) );
}

/** A copy of `list` with the item at `index` moved by `delta` (−1 up, +1 down); unchanged when it would leave the list. */
export function moveItem( list, index, delta ) {
	const target = index + delta;
	if ( index < 0 || index >= list.length || target < 0 || target >= list.length ) {
		return list;
	}
	const copy = [ ...list ];
	[ copy[ index ], copy[ target ] ] = [ copy[ target ], copy[ index ] ];
	return copy;
}

/** How many required translated fields have no own text in `lang` (they show the main language instead). */
export function missingTranslations( fields, values, lang ) {
	return fields.filter( ( field ) => field.required && isTranslated( field ) && ! values?.[ field.name ]?.[ lang ]?.trim() ).length;
}

/**
 * Nodes and links of a JSON-LD @graph, for the schema diagram. A link is any property whose value is { "@id": … }
 * (or a list of them) pointing at another node of the graph.
 */
export function schemaGraph( jsonld ) {
	const graph = jsonld?.[ '@graph' ] ?? [];
	const ids = new Set( graph.map( ( node ) => node[ '@id' ] ) );
	const nodes = graph.map( ( node ) => ( {
		id: node[ '@id' ],
		type: [].concat( node[ '@type' ] ).join( ', ' ),
		label: node.name ?? node.url ?? node[ '@id' ],
	} ) );
	const edges = graph.flatMap( ( node ) => Object.entries( node ).flatMap( ( [ property, value ] ) => [].concat( value )
		.filter( ( ref ) => ref && typeof ref === 'object' && Object.keys( ref ).length === 1 && ids.has( ref[ '@id' ] ) )
		.map( ( ref ) => ( { from: node[ '@id' ], to: ref[ '@id' ], label: property } ) ) ) );
	return { nodes, edges };
}

/** Wraps the selected part of `text` (selectionStart…selectionEnd) in <tag>…</tag>. */
export function wrapSelection( text, start, end, tag ) {
	return `${ text.slice( 0, start ) }<${ tag }>${ text.slice( start, end ) }</${ tag }>${ text.slice( end ) }`;
}
