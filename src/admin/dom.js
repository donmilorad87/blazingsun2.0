/**
 * Creates an element. Text always goes in as text (never innerHTML), so content from the API can't inject markup.
 *   h( 'button', { class: 'btn', type: 'button', onClick: fn, data: { id: 3 } }, 'Save' )
 * attrs: class, text, on<Event> handlers, data (dataset), booleans (true → present, false/null → absent), the rest as attributes.
 */
export function h( tag, attrs = {}, ...children ) {
	const element = document.createElement( tag );
	for ( const [ name, value ] of Object.entries( attrs ) ) {
		if ( value === false || value === null || value === undefined ) {
			continue;
		}
		if ( name === 'text' ) {
			element.textContent = value;
		} else if ( name === 'data' ) {
			Object.assign( element.dataset, value );
		} else if ( name.startsWith( 'on' ) && typeof value === 'function' ) {
			element.addEventListener( name.slice( 2 ).toLowerCase(), value );
		} else if ( name === 'value' || ( name in element && typeof value !== 'string' ) ) {
			element[ name ] = value;
		} else {
			element.setAttribute( name, value === true ? '' : String( value ) );
		}
	}
	element.append( ...children.flat( 2 ).filter( ( child ) => child !== null && child !== undefined && child !== false ) );
	return element;
}

let idCounter = 0;

/** Unique id for label/input pairs. */
export function uid( prefix = 'f' ) {
	idCounter += 1;
	return `${ prefix }-${ idCounter }`;
}
