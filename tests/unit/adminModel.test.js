import { test } from 'node:test';
import assert from 'node:assert/strict';
import { emptyValues, missingTranslations, moveItem, schemaGraph, wrapSelection } from '../../src/admin/model.js';

const FIELDS = [
	{ name: 'label', type: 'text', required: true },
	{ name: 'text', type: 'rich', required: false },
	{ name: 'href', type: 'url', required: true },
	{ name: 'style', type: 'select', required: true, options: [ 'primary', 'ghost' ] },
];

test( 'new items start empty: translations as objects, selects on their first option', () => {
	assert.deepEqual( emptyValues( FIELDS ), { label: {}, text: {}, href: '', style: 'primary' } );
} );

test( 'items move up and down but never out of the list', () => {
	assert.deepEqual( moveItem( [ 'a', 'b', 'c' ], 0, 1 ), [ 'b', 'a', 'c' ] );
	assert.deepEqual( moveItem( [ 'a', 'b', 'c' ], 2, -1 ), [ 'a', 'c', 'b' ] );
	const list = [ 'a', 'b' ];
	assert.equal( moveItem( list, 0, -1 ), list );
	assert.equal( moveItem( list, 1, 1 ), list );
} );

test( 'counts required translations missing in a language', () => {
	assert.equal( missingTranslations( FIELDS, { label: { en: 'Hi', sr: '  ' } }, 'sr' ), 1 );
	assert.equal( missingTranslations( FIELDS, { label: { en: 'Hi' } }, 'en' ), 0 );
	assert.equal( missingTranslations( FIELDS, undefined, 'en' ), 1 );
} );

test( 'schema graph: nodes and the @id links between them', () => {
	const { nodes, edges } = schemaGraph( { '@graph': [
		{ '@type': 'Person', '@id': '#p', name: 'Me', worksFor: { '@id': '#c' }, address: { '@type': 'PostalAddress' } },
		{ '@type': [ 'ProfessionalService' ], '@id': '#c', name: 'Co', founder: { '@id': '#p' }, sameAs: [ { '@id': '#elsewhere' } ] },
	] } );
	assert.deepEqual( nodes, [ { id: '#p', type: 'Person', label: 'Me' }, { id: '#c', type: 'ProfessionalService', label: 'Co' } ] );
	assert.deepEqual( edges, [ { from: '#p', to: '#c', label: 'worksFor' }, { from: '#c', to: '#p', label: 'founder' } ] );
	assert.deepEqual( schemaGraph( null ), { nodes: [], edges: [] } );
} );

test( 'wraps the selection in a tag', () => {
	assert.equal( wrapSelection( 'Building digital systems', 9, 16, 'em' ), 'Building <em>digital</em> systems' );
	assert.equal( wrapSelection( 'ab', 1, 1, 'b' ), 'a<b></b>b' );
} );
