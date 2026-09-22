/**
 * This file is part of the MediaWiki extension JsonForms.
 *
 * JsonForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * JsonForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JsonForms. If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2026, https://wikisphere.org
 */

/* global JsonForms, jQuery */
/* eslint-disable es-x/no-rest-spread-properties */

function JsonFormsSlotManager( el, data ) {
	JsonFormsSlotManager.super.call( this, el, data );

	this.metadata = data.metadata;
	this.jsonformsConfig = mw.config.get( 'jsonforms' );
	this.editPage = data.editPage;
}

OO.inheritClass( JsonFormsSlotManager, JsonForms );

JsonFormsSlotManager.prototype.onFormButton = function ( action, editor ) {
	const innerformEditor = this.editor.getEditor( 'root.editor' );
	const innerEditor = innerformEditor.input.editor;

	switch ( action ) {
		case 'cancel': {
			const url = mw.config
				.get( 'wgArticlePath' )
				.replace( '$1', mw.config.get( 'wgPageName' ) );

			window.location.href = url; }
			break;

		case 'submit': {
			const innerEditorValidationResults = innerEditor.validate();
			console.log( 'innerEditorValidationResults', innerEditorValidationResults );

			if ( innerEditorValidationResults.length ) {
				JsonForms.Alert( this.getMsg( 'there-are-errors' ) );
				return;
			} else {
				editor.disable();
				this.submitForm()
					.then( () => editor.enable() )
					.catch( ( err ) => {
						console.error( 'API error:', err );
						editor.enable();
					} );
			} }
			break;
	}
};

// ***redefine enum provider and callbacks
JsonFormsSlotManager.prototype.initialize = async function () {
	await JsonFormsSlotManager.super.prototype.initialize.call( this );

	let roles = mw.config.get( 'jsonforms' ).slotRoles;
	roles = JsonForms.Utilities.removeArrayItem( roles, 'main' );
	roles = JsonForms.Utilities.removeArrayItem( roles, 'jsonforms-metadata' );

	this.enumProviders.slotRolesNoMain = () => ( {
		source: () => roles
	} );

	if ( !this.defaultOptions ) {
		this.defaultOptions = {};
	}
	if ( !this.defaultOptions.callbacks ) {
		this.defaultOptions.callbacks = {};
	}
	if ( !this.defaultOptions.callbacks.actions ) {
		this.defaultOptions.callbacks.actions = {};
	}

	this.defaultOptions.callbacks.actions.submitButton = ( editor ) => {
		this.onFormButton( 'submit', editor );
	};
	this.defaultOptions.callbacks.actions.cancelButton = ( editor ) => {
		this.onFormButton( 'cancel', editor );
	};
};

JsonFormsSlotManager.prototype.submitForm = function () {
	const formEditor = this.editor.getEditor( 'root.editor' );

	const innerEditor = formEditor.input.editor;
	const structuredValue = innerEditor.getStructuredValue();
	const formDescriptor = { edit: this.editPage };

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		value: innerEditor.getValue(),
		structuredValue,
		formDescriptor,
		options: {
			// summary, minor
			...this.editor.getEditor( 'root.footer' ).getValue()
		},
		config: mw.config.get( 'jsonforms' ),

		// submit processor
		processor: 'SlotManager'
	};

	/*
console.log('data', data);
	return new Promise( ( resolve, reject ) => {
resolve()
})
*/
	const payload = {
		data: JSON.stringify( data ),
		action: 'jsonforms-submit-form'
	};

	// console.log('payload', payload);
	return new Promise( ( resolve, reject ) => {
		new mw.Api()
			.postWithToken( 'csrf', payload )
			.done( ( thisRes ) => {
				console.log( 'thisRes', thisRes );
				let result = thisRes[ payload.action ].result;
				result = JSON.parse( result );
				if ( result.errors && result.errors.length ) {
					const config = {
						htmlMessage: mw.msg(
							'jsonforms-jsmodule-return-errors',
							result.errors.join( ' ,' )
						),
						type: 'error'
					};
					resolve( false );
					const nonModalDialog = new JsonForms.NonModalDialog();
					nonModalDialog.open( config );
				} else {
					if ( result.returnUrl === window.location.href ) {
						window.location.reload();
					} else {
						window.location.href = result.returnUrl;
					}
					resolve( result );
				}
			} )
			.fail( ( thisRes ) => {
				// eslint-disable-next-line no-console
				console.error( 'jsonforms-submit-form', thisRes );
				reject( thisRes );
			} );
	} );
};

( function ( $ ) {
	$( () => {
		$( '.jsonforms-form-wrapper' ).each( async function ( index, el ) {
			this.el = el;
			const data = $( el ).data().formData;

			const jsonFormsSlotManager = new JsonFormsSlotManager( el, data );
			await jsonFormsSlotManager.initialize();
			const editor = jsonFormsSlotManager.createDefaultEditor();

			const editorOnChange = async ( editor ) => {
				const watching = [];
				const formEditor = editor.getEditor( 'root.editor' );

				if ( !formEditor ) {
					console.warn( 'formEditor not set' );
					return;
				}

				// *** do something with the child editor if needed
				// await is necessary since the input is the JsonForms
				// widget that needs to be loaded
				const innerEditor = await formEditor.input.getEditor();

				const slotRoles = mw.config.get( 'jsonforms' ).slotRoles;

				const innerEditorOnChange = async ( editor ) => {
					const editors = editor.getEditors();

					// assign watchers to new slots
					for ( const path in editors ) {
						// maybe role
						const role = path.replace( /^root\./, '' );

						// on slot creation
						if ( slotRoles.includes( role ) ) {
							if ( !watching.includes( path ) ) {
								// set role to hidden property

								// console.log('`${path}.role`', `${path}.role`);
								const roleEditor = editor.getEditor( `${ path }.role` );

								// @TODO replace with setValue
								// after updating the editor's setValue method
								roleEditor.setStateValue( role );
								watching.push( path );
							}
						}
					}
				};

				// inner editor is ready/changed before outer editor
				// is ready, therefore this is necessary
				// innerEditorOnChange(true);

				// this is attached on ready, therefore is not fired
				// immediately

				innerEditor.on( 'ready', innerEditorOnChange );
				innerEditor.on( 'addObjectProperty', innerEditorOnChange );
			};

			editor.on( 'ready', editorOnChange );
		} );
	} );

}( jQuery ) );
