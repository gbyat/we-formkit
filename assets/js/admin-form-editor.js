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
		text: 'dashicons-editor-textcolor',
		email: 'dashicons-email',
		tel: 'dashicons-phone',
		url: 'dashicons-admin-links',
		textarea: 'dashicons-editor-paragraph',
		number: 'dashicons-calculator',
		select: 'dashicons-arrow-down-alt2',
		radio: 'dashicons-marker',
		radio_image: 'dashicons-format-image',
		checkbox: 'dashicons-yes',
		checkboxes: 'dashicons-yes-alt',
		date: 'dashicons-calendar-alt',
		time: 'dashicons-clock',
		datetime: 'dashicons-calendar',
		consent: 'dashicons-privacy',
		html: 'dashicons-editor-code',
		hidden: 'dashicons-hidden',
		upload: 'dashicons-upload',
		repeater: 'dashicons-image-rotate',
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
			{ value: 'equals', label: i18n.opEquals || 'equals' },
			{ value: 'not_equals', label: i18n.opNotEquals || 'not equals' },
			{ value: 'contains', label: i18n.opContains || 'contains' },
			{ value: 'is_checked', label: i18n.opIsChecked || 'is checked' },
			{ value: 'is_not_empty', label: i18n.opIsNotEmpty || 'is not empty' },
		];

		const opsNoValue = { is_checked: true, is_not_empty: true };

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

		function normalizeWidth( width ) {
			const allowed = [ 'full', 'two_thirds', 'half', 'third' ];
			return allowed.indexOf( width ) !== -1 ? width : 'full';
		}

		function widthLabel( width ) {
			const map = {
				full: i18n.widthFull || 'Full',
				two_thirds: i18n.widthTwoThirds || 'Two thirds',
				half: i18n.widthHalf || 'Half',
				third: i18n.widthThird || 'One third',
			};
			return map[ normalizeWidth( width ) ] || map.full;
		}

		function widthClass( width, selected ) {
			return (
				'wek-builder__field wek-builder__field--width-' +
				normalizeWidth( width ) +
				( selected ? ' is-selected' : '' )
			);
		}

		function renderColumnPicker( field ) {
			field.width = normalizeWidth( field.width );
			const wrap = el( 'div', { className: 'wek-builder__columns', role: 'group' } );
			const spans = {
				full: 6,
				two_thirds: 4,
				half: 3,
				third: 2,
			};
			[
				{ value: 'full', label: i18n.widthFull || 'Full' },
				{ value: 'two_thirds', label: i18n.widthTwoThirds || 'Two thirds' },
				{ value: 'half', label: i18n.widthHalf || 'Half' },
				{ value: 'third', label: i18n.widthThird || 'One third' },
			].forEach( function ( item ) {
				const preview = el( 'div', { className: 'wek-builder__column-preview' } );
				const onCount = spans[ item.value ];
				for ( let i = 0; i < 6; i++ ) {
					preview.appendChild(
						el( 'span', {
							className: i < onCount ? 'is-on' : '',
						} )
					);
				}
				wrap.appendChild(
					el( 'button', {
						type: 'button',
						className:
							'wek-builder__column-btn' +
							( field.width === item.value ? ' is-active' : '' ),
						'aria-pressed': field.width === item.value ? 'true' : 'false',
						title: item.label,
						onClick: function () {
							field.width = item.value;
							syncHidden();
							render();
						},
					}, [
						preview,
						el( 'strong', { text: item.label } ),
					] )
				);
			} );
			return wrap;
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
			if ( selection.type === 'nested' ) {
				const opts = field.type_options || {};
				const nested = Array.isArray( opts.fields ) ? opts.fields[ selection.nIndex ] : null;
				if ( ! nested ) {
					return null;
				}
				return {
					kind: 'nested',
					section: section,
					parent: field,
					field: nested,
					sIndex: selection.sIndex,
					fIndex: selection.fIndex,
					nIndex: selection.nIndex,
				};
			}
			return {
				kind: 'field',
				section: section,
				field: field,
				sIndex: selection.sIndex,
				fIndex: selection.fIndex,
			};
		}

		function getActiveRepeaterTarget() {
			const selected = getSelected();
			if ( ! selected ) {
				return null;
			}
			if ( selected.kind === 'nested' ) {
				return {
					section: selected.section,
					repeater: selected.parent,
					sIndex: selected.sIndex,
					fIndex: selected.fIndex,
				};
			}
			if ( selected.kind === 'field' && selected.field.type === 'repeater' ) {
				return {
					section: selected.section,
					repeater: selected.field,
					sIndex: selected.sIndex,
					fIndex: selected.fIndex,
				};
			}
			return null;
		}

		function isAllowedInRepeater( typeId ) {
			return getRepeaterItemTypes().some( function ( item ) {
				return item.value === typeId;
			} );
		}

		function createBlankField( typeId ) {
			const typeMeta = fieldTypes.find( function ( item ) {
				return item.type === typeId;
			} );
			const typeOptions = {};
			if ( typeId === 'repeater' ) {
				typeOptions.min_items = 1;
				typeOptions.max_items = 5;
				typeOptions.add_button_label = '';
				typeOptions.fields = [];
			}
			return {
				id: ( typeId || 'field' ) + '_' + Date.now().toString( 36 ),
				type: typeId || 'text',
				label: ( typeMeta && typeMeta.label ) || i18n.field || 'Field',
				help: '',
				required: false,
				placeholder: '',
				type_options: typeOptions,
				width: 'full',
				options: [],
				show_when: null,
			};
		}

		function selectItem( next, tab ) {
			selection = next;
			if ( tab ) {
				activeTab = tab;
			} else if ( next && next.type === 'section' && activeTab === 'appearance' ) {
				activeTab = 'general';
			} else if ( next && next.type === 'nested' && ( activeTab === 'appearance' || activeTab === 'conditional' ) ) {
				activeTab = 'general';
			}
			render();
		}

		function fieldRow( labelText, control ) {
			return el( 'p', { className: 'wek-builder__row' }, [
				el( 'label', null, [ labelText, control ] ),
			] );
		}

		function toggleRow( labelText, input ) {
			input.className = ( input.className ? input.className + ' ' : '' ) + 'wek-builder__toggle-input';
			return el( 'p', { className: 'wek-builder__row' }, [
				el( 'label', { className: 'wek-builder__toggle' }, [
					input,
					el( 'span', { className: 'wek-builder__toggle-ui', 'aria-hidden': 'true' } ),
					el( 'span', { className: 'wek-builder__toggle-label', text: labelText } ),
				] ),
			] );
		}

		function listConditionFields( excludeId ) {
			const list = [];
			( schema.sections || [] ).forEach( function ( section ) {
				( section.fields || [] ).forEach( function ( field ) {
					if ( ! field || ! field.id || field.id === excludeId ) {
						return;
					}
					if ( field.type === 'html' || field.type === 'hidden' ) {
						return;
					}
					list.push( {
						id: String( field.id ),
						label: String( field.label || field.id ),
						options: Array.isArray( field.options ) ? field.options : [],
					} );
				} );
			} );
			return list;
		}

		function normalizeShowWhen( raw ) {
			if ( ! raw ) {
				return { relation: 'AND', rules: [] };
			}
			if ( raw.field && ! raw.rules ) {
				return {
					relation: 'AND',
					rules: [
						{
							field: String( raw.field || '' ),
							op: String( raw.op || 'equals' ),
							value: raw.value != null ? String( raw.value ) : '',
						},
					],
				};
			}
			const rules = Array.isArray( raw.rules )
				? raw.rules.map( function ( r ) {
						return {
							field: String( ( r && r.field ) || '' ),
							op: String( ( r && r.op ) || 'equals' ),
							value: r && r.value != null ? String( r.value ) : '',
						};
				  } )
				: [];
			return {
				relation: String( raw.relation || 'AND' ).toUpperCase() === 'OR' ? 'OR' : 'AND',
				rules: rules,
			};
		}

		function commitShowWhen( target, container ) {
			const rules = ( container.rules || [] ).filter( function ( r ) {
				return r && r.field && r.op;
			} );
			if ( ! rules.length ) {
				target.show_when = null;
			} else {
				target.show_when = {
					relation: container.relation === 'OR' ? 'OR' : 'AND',
					rules: rules.map( function ( r ) {
						return {
							field: r.field,
							op: r.op,
							value: opsNoValue[ r.op ] ? '' : r.value || '',
						};
					} ),
				};
			}
			syncHidden();
		}

		function fillFieldSelect( select, fields, selectedId ) {
			select.innerHTML = '';
			select.appendChild(
				el( 'option', {
					value: '',
					text: i18n.selectField || '— Select field —',
				} )
			);
			fields.forEach( function ( f ) {
				const opt = el( 'option', {
					value: f.id,
					text: f.label,
					title: f.id,
				} );
				if ( selectedId === f.id ) {
					opt.selected = true;
				}
				select.appendChild( opt );
			} );
			if ( selectedId && ! fields.some( function ( f ) {
				return f.id === selectedId;
			} ) ) {
				select.appendChild(
					el( 'option', { value: selectedId, text: selectedId } )
				);
				select.value = selectedId;
			}
		}

		function renderConditional( target, excludeFieldId ) {
			const container = normalizeShowWhen( target.show_when );
			const fields = listConditionFields( excludeFieldId );
			const panel = el( 'div', { className: 'wek-builder__conditions' } );

			const relationSelect = el( 'select', { className: 'wek-builder__conditions-relation' } );
			[
				{ value: 'AND', label: i18n.matchAll || 'Match all of the following (AND)' },
				{ value: 'OR', label: i18n.matchAny || 'Match any of the following (OR)' },
			].forEach( function ( item ) {
				const opt = el( 'option', { value: item.value, text: item.label } );
				if ( container.relation === item.value ) {
					opt.selected = true;
				}
				relationSelect.appendChild( opt );
			} );
			relationSelect.addEventListener( 'change', function () {
				container.relation = relationSelect.value === 'OR' ? 'OR' : 'AND';
				commitShowWhen( target, container );
			} );
			panel.appendChild(
				el( 'div', { className: 'wek-builder__conditions-header' }, [ relationSelect ] )
			);

			if ( ! fields.length ) {
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.conditionsNoFields ||
							'Add other fields first — conditions depend on another field’s value.',
					} )
				);
				return panel;
			}

			const list = el( 'div', { className: 'wek-builder__conditions-list' } );

			function paintValueControl( valueWrap, rule, fieldSelect, opSelect ) {
				valueWrap.innerHTML = '';
				const op = opSelect.value || 'equals';
				if ( opsNoValue[ op ] ) {
					valueWrap.hidden = true;
					rule.value = '';
					return;
				}
				valueWrap.hidden = false;
				const meta = fields.find( function ( f ) {
					return f.id === fieldSelect.value;
				} );
				const options = meta && Array.isArray( meta.options ) ? meta.options : [];
				if ( options.length && ( op === 'equals' || op === 'not_equals' ) ) {
					const sel = el( 'select' );
					sel.appendChild(
						el( 'option', {
							value: '',
							text: i18n.selectValue || '— Select value —',
						} )
					);
					options.forEach( function ( o ) {
						const v = o.value != null ? String( o.value ) : '';
						const lab = o.label != null ? String( o.label ) : v;
						const opt = el( 'option', { value: v, text: lab } );
						if ( String( rule.value || '' ) === v ) {
							opt.selected = true;
						}
						sel.appendChild( opt );
					} );
					sel.addEventListener( 'change', function () {
						rule.value = sel.value;
						commitShowWhen( target, container );
					} );
					valueWrap.appendChild( sel );
					return;
				}
				const input = el( 'input', {
					type: 'text',
					className: 'regular-text',
					placeholder: i18n.showValue || 'Value',
					value: rule.value || '',
				} );
				input.addEventListener( 'input', function () {
					rule.value = input.value;
					commitShowWhen( target, container );
				} );
				valueWrap.appendChild( input );
			}

			function redrawRules() {
				list.innerHTML = '';
				if ( ! container.rules.length ) {
					list.appendChild(
						el( 'p', {
							className: 'wek-builder__conditions-empty description',
							text:
								i18n.conditionsEmpty ||
								'No conditions — this item is always visible. Add a rule to show it only when…',
						} )
					);
					return;
				}

				container.rules.forEach( function ( rule, index ) {
					const card = el( 'div', { className: 'wek-builder__condition-rule' } );
					card.appendChild(
						el( 'span', {
							className: 'wek-builder__condition-badge',
							text: ( i18n.ruleLabel || 'Rule' ) + ' #' + ( index + 1 ),
						} )
					);

					const fieldSelect = el( 'select', {
						className: 'wek-builder__condition-field',
						'aria-label': i18n.showField || 'Depends on field',
					} );
					fillFieldSelect( fieldSelect, fields, rule.field );

					const opSelect = el( 'select', {
						className: 'wek-builder__condition-op',
						'aria-label': i18n.showOp || 'Operator',
					} );
					ops.forEach( function ( op ) {
						const opt = el( 'option', { value: op.value, text: op.label } );
						if ( ( rule.op || 'equals' ) === op.value ) {
							opt.selected = true;
						}
						opSelect.appendChild( opt );
					} );

					const valueWrap = el( 'div', { className: 'wek-builder__condition-value' } );

					fieldSelect.addEventListener( 'change', function () {
						rule.field = fieldSelect.value;
						paintValueControl( valueWrap, rule, fieldSelect, opSelect );
						commitShowWhen( target, container );
					} );
					opSelect.addEventListener( 'change', function () {
						rule.op = opSelect.value || 'equals';
						paintValueControl( valueWrap, rule, fieldSelect, opSelect );
						commitShowWhen( target, container );
					} );

					card.appendChild( fieldSelect );
					card.appendChild( opSelect );
					card.appendChild( valueWrap );
					card.appendChild(
						el( 'button', {
							type: 'button',
							className: 'button-link-delete wek-builder__condition-remove',
							text: i18n.remove || 'Remove',
							onClick: function () {
								container.rules.splice( index, 1 );
								commitShowWhen( target, container );
								redrawRules();
							},
						} )
					);
					paintValueControl( valueWrap, rule, fieldSelect, opSelect );
					list.appendChild( card );
				} );
			}

			redrawRules();
			panel.appendChild( list );

			panel.appendChild(
				el( 'button', {
					type: 'button',
					className: 'button button-secondary',
					text: i18n.addCondition || 'Add condition',
					onClick: function () {
						container.rules.push( {
							field: fields[ 0 ] ? fields[ 0 ].id : '',
							op: 'equals',
							value: '',
						} );
						commitShowWhen( target, container );
						redrawRules();
					},
				} )
			);

			return panel;
		}

		function textInput( value, onChange ) {
			const input = el( 'input', { type: 'text', className: 'regular-text', value: value || '' } );
			input.addEventListener( 'input', function () {
				onChange( input.value );
			} );
			return input;
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
						className: 'button-link-delete wek-builder__option-remove',
						title: i18n.remove || 'Remove',
						'aria-label': i18n.remove || 'Remove',
						onClick: function () {
							field.options.splice( oIndex, 1 );
							syncHidden();
							render();
						},
					}, [
						el( 'span', {
							className: 'dashicons dashicons-trash',
							'aria-hidden': 'true',
						} ),
					] )
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
				toggleRow( i18n.enabled || 'Enabled', enabled ),
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

		function renderRepeaterSettings( field ) {
			const opts = ensureRepeaterDefaults( field );
			const wrap = el( 'div', { className: 'wek-builder__repeater-options' } );

			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.repeaterHint ||
						'Drag or click fields from the library into this repeater. The whole group is cloned for each row on the front end.',
				} )
			);

			wrap.appendChild(
				fieldRow(
					i18n.minRows || 'Minimum rows',
					numberInput(
						opts.min_items,
						function ( v ) {
							const n = parseInt( v, 10 );
							opts.min_items = isNaN( n ) ? 0 : Math.max( 0, n );
							syncHidden();
						},
						{ min: '0' }
					)
				)
			);
			wrap.appendChild(
				fieldRow(
					i18n.maxRows || 'Maximum rows',
					numberInput(
						opts.max_items,
						function ( v ) {
							const n = parseInt( v, 10 );
							opts.max_items = isNaN( n ) ? 5 : Math.max( 1, n );
							syncHidden();
						},
						{ min: '1' }
					)
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

			return wrap;
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

			const isNested = selected.kind === 'nested';
			const isField = selected.kind === 'field' || isNested;
			const title = isField
				? ( i18n.fieldSettings || 'Field settings' )
				: ( i18n.sectionSettings || 'Section settings' );

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
						if ( isNested ) {
							ensureRepeaterDefaults( selected.parent );
							selected.parent.type_options.fields.splice( selected.nIndex, 1 );
						} else if ( selected.kind === 'field' ) {
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

			const tabs = isNested
				? [ { id: 'general', label: i18n.tabGeneral || 'General' } ]
				: selected.kind === 'field'
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
							field.id = isNested
								? String( v || '' )
										.toLowerCase()
										.replace( /[^a-z0-9_-]/g, '_' )
								: v;
							syncHidden();
							const titleEl = aside.querySelector( '.wek-builder-sidebar__title' );
							if ( titleEl ) {
								titleEl.textContent = ( i18n.field || 'Field' ) + ': ' + field.id;
							}
							render();
						} )
					)
				);
				panel.appendChild(
					fieldRow(
						i18n.label || 'Label',
						textInput( field.label || '', function ( v ) {
							field.label = v;
							syncHidden();
							render();
						} )
					)
				);

				const typeChoices = isNested
					? getRepeaterItemTypes().map( function ( item ) {
							return { type: item.value, label: item.label };
					  } )
					: fieldTypes;
				const typeSelect = el( 'select' );
				typeChoices.forEach( function ( item ) {
					const opt = el( 'option', { value: item.type, text: item.label || item.type } );
					if ( field.type === item.type ) {
						opt.selected = true;
					}
					typeSelect.appendChild( opt );
				} );
				if ( field.type && ! typeChoices.some( function ( item ) {
					return item.type === field.type;
				} ) ) {
					const orphan = el( 'option', { value: field.type, text: field.type } );
					orphan.selected = true;
					typeSelect.appendChild( orphan );
				}
				typeSelect.addEventListener( 'change', function () {
					field.type = typeSelect.value;
					ensureTypeOptions( field );
					if ( field.type === 'repeater' ) {
						ensureRepeaterDefaults( field );
					}
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
				panel.appendChild( toggleRow( i18n.required || 'Required', req ) );

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

				if ( field.type === 'repeater' && ! isNested ) {
					panel.appendChild( renderRepeaterSettings( field ) );
				}
			} else if ( selected.kind === 'field' && activeTab === 'appearance' ) {
				const field = selected.field;
				field.width = normalizeWidth( field.width );
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text: i18n.widthHint || 'Choose how many columns this field spans in the row.',
					} )
				);
				panel.appendChild( el( 'label', { text: i18n.width || 'Columns' } ) );
				panel.appendChild( renderColumnPicker( field ) );
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.resizeHint ||
							'You can also drag the right edge of a field on the canvas to change its width.',
					} )
				);
			} else if ( selected.kind === 'field' && activeTab === 'conditional' ) {
				panel.appendChild( renderConditional( selected.field, selected.field.id ) );
			} else if ( selected.kind === 'section' && activeTab === 'general' ) {
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
			} else if ( selected.kind === 'section' && activeTab === 'conditional' ) {
				panel.appendChild( renderConditional( selected.section, null ) );
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
			if ( ratio < 0.22 ) {
				next = 'third';
			} else if ( ratio < 0.42 ) {
				next = 'half';
			} else if ( ratio < 0.72 ) {
				next = 'two_thirds';
			}
			if ( field.width !== next ) {
				field.width = next;
				const selected =
					selection &&
					selection.type === 'field' &&
					String( selection.sIndex ) === card.getAttribute( 'data-s' ) &&
					String( selection.fIndex ) === card.getAttribute( 'data-f' ) &&
					! card.getAttribute( 'data-n' );
				card.className =
					widthClass( next, selected ) +
					( card.className.indexOf( 'wek-builder__field--repeater' ) !== -1
						? ' wek-builder__field--repeater'
						: '' );
				announce( ( i18n.width || 'Columns' ) + ': ' + widthLabel( next ) );
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

		function getListAt( loc ) {
			const section = schema.sections[ loc.s ];
			if ( ! section ) {
				return null;
			}
			if ( loc.scope === 'repeater' ) {
				const parent = section.fields && section.fields[ loc.f ];
				if ( ! parent || parent.type !== 'repeater' ) {
					return null;
				}
				ensureRepeaterDefaults( parent );
				return parent.type_options.fields;
			}
			section.fields = section.fields || [];
			return section.fields;
		}

		function relocateField( from, to ) {
			if ( ! from || ! to ) {
				return;
			}
			const fromList = getListAt( from );
			const toList = getListAt( to );
			if ( ! fromList || ! toList || from.index < 0 || from.index >= fromList.length ) {
				return;
			}

			// Cannot nest a repeater inside a repeater.
			const moving = fromList[ from.index ];
			if ( to.scope === 'repeater' && moving && moving.type === 'repeater' ) {
				announce( i18n.repeaterNoNest || 'A repeater cannot be placed inside another repeater.' );
				return;
			}
			if ( to.scope === 'repeater' && moving && ! isAllowedInRepeater( moving.type ) ) {
				announce( i18n.repeaterTypeBlocked || 'This field type cannot be used inside a repeater.' );
				return;
			}

			// Same list no-op.
			if (
				from.scope === to.scope &&
				from.s === to.s &&
				( from.scope !== 'repeater' || from.f === to.f ) &&
				( to.index === from.index || to.index === from.index + 1 )
			) {
				if ( from.scope === 'repeater' ) {
					selection = {
						type: 'nested',
						sIndex: from.s,
						fIndex: from.f,
						nIndex: from.index,
					};
				} else {
					selection = { type: 'field', sIndex: from.s, fIndex: from.index };
				}
				render();
				return;
			}

			const item = fromList.splice( from.index, 1 )[ 0 ];
			let insertAt = typeof to.index === 'number' ? to.index : toList.length;

			const sameList =
				from.scope === to.scope &&
				from.s === to.s &&
				( from.scope !== 'repeater' || from.f === to.f );
			if ( sameList && from.index < insertAt ) {
				insertAt -= 1;
			}
			insertAt = Math.max( 0, Math.min( insertAt, toList.length ) );
			toList.splice( insertAt, 0, item );

			if ( to.scope === 'repeater' ) {
				selection = {
					type: 'nested',
					sIndex: to.s,
					fIndex: to.f,
					nIndex: insertAt,
				};
			} else {
				selection = { type: 'field', sIndex: to.s, fIndex: insertAt };
			}
			announce( i18n.moved || 'Item moved.' );
			syncHidden();
			render();
		}

		function moveField( fromS, fromF, toS, toF ) {
			relocateField(
				{ scope: 'section', s: fromS, f: null, index: fromF },
				{ scope: 'section', s: toS, f: null, index: toF }
			);
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

		function resolveDropLocation( clientX, clientY, dragCard ) {
			const elUnder = document.elementFromPoint( clientX, clientY );
			if ( ! elUnder ) {
				return null;
			}

			const nestedCard = elUnder.closest( '.wek-builder__field[data-n]' );
			if ( nestedCard && nestedCard !== dragCard ) {
				const s = parseInt( nestedCard.getAttribute( 'data-s' ), 10 );
				const f = parseInt( nestedCard.getAttribute( 'data-f' ), 10 );
				const n = parseInt( nestedCard.getAttribute( 'data-n' ), 10 );
				const rect = nestedCard.getBoundingClientRect();
				const before = clientY < rect.top + rect.height / 2;
				const line = el( 'div', { className: 'wek-builder__drop-line' } );
				if ( before ) {
					nestedCard.parentNode.insertBefore( line, nestedCard );
				} else {
					nestedCard.parentNode.insertBefore( line, nestedCard.nextSibling );
				}
				return { scope: 'repeater', s: s, f: f, index: before ? n : n + 1 };
			}

			const repeaterCard = elUnder.closest( '.wek-builder__field[data-repeater="1"]' );
			if ( repeaterCard && repeaterCard !== dragCard ) {
				const s = parseInt( repeaterCard.getAttribute( 'data-s' ), 10 );
				const f = parseInt( repeaterCard.getAttribute( 'data-f' ), 10 );
				const grid = repeaterCard.querySelector( '.wek-builder__repeater-canvas' );
				repeaterCard.classList.add( 'is-drop-target' );
				if ( grid ) {
					grid.appendChild( el( 'div', { className: 'wek-builder__drop-line' } ) );
				}
				const parent = schema.sections[ s ] && schema.sections[ s ].fields
					? schema.sections[ s ].fields[ f ]
					: null;
				const len =
					parent && parent.type_options && Array.isArray( parent.type_options.fields )
						? parent.type_options.fields.length
						: 0;
				return { scope: 'repeater', s: s, f: f, index: len };
			}

			const fieldCard = elUnder.closest( '.wek-builder__field:not([data-n])' );
			if ( fieldCard && fieldCard !== dragCard && fieldCard.getAttribute( 'data-repeater' ) !== '1' ) {
				const s = parseInt( fieldCard.getAttribute( 'data-s' ), 10 );
				const f = parseInt( fieldCard.getAttribute( 'data-f' ), 10 );
				const rect = fieldCard.getBoundingClientRect();
				const before = clientY < rect.top + rect.height / 2;
				const line = el( 'div', { className: 'wek-builder__drop-line' } );
				if ( before ) {
					fieldCard.parentNode.insertBefore( line, fieldCard );
				} else {
					fieldCard.parentNode.insertBefore( line, fieldCard.nextSibling );
				}
				return { scope: 'section', s: s, f: null, index: before ? f : f + 1 };
			}

			const sectionCard = elUnder.closest( '.wek-builder__section' );
			if ( sectionCard ) {
				const s = parseInt( sectionCard.getAttribute( 'data-s' ), 10 );
				const grid = sectionCard.querySelector( ':scope > .wek-builder__fields' );
				sectionCard.classList.add( 'is-drop-target' );
				if ( grid ) {
					grid.appendChild( el( 'div', { className: 'wek-builder__drop-line' } ) );
				}
				const len =
					schema.sections[ s ] && schema.sections[ s ].fields
						? schema.sections[ s ].fields.length
						: 0;
				return { scope: 'section', s: s, f: null, index: len };
			}

			return null;
		}

		function bindFieldDrag( handle, card, fromLoc ) {
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
				let dropLoc = null;

				function clearIndicators() {
					root.querySelectorAll( '.wek-builder__drop-line' ).forEach( function ( n ) {
						n.remove();
					} );
					root.querySelectorAll( '.is-drop-target' ).forEach( function ( n ) {
						n.classList.remove( 'is-drop-target' );
					} );
				}

				const onMove = function ( e ) {
					const dx = e.clientX - startX;
					const dy = e.clientY - startY;
					if ( ! started && Math.abs( dx ) + Math.abs( dy ) < 6 ) {
						return;
					}
					started = true;
					clearIndicators();
					dropLoc = resolveDropLocation( e.clientX, e.clientY, card );
				};

				const onUp = function () {
					document.removeEventListener( 'pointermove', onMove );
					document.removeEventListener( 'pointerup', onUp );
					document.removeEventListener( 'pointercancel', onUp );
					handle.setAttribute( 'aria-grabbed', 'false' );
					card.classList.remove( 'is-dragging' );
					clearIndicators();
					if ( ! started ) {
						if ( fromLoc.scope === 'repeater' ) {
							selectItem( {
								type: 'nested',
								sIndex: fromLoc.s,
								fIndex: fromLoc.f,
								nIndex: fromLoc.index,
							} );
						} else {
							selectItem( { type: 'field', sIndex: fromLoc.s, fIndex: fromLoc.index } );
						}
						return;
					}
					if ( ! dropLoc ) {
						return;
					}
					relocateField( fromLoc, dropLoc );
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

		function previewChoiceRows( field, inputKind ) {
			const options = Array.isArray( field.options ) ? field.options : [];
			const list = el( 'div', {
				className: 'wek-builder__field-preview wek-builder__field-preview--choices',
				'aria-hidden': 'true',
			} );
			const rows = options.length
				? options.slice( 0, 4 )
				: [
						{ label: i18n.optionPreview || 'Option' },
						{ label: ( i18n.optionPreview || 'Option' ) + ' 2' },
				  ];
			rows.forEach( function ( opt ) {
				list.appendChild(
					el( 'span', { className: 'wek-builder__choice-row' }, [
						el( 'span', {
							className:
								'wek-builder__choice-mark wek-builder__choice-mark--' + inputKind,
						} ),
						el( 'span', {
							className: 'wek-builder__choice-label',
							text: opt.label || opt.value || '',
						} ),
					] )
				);
			} );
			if ( options.length > 4 ) {
				list.appendChild(
					el( 'span', {
						className: 'wek-builder__choice-more',
						text: '+' + ( options.length - 4 ),
					} )
				);
			}
			return list;
		}

		function renderFieldPreview( field ) {
			const type = field.type || 'text';
			const hint = field.placeholder || field.help || '';

			if ( type === 'checkbox' || type === 'consent' ) {
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--toggle',
					'aria-hidden': 'true',
				}, [
					el( 'span', { className: 'wek-builder__choice-mark wek-builder__choice-mark--checkbox' } ),
					el( 'span', {
						className: 'wek-builder__choice-label',
						text:
							hint ||
							( type === 'consent'
								? i18n.consentPreview || 'Consent checkbox'
								: i18n.checkboxPreview || 'Single checkbox' ),
					} ),
				] );
			}

			if ( type === 'checkboxes' ) {
				return previewChoiceRows( field, 'checkbox' );
			}
			if ( type === 'radio' || type === 'radio_image' ) {
				return previewChoiceRows( field, 'radio' );
			}

			if ( type === 'select' ) {
				const options = Array.isArray( field.options ) ? field.options : [];
				const first = options[ 0 ];
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--select',
					'aria-hidden': 'true',
					text: ( first && ( first.label || first.value ) ) || hint || i18n.selectPreview || 'Select…',
				} );
			}

			if ( type === 'textarea' ) {
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--textarea',
					'aria-hidden': 'true',
					text: hint,
				} );
			}

			if ( type === 'html' ) {
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--html',
					'aria-hidden': 'true',
					text: hint || i18n.htmlPreview || 'HTML block',
				} );
			}

			if ( type === 'hidden' ) {
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--hidden',
					'aria-hidden': 'true',
					text: i18n.hiddenPreview || 'Hidden field',
				} );
			}

			if ( type === 'upload' ) {
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--upload',
					'aria-hidden': 'true',
					text: hint || i18n.uploadPreview || 'Choose file…',
				} );
			}

			if ( type === 'signature' ) {
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--signature',
					'aria-hidden': 'true',
					text: i18n.signaturePreview || 'Signature pad',
				} );
			}

			return el( 'div', {
				className: 'wek-builder__field-preview wek-builder__field-preview--input',
				'aria-hidden': 'true',
				text: hint,
			} );
		}

		function cloneFieldDeep( field ) {
			const copy = JSON.parse( JSON.stringify( field ) );
			copy.id = ( copy.type || 'field' ) + '_' + Date.now().toString( 36 );
			if ( copy.type === 'repeater' && copy.type_options && Array.isArray( copy.type_options.fields ) ) {
				copy.type_options.fields = copy.type_options.fields.map( function ( child ) {
					return cloneFieldDeep( child );
				} );
			}
			return copy;
		}

		function toolbarIconButton( iconClass, title, onClick, tone ) {
			return el(
				'button',
				{
					type: 'button',
					className:
						'wek-builder__chip-btn' +
						( tone ? ' wek-builder__chip-btn--' + tone : '' ),
					title: title,
					'aria-label': title,
					onClick: function ( e ) {
						e.preventDefault();
						e.stopPropagation();
						onClick( e );
					},
				},
				[
					el( 'span', {
						className: 'dashicons ' + iconClass,
						'aria-hidden': 'true',
					} ),
				]
			);
		}

		function moveInArray( list, from, to ) {
			if ( to < 0 || to >= list.length || from === to ) {
				return from;
			}
			const item = list.splice( from, 1 )[ 0 ];
			list.splice( to, 0, item );
			return to;
		}

		function renderFieldToolbar( actions ) {
			const bar = el( 'div', {
				className: 'wek-builder__field-toolbar',
				role: 'toolbar',
				'aria-label': i18n.fieldActions || 'Field actions',
			} );
			bar.appendChild(
				toolbarIconButton(
					'dashicons-edit',
					i18n.edit || 'Edit',
					actions.onEdit,
					'edit'
				)
			);
			bar.appendChild(
				toolbarIconButton(
					'dashicons-admin-page',
					i18n.duplicate || 'Duplicate',
					actions.onDuplicate
				)
			);
			const upBtn = toolbarIconButton(
				'dashicons-arrow-up-alt2',
				i18n.moveUp || 'Move up',
				actions.onMoveUp
			);
			upBtn.disabled = ! actions.canUp;
			bar.appendChild( upBtn );
			const downBtn = toolbarIconButton(
				'dashicons-arrow-down-alt2',
				i18n.moveDown || 'Move down',
				actions.onMoveDown
			);
			downBtn.disabled = ! actions.canDown;
			bar.appendChild( downBtn );
			bar.appendChild(
				toolbarIconButton(
					'dashicons-trash',
					i18n.remove || 'Remove',
					actions.onDelete,
					'danger'
				)
			);
			return bar;
		}

		function renderNestedFieldCard( parent, child, sIndex, fIndex, nIndex ) {
			const selected =
				selection &&
				selection.type === 'nested' &&
				selection.sIndex === sIndex &&
				selection.fIndex === fIndex &&
				selection.nIndex === nIndex;
			const card = el( 'div', {
				className: widthClass( child.width, selected ) + ' wek-builder__field--nested',
				'data-s': String( sIndex ),
				'data-f': String( fIndex ),
				'data-n': String( nIndex ),
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

			const nestedList =
				parent.type_options && Array.isArray( parent.type_options.fields )
					? parent.type_options.fields
					: [];

			card.appendChild(
				el( 'div', { className: 'wek-builder__field-main' }, [
					handle,
					el( 'div', { className: 'wek-builder__field-body' }, [
						el( 'span', {
							className: 'wek-builder__field-label',
							text: child.label || child.id || i18n.field || 'Field',
						} ),
						renderFieldPreview( child ),
					] ),
				] )
			);

			card.appendChild(
				renderFieldToolbar( {
					canUp: nIndex > 0,
					canDown: nIndex < nestedList.length - 1,
					onEdit: function () {
						selectItem(
							{
								type: 'nested',
								sIndex: sIndex,
								fIndex: fIndex,
								nIndex: nIndex,
							},
							'general'
						);
					},
					onDuplicate: function () {
						const copy = cloneFieldDeep( child );
						nestedList.splice( nIndex + 1, 0, copy );
						syncHidden();
						selectItem( {
							type: 'nested',
							sIndex: sIndex,
							fIndex: fIndex,
							nIndex: nIndex + 1,
						} );
					},
					onMoveUp: function () {
						const next = moveInArray( nestedList, nIndex, nIndex - 1 );
						syncHidden();
						selectItem( {
							type: 'nested',
							sIndex: sIndex,
							fIndex: fIndex,
							nIndex: next,
						} );
					},
					onMoveDown: function () {
						const next = moveInArray( nestedList, nIndex, nIndex + 1 );
						syncHidden();
						selectItem( {
							type: 'nested',
							sIndex: sIndex,
							fIndex: fIndex,
							nIndex: next,
						} );
					},
					onDelete: function () {
						if ( ! window.confirm( i18n.confirmDel || 'Remove this item?' ) ) {
							return;
						}
						nestedList.splice( nIndex, 1 );
						selection = null;
						syncHidden();
						render();
					},
				} )
			);

			card.addEventListener( 'click', function ( e ) {
				if (
					e.target.closest( '.wek-builder__handle' ) ||
					e.target.closest( '.wek-builder__field-toolbar' )
				) {
					return;
				}
				e.stopPropagation();
				selectItem( {
					type: 'nested',
					sIndex: sIndex,
					fIndex: fIndex,
					nIndex: nIndex,
				} );
			} );

			bindFieldDrag( handle, card, {
				scope: 'repeater',
				s: sIndex,
				f: fIndex,
				index: nIndex,
			} );
			return card;
		}

		function renderRepeaterCard( section, field, sIndex, fIndex ) {
			ensureRepeaterDefaults( field );
			const selected =
				selection &&
				selection.type === 'field' &&
				selection.sIndex === sIndex &&
				selection.fIndex === fIndex;
			const card = el( 'div', {
				className: widthClass( field.width, selected ) + ' wek-builder__field--repeater',
				'data-s': String( sIndex ),
				'data-f': String( fIndex ),
				'data-repeater': '1',
				tabindex: '0',
				role: 'group',
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

			const head = el( 'div', { className: 'wek-builder__repeater-head' }, [
				handle,
				el( 'div', { className: 'wek-builder__field-body' }, [
					el( 'span', {
						className: 'wek-builder__field-label',
						text: field.label || field.id || i18n.field || 'Field',
					} ),
					el( 'span', {
						className: 'wek-builder__field-preview',
						text:
							i18n.repeaterDropHint ||
							'Drop fields here — they repeat together on the front end.',
					} ),
				] ),
			] );
			head.addEventListener( 'click', function ( e ) {
				if (
					e.target.closest( '.wek-builder__handle' ) ||
					e.target.closest( '.wek-builder__field-toolbar' )
				) {
					return;
				}
				selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex } );
			} );
			card.appendChild( head );
			card.appendChild(
				renderFieldToolbar( {
					canUp: fIndex > 0,
					canDown: fIndex < ( section.fields || [] ).length - 1,
					onEdit: function () {
						selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex }, 'general' );
					},
					onDuplicate: function () {
						const copy = cloneFieldDeep( field );
						section.fields.splice( fIndex + 1, 0, copy );
						syncHidden();
						selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex + 1 } );
					},
					onMoveUp: function () {
						const next = moveInArray( section.fields, fIndex, fIndex - 1 );
						syncHidden();
						selectItem( { type: 'field', sIndex: sIndex, fIndex: next } );
					},
					onMoveDown: function () {
						const next = moveInArray( section.fields, fIndex, fIndex + 1 );
						syncHidden();
						selectItem( { type: 'field', sIndex: sIndex, fIndex: next } );
					},
					onDelete: function () {
						if ( ! window.confirm( i18n.confirmDel || 'Remove this item?' ) ) {
							return;
						}
						section.fields.splice( fIndex, 1 );
						selection = null;
						syncHidden();
						render();
					},
				} )
			);

			const canvas = el( 'div', { className: 'wek-builder__repeater-canvas' } );
			const nested = field.type_options.fields;
			if ( ! nested.length ) {
				canvas.appendChild(
					el( 'p', {
						className: 'wek-builder__repeater-empty',
						text:
							i18n.repeaterEmpty ||
							'Drop fields here from the library.',
					} )
				);
			} else {
				nested.forEach( function ( child, nIndex ) {
					canvas.appendChild( renderNestedFieldCard( field, child, sIndex, fIndex, nIndex ) );
				} );
			}
			card.appendChild( canvas );

			const resize = el( 'button', {
				type: 'button',
				className: 'wek-builder__resize',
				title: i18n.resizeHandle || 'Drag to resize',
				'aria-label': i18n.resizeHandle || 'Drag to resize',
			} );
			resize.appendChild( el( 'span', { className: 'wek-builder__resize-grip' } ) );
			card.appendChild( resize );

			bindFieldDrag( handle, card, {
				scope: 'section',
				s: sIndex,
				f: null,
				index: fIndex,
			} );
			bindResize( resize, field, card );
			return card;
		}

		function renderFieldCard( section, field, sIndex, fIndex ) {
			if ( field.type === 'repeater' ) {
				return renderRepeaterCard( section, field, sIndex, fIndex );
			}

			const selected =
				selection &&
				selection.type === 'field' &&
				selection.sIndex === sIndex &&
				selection.fIndex === fIndex;
			const card = el( 'div', {
				className: widthClass( field.width, selected ),
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
					renderFieldPreview( field ),
				] ),
			] );
			card.appendChild( main );

			card.appendChild(
				renderFieldToolbar( {
					canUp: fIndex > 0,
					canDown: fIndex < ( section.fields || [] ).length - 1,
					onEdit: function () {
						selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex }, 'general' );
					},
					onDuplicate: function () {
						const copy = cloneFieldDeep( field );
						section.fields.splice( fIndex + 1, 0, copy );
						syncHidden();
						selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex + 1 } );
					},
					onMoveUp: function () {
						const next = moveInArray( section.fields, fIndex, fIndex - 1 );
						syncHidden();
						selectItem( { type: 'field', sIndex: sIndex, fIndex: next } );
					},
					onMoveDown: function () {
						const next = moveInArray( section.fields, fIndex, fIndex + 1 );
						syncHidden();
						selectItem( { type: 'field', sIndex: sIndex, fIndex: next } );
					},
					onDelete: function () {
						if ( ! window.confirm( i18n.confirmDel || 'Remove this item?' ) ) {
							return;
						}
						section.fields.splice( fIndex, 1 );
						selection = null;
						syncHidden();
						render();
					},
				} )
			);

			const resize = el( 'button', {
				type: 'button',
				className: 'wek-builder__resize',
				title: i18n.resizeHandle || 'Drag to resize',
				'aria-label': i18n.resizeHandle || 'Drag to resize',
			} );
			resize.appendChild( el( 'span', { className: 'wek-builder__resize-grip' } ) );
			card.appendChild( resize );

			card.addEventListener( 'click', function ( e ) {
				if (
					e.target.closest( '.wek-builder__handle' ) ||
					e.target.closest( '.wek-builder__resize' ) ||
					e.target.closest( '.wek-builder__field-toolbar' )
				) {
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

			bindFieldDrag( handle, card, {
				scope: 'section',
				s: sIndex,
				f: null,
				index: fIndex,
			} );
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
			const fields = section.fields || [];
			if ( ! fields.length ) {
				grid.appendChild(
					el( 'p', {
						className: 'wek-builder__fields-empty',
						text:
							i18n.sectionEmpty ||
							'Click or drag a field from the library.',
					} )
				);
			} else {
				fields.forEach( function ( field, fIndex ) {
					grid.appendChild( renderFieldCard( section, field, sIndex, fIndex ) );
				} );
			}
			box.appendChild( grid );

			bindSectionDrag( handle, box, sIndex );
			return box;
		}

		function insertFieldAt( typeId, dropLoc ) {
			if ( ! dropLoc ) {
				addFieldOfType( typeId );
				return;
			}

			if ( dropLoc.scope === 'repeater' ) {
				if ( typeId === 'repeater' || ! isAllowedInRepeater( typeId ) ) {
					announce(
						i18n.repeaterTypeBlocked ||
							'This field type cannot be used inside a repeater.'
					);
					return;
				}
				const parent =
					schema.sections[ dropLoc.s ] && schema.sections[ dropLoc.s ].fields
						? schema.sections[ dropLoc.s ].fields[ dropLoc.f ]
						: null;
				if ( ! parent || parent.type !== 'repeater' ) {
					addFieldOfType( typeId );
					return;
				}
				ensureRepeaterDefaults( parent );
				const list = parent.type_options.fields;
				let at = typeof dropLoc.index === 'number' ? dropLoc.index : list.length;
				at = Math.max( 0, Math.min( at, list.length ) );
				list.splice( at, 0, createBlankField( typeId ) );
				selection = {
					type: 'nested',
					sIndex: dropLoc.s,
					fIndex: dropLoc.f,
					nIndex: at,
				};
				activeTab = 'general';
				syncHidden();
				render();
				return;
			}

			if ( dropLoc.scope === 'section' && schema.sections[ dropLoc.s ] ) {
				const section = schema.sections[ dropLoc.s ];
				section.fields = section.fields || [];
				let at = typeof dropLoc.index === 'number' ? dropLoc.index : section.fields.length;
				at = Math.max( 0, Math.min( at, section.fields.length ) );
				section.fields.splice( at, 0, createBlankField( typeId ) );
				selection = { type: 'field', sIndex: dropLoc.s, fIndex: at };
				activeTab = 'general';
				syncHidden();
				render();
				return;
			}

			addFieldOfType( typeId );
		}

		function bindLibraryDrag( tile, typeId ) {
			tile.addEventListener( 'pointerdown', function ( event ) {
				if ( event.button !== 0 ) {
					return;
				}
				event.preventDefault();

				const startX = event.clientX;
				const startY = event.clientY;
				let started = false;
				let dropLoc = null;

				function clearIndicators() {
					root.querySelectorAll( '.wek-builder__drop-line' ).forEach( function ( n ) {
						n.remove();
					} );
					root.querySelectorAll( '.is-drop-target' ).forEach( function ( n ) {
						n.classList.remove( 'is-drop-target' );
					} );
				}

				const onMove = function ( e ) {
					const dx = e.clientX - startX;
					const dy = e.clientY - startY;
					if ( ! started && Math.abs( dx ) + Math.abs( dy ) < 6 ) {
						return;
					}
					started = true;
					tile.classList.add( 'is-dragging' );
					clearIndicators();
					dropLoc = resolveDropLocation( e.clientX, e.clientY, null );
				};

				const onUp = function () {
					document.removeEventListener( 'pointermove', onMove );
					document.removeEventListener( 'pointerup', onUp );
					document.removeEventListener( 'pointercancel', onUp );
					tile.classList.remove( 'is-dragging' );
					clearIndicators();
					if ( ! started ) {
						addFieldOfType( typeId );
						return;
					}
					insertFieldAt( typeId, dropLoc );
				};

				document.addEventListener( 'pointermove', onMove );
				document.addEventListener( 'pointerup', onUp );
				document.addEventListener( 'pointercancel', onUp );
			} );
		}

		function addFieldOfType( typeId ) {
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

			const repeaterTarget = typeId !== 'repeater' ? getActiveRepeaterTarget() : null;
			if ( repeaterTarget ) {
				if ( ! isAllowedInRepeater( typeId ) ) {
					announce(
						i18n.repeaterTypeBlocked ||
							'This field type cannot be used inside a repeater.'
					);
					return;
				}
				ensureRepeaterDefaults( repeaterTarget.repeater );
				repeaterTarget.repeater.type_options.fields.push( createBlankField( typeId ) );
				selection = {
					type: 'nested',
					sIndex: repeaterTarget.sIndex,
					fIndex: repeaterTarget.fIndex,
					nIndex: repeaterTarget.repeater.type_options.fields.length - 1,
				};
				activeTab = 'general';
				syncHidden();
				render();
				return;
			}

			const section = schema.sections[ sIndex ];
			section.fields = section.fields || [];
			section.fields.push( createBlankField( typeId ) );
			selection = {
				type: 'field',
				sIndex: sIndex,
				fIndex: section.fields.length - 1,
			};
			activeTab = 'general';
			syncHidden();
			render();
		}

		function renderLibrary() {
			const panel = el( 'aside', { className: 'wek-builder-library' } );

			const search = el( 'input', {
				type: 'search',
				className: 'wek-builder-library__search',
				placeholder: i18n.searchFields || 'Search fields…',
				'aria-label': i18n.searchFields || 'Search fields…',
			} );
			panel.appendChild(
				el( 'div', { className: 'wek-builder-library__search-wrap' }, [ search ] )
			);

			const grid = el( 'div', { className: 'wek-builder-library__grid' } );
			const empty = el( 'p', {
				className: 'wek-builder-library__empty',
				text: i18n.noFieldsMatch || 'No matching fields.',
			} );
			empty.hidden = true;

			const tiles = [];
			fieldTypes.forEach( function ( item ) {
				const typeId = item.type;
				const label = item.label || typeId;
				const iconClass = FIELD_ICONS[ typeId ] || 'dashicons-plus-alt2';
				const tile = el( 'button', {
					type: 'button',
					className: 'wek-builder-library__item',
					title: label,
				}, [
					el( 'span', {
						className: 'wek-builder-library__icon dashicons ' + iconClass,
						'aria-hidden': 'true',
					} ),
					el( 'span', {
						className: 'wek-builder-library__label',
						text: label,
					} ),
				] );
				bindLibraryDrag( tile, typeId );
				tiles.push( { node: tile, haystack: ( label + ' ' + typeId ).toLowerCase() } );
				grid.appendChild( tile );
			} );

			function applyFilter() {
				const q = search.value.trim().toLowerCase();
				let visible = 0;
				tiles.forEach( function ( entry ) {
					const match = ! q || entry.haystack.indexOf( q ) !== -1;
					entry.node.hidden = ! match;
					if ( match ) {
						visible++;
					}
				} );
				empty.hidden = visible !== 0;
			}

			search.addEventListener( 'input', applyFilter );

			panel.appendChild( grid );
			panel.appendChild( empty );
			return panel;
		}

		function render() {
			root.innerHTML = '';
			const layout = el( 'div', { className: 'wek-builder-layout' } );
			const library = renderLibrary();
			const canvas = el( 'div', { className: 'wek-builder-canvas' } );
			const sheet = el( 'div', { className: 'wek-builder-canvas__sheet' } );
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
			canvas.appendChild( sheet );

			if ( ! schema.sections.length ) {
				sheet.appendChild(
					el( 'p', {
						className: 'description',
						text: i18n.empty || 'No sections yet. Add a section or pick a field from the library.',
					} )
				);
			}

			schema.sections.forEach( function ( section, sIndex ) {
				sheet.appendChild( renderSectionCard( section, sIndex ) );
			} );

			sheet.appendChild(
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

			if ( schema.sections.length ) {
				sheet.appendChild(
					el( 'div', {
						className: 'wek-builder__submit-preview',
						text: i18n.submitPreview || 'Submit',
					} )
				);
			}

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
