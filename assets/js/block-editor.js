( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, Notice, Placeholder } = wp.components;

	const config = window.weFormkitBlock || { forms: [], i18n: {} };
	const i18n = config.i18n || {};

	registerBlockType( 'we-formkit/form', {
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( {
				className: 'we-formkit-block-editor',
			} );
			const forms = config.forms || [];
			const options = [
				{
					label: i18n.selectPlaceholder || 'Select a form…',
					value: '0',
				},
			].concat(
				forms.map( ( form ) => ( {
					label: form.title + ( form.slug ? ' (' + form.slug + ')' : '' ),
					value: String( form.id ),
				} ) )
			);

			const selected = forms.find(
				( form ) => String( form.id ) === String( attributes.formId )
			);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: i18n.selectForm || 'Form',
							initialOpen: true,
						},
						forms.length
							? el( SelectControl, {
									label: i18n.selectForm || 'Form',
									value: String( attributes.formId || 0 ),
									options,
									onChange( value ) {
										const id = parseInt( value, 10 ) || 0;
										const match = forms.find( ( form ) => form.id === id );
										setAttributes( {
											formId: id,
											slug: match && match.slug ? match.slug : '',
										} );
									},
							  } )
							: el( Notice, { status: 'warning', isDismissible: false }, i18n.noForms )
					)
				),
				el(
					'div',
					blockProps,
					el( Placeholder, {
						icon: 'clipboard',
						label: i18n.title || 'Formkit Form',
						instructions: selected
							? selected.title + ' — ' + ( i18n.preview || '' )
							: i18n.description || i18n.selectInSidebar || '',
					} )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
