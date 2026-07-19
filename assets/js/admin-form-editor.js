/**
 * Gravity Forms–style form builder: canvas + sidebar, DnD, width resize.
 */
( function () {
	'use strict';

	const CORE_FIELD_TYPES = [
		'text',
		'email',
		'tel',
		'url',
		'textarea',
		'number',
		'select',
		'radio',
		'radio_image',
		'checkbox',
		'checkboxes',
		'date',
		'time',
		'datetime',
		'consent',
		'html',
		'hidden',
		'upload',
		'repeater',
	];

	const FIELD_ICONS = {
		text: 'T',
		email: '@',
		tel: 'P',
		url: 'U',
		textarea: 'A',
		number: '#',
		select: 'S',
		radio: 'R',
		radio_image: 'I',
		checkbox: 'C',
		checkboxes: 'M',
		date: 'D',
		time: 'H',
		datetime: 'DT',
		consent: 'OK',
		html: '<>',
		hidden: '·',
		upload: 'F',
		repeater: '↻',
	};

	function getFieldTypes() {
		const fromAdmin =
			window.weFormkitAdmin && Array.isArray( window.weFormkitAdmin.fieldTypes )
				? window.weFormkitAdmin.fieldTypes
				: null;
		if ( fromAdmin && fromAdmin.length ) {
			return fromAdmin
				.map( function ( item ) {
					if ( typeof item === 'string' ) {
						return { type: item, label: item, adminSchema: {} };
					}
					const type = item && item.type ? String( item.type ) : '';
					if ( ! type ) {
						return null;
					}
					return {
						type: type,
						label: item.label ? String( item.label ) : type,
						adminSchema: item.adminSchema && typeof item.adminSchema === 'object' ? item.adminSchema : {},
					};
				} )
				.filter( Boolean );
		}
		return CORE_FIELD_TYPES.map( function ( type ) {
			return { type: type, label: type, adminSchema: {} };
		} );
	}

	function boot() {
		const i18n = ( window.weFormkitAdmin && window.weFormkitAdmin.i18n ) || {};
		const fieldTypes = getFieldTypes();
		const theme = ( window.weFormkitAdmin && window.weFormkitAdmin.theme ) || {};
		const root = document.getElementById( 'wek-builder' );
		const hidden = document.getElementById( 'wek_schema_json' );
		const introInput = document.getElementById( 'wek_intro' );
		const titleInput = document.getElementById( 'wek_title' );

		if ( ! root || ! hidden ) {
			return;
		}

		function readSchema() {
			if ( window.weFormkitFormSchema && typeof window.weFormkitFormSchema === 'object' ) {
				try {
					return JSON.parse( JSON.stringify( window.weFormkitFormSchema ) );
				} catch ( e ) {
					// fall through
				}
			}
			if ( hidden.value ) {
				try {
					const parsed = JSON.parse( hidden.value );
					if ( parsed && typeof parsed === 'object' ) {
						return parsed;
					}
				} catch ( e ) {
					// fall through
				}
			}
			return { version: 1, title: '', intro: '', sections: [] };
		}

		let schema = readSchema();
		if ( ! Array.isArray( schema.sections ) ) {
			schema.sections = [];
		}

		/** @type {{ type: string, sIndex: number, fIndex?: number }|null} */
		let selection = null;
		let activeTab = 'general';
		const live = { node: null };

		const ops = [
			{ value: '', label: i18n.none || 'Always visible' },
			{ value: 'equals', label: 'equals' },
			{ value: 'not_equals', label: 'not_equals' },
			{ value: 'contains', label: 'contains' },
			{ value: 'is_checked', label: 'is_checked' },
			{ value: 'is_not_empty', label: 'is_not_empty' },
		];

		function el( tag, attrs, children ) {
			const node = document.createElement( tag );
			if ( attrs ) {
				Object.keys( attrs ).forEach( function ( key ) {
					if ( key === 'className' ) {
						node.className = attrs[ key ];
					} else if ( key === 'text' ) {
						node.textContent = attrs[ key ];
					} else if ( key === 'html' ) {
						node.innerHTML = attrs[ key ];
					} else if ( key.slice( 0, 2 ) === 'on' ) {
						node.addEventListener( key.slice( 2 ).toLowerCase(), attrs[ key ] );
					} else if ( attrs[ key ] === true ) {
						node.setAttribute( key, '' );
					} else if ( attrs[ key ] !== undefined && attrs[ key ] !== null && attrs[ key ] !== false ) {
						node.setAttribute( key, attrs[ key ] );
					}
				} );
			}
			( children || [] ).forEach( function ( child ) {
				if ( child == null ) {
					return;
				}
				node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
			} );
			return node;
		}

		function announce( message ) {
			if ( live.node ) {
				live.node.textContent = message;
			}
		}

		function syncHidden() {
			if ( titleInput ) {
				schema.title = titleInput.value;
			}
			if ( introInput ) {
				schema.intro = introInput.value;
			}
			hidden.value = JSON.stringify( schema );
		}

		function widthClass( width ) {
			const w = width === 'half' || width === 'third' ? width : 'full';
			return 'wek-builder__field wek-builder__field--width-' + w;
		}

		function getSelected() {
			if ( ! selection ) {
				return null;
			}
			const section = schema.sections[ selection.sIndex ];
			if ( ! section ) {
				return null;
			}
			if ( selection.type === 'section' ) {
				return { kind: 'section', section: section, sIndex: selection.sIndex };
			}
			const field = section.fields && section.fields[ selection.fIndex ];
			if ( ! field ) {
				return null;
			}
			return {
				kind: 'field',
				section: section,
				field: field,
				sIndex: selection.sIndex,
				fIndex: selection.fIndex,
			};
		}

		function selectItem( next, tab ) {
			selection = next;
			if ( tab ) {
				activeTab = tab;
			} else if ( next && next.type === 'section' && activeTab === 'appearance' ) {
				activeTab = 'general';
			}
			render();
		}

		function fieldRow( labelText, control ) {
			return el( 'p', { className: 'wek-builder__row' }, [
				el( 'label', null, [ labelText, control ] ),
			] );
		}

		function textInput( value, onChange ) {
			const input = el( 'input', { type: 'text', className: 'regular-text', value: value || '' } );
			input.addEventListener( 'input', function () {
				onChange( input.value );
			} );
			return input;
		}

		function renderConditional( target ) {
			const rule = target.show_when || { field: '', op: '', value: '' };
			const fieldInput = el( 'input', {
				type: 'text',
				className: 'regular-text',
				placeholder: i18n.showField || 'field id',
				value: rule.field || '',
			} );
			const opSelect = el( 'select' );
			ops.forEach( function ( op ) {
				const opt = el( 'option', { value: op.value, text: op.label } );
				if ( ( rule.op || '' ) === op.value || ( ! rule.op && op.value === '' ) ) {
					opt.selected = true;
				}
				opSelect.appendChild( opt );
			} );
			const valueInput = el( 'input', {
				type: 'text',
				className: 'regular-text',
				placeholder: i18n.showValue || 'value',
				value: rule.value || '',
			} );

			function updateRule() {
				if ( ! opSelect.value ) {
					target.show_when = null;
				} else {
					target.show_when = {
						field: fieldInput.value,
						op: opSelect.value,
						value: valueInput.value,
					};
				}
				syncHidden();
			}

			fieldInput.addEventListener( 'input', updateRule );
			opSelect.addEventListener( 'change', updateRule );
			valueInput.addEventListener( 'input', updateRule );

			return el( 'div', { className: 'wek-builder__rule-panel' }, [
				fieldRow( i18n.showField || 'Depends on field ID', fieldInput ),
				fieldRow( i18n.showOp || 'Operator', opSelect ),
				fieldRow( i18n.showValue || 'Value', valueInput ),
			] );
		}

		function renderOptionsEditor( field ) {
			if ( ! Array.isArray( field.options ) ) {
				field.options = [];
			}
			const isRadioImage = field.type === 'radio_image';
			const wrap = el( 'div', { className: 'wek-builder__options' } );
			wrap.appendChild( el( 'strong', { text: i18n.options || 'Options' } ) );

			field.options.forEach( function ( option, oIndex ) {
				const row = el( 'div', { className: 'wek-builder__option-row' } );
				const valueInput = el( 'input', {
					type: 'text',
					className: 'regular-text',
					placeholder: 'value',
					value: option.value || '',
				} );
				const labelInput = el( 'input', {
					type: 'text',
					className: 'regular-text',
					placeholder: 'Label',
					value: option.label || '',
				} );
				valueInput.addEventListener( 'input', function () {
					field.options[ oIndex ].value = valueInput.value;
					syncHidden();
				} );
				labelInput.addEventListener( 'input', function () {
					field.options[ oIndex ].label = labelInput.value;
					syncHidden();
				} );
				row.appendChild( valueInput );
				row.appendChild( labelInput );

				if ( isRadioImage ) {
					const imageIdInput = el( 'input', {
						type: 'number',
						className: 'small-text',
						placeholder: 'image_id',
						value:
							option.image_id != null && option.image_id !== ''
								? String( option.image_id )
								: '',
						min: '0',
					} );
					const imageUrlInput = el( 'input', {
						type: 'text',
						className: 'regular-text',
						placeholder: 'image_url',
						value: option.image_url || '',
					} );
					imageIdInput.addEventListener( 'input', function () {
						const n = parseInt( imageIdInput.value, 10 );
						field.options[ oIndex ].image_id = isNaN( n ) ? 0 : n;
						syncHidden();
					} );
					imageUrlInput.addEventListener( 'input', function () {
						field.options[ oIndex ].image_url = imageUrlInput.value;
						syncHidden();
					} );
					row.appendChild( imageIdInput );
					row.appendChild( imageUrlInput );
				}

				row.appendChild(
					el( 'button', {
						type: 'button',
						className: 'button-link-delete',
						text: i18n.remove || 'Remove',
						onClick: function () {
							field.options.splice( oIndex, 1 );
							syncHidden();
							render();
						},
					} )
				);
				wrap.appendChild( row );
			} );

			wrap.appendChild(
				el( 'button', {
					type: 'button',
					className: 'button',
					text: i18n.addOption || 'Add option',
					onClick: function () {
						const next = {
							value: 'option_' + ( field.options.length + 1 ),
							label: 'Option ' + ( field.options.length + 1 ),
						};
						if ( isRadioImage ) {
							next.image_id = 0;
							next.image_url = '';
						}
						field.options.push( next );
						syncHidden();
						render();
					},
				} )
			);
			return wrap;
		}

		function ensureTypeOptions( field ) {
			if ( ! field.type_options || typeof field.type_options !== 'object' ) {
				field.type_options = {};
			}
			return field.type_options;
		}

		function numberInput( value, onChange, attrs ) {
			const input = el(
				'input',
				Object.assign(
					{ type: 'number', className: 'small-text', value: value != null ? String( value ) : '' },
					attrs || {}
				)
			);
			input.addEventListener( 'input', function () {
				onChange( input.value );
			} );
			return input;
		}

		function selectInput( value, choices, onChange ) {
			const select = el( 'select' );
			choices.forEach( function ( choice ) {
				const opt = el( 'option', { value: choice.value, text: choice.label } );
				if ( String( value || '' ) === String( choice.value ) ) {
					opt.selected = true;
				}
				select.appendChild( opt );
			} );
			select.addEventListener( 'change', function () {
				onChange( select.value );
			} );
			return select;
		}

		function renderConstraintBound( field, key, title ) {
			const opts = ensureTypeOptions( field );
			if ( ! opts.constraints || typeof opts.constraints !== 'object' ) {
				opts.constraints = {};
			}
			if ( ! opts.constraints[ key ] || typeof opts.constraints[ key ] !== 'object' ) {
				opts.constraints[ key ] = {
					enabled: false,
					amount: 0,
					unit: 'days',
					direction: 'future',
				};
			}
			const bound = opts.constraints[ key ];
			const enabled = el( 'input', { type: 'checkbox' } );
			enabled.checked = !! bound.enabled;
			enabled.addEventListener( 'change', function () {
				bound.enabled = !! enabled.checked;
				syncHidden();
			} );

			return el( 'div', { className: 'wek-builder__constraint' }, [
				el( 'strong', { text: title } ),
				el( 'p', { className: 'wek-builder__row' }, [
					el( 'label', { className: 'wek-builder__check' }, [
						enabled,
						' ' + ( i18n.enabled || 'Enabled' ),
					] ),
				] ),
				fieldRow(
					i18n.amount || 'Amount',
					numberInput( bound.amount, function ( v ) {
						const n = parseInt( v, 10 );
						bound.amount = isNaN( n ) ? 0 : Math.max( 0, n );
						syncHidden();
					}, { min: '0' } )
				),
				fieldRow(
					i18n.unit || 'Unit',
					selectInput(
						bound.unit || 'days',
						[
							{ value: 'days', label: 'days' },
							{ value: 'weeks', label: 'weeks' },
							{ value: 'months', label: 'months' },
							{ value: 'years', label: 'years' },
						],
						function ( v ) {
							bound.unit = v;
							syncHidden();
						}
					)
				),
				fieldRow(
					i18n.direction || 'Direction',
					selectInput(
						bound.direction || 'future',
						[
							{ value: 'past', label: 'past' },
							{ value: 'future', label: 'future' },
						],
						function ( v ) {
							bound.direction = v;
							syncHidden();
						}
					)
				),
			] );
		}

		function renderDateConstraintsEditor( field ) {
			const wrap = el( 'div', { className: 'wek-builder__constraints' } );
			wrap.appendChild( el( 'strong', { text: i18n.constraints || 'Date constraints' } ) );
			wrap.appendChild( renderConstraintBound( field, 'min', i18n.minConstraint || 'Minimum' ) );
			wrap.appendChild( renderConstraintBound( field, 'max', i18n.maxConstraint || 'Maximum' ) );
			return wrap;
		}

		function renderUploadOptionsEditor( field ) {
			const opts = ensureTypeOptions( field );
			if ( opts.max_files == null ) {
				opts.max_files = 1;
			}
			if ( opts.max_file_size_mb == null ) {
				opts.max_file_size_mb = 5;
			}
			if ( opts.allowed_mime_types == null ) {
				opts.allowed_mime_types = '';
			}
			if ( ! opts.storage_mode ) {
				opts.storage_mode = 'uploads_only';
			}

			return el( 'div', { className: 'wek-builder__upload-options' }, [
				fieldRow(
					i18n.maxFiles || 'Max files',
					numberInput( opts.max_files, function ( v ) {
						const n = parseInt( v, 10 );
						opts.max_files = isNaN( n ) ? 1 : Math.max( 1, n );
						syncHidden();
					}, { min: '1' } )
				),
				fieldRow(
					i18n.maxFileSize || 'Max file size (MB)',
					numberInput( opts.max_file_size_mb, function ( v ) {
						const n = parseInt( v, 10 );
						opts.max_file_size_mb = isNaN( n ) ? 5 : Math.max( 1, n );
						syncHidden();
					}, { min: '1' } )
				),
				fieldRow(
					i18n.allowedMime || 'Allowed MIME types',
					textInput( opts.allowed_mime_types || '', function ( v ) {
						opts.allowed_mime_types = v;
						syncHidden();
					} )
				),
				fieldRow(
					i18n.storageMode || 'Storage mode',
					selectInput(
						opts.storage_mode,
						[
							{ value: 'uploads_only', label: 'uploads_only' },
							{ value: 'media_library', label: 'media_library' },
						],
						function ( v ) {
							opts.storage_mode = v;
							syncHidden();
						}
					)
				),
			] );
		}

		function renderHtmlContentEditor( field ) {
			const opts = ensureTypeOptions( field );
			const area = el( 'textarea', {
				className: 'large-text',
				rows: '6',
			} );
			area.value = opts.content || '';
			area.addEventListener( 'input', function () {
				opts.content = area.value;
				syncHidden();
			} );
			return fieldRow( i18n.htmlContent || 'HTML content', area );
		}

		function renderHiddenDefaultEditor( field ) {
			const opts = ensureTypeOptions( field );
			return fieldRow(
				i18n.defaultValue || 'Default value',
				textInput( opts.default_value || '', function ( v ) {
					opts.default_value = v;
					syncHidden();
				} )
			);
		}

		function getRepeaterItemTypes() {
			const allowed =
				window.weFormkitAdmin && Array.isArray( window.weFormkitAdmin.repeaterItemTypes )
					? window.weFormkitAdmin.repeaterItemTypes
					: [ 'text', 'email', 'tel', 'url', 'number', 'textarea', 'select', 'date', 'time', 'datetime' ];
			return allowed
				.map( function ( typeId ) {
					const meta = fieldTypes.find( function ( item ) {
						return item.type === typeId;
					} );
					return {
						value: typeId,
						label: ( meta && meta.label ) || typeId,
					};
				} )
				.filter( Boolean );
		}

		function ensureRepeaterDefaults( field ) {
			const opts = ensureTypeOptions( field );
			if ( opts.min_items == null ) {
				opts.min_items = 1;
			}
			if ( opts.max_items == null ) {
				opts.max_items = 5;
			}
			if ( opts.add_button_label == null ) {
				opts.add_button_label = '';
			}
			if ( ! Array.isArray( opts.fields ) ) {
				opts.fields = [];
			}
			return opts;
		}

		function renderRepeaterFieldsEditor( field ) {
			const opts = ensureRepeaterDefaults( field );
			const wrap = el( 'div', { className: 'wek-builder__repeater-options' } );

			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.repeaterHint ||
						'These fields are cloned together when someone adds another row.',
				} )
			);

			wrap.appendChild(
				fieldRow(
					i18n.minRows || 'Minimum rows',
					numberInput( opts.min_items, function ( v ) {
						const n = parseInt( v, 10 );
						opts.min_items = isNaN( n ) ? 0 : Math.max( 0, n );
						syncHidden();
					}, { min: '0' } )
				)
			);
			wrap.appendChild(
				fieldRow(
					i18n.maxRows || 'Maximum rows',
					numberInput( opts.max_items, function ( v ) {
						const n = parseInt( v, 10 );
						opts.max_items = isNaN( n ) ? 5 : Math.max( 1, n );
						syncHidden();
					}, { min: '1' } )
				)
			);
			wrap.appendChild(
				fieldRow(
					i18n.addRowLabel || 'Add row button label',
					textInput( opts.add_button_label || '', function ( v ) {
						opts.add_button_label = v;
						syncHidden();
					} )
				)
			);

			wrap.appendChild(
				el( 'strong', {
					className: 'wek-builder__repeater-fields-title',
					text: i18n.repeaterFields || 'Fields in each row',
				} )
			);

			const list = el( 'div', { className: 'wek-builder__repeater-fields' } );

			function refreshList() {
				list.innerHTML = '';
				opts.fields.forEach( function ( child, index ) {
					list.appendChild( renderNestedFieldCard( field, child, index ) );
				} );
			}

			function renderNestedFieldCard( parentField, child, index ) {
				const card = el( 'div', { className: 'wek-builder__nested-field' } );
				card.appendChild(
					el( 'div', { className: 'wek-builder__nested-field-head' }, [
						el( 'strong', {
							text: ( i18n.nestedField || 'Row field' ) + ' ' + ( index + 1 ),
						} ),
						el( 'button', {
							type: 'button',
							className: 'button-link-delete',
							text: i18n.removeNested || 'Remove from row',
							onClick: function () {
								opts.fields.splice( index, 1 );
								syncHidden();
								refreshList();
								render();
							},
						} ),
					] )
				);

				const typeChoices = getRepeaterItemTypes();
				card.appendChild(
					fieldRow(
						i18n.type || 'Type',
						selectInput( child.type || 'text', typeChoices, function ( v ) {
							child.type = v;
							if ( [ 'select' ].indexOf( v ) !== -1 && ! Array.isArray( child.options ) ) {
								child.options = [];
							}
							syncHidden();
							refreshList();
						} )
					)
				);
				card.appendChild(
					fieldRow(
						i18n.id || 'Field ID',
						textInput( child.id || '', function ( v ) {
							child.id = String( v || '' )
								.toLowerCase()
								.replace( /[^a-z0-9_-]/g, '_' );
							syncHidden();
						} )
					)
				);
				card.appendChild(
					fieldRow(
						i18n.label || 'Label',
						textInput( child.label || '', function ( v ) {
							child.label = v;
							syncHidden();
							const preview = root.querySelector(
								'.wek-builder__field[data-s="' +
									selection.sIndex +
									'"][data-f="' +
									selection.fIndex +
									'"] .wek-builder__field-preview'
							);
							if ( preview ) {
								preview.textContent = repeaterPreviewText( parentField );
							}
						} )
					)
				);
				card.appendChild(
					fieldRow(
						i18n.placeholder || 'Placeholder',
						textInput( child.placeholder || '', function ( v ) {
							child.placeholder = v;
							syncHidden();
						} )
					)
				);

				const req = el( 'input', { type: 'checkbox' } );
				req.checked = !! child.required;
				req.addEventListener( 'change', function () {
					child.required = req.checked;
					syncHidden();
				} );
				card.appendChild(
					el( 'p', { className: 'wek-builder__row' }, [
						el( 'label', { className: 'wek-builder__check' }, [
							req,
							' ' + ( i18n.required || 'Required' ),
						] ),
					] )
				);

				if ( child.type === 'select' ) {
					if ( ! Array.isArray( child.options ) ) {
						child.options = [];
					}
					card.appendChild( renderOptionsEditor( child ) );
				}

				return card;
			}

			refreshList();
			wrap.appendChild( list );

			const addType = el( 'select' );
			getRepeaterItemTypes().forEach( function ( item ) {
				addType.appendChild( el( 'option', { value: item.value, text: item.label } ) );
			} );

			wrap.appendChild(
				el( 'div', { className: 'wek-builder__repeater-add-row' }, [
					addType,
					el( 'button', {
						type: 'button',
						className: 'button button-secondary',
						text: i18n.addNestedField || 'Add field to row',
						onClick: function () {
							const typeId = addType.value || 'text';
							const meta = fieldTypes.find( function ( item ) {
								return item.type === typeId;
							} );
							opts.fields.push( {
								id: typeId + '_' + Date.now().toString( 36 ),
								type: typeId,
								label: ( meta && meta.label ) || typeId,
								help: '',
								required: false,
								placeholder: '',
								type_options: {},
								width: 'full',
								options: [],
								show_when: null,
							} );
							syncHidden();
							refreshList();
							render();
						},
					} ),
				] )
			);

			return wrap;
		}

		function repeaterPreviewText( field ) {
			const opts = field.type_options || {};
			const nested = Array.isArray( opts.fields ) ? opts.fields : [];
			if ( ! nested.length ) {
				return i18n.repeaterFields || 'Fields in each row';
			}
			return nested
				.map( function ( child ) {
					return child.label || child.id || child.type;
				} )
				.join( ' · ' );
		}

		function shouldShowPlaceholder( type ) {
			return (
				[
					'checkbox',
					'consent',
					'html',
					'hidden',
					'radio',
					'checkboxes',
					'select',
					'radio_image',
					'upload',
					'repeater',
				].indexOf( type ) === -1
			);
		}

		function renderSidebar( aside ) {
			aside.innerHTML = '';
			const selected = getSelected();
			if ( ! selected ) {
				aside.appendChild(
					el( 'p', {
						className: 'wek-builder-sidebar__empty',
						text: i18n.selectHint || 'Select a field or section to edit its settings.',
					} )
				);
				return;
			}

			const isField = selected.kind === 'field';
			const title = isField
				? ( i18n.field || 'Field' ) + ': ' + ( selected.field.id || '' )
				: ( i18n.section || 'Section' ) + ': ' + ( selected.section.title || selected.section.id || '' );

			const head = el( 'div', { className: 'wek-builder-sidebar__head' }, [
				el( 'h3', { className: 'wek-builder-sidebar__title', id: 'wek-builder-sidebar-title', text: title } ),
				el( 'button', {
					type: 'button',
					className: 'button-link-delete',
					text: i18n.remove || 'Remove',
					onClick: function () {
						if ( ! window.confirm( i18n.confirmDel || 'Remove this item?' ) ) {
							return;
						}
						if ( isField ) {
							selected.section.fields.splice( selected.fIndex, 1 );
						} else {
							schema.sections.splice( selected.sIndex, 1 );
						}
						selection = null;
						syncHidden();
						render();
					},
				} ),
			] );
			aside.appendChild( head );

			const tabs = isField
				? [
						{ id: 'general', label: i18n.tabGeneral || 'General' },
						{ id: 'appearance', label: i18n.tabAppearance || 'Appearance' },
						{ id: 'conditional', label: i18n.tabConditional || 'Conditional' },
				  ]
				: [
						{ id: 'general', label: i18n.tabGeneral || 'General' },
						{ id: 'conditional', label: i18n.tabConditional || 'Conditional' },
				  ];

			if ( ! tabs.some( function ( t ) {
				return t.id === activeTab;
			} ) ) {
				activeTab = 'general';
			}

			const tabBar = el( 'div', { className: 'wek-builder-tabs', role: 'tablist' } );
			tabs.forEach( function ( tab ) {
				tabBar.appendChild(
					el( 'button', {
						type: 'button',
						className: 'wek-builder-tabs__btn' + ( activeTab === tab.id ? ' is-active' : '' ),
						role: 'tab',
						'aria-selected': activeTab === tab.id ? 'true' : 'false',
						text: tab.label,
						onClick: function () {
							activeTab = tab.id;
							render();
						},
					} )
				);
			} );
			aside.appendChild( tabBar );

			const panel = el( 'div', {
				className: 'wek-builder-tabs__panel is-active',
				role: 'tabpanel',
			} );

			if ( isField && activeTab === 'general' ) {
				const field = selected.field;
				panel.appendChild(
					fieldRow(
						i18n.id || 'Field ID',
						textInput( field.id || '', function ( v ) {
							field.id = v;
							syncHidden();
							const titleEl = aside.querySelector( '.wek-builder-sidebar__title' );
							if ( titleEl ) {
								titleEl.textContent = ( i18n.field || 'Field' ) + ': ' + v;
							}
							const card = root.querySelector(
								'.wek-builder__field[data-s="' +
									selected.sIndex +
									'"][data-f="' +
									selected.fIndex +
									'"] .wek-builder__field-label'
							);
							if ( card && ! field.label ) {
								card.textContent = v || i18n.field || 'Field';
							}
						} )
					)
				);
				panel.appendChild(
					fieldRow(
						i18n.label || 'Label',
						textInput( field.label || '', function ( v ) {
							field.label = v;
							syncHidden();
							const card = root.querySelector(
								'.wek-builder__field[data-s="' +
									selected.sIndex +
									'"][data-f="' +
									selected.fIndex +
									'"] .wek-builder__field-label'
							);
							if ( card ) {
								card.textContent = v || field.id || i18n.field || 'Field';
							}
						} )
					)
				);

				const typeSelect = el( 'select' );
				fieldTypes.forEach( function ( item ) {
					const opt = el( 'option', { value: item.type, text: item.label || item.type } );
					if ( field.type === item.type ) {
						opt.selected = true;
					}
					typeSelect.appendChild( opt );
				} );
				if ( field.type && ! fieldTypes.some( function ( item ) {
					return item.type === field.type;
				} ) ) {
					const orphan = el( 'option', { value: field.type, text: field.type } );
					orphan.selected = true;
					typeSelect.appendChild( orphan );
				}
				typeSelect.addEventListener( 'change', function () {
					field.type = typeSelect.value;
					ensureTypeOptions( field );
					syncHidden();
					render();
				} );
				panel.appendChild( fieldRow( i18n.type || 'Type', typeSelect ) );

				const req = el( 'input', { type: 'checkbox' } );
				req.checked = !! field.required;
				req.addEventListener( 'change', function () {
					field.required = !! req.checked;
					syncHidden();
				} );
				panel.appendChild(
					el( 'p', { className: 'wek-builder__row' }, [
						el( 'label', { className: 'wek-builder__check' }, [
							req,
							' ' + ( i18n.required || 'Required' ),
						] ),
					] )
				);

				panel.appendChild(
					fieldRow(
						i18n.help || 'Help text',
						textInput( field.help || '', function ( v ) {
							field.help = v;
							syncHidden();
						} )
					)
				);

				if ( shouldShowPlaceholder( field.type ) ) {
					if ( field.placeholder == null ) {
						field.placeholder = '';
					}
					panel.appendChild(
						fieldRow(
							i18n.placeholder || 'Placeholder',
							textInput( field.placeholder || '', function ( v ) {
								field.placeholder = v;
								syncHidden();
							} )
						)
					);
				}

				if ( [ 'radio', 'checkboxes', 'select', 'radio_image' ].indexOf( field.type ) !== -1 ) {
					panel.appendChild( renderOptionsEditor( field ) );
				}

				if ( field.type === 'date' || field.type === 'datetime' ) {
					panel.appendChild( renderDateConstraintsEditor( field ) );
				}

				if ( field.type === 'upload' ) {
					panel.appendChild( renderUploadOptionsEditor( field ) );
				}

				if ( field.type === 'html' ) {
					panel.appendChild( renderHtmlContentEditor( field ) );
				}

				if ( field.type === 'hidden' ) {
					panel.appendChild( renderHiddenDefaultEditor( field ) );
				}

				if ( field.type === 'repeater' ) {
					panel.appendChild( renderRepeaterFieldsEditor( field ) );
				}
			} else if ( isField && activeTab === 'appearance' ) {
				const field = selected.field;
				if ( ! field.width ) {
					field.width = 'full';
				}
				const widthSelect = el( 'select' );
				[
					{ value: 'full', label: i18n.widthFull || 'Full width' },
					{ value: 'half', label: i18n.widthHalf || 'Half width' },
					{ value: 'third', label: i18n.widthThird || 'One third' },
				].forEach( function ( item ) {
					const opt = el( 'option', { value: item.value, text: item.label } );
					if ( field.width === item.value ) {
						opt.selected = true;
					}
					widthSelect.appendChild( opt );
				} );
				widthSelect.addEventListener( 'change', function () {
					field.width = widthSelect.value;
					syncHidden();
					render();
				} );
				panel.appendChild( fieldRow( i18n.width || 'Width', widthSelect ) );
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.resizeHint ||
							'You can also drag the right edge of a field on the canvas to change its width.',
					} )
				);
			} else if ( isField && activeTab === 'conditional' ) {
				panel.appendChild( renderConditional( selected.field ) );
			} else if ( ! isField && activeTab === 'general' ) {
				const section = selected.section;
				panel.appendChild(
					fieldRow(
						i18n.sectionTitle || 'Title',
						textInput( section.title || '', function ( v ) {
							section.title = v;
							syncHidden();
							const card = root.querySelector(
								'.wek-builder__section[data-s="' + selected.sIndex + '"] .wek-builder__section-title'
							);
							if ( card ) {
								card.textContent = v || section.id || i18n.section || 'Section';
							}
						} )
					)
				);
				panel.appendChild(
					fieldRow(
						i18n.sectionId || 'Section ID',
						textInput( section.id || '', function ( v ) {
							section.id = v;
							syncHidden();
						} )
					)
				);
			} else if ( ! isField && activeTab === 'conditional' ) {
				panel.appendChild( renderConditional( selected.section ) );
			}

			aside.appendChild( panel );
		}

		function applyWidthFromPointer( field, card, clientX ) {
			const grid = card.parentElement;
			if ( ! grid ) {
				return;
			}
			const rect = grid.getBoundingClientRect();
			const ratio = ( clientX - rect.left ) / Math.max( rect.width, 1 );
			let next = 'full';
			if ( ratio < 0.28 ) {
				next = 'third';
			} else if ( ratio < 0.58 ) {
				next = 'half';
			}
			if ( field.width !== next ) {
				field.width = next;
				card.className =
					widthClass( next ) +
					( selection &&
					selection.type === 'field' &&
					String( selection.sIndex ) === card.getAttribute( 'data-s' ) &&
					String( selection.fIndex ) === card.getAttribute( 'data-f' )
						? ' is-selected'
						: '' );
				announce( ( i18n.width || 'Width' ) + ': ' + next );
			}
		}

		function bindResize( handle, field, card ) {
			handle.addEventListener( 'pointerdown', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				handle.setPointerCapture( event.pointerId );
				const onMove = function ( e ) {
					applyWidthFromPointer( field, card, e.clientX );
				};
				const onUp = function () {
					handle.releasePointerCapture( event.pointerId );
					handle.removeEventListener( 'pointermove', onMove );
					handle.removeEventListener( 'pointerup', onUp );
					handle.removeEventListener( 'pointercancel', onUp );
					syncHidden();
					render();
				};
				handle.addEventListener( 'pointermove', onMove );
				handle.addEventListener( 'pointerup', onUp );
				handle.addEventListener( 'pointercancel', onUp );
			} );
		}

		function moveField( fromS, fromF, toS, toF ) {
			const source = schema.sections[ fromS ];
			const target = schema.sections[ toS ];
			if ( ! source || ! target || ! source.fields ) {
				return;
			}
			if ( fromF < 0 || fromF >= source.fields.length ) {
				return;
			}
			const item = source.fields.splice( fromF, 1 )[ 0 ];
			if ( ! target.fields ) {
				target.fields = [];
			}
			let insertAt = typeof toF === 'number' ? toF : target.fields.length;
			if ( fromS === toS && fromF < insertAt ) {
				insertAt -= 1;
			}
			insertAt = Math.max( 0, Math.min( insertAt, target.fields.length ) );
			target.fields.splice( insertAt, 0, item );
			selection = { type: 'field', sIndex: toS, fIndex: insertAt };
			announce( i18n.moved || 'Item moved.' );
			syncHidden();
			render();
		}

		function moveSection( fromS, toS ) {
			if ( fromS === toS || fromS < 0 || toS < 0 ) {
				return;
			}
			if ( fromS >= schema.sections.length || toS > schema.sections.length ) {
				return;
			}
			const item = schema.sections.splice( fromS, 1 )[ 0 ];
			let insertAt = toS;
			if ( fromS < toS ) {
				insertAt -= 1;
			}
			insertAt = Math.max( 0, Math.min( insertAt, schema.sections.length ) );
			schema.sections.splice( insertAt, 0, item );
			selection = { type: 'section', sIndex: insertAt };
			announce( i18n.moved || 'Item moved.' );
			syncHidden();
			render();
		}

		function bindFieldDrag( handle, card, sIndex, fIndex ) {
			handle.addEventListener( 'pointerdown', function ( event ) {
				if ( event.button !== 0 ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				card.classList.add( 'is-dragging' );
				handle.setAttribute( 'aria-grabbed', 'true' );

				const startX = event.clientX;
				const startY = event.clientY;
				let started = false;
				let dropS = sIndex;
				let dropF = fIndex;

				function clearIndicators() {
					root.querySelectorAll( '.wek-builder__drop-line' ).forEach( function ( n ) {
						n.remove();
					} );
					root.querySelectorAll( '.is-drop-target' ).forEach( function ( n ) {
						n.classList.remove( 'is-drop-target' );
					} );
				}

				function updateDropTarget( clientX, clientY ) {
					clearIndicators();
					const elUnder = document.elementFromPoint( clientX, clientY );
					if ( ! elUnder ) {
						return;
					}
					const fieldCard = elUnder.closest( '.wek-builder__field' );
					const sectionCard = elUnder.closest( '.wek-builder__section' );
					if ( fieldCard && fieldCard !== card ) {
						const ts = parseInt( fieldCard.getAttribute( 'data-s' ), 10 );
						const tf = parseInt( fieldCard.getAttribute( 'data-f' ), 10 );
						const rect = fieldCard.getBoundingClientRect();
						const before = clientY < rect.top + rect.height / 2;
						dropS = ts;
						dropF = before ? tf : tf + 1;
						const line = el( 'div', { className: 'wek-builder__drop-line' } );
						if ( before ) {
							fieldCard.parentNode.insertBefore( line, fieldCard );
						} else {
							fieldCard.parentNode.insertBefore( line, fieldCard.nextSibling );
						}
						return;
					}
					if ( sectionCard ) {
						const ts = parseInt( sectionCard.getAttribute( 'data-s' ), 10 );
						const grid = sectionCard.querySelector( '.wek-builder__fields' );
						dropS = ts;
						dropF = schema.sections[ ts ] && schema.sections[ ts ].fields
							? schema.sections[ ts ].fields.length
							: 0;
						sectionCard.classList.add( 'is-drop-target' );
						if ( grid ) {
							grid.appendChild( el( 'div', { className: 'wek-builder__drop-line' } ) );
						}
					}
				}

				const onMove = function ( e ) {
					const dx = e.clientX - startX;
					const dy = e.clientY - startY;
					if ( ! started && Math.abs( dx ) + Math.abs( dy ) < 6 ) {
						return;
					}
					started = true;
					updateDropTarget( e.clientX, e.clientY );
				};

				const onUp = function () {
					document.removeEventListener( 'pointermove', onMove );
					document.removeEventListener( 'pointerup', onUp );
					document.removeEventListener( 'pointercancel', onUp );
					handle.setAttribute( 'aria-grabbed', 'false' );
					card.classList.remove( 'is-dragging' );
					clearIndicators();
					if ( ! started ) {
						selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex } );
						return;
					}
					// Same slot: dropping after self is a no-op.
					if ( dropS === sIndex && ( dropF === fIndex || dropF === fIndex + 1 ) ) {
						selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex } );
						return;
					}
					moveField( sIndex, fIndex, dropS, dropF );
				};

				document.addEventListener( 'pointermove', onMove );
				document.addEventListener( 'pointerup', onUp );
				document.addEventListener( 'pointercancel', onUp );
			} );
		}

		function bindSectionDrag( handle, sectionEl, sIndex ) {
			handle.addEventListener( 'pointerdown', function ( event ) {
				if ( event.button !== 0 ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				sectionEl.classList.add( 'is-dragging' );
				handle.setAttribute( 'aria-grabbed', 'true' );

				const startY = event.clientY;
				let started = false;
				let dropS = sIndex;

				function clearIndicators() {
					root.querySelectorAll( '.is-drop-target' ).forEach( function ( n ) {
						n.classList.remove( 'is-drop-target' );
					} );
				}

				const onMove = function ( e ) {
					if ( ! started && Math.abs( e.clientY - startY ) < 6 ) {
						return;
					}
					started = true;
					clearIndicators();
					const elUnder = document.elementFromPoint( e.clientX, e.clientY );
					const other = elUnder && elUnder.closest( '.wek-builder__section' );
					if ( other && other !== sectionEl ) {
						const ts = parseInt( other.getAttribute( 'data-s' ), 10 );
						const rect = other.getBoundingClientRect();
						dropS = e.clientY < rect.top + rect.height / 2 ? ts : ts + 1;
						other.classList.add( 'is-drop-target' );
					}
				};

				const onUp = function () {
					document.removeEventListener( 'pointermove', onMove );
					document.removeEventListener( 'pointerup', onUp );
					document.removeEventListener( 'pointercancel', onUp );
					handle.setAttribute( 'aria-grabbed', 'false' );
					sectionEl.classList.remove( 'is-dragging' );
					clearIndicators();
					if ( ! started || dropS === sIndex || dropS === sIndex + 1 ) {
						selectItem( { type: 'section', sIndex: sIndex } );
						return;
					}
					moveSection( sIndex, dropS );
				};

				document.addEventListener( 'pointermove', onMove );
				document.addEventListener( 'pointerup', onUp );
				document.addEventListener( 'pointercancel', onUp );
			} );
		}

		function renderFieldCard( section, field, sIndex, fIndex ) {
			const selected =
				selection &&
				selection.type === 'field' &&
				selection.sIndex === sIndex &&
				selection.fIndex === fIndex;
			const card = el( 'div', {
				className: widthClass( field.width || 'full' ) + ( selected ? ' is-selected' : '' ),
				'data-s': String( sIndex ),
				'data-f': String( fIndex ),
				tabindex: '0',
				role: 'button',
				'aria-pressed': selected ? 'true' : 'false',
			} );

			const handle = el( 'button', {
				type: 'button',
				className: 'wek-builder__handle',
				title: i18n.dragHandle || 'Drag to reorder',
				'aria-label': i18n.dragHandle || 'Drag to reorder',
				'aria-grabbed': 'false',
				text: '⋮⋮',
			} );

			const main = el( 'div', { className: 'wek-builder__field-main' }, [
				handle,
				el( 'div', { className: 'wek-builder__field-body' }, [
					el( 'span', {
						className: 'wek-builder__field-label',
						text: field.label || field.id || i18n.field || 'Field',
					} ),
					el( 'span', {
						className: 'wek-builder__field-preview',
						text:
							field.type === 'repeater'
								? repeaterPreviewText( field )
								: field.placeholder || field.help || field.type || '',
					} ),
				] ),
				el( 'span', { className: 'wek-builder__badge', text: field.type || 'text' } ),
			] );
			card.appendChild( main );

			const resize = el( 'button', {
				type: 'button',
				className: 'wek-builder__resize',
				title: i18n.resizeHandle || 'Drag to resize',
				'aria-label': i18n.resizeHandle || 'Drag to resize',
			} );
			resize.appendChild( el( 'span', { className: 'wek-builder__resize-grip' } ) );
			card.appendChild( resize );

			card.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.wek-builder__handle' ) || e.target.closest( '.wek-builder__resize' ) ) {
					return;
				}
				selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex } );
			} );
			card.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex } );
				}
			} );

			bindFieldDrag( handle, card, sIndex, fIndex );
			bindResize( resize, field, card );
			return card;
		}

		function renderSectionCard( section, sIndex ) {
			const selected = selection && selection.type === 'section' && selection.sIndex === sIndex;
			const box = el( 'div', {
				className: 'wek-builder__section' + ( selected ? ' is-selected' : '' ),
				'data-s': String( sIndex ),
			} );

			const handle = el( 'button', {
				type: 'button',
				className: 'wek-builder__handle',
				title: i18n.dragHandle || 'Drag to reorder',
				'aria-label': i18n.dragHandle || 'Drag to reorder',
				'aria-grabbed': 'false',
				text: '⋮⋮',
			} );

			const head = el( 'div', { className: 'wek-builder__section-head' }, [
				handle,
				el( 'strong', {
					className: 'wek-builder__section-title',
					text: section.title || section.id || ( i18n.section || 'Section' ) + ' ' + ( sIndex + 1 ),
				} ),
			] );
			head.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.wek-builder__handle' ) ) {
					return;
				}
				selectItem( { type: 'section', sIndex: sIndex } );
			} );
			box.appendChild( head );

			const grid = el( 'div', { className: 'wek-builder__fields' } );
			( section.fields || [] ).forEach( function ( field, fIndex ) {
				grid.appendChild( renderFieldCard( section, field, sIndex, fIndex ) );
			} );
			box.appendChild( grid );

			box.appendChild(
				el( 'div', { className: 'wek-builder__toolbar' }, [
					el( 'button', {
						type: 'button',
						className: 'button',
						text: i18n.addField || 'Add field',
						onClick: function () {
							section.fields = section.fields || [];
							section.fields.push( {
								id: 'field_' + Date.now(),
								type: 'text',
								label: i18n.field || 'Field',
								help: '',
								required: false,
								placeholder: '',
								type_options: {},
								width: 'full',
								options: [],
								show_when: null,
							} );
							selection = {
								type: 'field',
								sIndex: sIndex,
								fIndex: section.fields.length - 1,
							};
							activeTab = 'general';
							syncHidden();
							render();
						},
					} ),
				] )
			);

			bindSectionDrag( handle, box, sIndex );
			return box;
		}

		function addFieldOfType( typeId ) {
			const typeMeta = fieldTypes.find( function ( item ) {
				return item.type === typeId;
			} );
			let sIndex = 0;
			if ( selection && typeof selection.sIndex === 'number' ) {
				sIndex = selection.sIndex;
			} else if ( schema.sections.length ) {
				sIndex = schema.sections.length - 1;
			} else {
				schema.sections.push( {
					id: 'section_' + Date.now(),
					title: i18n.section || 'Section',
					intro: '',
					show_when: null,
					fields: [],
				} );
				sIndex = 0;
			}
			const section = schema.sections[ sIndex ];
			section.fields = section.fields || [];
			const typeOptions = {};
			if ( typeId === 'repeater' ) {
				typeOptions.min_items = 1;
				typeOptions.max_items = 5;
				typeOptions.add_button_label = '';
				typeOptions.fields = [
					{
						id: 'name',
						type: 'text',
						label: 'Name',
						help: '',
						required: false,
						placeholder: '',
						type_options: {},
						width: 'full',
						options: [],
						show_when: null,
					},
					{
						id: 'website',
						type: 'url',
						label: 'Website',
						help: '',
						required: false,
						placeholder: 'https://',
						type_options: {},
						width: 'full',
						options: [],
						show_when: null,
					},
				];
			}
			section.fields.push( {
				id: 'field_' + Date.now(),
				type: typeId || 'text',
				label: ( typeMeta && typeMeta.label ) || i18n.field || 'Field',
				help: '',
				required: false,
				placeholder: '',
				type_options: typeOptions,
				width: 'full',
				options: [],
				show_when: null,
			} );
			selection = {
				type: 'field',
				sIndex: sIndex,
				fIndex: section.fields.length - 1,
			};
			activeTab = 'general';
			syncHidden();
			render();
		}

		function applyThemeTokens() {
			const host = document.querySelector( '.wek-admin' ) || root;
			if ( ! host || ! theme ) {
				return;
			}
			if ( theme.accent ) {
				host.style.setProperty( '--wek-accent', theme.accent );
			}
			if ( theme.accentSoft ) {
				host.style.setProperty( '--wek-accent-soft', theme.accentSoft );
			}
		}

		function renderLibrary() {
			const panel = el( 'aside', { className: 'wek-builder-library' } );
			panel.appendChild(
				el( 'div', { className: 'wek-builder-library__head' }, [
					el( 'h3', {
						className: 'wek-builder-library__title',
						text: i18n.fieldsLibrary || 'Fields',
					} ),
				] )
			);

			if ( theme.palette && theme.palette.length ) {
				const palette = el( 'div', {
					className: 'wek-builder-palette',
					title: i18n.themeColors || 'Theme colors',
				} );
				theme.palette.slice( 0, 12 ).forEach( function ( swatch ) {
					const color = swatch && swatch.color ? String( swatch.color ) : '';
					if ( ! color ) {
						return;
					}
					const btn = el( 'button', {
						type: 'button',
						className:
							'wek-builder-palette__swatch' +
							( theme.accent && color.toLowerCase() === String( theme.accent ).toLowerCase()
								? ' is-accent'
								: '' ),
						title: ( swatch.name || swatch.slug || color ) + '',
						style: 'background:' + color,
						'aria-label': swatch.name || swatch.slug || color,
					} );
					btn.addEventListener( 'click', function () {
						const host = document.querySelector( '.wek-admin' ) || root;
						if ( ! host ) {
							return;
						}
						const soft = ( function ( hex ) {
							const raw = String( hex || '' ).replace( '#', '' );
							let full = raw;
							if ( raw.length === 3 ) {
								full = raw[ 0 ] + raw[ 0 ] + raw[ 1 ] + raw[ 1 ] + raw[ 2 ] + raw[ 2 ];
							}
							if ( ! /^[0-9a-fA-F]{6}$/.test( full ) ) {
								return '#e4f2ee';
							}
							const mix = function ( channel ) {
								return Math.round( channel * 0.12 + 255 * 0.88 );
							};
							const toHex = function ( n ) {
								const h = n.toString( 16 );
								return h.length === 1 ? '0' + h : h;
							};
							return (
								'#' +
								toHex( mix( parseInt( full.slice( 0, 2 ), 16 ) ) ) +
								toHex( mix( parseInt( full.slice( 2, 4 ), 16 ) ) ) +
								toHex( mix( parseInt( full.slice( 4, 6 ), 16 ) ) )
							);
						} )( color );
						host.style.setProperty( '--wek-accent', color );
						host.style.setProperty( '--wek-accent-soft', soft );
						theme.accent = color;
						theme.accentSoft = soft;
						Array.prototype.forEach.call( palette.querySelectorAll( '.wek-builder-palette__swatch' ), function ( node ) {
							node.classList.toggle( 'is-accent', node === btn );
						} );
					} );
					palette.appendChild( btn );
				} );
				panel.appendChild( palette );
			}

			const grid = el( 'div', { className: 'wek-builder-library__grid' } );
			fieldTypes.forEach( function ( item ) {
				const typeId = item.type;
				grid.appendChild(
					el( 'button', {
						type: 'button',
						className: 'wek-builder-library__item',
						title: item.label || typeId,
						onClick: function () {
							addFieldOfType( typeId );
						},
					}, [
						el( 'span', {
							className: 'wek-builder-library__icon',
							text: FIELD_ICONS[ typeId ] || typeId.slice( 0, 2 ).toUpperCase(),
						} ),
						el( 'span', {
							className: 'wek-builder-library__label',
							text: item.label || typeId,
						} ),
					] )
				);
			} );
			panel.appendChild( grid );
			return panel;
		}

		function render() {
			applyThemeTokens();
			root.innerHTML = '';
			const layout = el( 'div', { className: 'wek-builder-layout' } );
			const library = renderLibrary();
			const canvas = el( 'div', { className: 'wek-builder-canvas' } );
			const aside = el( 'aside', {
				className: 'wek-builder-sidebar',
				'aria-labelledby': 'wek-builder-sidebar-title',
			} );

			live.node = el( 'div', {
				className: 'wek-builder__live',
				role: 'status',
				'aria-live': 'polite',
			} );
			canvas.appendChild( live.node );

			if ( ! schema.sections.length ) {
				canvas.appendChild(
					el( 'p', {
						className: 'description',
						text: i18n.empty || 'No sections yet. Add a section or pick a field from the library.',
					} )
				);
			}

			schema.sections.forEach( function ( section, sIndex ) {
				canvas.appendChild( renderSectionCard( section, sIndex ) );
			} );

			canvas.appendChild(
				el( 'div', { className: 'wek-builder__toolbar' }, [
					el( 'button', {
						type: 'button',
						className: 'button button-secondary',
						text: i18n.addSection || 'Add section',
						onClick: function () {
							schema.sections.push( {
								id: 'section_' + Date.now(),
								title: i18n.section || 'Section',
								intro: '',
								show_when: null,
								fields: [],
							} );
							selection = { type: 'section', sIndex: schema.sections.length - 1 };
							activeTab = 'general';
							syncHidden();
							render();
						},
					} ),
				] )
			);

			renderSidebar( aside );
			layout.appendChild( library );
			layout.appendChild( canvas );
			layout.appendChild( aside );
			root.appendChild( layout );
			syncHidden();
			root.setAttribute( 'data-wek-builder-ready', '1' );
		}

		try {
			const form = document.getElementById( 'wek-form-editor' );
			if ( form ) {
				form.addEventListener( 'submit', syncHidden );
			}
			if ( titleInput ) {
				titleInput.addEventListener( 'input', syncHidden );
			}
			if ( introInput ) {
				introInput.addEventListener( 'input', syncHidden );
			}
			render();
		} catch ( err ) {
			root.textContent =
				i18n.loadError ||
				'Form builder failed to load. Hard-refresh the page or check the browser console.';
			if ( window.console && console.error ) {
				console.error( 'we-formkit form builder', err );
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
