/**
 * Flag dropdown in the header. Each language has its own URL (/, /sr/, …), so the options are plain links
 * rendered by PHP; this only opens and closes the menu.
 */
export class LanguagePicker {
	constructor( container ) {
		this.container = container;
		this.current = container.querySelector( '.lang-picker__current' );
		this.menu = container.querySelector( '.lang-picker__menu' );
	}

	init() {
		this.current.addEventListener( 'click', () => this.setOpen( this.menu.hidden ) );
		document.addEventListener( 'click', ( event ) => {
			if ( ! this.container.contains( event.target ) ) {
				this.setOpen( false );
			}
		} );
		this.container.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' && ! this.menu.hidden ) {
				this.setOpen( false );
				this.current.focus();
			}
		} );
	}

	setOpen( isOpen ) {
		this.menu.hidden = ! isOpen;
		this.current.setAttribute( 'aria-expanded', String( isOpen ) );
	}
}
