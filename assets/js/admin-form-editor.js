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
		'matrix',
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
		matrix: 'dashicons-grid-view',
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

		function normalizeSubmitWidth( width ) {
			const allowed = [ 'auto', 'full', 'two_thirds', 'half', 'third' ];
			return allowed.indexOf( width ) !== -1 ? width : 'auto';
		}

		const bootSubmit = ( window.weFormkitAdmin && window.weFormkitAdmin.submitButton ) || {};
		const submitButton = {
			label: bootSubmit.label || ( window.weFormkitAdmin && window.weFormkitAdmin.submitLabel ) || i18n.submitPreview || 'Submit form',
			icon_svg: bootSubmit.icon_svg || '',
			icon_position: bootSubmit.icon_position === 'after' ? 'after' : 'before',
			width: normalizeSubmitWidth( bootSubmit.width ),
		};

		/** @type {{ type: string, sIndex?: number, fIndex?: number, nIndex?: number }|null} */
		let selection = null;
		let activeTab = 'general';
		/** @type {'field'|'form'|'integrations'} */
		let sidebarScope = 'field';
		const live = { node: null };
		const CHROME_KEY = 'wek_formkit_builder_chrome';
		const SECTION_CLIP_KEY = 'we-formkit-section-clipboard';
		const formIdForUi = parseInt( ( window.weFormkitAdmin && window.weFormkitAdmin.formId ) || 0, 10 ) || 0;
		const COLLAPSE_KEY = 'wek_formkit_builder_collapsed_' + formIdForUi;
		const collapsedSectionIds = ( function () {
			const set = {};
			try {
				const raw = window.localStorage.getItem( COLLAPSE_KEY );
				if ( raw ) {
					const list = JSON.parse( raw );
					if ( Array.isArray( list ) ) {
						list.forEach( function ( id ) {
							if ( id ) {
								set[ String( id ) ] = true;
							}
						} );
					}
				}
			} catch ( e ) {
				// ignore
			}
			return set;
		} )();

		function saveCollapsedSections() {
			try {
				window.localStorage.setItem( COLLAPSE_KEY, JSON.stringify( Object.keys( collapsedSectionIds ) ) );
			} catch ( e ) {
				// ignore
			}
		}

		function isSectionCollapsed( section ) {
			return !!( section && section.id && collapsedSectionIds[ String( section.id ) ] );
		}

		function setSectionCollapsed( sectionId, collapsed ) {
			const key = String( sectionId || '' );
			if ( ! key ) {
				return;
			}
			if ( collapsed ) {
				collapsedSectionIds[ key ] = true;
			} else {
				delete collapsedSectionIds[ key ];
			}
			saveCollapsedSections();
		}

		const chrome = ( function () {
			const defaults = {
				libW: 280,
				sideW: 400,
				libCollapsed: false,
				sideCollapsed: false,
			};
			try {
				const raw = window.localStorage.getItem( CHROME_KEY );
				if ( raw ) {
					return Object.assign( {}, defaults, JSON.parse( raw ) );
				}
			} catch ( e ) {
				// ignore
			}
			return Object.assign( {}, defaults );
		} )();

		function saveChrome() {
			try {
				window.localStorage.setItem( CHROME_KEY, JSON.stringify( chrome ) );
			} catch ( e ) {
				// ignore
			}
		}

		function clampWidth( n, min, max ) {
			const v = parseInt( n, 10 );
			if ( isNaN( v ) ) {
				return min;
			}
			return Math.max( min, Math.min( max, v ) );
		}

		function applyChrome( layout ) {
			if ( ! layout ) {
				return;
			}
			layout.classList.toggle( 'is-lib-collapsed', !! chrome.libCollapsed );
			layout.classList.toggle( 'is-side-collapsed', !! chrome.sideCollapsed );
			layout.style.setProperty(
				'--wek-lib-w',
				( chrome.libCollapsed ? 36 : clampWidth( chrome.libW, 200, 420 ) ) + 'px'
			);
			layout.style.setProperty(
				'--wek-side-w',
				( chrome.sideCollapsed ? 36 : clampWidth( chrome.sideW, 280, 560 ) ) + 'px'
			);
		}

		/** Panel column resize (not field-width — that is bindFieldWidthResize). */
		function bindChromeResize( handle, which, layout ) {
			handle.addEventListener( 'pointerdown', function ( event ) {
				if ( event.button !== 0 ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				handle.setPointerCapture( event.pointerId );
				document.body.classList.add( 'wek-builder-is-resizing' );
				const startX = event.clientX;
				const startW = which === 'lib' ? chrome.libW : chrome.sideW;
				function onMove( ev ) {
					const dx = ev.clientX - startX;
					if ( which === 'lib' ) {
						chrome.libW = clampWidth( startW + dx, 200, 420 );
						chrome.libCollapsed = false;
					} else {
						chrome.sideW = clampWidth( startW - dx, 280, 560 );
						chrome.sideCollapsed = false;
					}
					applyChrome( layout );
				}
				function onUp() {
					handle.releasePointerCapture( event.pointerId );
					handle.removeEventListener( 'pointermove', onMove );
					handle.removeEventListener( 'pointerup', onUp );
					handle.removeEventListener( 'pointercancel', onUp );
					document.body.classList.remove( 'wek-builder-is-resizing' );
					saveChrome();
				}
				handle.addEventListener( 'pointermove', onMove );
				handle.addEventListener( 'pointerup', onUp );
				handle.addEventListener( 'pointercancel', onUp );
			} );
		}

		const opsNoValue = {
			is_checked: true,
			is_not_checked: true,
			is_not_empty: true,
			is_empty: true,
		};

		const allOps = [
			{ value: 'equals', label: i18n.opEquals || 'equals' },
			{ value: 'not_equals', label: i18n.opNotEquals || 'not equals' },
			{ value: 'contains', label: i18n.opContains || 'contains' },
			{ value: 'is_checked', label: i18n.opIsChecked || 'is checked' },
			{ value: 'is_not_checked', label: i18n.opIsNotChecked || 'is not checked' },
			{ value: 'is_not_empty', label: i18n.opIsNotEmpty || 'is not empty' },
			{ value: 'is_empty', label: i18n.opIsEmpty || 'is empty' },
		];

		function opsForConditionField( meta ) {
			const kind = meta && meta.kind ? meta.kind : 'field';
			const type = meta && meta.type ? meta.type : '';
			if ( kind === 'matrix_row' ) {
				return allOps.filter( function ( op ) {
					return op.value === 'is_checked' || op.value === 'is_not_checked';
				} );
			}
			if ( type === 'checkbox' || type === 'consent' ) {
				return allOps.filter( function ( op ) {
					return op.value === 'is_checked' || op.value === 'is_not_checked';
				} );
			}
			if ( type === 'checkboxes' ) {
				return allOps.filter( function ( op ) {
					return (
						op.value === 'contains' ||
						op.value === 'equals' ||
						op.value === 'not_equals' ||
						op.value === 'is_not_empty' ||
						op.value === 'is_empty'
					);
				} );
			}
			if ( type === 'radio' || type === 'radio_image' || type === 'select' ) {
				return allOps.filter( function ( op ) {
					return (
						op.value === 'equals' ||
						op.value === 'not_equals' ||
						op.value === 'is_not_empty' ||
						op.value === 'is_empty'
					);
				} );
			}
			if ( type === 'matrix' ) {
				return allOps.filter( function ( op ) {
					return op.value === 'is_not_empty' || op.value === 'is_empty';
				} );
			}
			if ( type === 'upload' || type === 'signature' || type === 'repeater' ) {
				return allOps.filter( function ( op ) {
					return op.value === 'is_not_empty' || op.value === 'is_empty';
				} );
			}
			// Text-like and fallback.
			return allOps.filter( function ( op ) {
				return (
					op.value === 'equals' ||
					op.value === 'not_equals' ||
					op.value === 'contains' ||
					op.value === 'is_not_empty' ||
					op.value === 'is_empty'
				);
			} );
		}

		function defaultOpForConditionField( meta ) {
			const allowed = opsForConditionField( meta );
			if ( ! allowed.length ) {
				return 'equals';
			}
			const preferred =
				meta && ( meta.type === 'checkbox' || meta.type === 'consent' || meta.kind === 'matrix_row' )
					? 'is_checked'
					: allowed[ 0 ].value;
			const hit = allowed.find( function ( op ) {
				return op.value === preferred;
			} );
			return hit ? hit.value : allowed[ 0 ].value;
		}

		function ensureRuleOpForField( rule, meta ) {
			const allowed = opsForConditionField( meta ).map( function ( op ) {
				return op.value;
			} );
			if ( allowed.indexOf( rule.op ) === -1 ) {
				rule.op = defaultOpForConditionField( meta );
			}
			if ( opsNoValue[ rule.op ] ) {
				rule.value = '';
			}
		}

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

		let syncDirty = false;
		let syncTimer = null;

		function flushSyncHidden() {
			if ( syncTimer ) {
				window.clearTimeout( syncTimer );
				syncTimer = null;
			}
			syncDirty = false;
			if ( titleInput ) {
				schema.title = titleInput.value;
			}
			if ( introInput ) {
				schema.intro = introInput.value;
			}
			hidden.value = JSON.stringify( schema );
			const labelInput = document.getElementById( 'wek_submit_label' );
			const iconInput = document.getElementById( 'wek_submit_icon_svg' );
			const posInput = document.getElementById( 'wek_submit_icon_position' );
			const widthInput = document.getElementById( 'wek_submit_width' );
			if ( labelInput ) {
				labelInput.value = submitButton.label || '';
			}
			if ( iconInput ) {
				iconInput.value = submitButton.icon_svg || '';
			}
			if ( posInput ) {
				posInput.value = submitButton.icon_position || 'before';
			}
			if ( widthInput ) {
				widthInput.value = normalizeSubmitWidth( submitButton.width );
			}
		}

		/** Debounced schema → hidden input write. Use flushSyncHidden() before save/render. */
		function syncHidden() {
			syncDirty = true;
			if ( syncTimer ) {
				window.clearTimeout( syncTimer );
			}
			syncTimer = window.setTimeout( function () {
				syncTimer = null;
				if ( syncDirty ) {
					flushSyncHidden();
				}
			}, 150 );
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

		function applyFieldWidthToCard( field, width ) {
			field.width = normalizeWidth( width );
			const selectedNow = getSelected();
			const cardSelector =
				selectedNow && selectedNow.field === field
					? canvasFieldCardSelector( selectedNow )
					: null;
			const card = cardSelector ? root.querySelector( cardSelector ) : null;
			if ( card ) {
				const isSelected = card.classList.contains( 'is-selected' );
				card.className =
					widthClass( field.width, isSelected ) +
					( card.className.indexOf( 'wek-builder__field--repeater' ) !== -1 ||
					field.type === 'repeater'
						? ' wek-builder__field--repeater'
						: '' ) +
					( card.className.indexOf( 'wek-builder__field--nested' ) !== -1
						? ' wek-builder__field--nested'
						: '' );
			}
			const picker = ui.aside
				? ui.aside.querySelector( '.wek-builder__columns' )
				: null;
			if ( picker ) {
				Array.prototype.forEach.call(
					picker.querySelectorAll( '.wek-builder__column-btn' ),
					function ( btn ) {
						const isActive = btn.getAttribute( 'data-width' ) === field.width;
						btn.classList.toggle( 'is-active', isActive );
						btn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
					}
				);
			}
			announce( ( i18n.width || 'Columns' ) + ': ' + widthLabel( field.width ) );
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
					el(
						'button',
						{
							type: 'button',
							className:
								'wek-builder__column-btn' +
								( field.width === item.value ? ' is-active' : '' ),
							'aria-pressed': field.width === item.value ? 'true' : 'false',
							title: item.label,
							'data-width': item.value,
							onClick: function () {
								applyFieldWidthToCard( field, item.value );
								syncHidden();
							},
						},
						[ preview, el( 'strong', { text: item.label } ) ]
					)
				);
			} );
			return wrap;
		}

		function getSelected() {
			if ( ! selection ) {
				return null;
			}
			if ( selection.type === 'submit' ) {
				return { kind: 'submit' };
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

		/**
		 * Build "Column 1" / "Row 2" style labels from a "%d" template.
		 *
		 * @param {string} template e.g. "Column %d" or legacy "Row 1".
		 * @param {number} n        1-based index.
		 * @return {string}
		 */
		function formatMatrixIndexLabel( template, n ) {
			const t = String( template || '' );
			const num = String( n );
			if ( t.indexOf( '%d' ) !== -1 ) {
				return t.replace( /%d/g, num );
			}
			if ( /1\s*$/.test( t ) ) {
				return t.replace( /1\s*$/, num );
			}
			return ( t.replace( /\s+\d+\s*$/, '' ).trim() || t || 'Item' ) + ' ' + num;
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
			if ( typeId === 'consent' ) {
				typeOptions.choice_label = i18n.consentDefaultText || 'I agree to the {link}';
				typeOptions.link_text = '';
				typeOptions.privacy_url = '';
			}
			if ( typeId === 'checkbox' ) {
				typeOptions.choice_label = i18n.checkboxDefaultText || 'Yes';
			}
			if ( typeId === 'text' ) {
				typeOptions.max_length = 200;
			}
			if ( typeId === 'textarea' ) {
				typeOptions.rows = 3;
				typeOptions.max_length = 5000;
			}
			if ( typeId === 'upload' ) {
				typeOptions.max_files = 1;
				typeOptions.max_file_size_mb = 5;
				typeOptions.allowed_mime_types = 'image/jpeg, image/png, application/pdf';
				typeOptions.storage_mode = 'uploads_only';
			}
			if ( typeId === 'radio_image' ) {
				typeOptions.image_size = 'medium';
				typeOptions.columns = 2;
				typeOptions.option_position = 'below';
				typeOptions.select_style = 'radio';
			}
			if ( typeId === 'matrix' ) {
				typeOptions.row_select = true;
				typeOptions.row_label_align = 'left';
				typeOptions.allow_custom_rows = true;
				typeOptions.max_custom_rows = 2;
				typeOptions.min_answered_rows = 0;
				typeOptions.rows = [];
				typeOptions.columns = [
					{
						id: 'radio',
						type: 'radio',
						label: formatMatrixIndexLabel( i18n.matrixColumnSample || 'Column %d', 1 ),
						required: false,
						options: [
							{ value: 'a', label: i18n.matrixOptA || 'Option A' },
							{ value: 'b', label: i18n.matrixOptB || 'Option B' },
						],
					},
					{
						id: 'text',
						type: 'text',
						label: formatMatrixIndexLabel( i18n.matrixColumnSample || 'Column %d', 2 ),
						required: false,
						options: [],
					},
					{
						id: 'checkbox',
						type: 'checkbox',
						label: formatMatrixIndexLabel( i18n.matrixColumnSample || 'Column %d', 3 ),
						required: false,
						options: [],
					},
				];
			}
			return {
				id: nextFieldId( typeId || 'field' ),
				type: typeId || 'text',
				label: ( typeMeta && typeMeta.label ) || i18n.field || 'Field',
				help: '',
				required: false,
				show_label: typeId === 'checkbox' || typeId === 'consent' ? false : true,
				css_class: '',
				placeholder: '',
				messages: { required: '', invalid: '' },
				default_value: '',
				type_options: typeOptions,
				width: 'full',
				options: [],
				show_when: null,
				role: '',
			};
		}

		let fieldIdSeq = 0;
		function nextFieldId( prefix ) {
			fieldIdSeq += 1;
			return ( prefix || 'field' ) + '_' + Date.now().toString( 36 ) + fieldIdSeq.toString( 36 );
		}

		const roleLabels =
			window.weFormkitAdmin && window.weFormkitAdmin.roleLabels
				? window.weFormkitAdmin.roleLabels
				: {};
		const fieldPacks =
			window.weFormkitAdmin && window.weFormkitAdmin.fieldPacks
				? window.weFormkitAdmin.fieldPacks
				: {};
		const countryPresetsBoot =
			window.weFormkitAdmin && window.weFormkitAdmin.countryPresets
				? window.weFormkitAdmin.countryPresets
				: { catalog: {}, otherValue: '__other__', defaultPreset: 'dach' };

		function orderCountryOptions( options, priority, includeOther ) {
			const otherValue = countryPresetsBoot.otherValue || '__other__';
			const byCode = {};
			( options || [] ).forEach( function ( o ) {
				if ( ! o || ! o.value || o.value === otherValue ) {
					return;
				}
				byCode[ String( o.value ).toUpperCase() ] = {
					value: String( o.value ).toUpperCase(),
					label: o.label,
				};
			} );
			const out = [];
			const used = {};
			( priority || [] ).forEach( function ( code ) {
				const key = String( code || '' ).toUpperCase();
				if ( byCode[ key ] && ! used[ key ] ) {
					out.push( byCode[ key ] );
					used[ key ] = true;
				}
			} );
			const rest = Object.keys( byCode )
				.filter( function ( key ) {
					return ! used[ key ];
				} )
				.map( function ( key ) {
					return byCode[ key ];
				} )
				.sort( function ( a, b ) {
					return String( a.label ).localeCompare( String( b.label ) );
				} );
			const merged = out.concat( rest );
			if ( includeOther ) {
				merged.push( {
					value: otherValue,
					label: i18n.otherCountry || 'Other',
				} );
			}
			return merged;
		}

		function clonePackSlots( packId ) {
			const pack = fieldPacks[ packId ];
			const slots = pack && Array.isArray( pack.slots ) ? pack.slots : [];
			return slots.map( function ( slot ) {
				return {
					role: slot.role,
					enabled: !! slot.enabled,
					width: slot.width || 'full',
				};
			} );
		}

		function buildPackFields( packId, slotsState, countryOpts ) {
			const labels = roleLabels;
			const otherValue = countryPresetsBoot.otherValue || '__other__';
			const fields = [];
			let countryFieldId = '';

			slotsState.forEach( function ( slot ) {
				if ( ! slot.enabled ) {
					return;
				}
				const role = slot.role;
				const label = labels[ role ] || role;

				if ( role === 'country' ) {
					const preset =
						( countryOpts && countryOpts.preset ) ||
						countryPresetsBoot.defaultPreset ||
						'dach';
					const includeOther = !!( countryOpts && countryOpts.includeOther );
					const catalog = countryPresetsBoot.catalog || {};
					const presetMeta = catalog[ preset ] || catalog.dach || {};
					let options = Array.isArray( presetMeta.options )
						? presetMeta.options.map( function ( o ) {
								return { value: o.value, label: o.label };
						  } )
						: [];
					const priority = Array.isArray( countryOpts && countryOpts.priority )
						? countryOpts.priority.slice()
						: [];
					options = orderCountryOptions( options, priority, includeOther );

					const countryField = createBlankField( 'select' );
					countryField.label = label;
					countryField.role = 'country';
					countryField.width = slot.width || 'full';
					countryField.options = options;
					countryField.placeholder = i18n.pleaseSelect || '';
					if ( countryOpts && countryOpts.defaultCountry ) {
						countryField.default_value = String( countryOpts.defaultCountry );
					}
					countryFieldId = countryField.id;
					fields.push( countryField );

					if ( includeOther ) {
						const otherField = createBlankField( 'text' );
						otherField.label = labels.country_other || 'Other country';
						otherField.role = 'country_other';
						otherField.width = 'full';
						otherField.show_when = {
							relation: 'AND',
							rules: [
								{
									field: countryFieldId,
									op: 'equals',
									value: otherValue,
								},
							],
						};
						fields.push( otherField );
					}
					return;
				}

				const field = createBlankField( 'text' );
				field.label = label;
				field.role = role;
				field.width = slot.width || 'full';
				if ( role === 'postal_code' ) {
					field.type_options.max_length = 16;
				}
				if ( role === 'given_name' || role === 'family_name' ) {
					field.required = true;
				}
				fields.push( field );
			} );

			return fields;
		}

		function packGroupId( field ) {
			return field && field.pack_group && field.pack_group.id
				? String( field.pack_group.id )
				: '';
		}

		function stampPackGroup( fields, packId ) {
			const pack = packId === 'address' ? 'address' : 'name';
			const group = {
				id: nextFieldId( 'pack' ),
				pack: pack,
			};
			( fields || [] ).forEach( function ( field ) {
				field.pack_group = { id: group.id, pack: group.pack };
			} );
			return fields;
		}

		/**
		 * Contiguous run of fields sharing pack_group.id, or null.
		 *
		 * @return {{ start: number, length: number, id: string, pack: string }|null}
		 */
		function getPackRun( fields, index ) {
			if ( ! Array.isArray( fields ) || index < 0 || index >= fields.length ) {
				return null;
			}
			const id = packGroupId( fields[ index ] );
			if ( ! id ) {
				return null;
			}
			let start = index;
			while ( start > 0 && packGroupId( fields[ start - 1 ] ) === id ) {
				start -= 1;
			}
			let end = index;
			while ( end < fields.length - 1 && packGroupId( fields[ end + 1 ] ) === id ) {
				end += 1;
			}
			return {
				start: start,
				length: end - start + 1,
				id: id,
				pack: fields[ start ].pack_group.pack === 'address' ? 'address' : 'name',
			};
		}

		function packLabel( pack ) {
			if ( pack === 'address' ) {
				return (
					( fieldPacks.address && fieldPacks.address.label ) ||
					i18n.addressPackTitle ||
					'Address'
				);
			}
			return (
				( fieldPacks.name && fieldPacks.name.label ) ||
				i18n.namePackTitle ||
				'Name'
			);
		}

		function clearPackGroup( field ) {
			if ( field && field.pack_group ) {
				delete field.pack_group;
			}
		}

		function clearPackGroupIfOrphan( list, index ) {
			const field = list[ index ];
			const id = packGroupId( field );
			if ( ! id ) {
				return;
			}
			const left = index > 0 && packGroupId( list[ index - 1 ] ) === id;
			const right =
				index < list.length - 1 && packGroupId( list[ index + 1 ] ) === id;
			if ( ! left && ! right ) {
				clearPackGroup( field );
			}
		}

		function patchCanvasSelection() {
			if ( ! ui.sheet ) {
				return;
			}
			const sheet = ui.sheet;
			Array.prototype.forEach.call(
				sheet.querySelectorAll( '.wek-builder__field.is-selected, .wek-builder__section.is-selected, .wek-builder__submit-preview.is-selected' ),
				function ( node ) {
					node.classList.remove( 'is-selected' );
					if ( node.getAttribute( 'aria-pressed' ) !== null ) {
						node.setAttribute( 'aria-pressed', 'false' );
					}
				}
			);

			if ( ! selection ) {
				return;
			}

			if ( selection.type === 'submit' ) {
				const submit = sheet.querySelector( '.wek-builder__submit-preview' );
				if ( submit ) {
					submit.classList.add( 'is-selected' );
					submit.setAttribute( 'aria-pressed', 'true' );
				}
				return;
			}

			if ( selection.type === 'section' ) {
				const section = sheet.querySelector(
					'.wek-builder__section[data-s="' + selection.sIndex + '"]'
				);
				if ( section ) {
					section.classList.add( 'is-selected' );
				}
				return;
			}

			if ( selection.type === 'nested' ) {
				const nested = sheet.querySelector(
					'.wek-builder__field[data-s="' +
						selection.sIndex +
						'"][data-f="' +
						selection.fIndex +
						'"][data-n="' +
						selection.nIndex +
						'"]'
				);
				if ( nested ) {
					nested.classList.add( 'is-selected' );
					nested.setAttribute( 'aria-pressed', 'true' );
				}
				return;
			}

			if ( selection.type === 'field' ) {
				const field = sheet.querySelector(
					'.wek-builder__field[data-s="' +
						selection.sIndex +
						'"][data-f="' +
						selection.fIndex +
						'"]:not([data-n])'
				);
				if ( field ) {
					field.classList.add( 'is-selected' );
					field.setAttribute( 'aria-pressed', 'true' );
				}
			}
		}

		function selectItem( next, tab ) {
			selection = next;
			if ( next ) {
				sidebarScope = 'field';
			}
			if ( tab ) {
				activeTab = tab;
			} else if ( next && ( next.type === 'section' || next.type === 'submit' ) && activeTab === 'appearance' ) {
				activeTab = 'general';
			} else if ( next && next.type === 'nested' && activeTab === 'conditional' ) {
				activeTab = 'general';
			} else if ( next && next.type === 'submit' ) {
				activeTab = 'general';
			}
			ensureShell();
			patchCanvasSelection();
			flushSyncHidden();
			refreshSidebar();
		}

		function fieldRow( labelText, control ) {
			return el( 'p', { className: 'wek-builder__row' }, [
				el( 'label', null, [ labelText, control ] ),
			] );
		}

		function toggleRow( labelText, input ) {
			input.className = ( input.className ? input.className + ' ' : '' ) + 'wek-builder__toggle-input';
			return el( 'p', { className: 'wek-builder__row wek-builder__row--toggle' }, [
				el( 'label', { className: 'wek-builder__toggle' }, [
					input,
					el( 'span', { className: 'wek-builder__toggle-ui', 'aria-hidden': 'true' } ),
					el( 'span', { className: 'wek-builder__toggle-label', text: labelText } ),
				] ),
			] );
		}

		/**
		 * Compact side-by-side field rows (e.g. min/max numbers).
		 *
		 * @param {Array<{label:string,control:HTMLElement}>} items Pair items.
		 * @return {HTMLElement}
		 */
		function fieldPair( items ) {
			const wrap = el( 'div', { className: 'wek-builder__pair' } );
			( items || [] ).forEach( function ( item ) {
				if ( ! item || ! item.control ) {
					return;
				}
				wrap.appendChild( fieldRow( item.label, item.control ) );
			} );
			return wrap;
		}

		/**
		 * Compact side-by-side toggles (e.g. Required + Show label).
		 *
		 * @param {Array<{label:string,input:HTMLInputElement}>} items Toggle items.
		 * @return {HTMLElement}
		 */
		function togglePair( items ) {
			const wrap = el( 'div', { className: 'wek-builder__pair wek-builder__toggle-pair' } );
			( items || [] ).forEach( function ( item ) {
				if ( ! item || ! item.input ) {
					return;
				}
				wrap.appendChild( toggleRow( item.label, item.input ) );
			} );
			return wrap;
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
					const label = String( field.label || field.id );
					list.push( {
						id: String( field.id ),
						label: label,
						options: Array.isArray( field.options ) ? field.options : [],
						kind: field.type === 'matrix' ? 'matrix' : 'field',
						type: String( field.type || 'text' ),
					} );
					if ( field.type !== 'matrix' ) {
						return;
					}
					const opts = field.type_options && typeof field.type_options === 'object' ? field.type_options : {};
					const rows = Array.isArray( opts.rows ) ? opts.rows : [];
					rows.forEach( function ( row ) {
						if ( ! row || ! row.value ) {
							return;
						}
						const rowLabel = String( row.label || row.value );
						list.push( {
							id: String( field.id ) + '.' + String( row.value ),
							label: label + ' › ' + rowLabel,
							options: [],
							kind: 'matrix_row',
							type: 'matrix_row',
						} );
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

		/**
		 * Rules that need a compare value (equals / not_equals / contains) stay
		 * incomplete until a value is set — do not activate them on the front end.
		 * (Bare equals+"" used to mean “is empty” and hid fields by accident.)
		 *
		 * @param {Object} rule Rule row.
		 * @return {boolean}
		 */
		function isCompleteShowWhenRule( rule ) {
			if ( ! rule || ! rule.field || ! rule.op ) {
				return false;
			}
			if ( opsNoValue[ rule.op ] ) {
				return true;
			}
			return String( rule.value != null ? rule.value : '' ).trim() !== '';
		}

		function commitShowWhen( target, container ) {
			const rules = ( container.rules || [] ).filter( isCompleteShowWhenRule );
			if ( ! rules.length ) {
				target.show_when = null;
			} else {
				target.show_when = {
					relation: container.relation === 'OR' ? 'OR' : 'AND',
					rules: rules.map( function ( r ) {
						return {
							field: r.field,
							op: r.op,
							value: opsNoValue[ r.op ] ? '' : String( r.value || '' ).trim(),
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
			// Scrub incomplete drafts from the saved schema (UI keeps them in container.rules).
			commitShowWhen( target, container );
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
						syncRuleCompleteUi( valueWrap.closest( '.wek-builder__condition-rule' ), rule );
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
					syncRuleCompleteUi( valueWrap.closest( '.wek-builder__condition-rule' ), rule );
				} );
				valueWrap.appendChild( input );
			}

			function syncRuleCompleteUi( card, rule ) {
				if ( ! card ) {
					return;
				}
				const complete = isCompleteShowWhenRule( rule );
				card.classList.toggle( 'is-incomplete', ! complete );
				let hint = card.querySelector( '.wek-builder__condition-incomplete' );
				if ( complete ) {
					if ( hint ) {
						hint.remove();
					}
					return;
				}
				if ( ! hint ) {
					hint = el( 'p', {
						className: 'wek-builder__condition-incomplete description',
						text:
							i18n.conditionIncomplete ||
							'Choose a value to activate this rule. To check for an empty field, use "is empty".',
					} );
					card.appendChild( hint );
				}
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
					const complete = isCompleteShowWhenRule( rule );
					const card = el( 'div', {
						className:
							'wek-builder__condition-rule' + ( complete ? '' : ' is-incomplete' ),
					} );
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

					const metaForRule = fields.find( function ( f ) {
						return f.id === rule.field;
					} ) || ( fields[ 0 ] || null );
					ensureRuleOpForField( rule, metaForRule );

					const opSelect = el( 'select', {
						className: 'wek-builder__condition-op',
						'aria-label': i18n.showOp || 'Operator',
					} );
					function refillOps( selectedOp ) {
						opSelect.innerHTML = '';
						const meta = fields.find( function ( f ) {
							return f.id === fieldSelect.value;
						} );
						opsForConditionField( meta ).forEach( function ( op ) {
							const opt = el( 'option', { value: op.value, text: op.label } );
							if ( selectedOp === op.value ) {
								opt.selected = true;
							}
							opSelect.appendChild( opt );
						} );
						if ( ! opSelect.value && opSelect.options.length ) {
							opSelect.selectedIndex = 0;
						}
					}
					refillOps( rule.op || 'equals' );

					const valueWrap = el( 'div', { className: 'wek-builder__condition-value' } );

					fieldSelect.addEventListener( 'change', function () {
						rule.field = fieldSelect.value;
						const meta = fields.find( function ( f ) {
							return f.id === fieldSelect.value;
						} );
						ensureRuleOpForField( rule, meta );
						refillOps( rule.op );
						rule.op = opSelect.value || defaultOpForConditionField( meta );
						paintValueControl( valueWrap, rule, fieldSelect, opSelect );
						commitShowWhen( target, container );
						syncRuleCompleteUi( card, rule );
					} );
					opSelect.addEventListener( 'change', function () {
						rule.op = opSelect.value || 'equals';
						paintValueControl( valueWrap, rule, fieldSelect, opSelect );
						commitShowWhen( target, container );
						syncRuleCompleteUi( card, rule );
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
					syncRuleCompleteUi( card, rule );
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
						const first = fields[ 0 ] || null;
						container.rules.push( {
							field: first ? first.id : '',
							op: defaultOpForConditionField( first ),
							value: '',
						} );
						commitShowWhen( target, container );
						redrawRules();
					},
				} )
			);

			return panel;
		}

		function textInput( value, onChange, attrs ) {
			const input = el(
				'input',
				Object.assign(
					{ type: 'text', className: 'regular-text', value: value || '' },
					attrs || {}
				)
			);
			input.addEventListener( 'input', function () {
				onChange( input.value );
			} );
			return input;
		}

		function canvasFieldCardSelector( selected ) {
			if ( ! selected ) {
				return null;
			}
			if ( selected.kind === 'nested' ) {
				return (
					'.wek-builder__field[data-s="' +
					selected.sIndex +
					'"][data-f="' +
					selected.fIndex +
					'"][data-n="' +
					selected.nIndex +
					'"]'
				);
			}
			if ( selected.kind === 'field' ) {
				return (
					'.wek-builder__field[data-s="' +
					selected.sIndex +
					'"][data-f="' +
					selected.fIndex +
					'"]:not([data-n])'
				);
			}
			return null;
		}

		function fieldLabelText( field, labelText ) {
			if ( labelText != null && String( labelText ) !== '' ) {
				return String( labelText );
			}
			return ( field && ( field.label || field.id ) ) || i18n.field || 'Field';
		}

		/**
		 * Checkbox/consent: field label is the title; choice_label is the control copy.
		 *
		 * @param {string} type Field type.
		 * @return {boolean}
		 */
		function usesChoiceLabel( type ) {
			return type === 'checkbox' || type === 'consent';
		}

		function fieldChoiceLabel( field ) {
			const opts = field && field.type_options && typeof field.type_options === 'object' ? field.type_options : {};
			if ( Object.prototype.hasOwnProperty.call( opts, 'choice_label' ) ) {
				return String( opts.choice_label || '' );
			}
			return fieldLabelText( field );
		}

		function consentPreviewLabel( field ) {
			const opts = field && field.type_options && typeof field.type_options === 'object' ? field.type_options : {};
			const linkText = String( opts.link_text || '' ).trim() || ( i18n.privacyPolicy || 'Privacy policy' );
			return String( fieldChoiceLabel( field ) ).replace( /\{link\}/g, linkText );
		}

		function buildFieldLabelEl( field, labelText ) {
			const wrap = el( 'span', {
				className:
					'wek-builder__field-label' + ( field && field.required ? ' is-required' : '' ),
			} );
			wrap.appendChild(
				el( 'span', {
					className: 'wek-builder__field-label-text',
					text: fieldLabelText( field, labelText ),
				} )
			);
			if ( field && field.required ) {
				wrap.appendChild(
					el( 'span', {
						className: 'wek-builder__field-required',
						title: i18n.required || 'Required',
						'aria-label': i18n.required || 'Required',
						text: '*',
					} )
				);
			}
			return wrap;
		}

		function updateCanvasFieldLabel( selected, labelText ) {
			const selector = canvasFieldCardSelector( selected );
			if ( ! selector || ! selected || ! selected.field ) {
				return;
			}
			const body = root.querySelector( selector + ' .wek-builder__field-body' );
			if ( ! body ) {
				return;
			}

			if ( usesChoiceLabel( selected.field.type ) ) {
				const choice = body.querySelector( '.wek-builder__choice-label' );
				if ( choice ) {
					choice.textContent =
						selected.field.type === 'consent'
							? consentPreviewLabel( selected.field )
							: fieldChoiceLabel( selected.field );
				}
				const title = body.querySelector( '.wek-builder__field-label-text' );
				if ( title && labelText != null ) {
					title.textContent = fieldLabelText( selected.field, labelText );
				}
				return;
			}

			const oldLabel = body.querySelector( '.wek-builder__field-label' );
			const nextLabel = buildFieldLabelEl( selected.field, labelText );
			if ( oldLabel ) {
				body.replaceChild( nextLabel, oldLabel );
			} else {
				body.insertBefore( nextLabel, body.firstChild );
			}
		}

		function updateSubmitPreview() {
			const btn = root.querySelector( '.wek-builder__submit-preview' );
			if ( ! btn ) {
				return;
			}
			const label =
				submitButton.label || i18n.submitPreview || 'Submit';
			const iconSvg = String( submitButton.icon_svg || '' ).trim();
			const hasIcon = iconSvg.indexOf( '<svg' ) !== -1;
			const width = normalizeSubmitWidth( submitButton.width );
			const selected = selection && selection.type === 'submit';
			btn.className =
				'wek-builder__submit-preview wek-builder__submit-preview--width-' +
				width +
				( selected ? ' is-selected' : '' );
			btn.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
			btn.innerHTML = '';
			if ( hasIcon && submitButton.icon_position !== 'after' ) {
				const iconBefore = el( 'span', {
					className: 'wek-builder__submit-preview-icon',
					'aria-hidden': 'true',
				} );
				iconBefore.innerHTML = iconSvg;
				btn.appendChild( iconBefore );
			}
			btn.appendChild(
				el( 'span', {
					className: 'wek-builder__submit-preview-text',
					text: label,
				} )
			);
			if ( hasIcon && submitButton.icon_position === 'after' ) {
				const iconAfter = el( 'span', {
					className: 'wek-builder__submit-preview-icon',
					'aria-hidden': 'true',
				} );
				iconAfter.innerHTML = iconSvg;
				btn.appendChild( iconAfter );
			}
		}

		function renderSubmitWidthPicker() {
			submitButton.width = normalizeSubmitWidth( submitButton.width );
			const wrap = el( 'div', { className: 'wek-builder__columns', role: 'group' } );
			const spans = {
				auto: 0,
				full: 6,
				two_thirds: 4,
				half: 3,
				third: 2,
			};
			[
				{ value: 'auto', label: i18n.widthAuto || 'Auto' },
				{ value: 'full', label: i18n.widthFull || 'Full' },
				{ value: 'two_thirds', label: i18n.widthTwoThirds || 'Two thirds' },
				{ value: 'half', label: i18n.widthHalf || 'Half' },
				{ value: 'third', label: i18n.widthThird || 'One third' },
			].forEach( function ( item ) {
				const preview = el( 'div', { className: 'wek-builder__column-preview' } );
				if ( item.value === 'auto' ) {
					preview.classList.add( 'is-auto' );
					preview.appendChild( el( 'span', { className: 'is-on is-auto-fit' } ) );
				} else {
					const onCount = spans[ item.value ];
					for ( let i = 0; i < 6; i++ ) {
						preview.appendChild(
							el( 'span', {
								className: i < onCount ? 'is-on' : '',
							} )
						);
					}
				}
				wrap.appendChild(
					el(
						'button',
						{
							type: 'button',
							className:
								'wek-builder__column-btn' +
								( submitButton.width === item.value ? ' is-active' : '' ),
							'aria-pressed': submitButton.width === item.value ? 'true' : 'false',
							title: item.label,
							'data-width': item.value,
							onClick: function () {
								submitButton.width = normalizeSubmitWidth( item.value );
								syncHidden();
								updateSubmitPreview();
								Array.prototype.forEach.call(
									wrap.querySelectorAll( '.wek-builder__column-btn' ),
									function ( btn ) {
										const isActive =
											btn.getAttribute( 'data-width' ) === submitButton.width;
										btn.classList.toggle( 'is-active', isActive );
										btn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
									}
								);
							},
						},
						[ preview, el( 'strong', { text: item.label } ) ]
					)
				);
			} );
			return wrap;
		}

		function updateCanvasFieldPreview( field ) {
			const selected = getSelected();
			if ( ! selected || ( selected.kind !== 'field' && selected.kind !== 'nested' ) ) {
				return;
			}
			const target = selected.field;
			if ( ! target || target !== field ) {
				return;
			}
			const selector = canvasFieldCardSelector( selected );
			if ( ! selector ) {
				return;
			}
			const body = root.querySelector( selector + ' .wek-builder__field-body' );
			if ( ! body ) {
				return;
			}
			const oldPreview = body.querySelector( '.wek-builder__field-preview' );
			const nextPreview = renderFieldPreview( field );
			if ( oldPreview ) {
				body.replaceChild( nextPreview, oldPreview );
			} else {
				body.appendChild( nextPreview );
			}
		}

		function refreshFieldOptionsUi( field ) {
			flushSyncHidden();
			refreshSidebar();
			updateCanvasFieldPreview( field );
		}

		function slugifyOptionKey( label, fallback ) {
			const map = {
				ä: 'ae',
				ö: 'oe',
				ü: 'ue',
				Ä: 'ae',
				Ö: 'oe',
				Ü: 'ue',
				ß: 'ss',
				æ: 'ae',
				ø: 'oe',
				å: 'aa',
				Æ: 'ae',
				Ø: 'oe',
				Å: 'aa',
			};
			let text = String( label || '' );
			text = text.replace( /[äöüÄÖÜßæøåÆØÅ]/g, function ( ch ) {
				return map[ ch ] || ch;
			} );
			// Strip remaining diacritics (é → e, etc.).
			try {
				text = text.normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' );
			} catch ( e ) {
				// ignore
			}
			let key = text
				.toLowerCase()
				.replace( /[^a-z0-9]+/g, '_' )
				.replace( /^_+|_+$/g, '' );
			if ( ! key ) {
				key = String( fallback || 'option' )
					.toLowerCase()
					.replace( /[^a-z0-9]+/g, '_' )
					.replace( /^_+|_+$/g, '' );
			}
			if ( ! key ) {
				key = 'option';
			}
			return key.slice( 0, 64 );
		}

		function uniqueOptionValue( options, base, skipIndex ) {
			let key = base || 'option';
			const used = {};
			( options || [] ).forEach( function ( opt, i ) {
				if ( i === skipIndex ) {
					return;
				}
				const v = String( ( opt && opt.value ) || '' );
				if ( v ) {
					used[ v ] = true;
				}
			} );
			if ( ! used[ key ] ) {
				return key;
			}
			let n = 2;
			while ( used[ key + '_' + n ] ) {
				n += 1;
			}
			return key + '_' + n;
		}

		function optionValueIsAuto( option ) {
			if ( ! option ) {
				return true;
			}
			if ( option.value_locked === true ) {
				return false;
			}
			if ( option.value_locked === false ) {
				return true;
			}
			const label = String( option.label || '' );
			const value = String( option.value || '' );
			if ( ! value ) {
				return true;
			}
			const auto = slugifyOptionKey( label, '' );
			if ( ! auto ) {
				return true;
			}
			if ( value === auto ) {
				return true;
			}
			const escaped = auto.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
			return new RegExp( '^' + escaped + '_\\d+$' ).test( value );
		}

		function syncOptionAutoValue( field, oIndex, labelInput, valueInput ) {
			const opt = field.options[ oIndex ];
			if ( ! opt || ! optionValueIsAuto( opt ) ) {
				return;
			}
			const prev = String( opt.value || '' );
			const base = slugifyOptionKey( opt.label, 'option_' + ( oIndex + 1 ) );
			const next = uniqueOptionValue( field.options, base, oIndex );
			opt.value = next;
			opt.value_locked = false;
			if ( valueInput ) {
				valueInput.value = next;
				valueInput.placeholder =
					next || i18n.optionValueAuto || 'auto from label';
			}
			if ( field.default_value === prev ) {
				field.default_value = next;
			}
		}

		function slugifyMatrixKey( label, fallback ) {
			return slugifyOptionKey( label, fallback ).slice( 0, 40 );
		}

		let optionImageFrame = null;
		let optionImageFrameOnSelect = null;

		/**
		 * Open Media Library for a radio_image option (images only).
		 *
		 * @param {Function} onSelect Callback receiving { id, url }.
		 */
		function openOptionImageMedia( onSelect ) {
			if ( typeof wp === 'undefined' || ! wp.media ) {
				window.alert(
					i18n.mediaLibraryMissing ||
						'Media Library could not be loaded. Please refresh the page.'
				);
				return;
			}
			optionImageFrameOnSelect = onSelect;
			if ( optionImageFrame ) {
				optionImageFrame.open();
				return;
			}
			optionImageFrame = wp.media( {
				title: i18n.selectImageTitle || 'Select option image',
				button: { text: i18n.useThisImage || 'Use this image' },
				library: { type: 'image' },
				multiple: false,
			} );
			optionImageFrame.on( 'select', function () {
				if ( typeof optionImageFrameOnSelect !== 'function' ) {
					return;
				}
				const file = optionImageFrame.state().get( 'selection' ).first().toJSON();
				const url =
					( file.sizes && file.sizes.medium && file.sizes.medium.url ) ||
					( file.sizes && file.sizes.thumbnail && file.sizes.thumbnail.url ) ||
					file.url ||
					'';
				optionImageFrameOnSelect( {
					id: parseInt( file.id, 10 ) || 0,
					url: url,
				} );
			} );
			optionImageFrame.open();
		}

		/**
		 * Compact Media Library control for one radio_image option.
		 *
		 * @param {Object} field Field.
		 * @param {number} oIndex Option index.
		 * @return {HTMLElement}
		 */
		function buildOptionImagePicker( field, oIndex ) {
			const wrap = el( 'div', { className: 'wek-builder__option-image' } );
			const preview = el( 'div', {
				className: 'wek-builder__option-image-preview',
				'aria-hidden': 'true',
			} );
			const actions = el( 'div', { className: 'wek-builder__option-image-actions' } );

			function currentId() {
				const n = parseInt( field.options[ oIndex ].image_id, 10 );
				return isNaN( n ) ? 0 : n;
			}

			function currentUrl() {
				return String( field.options[ oIndex ].image_url || '' );
			}

			function paintPreview() {
				preview.textContent = '';
				const url = currentUrl();
				const id = currentId();
				if ( url ) {
					preview.appendChild(
						el( 'img', {
							src: url,
							alt: '',
						} )
					);
					return;
				}
				if ( id > 0 ) {
					preview.appendChild(
						el( 'span', {
							className: 'wek-builder__option-image-id',
							text: '#' + id,
						} )
					);
					return;
				}
				preview.appendChild(
					el( 'span', {
						className: 'wek-builder__option-image-empty',
						text: i18n.noImage || 'No image',
					} )
				);
			}

			const pickBtn = el( 'button', {
				type: 'button',
				className: 'button button-small',
				text: currentId() || currentUrl() ? i18n.changeImage || 'Change image' : i18n.selectImage || 'Select image',
				onClick: function () {
					openOptionImageMedia( function ( picked ) {
						field.options[ oIndex ].image_id = picked.id;
						field.options[ oIndex ].image_url = picked.url;
						paintPreview();
						pickBtn.textContent =
							picked.id || picked.url
								? i18n.changeImage || 'Change image'
								: i18n.selectImage || 'Select image';
						clearBtn.hidden = ! ( picked.id || picked.url );
						syncHidden();
					} );
				},
			} );
			const clearBtn = el( 'button', {
				type: 'button',
				className: 'button-link wek-builder__option-image-clear',
				text: i18n.clearImage || 'Clear image',
				onClick: function () {
					field.options[ oIndex ].image_id = 0;
					field.options[ oIndex ].image_url = '';
					paintPreview();
					pickBtn.textContent = i18n.selectImage || 'Select image';
					clearBtn.hidden = true;
					syncHidden();
				},
			} );
			clearBtn.hidden = ! ( currentId() || currentUrl() );

			paintPreview();
			actions.appendChild( pickBtn );
			actions.appendChild( clearBtn );
			wrap.appendChild( preview );
			wrap.appendChild( actions );
			return wrap;
		}

		function renderOptionsEditor( field ) {
			if ( ! Array.isArray( field.options ) ) {
				field.options = [];
			}
			if ( field.default_value == null ) {
				field.default_value = '';
			}
			const isRadioImage = field.type === 'radio_image';
			const body = el( 'div', { className: 'wek-builder__options' } );
			body.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.optionsDefaultHint ||
						'Enter labels; keys are filled in automatically (ä→ae, ß→ss, …). Override a key only when you need a custom value. Drag to reorder; mark one option as default, or leave unset for the placeholder.',
				} )
			);

			const list = el( 'div', { className: 'wek-builder__options-list' } );
			const defaultName = 'wek-opt-default-' + ( field.id || 'field' );

			field.options.forEach( function ( option, oIndex ) {
				const row = el( 'div', {
					className: 'wek-builder__option-row' + ( isRadioImage ? ' is-radio-image' : '' ),
					draggable: 'false',
					'data-opt-index': String( oIndex ),
				} );

				const handle = el( 'span', {
					className: 'wek-builder__option-handle dashicons dashicons-menu',
					title: i18n.dragToReorder || 'Drag to reorder',
					'aria-label': i18n.dragToReorder || 'Drag to reorder',
					draggable: 'true',
				} );
				handle.addEventListener( 'dragstart', function ( event ) {
					event.dataTransfer.setData( 'text/plain', String( oIndex ) );
					event.dataTransfer.effectAllowed = 'move';
					row.classList.add( 'is-dragging' );
				} );
				handle.addEventListener( 'dragend', function () {
					row.classList.remove( 'is-dragging' );
					Array.prototype.forEach.call(
						list.querySelectorAll( '.wek-builder__option-row' ),
						function ( r ) {
							r.classList.remove( 'is-drag-over' );
						}
					);
				} );
				row.addEventListener( 'dragover', function ( event ) {
					event.preventDefault();
					event.dataTransfer.dropEffect = 'move';
					row.classList.add( 'is-drag-over' );
				} );
				row.addEventListener( 'dragleave', function () {
					row.classList.remove( 'is-drag-over' );
				} );
				row.addEventListener( 'drop', function ( event ) {
					event.preventDefault();
					row.classList.remove( 'is-drag-over' );
					const from = parseInt( event.dataTransfer.getData( 'text/plain' ), 10 );
					const to = oIndex;
					if ( isNaN( from ) || from === to ) {
						return;
					}
					const moved = field.options.splice( from, 1 )[ 0 ];
					field.options.splice( to, 0, moved );
					refreshFieldOptionsUi( field );
				} );

				if ( optionValueIsAuto( option ) && option.label ) {
					const base = slugifyOptionKey( option.label, 'option_' + ( oIndex + 1 ) );
					option.value = uniqueOptionValue( field.options, base, oIndex );
					option.value_locked = false;
				}

				const labelInput = el( 'input', {
					type: 'text',
					className: 'regular-text wek-builder__option-label',
					placeholder: i18n.optionLabel || 'Label',
					value: option.label || '',
					'aria-label': i18n.optionLabel || 'Label',
				} );
				const valueInput = el( 'input', {
					type: 'text',
					className: 'regular-text wek-builder__option-value',
					placeholder: i18n.optionValueAuto || 'auto from label',
					value: option.value || '',
					'aria-label': i18n.optionValue || 'Key (optional)',
					title:
						i18n.optionValueHint ||
						'Optional. Leave empty or auto-generated from the label. Edit to set a custom key.',
				} );
				labelInput.addEventListener( 'input', function () {
					field.options[ oIndex ].label = labelInput.value;
					syncOptionAutoValue( field, oIndex, labelInput, valueInput );
					syncHidden();
				} );
				valueInput.addEventListener( 'input', function () {
					const raw = valueInput.value;
					const prev = field.options[ oIndex ].value;
					if ( ! String( raw ).trim() ) {
						field.options[ oIndex ].value_locked = false;
						syncOptionAutoValue( field, oIndex, labelInput, valueInput );
						syncHidden();
						return;
					}
					field.options[ oIndex ].value_locked = true;
					field.options[ oIndex ].value = raw;
					if ( field.default_value === prev ) {
						field.default_value = raw;
					}
					syncHidden();
				} );
				valueInput.addEventListener( 'blur', function () {
					const raw = String( valueInput.value || '' ).trim();
					const prev = field.options[ oIndex ].value;
					if ( ! raw ) {
						field.options[ oIndex ].value_locked = false;
						syncOptionAutoValue( field, oIndex, labelInput, valueInput );
						syncHidden();
						return;
					}
					const cleaned = slugifyOptionKey( raw, 'option_' + ( oIndex + 1 ) );
					const next = uniqueOptionValue( field.options, cleaned, oIndex );
					field.options[ oIndex ].value_locked = true;
					field.options[ oIndex ].value = next;
					valueInput.value = next;
					if ( field.default_value === prev ) {
						field.default_value = next;
					}
					syncHidden();
				} );

				const defLabel = el( 'label', {
					className: 'wek-builder__option-default',
					title: i18n.defaultOption || 'Default',
				} );
				const defRadio = el( 'input', {
					type: 'radio',
					name: defaultName,
					value: option.value || '',
					'aria-label': i18n.defaultOption || 'Default',
				} );
				defRadio.checked = String( field.default_value || '' ) === String( option.value || '' ) && String( option.value || '' ) !== '';
				defRadio.addEventListener( 'change', function () {
					if ( defRadio.checked ) {
						field.default_value = String( field.options[ oIndex ].value || '' );
						refreshFieldOptionsUi( field );
					}
				} );
				defLabel.appendChild( defRadio );
				defLabel.appendChild(
					el( 'span', { className: 'screen-reader-text', text: i18n.defaultOption || 'Default' } )
				);

				row.appendChild( handle );
				row.appendChild( labelInput );
				row.appendChild( valueInput );

				if ( isRadioImage ) {
					row.appendChild( buildOptionImagePicker( field, oIndex ) );
				}

				row.appendChild( defLabel );
				row.appendChild(
					el( 'button', {
						type: 'button',
						className: 'button-link-delete wek-builder__option-remove',
						title: i18n.remove || 'Remove',
						'aria-label': i18n.remove || 'Remove',
						onClick: function () {
							const removed = field.options[ oIndex ];
							field.options.splice( oIndex, 1 );
							if ( removed && field.default_value === removed.value ) {
								field.default_value = '';
							}
							refreshFieldOptionsUi( field );
						},
					}, [
						el( 'span', {
							className: 'dashicons dashicons-trash',
							'aria-hidden': 'true',
						} ),
					] )
				);
				list.appendChild( row );
			} );

			body.appendChild( list );

			const clearDefault = el( 'button', {
				type: 'button',
				className: 'button-link wek-builder__clear-default',
				text: i18n.clearDefault || 'Clear default (use placeholder)',
				onClick: function () {
					field.default_value = '';
					refreshFieldOptionsUi( field );
				},
			} );
			if ( ! field.default_value ) {
				clearDefault.disabled = true;
			}
			body.appendChild( clearDefault );

			body.appendChild(
				el( 'button', {
					type: 'button',
					className: 'button',
					text: i18n.addOption || 'Add option',
					onClick: function () {
						const n = field.options.length + 1;
						const label = 'Option ' + n;
						const next = {
							label: label,
							value: uniqueOptionValue(
								field.options,
								slugifyOptionKey( label, 'option_' + n ),
								-1
							),
							value_locked: false,
						};
						if ( isRadioImage ) {
							next.image_id = 0;
							next.image_url = '';
						}
						field.options.push( next );
						refreshFieldOptionsUi( field );
					},
				} )
			);

			const count = Array.isArray( field.options ) ? field.options.length : 0;
			const summary =
				( i18n.options || 'Options' ) + ( count ? ' (' + count + ')' : '' );
			return collapsiblePanel( summary, body, 'wek-builder__options-fold', true );
		}

		function ensureTypeOptions( field ) {
			if ( ! field.type_options || typeof field.type_options !== 'object' ) {
				field.type_options = {};
			}
			return field.type_options;
		}

		function ensureMessages( field ) {
			if ( ! field.messages || typeof field.messages !== 'object' ) {
				field.messages = { required: '', invalid: '' };
			}
			if ( field.messages.required == null ) {
				field.messages.required = '';
			}
			if ( field.messages.invalid == null ) {
				field.messages.invalid = '';
			}
			return field.messages;
		}

		/**
		 * Appearance tab controls shared by top-level and nested fields.
		 *
		 * @param {HTMLElement} panel Target panel.
		 * @param {Object}      field Field config.
		 * @param {{isNested?:boolean}} options Options.
		 * @return {void}
		 */
		function appendFieldAppearanceControls( panel, field, options ) {
			const isNested = !!( options && options.isNested );
			const canToggleLabel = [ 'html', 'hidden' ].indexOf( field.type ) === -1;

			if ( canToggleLabel ) {
				if ( typeof field.show_label === 'undefined' ) {
					field.show_label = true;
				}
				const showLabel = el( 'input', { type: 'checkbox' } );
				showLabel.checked = field.show_label !== false;
				showLabel.addEventListener( 'change', function () {
					field.show_label = !! showLabel.checked;
					syncHidden();
					refreshCanvas();
				} );
				panel.appendChild( toggleRow( i18n.showLabel || 'Show label', showLabel ) );
			}

			if ( field.type === 'matrix' && ! isNested ) {
				const opts = ensureMatrixOptions( field );
				const alignSelect = el( 'select' );
				alignSelect.className = 'wek-builder__select';
				[
					{ value: 'left', label: i18n.alignLeft || 'Left' },
					{ value: 'center', label: i18n.alignCenter || 'Center' },
					{ value: 'right', label: i18n.alignRight || 'Right' },
				].forEach( function ( choice ) {
					const opt = el( 'option', { value: choice.value, text: choice.label } );
					if ( ( opts.row_label_align || 'left' ) === choice.value ) {
						opt.selected = true;
					}
					alignSelect.appendChild( opt );
				} );
				alignSelect.addEventListener( 'change', function () {
					opts.row_label_align = alignSelect.value || 'left';
					syncHidden();
				} );
				panel.appendChild(
					fieldRow( i18n.matrixRowLabelAlign || 'Row label alignment', alignSelect )
				);
			}

			if ( ! isNested ) {
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
			}

			if ( typeof field.css_class !== 'string' ) {
				field.css_class = '';
			}
			panel.appendChild(
				fieldRow(
					i18n.cssClass || 'CSS class',
					textInput( field.css_class || '', function ( v ) {
						field.css_class = String( v || '' )
							.trim()
							.replace( /\s+/g, ' ' );
						syncHidden();
					} )
				)
			);
			panel.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.cssClassHint ||
						'Optional extra class on the field wrapper (space-separated).',
				} )
			);
		}

		/**
		 * Collapsible sidebar panel (same pattern as validation messages).
		 *
		 * @param {string}      summaryText Summary label.
		 * @param {HTMLElement} body        Panel body.
		 * @param {string}      className   Extra class.
		 * @param {boolean}     startOpen   Whether open by default.
		 * @return {HTMLElement}
		 */
		function collapsiblePanel( summaryText, body, className, startOpen ) {
			const details = el( 'details', {
				className: 'wek-builder__fold' + ( className ? ' ' + className : '' ),
			} );
			if ( startOpen ) {
				details.open = true;
			}
			details.appendChild( el( 'summary', { text: summaryText } ) );
			body.className =
				( body.className ? body.className + ' ' : '' ) + 'wek-builder__fold-body';
			details.appendChild( body );
			return details;
		}

		function renderValidationMessagesEditor( field ) {
			const msgs = ensureMessages( field );
			const body = el( 'div', { className: 'wek-builder__validation-body' } );
			const isRequired = !! field.required;

			if ( isRequired ) {
				body.appendChild(
					fieldRow(
						i18n.msgRequired || 'Required message',
						textInput( msgs.required || '', function ( v ) {
							ensureMessages( field ).required = v;
							syncHidden();
						} )
					)
				);
			}

			body.appendChild(
				fieldRow(
					i18n.msgInvalid || 'Invalid message',
					textInput( msgs.invalid || '', function ( v ) {
						ensureMessages( field ).invalid = v;
						syncHidden();
					} )
				)
			);
			body.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.msgHint ||
						'Leave empty to use Formkit Settings defaults. Use {label} for the field label.',
				} )
			);

			const details = el( 'details', { className: 'wek-builder__validation' } );
			const hasCustom = !!(
				( isRequired && String( msgs.required || '' ).trim() ) ||
				String( msgs.invalid || '' ).trim()
			);
			if ( hasCustom ) {
				details.open = true;
			}
			details.appendChild( el( 'summary', { text: i18n.validationMessages || 'Validation messages' } ) );
			details.appendChild( body );
			return details;
		}

		const PREFILL_TYPES = [
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
			'consent',
			'date',
			'time',
			'datetime',
			'hidden',
		];

		function prefillParamFor( field ) {
			const custom = String( field.prefill_param || '' )
				.toLowerCase()
				.replace( /[^a-z0-9_-]/g, '' );
			if ( custom ) {
				return custom;
			}
			return String( field.id || '' )
				.toLowerCase()
				.replace( /[^a-z0-9_-]/g, '' );
		}

		function prefillBaseStorageKey() {
			const formId = ( window.weFormkitAdmin && window.weFormkitAdmin.formId ) || 0;
			return 'wek_prefill_base_' + String( formId );
		}

		function readPrefillBaseUrl() {
			try {
				return String( window.sessionStorage.getItem( prefillBaseStorageKey() ) || '' );
			} catch ( e ) {
				return '';
			}
		}

		function writePrefillBaseUrl( url ) {
			try {
				window.sessionStorage.setItem( prefillBaseStorageKey(), String( url || '' ) );
			} catch ( e ) {
				// ignore
			}
		}

		function buildPrefillUrl( baseUrl, param, value ) {
			const base = String( baseUrl || '' ).trim();
			const formId = parseInt( ( window.weFormkitAdmin && window.weFormkitAdmin.formId ) || 0, 10 ) || 0;
			const hash = formId > 0 ? 'we-formkit-' + String( formId ) : '';
			const q = encodeURIComponent( param ) + '=' + encodeURIComponent( value );
			if ( ! base ) {
				return '?' + q + ( hash ? '#' + hash : '' );
			}
			try {
				const url = new URL( base, window.location.origin );
				url.searchParams.set( param, value );
				if ( hash ) {
					url.hash = hash;
				}
				return url.toString();
			} catch ( e ) {
				const join = base.indexOf( '?' ) === -1 ? '?' : '&';
				const withoutHash = base.split( '#' )[ 0 ];
				return withoutHash + join + q + ( hash ? '#' + hash : '' );
			}
		}

		function copyText( text, button ) {
			const done = function () {
				const prev = button.textContent;
				button.textContent = i18n.prefillCopied || 'Copied';
				window.setTimeout( function () {
					button.textContent = prev;
				}, 1200 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done ).catch( function () {
					window.prompt( i18n.prefillCopy || 'Copy', text );
				} );
			} else {
				window.prompt( i18n.prefillCopy || 'Copy', text );
			}
		}

		function renderPrefillEditor( field ) {
			if ( PREFILL_TYPES.indexOf( field.type ) === -1 ) {
				return el( 'div' );
			}

			if ( typeof field.allow_url_prefill === 'undefined' ) {
				field.allow_url_prefill = true;
			}
			if ( typeof field.prefill_param !== 'string' ) {
				field.prefill_param = '';
			}

			const body = el( 'div', { className: 'wek-builder__prefill-body' } );

			const allow = el( 'input', { type: 'checkbox' } );
			allow.checked = field.allow_url_prefill !== false;
			allow.addEventListener( 'change', function () {
				field.allow_url_prefill = !! allow.checked;
				syncHidden();
				refreshSidebar();
			} );
			body.appendChild( toggleRow( i18n.prefillAllow || 'Allow URL / embed prefill', allow ) );

			if ( field.allow_url_prefill === false ) {
				return collapsiblePanel( i18n.prefillTitle || 'URL prefill', body, 'wek-builder__prefill', false );
			}

			body.appendChild(
				fieldRow(
					i18n.prefillParam || 'Custom query parameter',
					textInput( field.prefill_param || '', function ( v ) {
						field.prefill_param = String( v || '' )
							.toLowerCase()
							.replace( /[^a-z0-9_-]/g, '' );
						syncHidden();
					} )
				)
			);
			body.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.prefillParamHint ||
						'Optional. Leave empty to use the Field ID. Example: anliegen',
				} )
			);

			const baseInput = el( 'input', {
				type: 'url',
				className: 'regular-text',
				value: readPrefillBaseUrl(),
				placeholder: 'https://example.com/contact/',
			} );
			baseInput.addEventListener( 'change', function () {
				writePrefillBaseUrl( baseInput.value );
			} );
			baseInput.addEventListener( 'blur', function () {
				writePrefillBaseUrl( baseInput.value );
			} );
			body.appendChild( fieldRow( i18n.prefillBaseUrl || 'Page URL (for copy links)', baseInput ) );
			body.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.prefillBaseUrlHint ||
						'Paste the public page where this form is embedded. Copy links include #we-formkit-{formId} so the page scrolls to the form.',
				} )
			);

			const param = function () {
				return prefillParamFor( field ) || 'field';
			};

			const links = el( 'div', { className: 'wek-builder__prefill-links' } );
			const choiceTypes = [ 'select', 'radio', 'radio_image', 'checkboxes' ];
			if ( choiceTypes.indexOf( field.type ) !== -1 && Array.isArray( field.options ) ) {
				field.options.forEach( function ( option ) {
					const value = String( option.value || '' ).trim();
					if ( ! value ) {
						return;
					}
					const label = String( option.label || value );
					const row = el( 'div', { className: 'wek-builder__prefill-row' } );
					row.appendChild(
						el( 'span', {
							className: 'wek-builder__prefill-label',
							text: label,
						} )
					);
					const btn = el( 'button', {
						type: 'button',
						className: 'button button-small',
						text: i18n.prefillCopy || 'Copy',
					} );
					btn.addEventListener( 'click', function () {
						const url = buildPrefillUrl( baseInput.value || readPrefillBaseUrl(), param(), value );
						copyText( url, btn );
					} );
					row.appendChild( btn );
					links.appendChild( row );
				} );
			} else if ( field.type === 'checkbox' || field.type === 'consent' ) {
				const row = el( 'div', { className: 'wek-builder__prefill-row' } );
				row.appendChild(
					el( 'span', {
						className: 'wek-builder__prefill-label',
						text: ( i18n.prefillExample || 'Example' ) + ': 1',
					} )
				);
				const btn = el( 'button', {
					type: 'button',
					className: 'button button-small',
					text: i18n.prefillCopy || 'Copy',
				} );
				btn.addEventListener( 'click', function () {
					copyText( buildPrefillUrl( baseInput.value || readPrefillBaseUrl(), param(), '1' ), btn );
				} );
				row.appendChild( btn );
				links.appendChild( row );
			} else {
				const sample =
					field.type === 'email'
						? 'name@example.com'
						: field.type === 'number'
							? '1'
							: 'value';
				const row = el( 'div', { className: 'wek-builder__prefill-row' } );
				row.appendChild(
					el( 'span', {
						className: 'wek-builder__prefill-label',
						text: ( i18n.prefillExample || 'Example' ) + ': ' + sample,
					} )
				);
				const btn = el( 'button', {
					type: 'button',
					className: 'button button-small',
					text: i18n.prefillCopy || 'Copy',
				} );
				btn.addEventListener( 'click', function () {
					copyText( buildPrefillUrl( baseInput.value || readPrefillBaseUrl(), param(), sample ), btn );
				} );
				row.appendChild( btn );
				links.appendChild( row );
			}

			if ( links.childNodes.length ) {
				body.appendChild(
					el( 'p', {
						className: 'wek-builder__prefill-links-title',
						text: i18n.prefillLinks || 'Prefill links',
					} )
				);
				body.appendChild( links );
			}

			body.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.prefillMultiHint ||
						'Several fields: append more parameters, e.g. ?anliegen=angebot&email=name@example.com',
				} )
			);
			if ( field.type === 'checkboxes' ) {
				body.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.prefillCheckboxesHint ||
							'Checkboxes: comma-separated values, e.g. ?topics=a,b',
					} )
				);
			}

			return collapsiblePanel( i18n.prefillTitle || 'URL prefill', body, 'wek-builder__prefill', true );
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
			const select = document.createElement( 'select' );
			choices.forEach( function ( choice ) {
				const opt = document.createElement( 'option' );
				opt.value = String( choice.value != null ? choice.value : '' );
				opt.textContent = String(
					choice.label != null && choice.label !== '' ? choice.label : choice.value
				);
				if ( String( value || '' ) === opt.value ) {
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

		function parseMimeList( raw ) {
			return String( raw || '' )
				.split( ',' )
				.map( function ( part ) {
					return part.trim();
				} )
				.filter( Boolean );
		}

		function mimeLabelFor( value, choices ) {
			for ( let i = 0; i < choices.length; i++ ) {
				if ( choices[ i ].value === value ) {
					return choices[ i ].label || value;
				}
			}
			return value;
		}

		function renderMimeMultiSelect( opts ) {
			const choices =
				( window.weFormkitAdmin && Array.isArray( window.weFormkitAdmin.mimeChoices )
					? window.weFormkitAdmin.mimeChoices
					: [] ) || [];
			const wrap = el( 'div', { className: 'wek-builder__mime-select' } );
			const chips = el( 'div', {
				className: 'wek-builder__mime-chips',
				role: 'list',
			} );
			const footer = el( 'div', { className: 'wek-builder__mime-footer' } );
			const picker = el( 'select', {
				className: 'wek-builder__mime-picker',
				'aria-label': i18n.addMimeType || 'Add type…',
			} );
			const clearBtn = el( 'button', {
				type: 'button',
				className: 'wek-builder__mime-clear',
				text: i18n.clearMimeTypes || 'Clear all',
			} );

			function getSelected() {
				return parseMimeList( opts.allowed_mime_types );
			}

			function setSelected( list ) {
				opts.allowed_mime_types = list.join( ', ' );
				syncHidden();
				refresh();
			}

			function refreshPicker( selected ) {
				picker.innerHTML = '';
				picker.appendChild(
					el( 'option', {
						value: '',
						text: i18n.addMimeType || 'Add type…',
					} )
				);
				choices.forEach( function ( choice ) {
					if ( ! choice || ! choice.value || selected.indexOf( choice.value ) !== -1 ) {
						return;
					}
					picker.appendChild(
						el( 'option', {
							value: choice.value,
							text: choice.label || choice.value,
						} )
					);
				} );
				picker.disabled = picker.options.length <= 1;
			}

			function refresh() {
				const selected = getSelected();
				chips.innerHTML = '';
				if ( ! selected.length ) {
					chips.appendChild(
						el( 'span', {
							className: 'wek-builder__mime-empty',
							text: i18n.allowedMimeHint || 'Leave empty to use the WordPress default whitelist.',
						} )
					);
				} else {
					selected.forEach( function ( mime ) {
						const remove = el( 'button', {
							type: 'button',
							className: 'wek-builder__mime-chip-remove',
							title: i18n.removeMimeType || 'Remove',
							'aria-label':
								( i18n.removeMimeType || 'Remove' ) +
								': ' +
								mimeLabelFor( mime, choices ),
							text: '×',
						} );
						remove.addEventListener( 'click', function ( event ) {
							event.preventDefault();
							setSelected(
								getSelected().filter( function ( item ) {
									return item !== mime;
								} )
							);
						} );
						chips.appendChild(
							el(
								'span',
								{
									className: 'wek-builder__mime-chip',
									role: 'listitem',
									title: mime,
								},
								[
									el( 'span', {
										className: 'wek-builder__mime-chip-label',
										text: mimeLabelFor( mime, choices ),
									} ),
									remove,
								]
							)
						);
					} );
				}
				refreshPicker( selected );
				clearBtn.hidden = selected.length === 0;
			}

			picker.addEventListener( 'change', function () {
				const value = picker.value;
				if ( ! value ) {
					return;
				}
				const next = getSelected();
				if ( next.indexOf( value ) === -1 ) {
					next.push( value );
					setSelected( next );
				}
				picker.value = '';
			} );

			clearBtn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				setSelected( [] );
			} );

			footer.appendChild( picker );
			footer.appendChild( clearBtn );
			wrap.appendChild( chips );
			wrap.appendChild( footer );
			refresh();
			return wrap;
		}

		function getUploadStorageChoices() {
			const uploadMeta = fieldTypes.find( function ( item ) {
				return item.type === 'upload';
			} );
			const schema =
				uploadMeta &&
				uploadMeta.adminSchema &&
				uploadMeta.adminSchema.storage_mode
					? uploadMeta.adminSchema.storage_mode
					: null;
			if ( schema && Array.isArray( schema.options ) && schema.options.length ) {
				return schema.options.map( function ( item ) {
					return {
						value: item.value,
						label: item.label || item.value,
					};
				} );
			}
			return [
				{
					value: 'uploads_only',
					label: i18n.storagePrivate || 'Private Formkit folder (recommended)',
				},
				{
					value: 'media_library',
					label:
						i18n.storageMedia ||
						'Media Library (not recommended for personal data)',
				},
			];
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
				opts.allowed_mime_types = 'image/jpeg, image/png, application/pdf';
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
				( function () {
					const row = el( 'div', {
						className: 'wek-builder__row wek-builder__row--stack',
					} );
					row.appendChild(
						el( 'span', {
							className: 'wek-builder__row-heading',
							text: i18n.allowedMime || 'Allowed MIME types',
						} )
					);
					row.appendChild( renderMimeMultiSelect( opts ) );
					return row;
				} )(),
				fieldRow(
					i18n.storageMode || 'Storage mode',
					selectInput( opts.storage_mode, getUploadStorageChoices(), function ( v ) {
						opts.storage_mode = v;
						syncHidden();
					} )
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

		function ensureMatrixOptions( field ) {
			const opts = ensureTypeOptions( field );
			if ( typeof opts.row_select === 'undefined' ) {
				opts.row_select = true;
			}
			if ( typeof opts.row_label_align === 'undefined' ) {
				opts.row_label_align = 'left';
			}
			if ( typeof opts.allow_custom_rows === 'undefined' ) {
				opts.allow_custom_rows = false;
			}
			if ( typeof opts.max_custom_rows === 'undefined' || opts.max_custom_rows === '' ) {
				opts.max_custom_rows = 2;
			}
			let maxCustom = parseInt( opts.max_custom_rows, 10 );
			if ( ! Number.isFinite( maxCustom ) || maxCustom < 1 ) {
				maxCustom = 2;
			}
			opts.max_custom_rows = Math.min( 20, maxCustom );
			if ( typeof opts.min_answered_rows === 'undefined' || opts.min_answered_rows === '' ) {
				opts.min_answered_rows = field.required ? 1 : 0;
			}
			let minAns = parseInt( opts.min_answered_rows, 10 );
			if ( ! Number.isFinite( minAns ) || minAns < 0 ) {
				minAns = 0;
			}
			opts.min_answered_rows = minAns;
			if ( ! Array.isArray( opts.rows ) ) {
				opts.rows = [];
			}
			if ( ! Array.isArray( opts.columns ) ) {
				opts.columns = [];
			}
			opts.rows.forEach( function ( row ) {
				if ( row && typeof row.required === 'undefined' ) {
					row.required = false;
				}
			} );
			opts.columns.forEach( function ( col ) {
				if ( col && typeof col.required === 'undefined' ) {
					col.required = false;
				}
			} );
			return opts;
		}

		function syncMatrixRequiredFlag( field ) {
			const opts = ensureMatrixOptions( field );
			const min = parseInt( opts.min_answered_rows, 10 ) || 0;
			const rowReq = ( opts.rows || [] ).some( function ( row ) {
				return !!( row && row.required );
			} );
			const colReq = ( opts.columns || [] ).some( function ( col ) {
				return !!( col && col.required );
			} );
			field.required = min > 0 || rowReq || colReq;
		}

		function renderMatrixPreview( field ) {
			const opts = ensureMatrixOptions( field );
			const rows = opts.rows.slice( 0, 3 );
			const wrap = el( 'div', {
				className: 'wek-builder__field-preview wek-builder__field-preview--matrix',
				'aria-hidden': 'true',
			} );
			if ( ! rows.length ) {
				wrap.textContent = opts.allow_custom_rows
					? i18n.matrixPreviewSelfFill || 'Matrix — visitors add their own rows'
					: i18n.matrixPreviewEmpty || 'Matrix — add rows and columns';
				return wrap;
			}
			rows.forEach( function ( row ) {
				wrap.appendChild(
					el( 'div', {
						className: 'wek-builder__matrix-preview-row',
						text: ( row && row.label ) || ( row && row.value ) || '—',
					} )
				);
			} );
			if ( opts.rows.length > 3 ) {
				wrap.appendChild(
					el( 'div', {
						className: 'wek-builder__matrix-preview-more',
						text: '+' + ( opts.rows.length - 3 ),
					} )
				);
			}
			if ( opts.allow_custom_rows ) {
				wrap.appendChild(
					el( 'div', {
						className: 'wek-builder__matrix-preview-custom',
						text: i18n.matrixPreviewCustom || '+ Other',
					} )
				);
			}
			return wrap;
		}

		function renderMatrixListEditor( title, items, onChange, kind, onRequiredChange ) {
			const body = el( 'div', { className: 'wek-builder__matrix-list' } );
			body.appendChild(
				el( 'p', {
					className: 'description',
					text: i18n.matrixReorderHint || 'Drag the handle or use the arrows to reorder.',
				} )
			);
			const list = el( 'div', { className: 'wek-builder__matrix-items' } );

			function refresh() {
				refreshSidebar();
			}

			function moveItem( from, to ) {
				if ( to < 0 || to >= items.length || from === to ) {
					return;
				}
				const moved = items.splice( from, 1 )[ 0 ];
				items.splice( to, 0, moved );
				flushSyncHidden();
				refresh();
			}

			function stopSummaryToggle( event ) {
				event.preventDefault();
				event.stopPropagation();
			}

			items.forEach( function ( item, index ) {
				const row = el( 'details', {
					className: 'wek-builder__matrix-item',
					'data-matrix-index': String( index ),
				} );
				row.open = true;

				const summary = el( 'summary', { className: 'wek-builder__matrix-item-summary' } );
				const toolbar = el( 'div', { className: 'wek-builder__matrix-item-toolbar' } );
				const handle = el( 'span', {
					className: 'wek-builder__matrix-item-handle dashicons dashicons-menu',
					title: i18n.dragToReorder || 'Drag to reorder',
					'aria-label': i18n.dragToReorder || 'Drag to reorder',
					draggable: 'true',
				} );
				handle.addEventListener( 'click', stopSummaryToggle );
				handle.addEventListener( 'dragstart', function ( event ) {
					event.dataTransfer.setData( 'text/plain', String( index ) );
					event.dataTransfer.effectAllowed = 'move';
					row.classList.add( 'is-dragging' );
				} );
				handle.addEventListener( 'dragend', function () {
					row.classList.remove( 'is-dragging' );
					Array.prototype.forEach.call(
						list.querySelectorAll( '.wek-builder__matrix-item' ),
						function ( r ) {
							r.classList.remove( 'is-drag-over' );
						}
					);
				} );
				row.addEventListener( 'dragover', function ( event ) {
					event.preventDefault();
					event.dataTransfer.dropEffect = 'move';
					row.classList.add( 'is-drag-over' );
				} );
				row.addEventListener( 'dragleave', function () {
					row.classList.remove( 'is-drag-over' );
				} );
				row.addEventListener( 'drop', function ( event ) {
					event.preventDefault();
					row.classList.remove( 'is-drag-over' );
					const from = parseInt( event.dataTransfer.getData( 'text/plain' ), 10 );
					if ( isNaN( from ) || from === index ) {
						return;
					}
					moveItem( from, index );
				} );

				const upBtn = el( 'button', {
					type: 'button',
					className: 'button-link wek-builder__matrix-move',
					title: i18n.moveUp || 'Move up',
					'aria-label': i18n.moveUp || 'Move up',
					text: '↑',
					disabled: index === 0,
				} );
				upBtn.addEventListener( 'click', function ( event ) {
					stopSummaryToggle( event );
					moveItem( index, index - 1 );
				} );
				const downBtn = el( 'button', {
					type: 'button',
					className: 'button-link wek-builder__matrix-move',
					title: i18n.moveDown || 'Move down',
					'aria-label': i18n.moveDown || 'Move down',
					text: '↓',
					disabled: index >= items.length - 1,
				} );
				downBtn.addEventListener( 'click', function ( event ) {
					stopSummaryToggle( event );
					moveItem( index, index + 1 );
				} );

				toolbar.appendChild( handle );
				toolbar.appendChild( upBtn );
				toolbar.appendChild( downBtn );

				const remove = el( 'button', {
					type: 'button',
					className: 'button-link-delete wek-builder__matrix-remove',
					title: i18n.remove || 'Remove',
					'aria-label': i18n.remove || 'Remove',
					text: '×',
				} );
				remove.addEventListener( 'click', function ( event ) {
					stopSummaryToggle( event );
					items.splice( index, 1 );
					if ( typeof onRequiredChange === 'function' ) {
						onRequiredChange();
					}
					flushSyncHidden();
					refresh();
				} );
				toolbar.appendChild( remove );
				summary.appendChild( toolbar );

				let typeLabel = '';
				if ( kind === 'column' && item.type ) {
					const typeMap = {
						radio: i18n.matrixColRadio || 'Radio',
						checkbox: i18n.matrixColCheckbox || 'Checkbox',
						text: i18n.matrixColText || 'Text',
						number: i18n.matrixColNumber || 'Number',
					};
					typeLabel = ' · ' + ( typeMap[ item.type ] || item.type );
				}
				const itemTitle =
					( item.label || item.value || item.id || ( kind === 'row' ? 'Row' : 'Column' ) ) +
					typeLabel;
				summary.appendChild(
					el( 'span', { className: 'wek-builder__matrix-item-title', text: itemTitle } )
				);
				row.appendChild( summary );

				const itemBody = el( 'div', { className: 'wek-builder__matrix-item-body' } );

				const labelInput = el( 'input', {
					type: 'text',
					className: 'regular-text',
					value: item.label || '',
					placeholder: i18n.optionLabel || 'Label',
				} );
				labelInput.addEventListener( 'input', function () {
					item.label = labelInput.value;
					if ( kind === 'row' && ! item._valueLocked ) {
						item.value = slugifyMatrixKey( item.label, 'row_' + ( index + 1 ) );
					}
					if ( kind === 'column' && ! item._idLocked ) {
						item.id = slugifyMatrixKey( item.label, 'col_' + ( index + 1 ) );
					}
					syncHidden();
				} );
				itemBody.appendChild( labelInput );

				if ( kind === 'row' ) {
					const rowReq = el( 'input', { type: 'checkbox' } );
					rowReq.checked = !! item.required;
					rowReq.addEventListener( 'change', function () {
						item.required = !! rowReq.checked;
						if ( typeof onRequiredChange === 'function' ) {
							onRequiredChange();
						}
						syncHidden();
					} );
					itemBody.appendChild(
						toggleRow( i18n.required || 'Required', rowReq )
					);
				}

				if ( kind === 'column' ) {
					const typeSelect = el( 'select' );
					typeSelect.className = 'wek-builder__select';
					const typeChoices = [
						{ value: 'radio', label: i18n.matrixColRadio || 'Radio' },
						{ value: 'checkbox', label: i18n.matrixColCheckbox || 'Checkbox' },
						{ value: 'text', label: i18n.matrixColText || 'Text' },
						{ value: 'number', label: i18n.matrixColNumber || 'Number' },
					];
					typeChoices.forEach( function ( t ) {
						const opt = el( 'option', { value: t.value, text: t.label } );
						if ( item.type === t.value ) {
							opt.selected = true;
						}
						typeSelect.appendChild( opt );
					} );
					typeSelect.addEventListener( 'change', function () {
						item.type = typeSelect.value;
						if ( item.type === 'radio' ) {
							if ( ! Array.isArray( item.options ) || ! item.options.length ) {
								item.options = [
									{ value: 'a', label: i18n.matrixOptA || 'Option A' },
									{ value: 'b', label: i18n.matrixOptB || 'Option B' },
								];
							}
						} else {
							item.options = [];
						}
						syncHidden();
						refresh();
					} );
					itemBody.appendChild( typeSelect );

					const colReq = el( 'input', { type: 'checkbox' } );
					colReq.checked = !! item.required;
					colReq.title =
						i18n.matrixColRequiredHint ||
						'Only when the row is selected or filled.';
					colReq.addEventListener( 'change', function () {
						item.required = !! colReq.checked;
						if ( typeof onRequiredChange === 'function' ) {
							onRequiredChange();
						}
						syncHidden();
					} );
					itemBody.appendChild(
						toggleRow( i18n.required || 'Required', colReq )
					);
				}

				if ( kind === 'column' && item.type === 'radio' ) {
					if ( ! Array.isArray( item.options ) ) {
						item.options = [];
					}
					const optWrap = el( 'div', { className: 'wek-builder__matrix-col-options-wrap' } );
					optWrap.appendChild(
						el( 'p', {
							className: 'wek-builder__matrix-col-options-label',
							text:
								( i18n.matrixRadioOptions || 'Radio options (column headers)' ) +
								' (' +
								item.options.length +
								')',
						} )
					);
					const optBody = el( 'div', { className: 'wek-builder__matrix-col-options' } );
					item.options.forEach( function ( opt, oIndex ) {
						const optRow = el( 'div', { className: 'wek-builder__matrix-opt-row' } );
						const optLabel = el( 'input', {
							type: 'text',
							className: 'regular-text',
							value: opt.label || '',
							placeholder: i18n.optionLabel || 'Label',
						} );
						optLabel.addEventListener( 'input', function () {
							opt.label = optLabel.value;
							if ( ! opt._valueLocked ) {
								opt.value = slugifyMatrixKey( opt.label, 'opt_' + ( oIndex + 1 ) );
							}
							syncHidden();
						} );
						const optUp = el( 'button', {
							type: 'button',
							className: 'button-link wek-builder__matrix-move',
							title: i18n.moveUp || 'Move up',
							text: '↑',
							disabled: oIndex === 0,
						} );
						optUp.addEventListener( 'click', function ( event ) {
							event.preventDefault();
							event.stopPropagation();
							if ( oIndex <= 0 ) {
								return;
							}
							const moved = item.options.splice( oIndex, 1 )[ 0 ];
							item.options.splice( oIndex - 1, 0, moved );
							flushSyncHidden();
							refresh();
						} );
						const optDown = el( 'button', {
							type: 'button',
							className: 'button-link wek-builder__matrix-move',
							title: i18n.moveDown || 'Move down',
							text: '↓',
							disabled: oIndex >= item.options.length - 1,
						} );
						optDown.addEventListener( 'click', function ( event ) {
							event.preventDefault();
							event.stopPropagation();
							if ( oIndex >= item.options.length - 1 ) {
								return;
							}
							const moved = item.options.splice( oIndex, 1 )[ 0 ];
							item.options.splice( oIndex + 1, 0, moved );
							flushSyncHidden();
							refresh();
						} );
						const optRemove = el( 'button', {
							type: 'button',
							className: 'button-link-delete wek-builder__matrix-opt-remove',
							text: '×',
							title: i18n.remove || 'Remove',
							'aria-label': i18n.remove || 'Remove',
						} );
						optRemove.addEventListener( 'click', function ( event ) {
							event.preventDefault();
							event.stopPropagation();
							if ( item.options.length <= 1 ) {
								return;
							}
							item.options.splice( oIndex, 1 );
							flushSyncHidden();
							refresh();
						} );
						if ( item.options.length <= 1 ) {
							optRemove.disabled = true;
						}
						optRow.appendChild( optLabel );
						optRow.appendChild( optUp );
						optRow.appendChild( optDown );
						optRow.appendChild( optRemove );
						optBody.appendChild( optRow );
					} );
					const addOpt = el( 'button', {
						type: 'button',
						className: 'button button-small',
						text: i18n.addOption || 'Add option',
					} );
					addOpt.addEventListener( 'click', function ( event ) {
						event.preventDefault();
						event.stopPropagation();
						const n = item.options.length + 1;
						item.options.push( {
							value: 'opt_' + n,
							label: ( i18n.optionPreview || 'Option' ) + ' ' + n,
						} );
						flushSyncHidden();
						refresh();
					} );
					optBody.appendChild( addOpt );
					optWrap.appendChild( optBody );
					itemBody.appendChild( optWrap );
				}

				row.appendChild( itemBody );
				list.appendChild( row );
			} );

			body.appendChild( list );
			const addBtn = el( 'button', {
				type: 'button',
				className: 'button',
				text: kind === 'row' ? ( i18n.matrixAddRow || 'Add row' ) : ( i18n.matrixAddColumn || 'Add column' ),
			} );
			addBtn.addEventListener( 'click', function () {
				if ( kind === 'row' ) {
					const n = items.length + 1;
					items.push( {
						value: 'row_' + n,
						label: formatMatrixIndexLabel(
							i18n.matrixRowSample || i18n.matrixRowSample1 || 'Row %d',
							n
						),
						required: false,
					} );
				} else {
					const n = items.length + 1;
					items.push( {
						id: 'col_' + n,
						type: 'radio',
						label: formatMatrixIndexLabel( i18n.matrixColumnSample || 'Column %d', n ),
						required: false,
						options: [
							{ value: 'a', label: i18n.matrixOptA || 'Option A' },
							{ value: 'b', label: i18n.matrixOptB || 'Option B' },
						],
					} );
				}
				syncHidden();
				flushSyncHidden();
				refresh();
			} );
			body.appendChild( addBtn );

			const count = items.length;
			return collapsiblePanel(
				title + ( count ? ' (' + count + ')' : '' ),
				body,
				'wek-builder__matrix-list-fold',
				true
			);
		}

		function renderMatrixEditor( field ) {
			const opts = ensureMatrixOptions( field );
			const wrap = el( 'div', { className: 'wek-builder__matrix-editor' } );

			function onMatrixRequiredChange() {
				syncMatrixRequiredFlag( field );
				updateCanvasFieldLabel( selected, field.label );
			}

			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.matrixHint ||
						'Preset rows and columns define the grid. Optional visitor-added rows and validation sit below.',
				} )
			);

			const rowSelect = el( 'input', { type: 'checkbox' } );
			rowSelect.checked = !! opts.row_select;
			rowSelect.addEventListener( 'change', function () {
				opts.row_select = rowSelect.checked;
				syncHidden();
			} );
			wrap.appendChild(
				toggleRow( i18n.matrixRowSelect || 'Show row select checkbox', rowSelect )
			);

			wrap.appendChild(
				renderMatrixListEditor(
					i18n.matrixRows || 'Rows',
					opts.rows,
					null,
					'row',
					onMatrixRequiredChange
				)
			);
			wrap.appendChild(
				renderMatrixListEditor(
					i18n.matrixColumns || 'Columns',
					opts.columns,
					null,
					'column',
					onMatrixRequiredChange
				)
			);

			const visitor = el( 'div', { className: 'wek-builder__matrix-section' } );
			visitor.appendChild(
				el( 'h4', {
					className: 'wek-builder__matrix-section-title',
					text: i18n.matrixVisitorSection || 'Visitor-added rows',
				} )
			);
			const allowCustom = el( 'input', { type: 'checkbox' } );
			allowCustom.checked = !! opts.allow_custom_rows;
			const maxCustomInput = el( 'input', {
				type: 'number',
				min: '1',
				max: '20',
				step: '1',
				className: 'small-text',
				value: String( opts.max_custom_rows || 2 ),
			} );

			function applyMaxCustomRows( fromBlur ) {
				opts.allow_custom_rows = allowCustom.checked;
				maxCustomInput.disabled = ! allowCustom.checked;
				const raw = String( maxCustomInput.value || '' ).trim();
				if ( raw === '' ) {
					if ( fromBlur ) {
						opts.max_custom_rows = 2;
						maxCustomInput.value = '2';
						flushSyncHidden();
					}
					updateExampleStatus();
					return;
				}
				let n = parseInt( raw, 10 );
				if ( ! Number.isFinite( n ) ) {
					if ( fromBlur ) {
						opts.max_custom_rows = 2;
						maxCustomInput.value = '2';
						flushSyncHidden();
					}
					updateExampleStatus();
					return;
				}
				// While typing, store only in-range values; do not rewrite the input
				// (rewriting "2" while aiming for "20" blocks higher numbers).
				if ( ! fromBlur ) {
					if ( n >= 1 && n <= 20 ) {
						opts.max_custom_rows = n;
						flushSyncHidden();
					}
					updateExampleStatus();
					return;
				}
				n = Math.min( 20, Math.max( 1, n ) );
				opts.max_custom_rows = n;
				maxCustomInput.value = String( n );
				flushSyncHidden();
				updateExampleStatus();
			}

			const exampleStatus = el( 'p', {
				className: 'description wek-builder__matrix-example-status',
			} );

			function updateExampleStatus() {
				const selfFill = allowCustom.checked && ! ( opts.rows && opts.rows.length );
				if ( selfFill ) {
					exampleStatus.textContent =
						i18n.matrixExampleOn ||
						'Inactive example row: on. Front end shows a non-editable Example row until the visitor adds a real row. Clear all preset rows under “Rows” to keep this mode.';
				} else if ( allowCustom.checked ) {
					exampleStatus.textContent =
						i18n.matrixExampleOffPresets ||
						'Inactive example row: off. You still have preset rows. Delete every catalog row under “Rows” if you want a self-fill matrix with an example row only.';
				} else {
					exampleStatus.textContent =
						i18n.matrixExampleOff ||
						'Inactive example row: off. Turn on “Allow visitor-added rows” (and clear preset rows) to show a non-editable Example row.';
				}
			}

			allowCustom.addEventListener( 'change', function () {
				applyMaxCustomRows( true );
			} );
			maxCustomInput.addEventListener( 'input', function () {
				applyMaxCustomRows( false );
			} );
			maxCustomInput.addEventListener( 'change', function () {
				applyMaxCustomRows( true );
			} );
			maxCustomInput.addEventListener( 'blur', function () {
				applyMaxCustomRows( true );
			} );
			maxCustomInput.disabled = ! allowCustom.checked;

			visitor.appendChild(
				toggleRow( i18n.matrixAllowCustomRows || 'Allow visitor-added rows', allowCustom )
			);
			visitor.appendChild(
				fieldRow( i18n.matrixMaxCustomRows || 'Max visitor-added rows', maxCustomInput )
			);
			visitor.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.matrixMaxCustomRowsHint ||
						'1–20. How many rows visitors may add on the form (not the catalog “Rows” list above).',
				} )
			);
			visitor.appendChild( exampleStatus );
			updateExampleStatus();
			wrap.appendChild( visitor );

			const validation = el( 'div', { className: 'wek-builder__matrix-section' } );
			validation.appendChild(
				el( 'h4', {
					className: 'wek-builder__matrix-section-title',
					text: i18n.matrixValidationSection || 'Validation',
				} )
			);
			const minRowsInput = el( 'input', {
				type: 'number',
				min: '0',
				step: '1',
				value: String( opts.min_answered_rows || 0 ),
			} );
			function syncMinRows() {
				let n = parseInt( minRowsInput.value, 10 );
				if ( ! Number.isFinite( n ) || n < 0 ) {
					n = 0;
				}
				opts.min_answered_rows = n;
				minRowsInput.value = String( n );
				syncMatrixRequiredFlag( field );
				syncHidden();
				updateCanvasFieldLabel( selected, field.label );
			}
			minRowsInput.addEventListener( 'change', syncMinRows );
			minRowsInput.addEventListener( 'blur', syncMinRows );
			validation.appendChild(
				fieldRow(
					i18n.matrixMinAnsweredRows || 'Minimum answered rows',
					minRowsInput
				)
			);
			validation.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.matrixMinAnsweredRowsHint ||
						'0 = optional. Per-row / per-column Required is set on each item above.',
				} )
			);
			wrap.appendChild( validation );

			return wrap;
		}

		function ensureTextLimits( field ) {
			const opts = ensureTypeOptions( field );
			if ( opts.max_length == null || opts.max_length === '' ) {
				opts.max_length = field.type === 'textarea' ? 5000 : 200;
			}
			if ( field.type === 'textarea' && ( opts.rows == null || opts.rows === '' ) ) {
				opts.rows = 3;
			}
			return opts;
		}

		function renderTextLimitsEditor( field ) {
			const opts = ensureTextLimits( field );
			const wrap = el( 'div', { className: 'wek-builder__text-limits' } );
			if ( field.type === 'textarea' ) {
				wrap.appendChild(
					fieldRow(
						i18n.textareaRows || 'Height (rows)',
						numberInput( opts.rows || 3, function ( v ) {
							const n = parseInt( v, 10 );
							opts.rows = ! isNaN( n ) && n > 0 ? Math.min( 40, n ) : 3;
							syncHidden();
							refreshCanvas();
						}, { min: '1', max: '40' } )
					)
				);
				wrap.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.textareaRowsHint ||
							'Visible height of the box. Independent of maximum characters.',
					} )
				);
			}
			wrap.appendChild(
				fieldRow(
					i18n.maxLength || 'Maximum characters',
					numberInput(
						opts.max_length === 0 || opts.max_length ? String( opts.max_length ) : '',
						function ( v ) {
							if ( v === '' ) {
								opts.max_length = 0;
							} else {
								const n = parseInt( v, 10 );
								opts.max_length = ! isNaN( n ) && n >= 0 ? n : 0;
							}
							syncHidden();
						},
						{ min: '0', step: '1' }
					)
				)
			);
			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.maxLengthHint ||
						'0 = no limit. Caps input length (helps against spam dumps in single-line fields).',
				} )
			);
			return wrap;
		}

		function ensureNumberOptions( field ) {
			const opts = ensureTypeOptions( field );
			if ( opts.min == null ) {
				opts.min = '';
			}
			if ( opts.max == null ) {
				opts.max = '';
			}
			if ( opts.step == null ) {
				opts.step = '';
			}
			if ( opts.decimals == null ) {
				opts.decimals = '';
			}
			return opts;
		}

		function renderRadioImageDisplayEditor( field ) {
			const opts = ensureTypeOptions( field );
			if ( ! opts.image_size ) {
				opts.image_size = 'medium';
			}
			if ( opts.columns == null || opts.columns === '' ) {
				opts.columns = 2;
			}
			if ( ! opts.option_position ) {
				opts.option_position = 'below';
			}
			if ( ! opts.select_style ) {
				opts.select_style = 'radio';
			}
			const body = el( 'div', { className: 'wek-builder__radio-image-display' } );
			body.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.radioImageDisplayHint ||
						'Image size uses WordPress media sizes (not full resolution). Columns apply from tablet width up; phones stay single-column with the label under the image.',
				} )
			);
			body.appendChild(
				fieldRow(
					i18n.radioImageSize || 'Image size',
					selectInput(
						opts.image_size || 'medium',
						[
							{ value: 'thumbnail', label: i18n.imageSizeThumbnail || 'Thumbnail' },
							{ value: 'medium', label: i18n.imageSizeMedium || 'Medium' },
						],
						function ( v ) {
							opts.image_size = v === 'thumbnail' ? 'thumbnail' : 'medium';
							syncHidden();
						}
					)
				)
			);
			body.appendChild(
				fieldRow(
					i18n.radioImageColumns || 'Columns (desktop)',
					numberInput(
						opts.columns,
						function ( v ) {
							const n = parseInt( v, 10 );
							opts.columns = isNaN( n ) ? 2 : Math.min( 4, Math.max( 1, n ) );
							syncHidden();
						},
						{ min: '1', max: '4', step: '1' }
					)
				)
			);
			body.appendChild(
				fieldRow(
					i18n.radioImageOptionPosition || 'Option placement',
					selectInput(
						opts.option_position || 'below',
						[
							{ value: 'below', label: i18n.radioImagePosBelow || 'Under the image' },
							{ value: 'beside', label: i18n.radioImagePosBeside || 'Beside the image' },
							{ value: 'above', label: i18n.radioImagePosAbove || 'Above the image' },
						],
						function ( v ) {
							opts.option_position =
								v === 'beside' || v === 'above' ? v : 'below';
							syncHidden();
						}
					)
				)
			);
			body.appendChild(
				fieldRow(
					i18n.radioImageSelectStyle || 'Selection style',
					selectInput(
						opts.select_style || 'radio',
						[
							{ value: 'radio', label: i18n.radioImageSelectRadio || 'Show radio control' },
							{
								value: 'frame',
								label: i18n.radioImageSelectFrame || 'Frame image (hide radio, show checkmark)',
							},
						],
						function ( v ) {
							opts.select_style = v === 'frame' ? 'frame' : 'radio';
							syncHidden();
						}
					)
				)
			);
			return collapsiblePanel(
				i18n.radioImageDisplay || 'Display',
				body,
				'wek-builder__radio-image-display-panel',
				false
			);
		}

		function renderNumberOptionsEditor( field ) {
			const opts = ensureNumberOptions( field );
			const wrap = el( 'div', { className: 'wek-builder__number-options' } );
			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.numberOptionsHint ||
						'Optional limits for the number input. Decimal places set the step when Step is left empty (0 → 1, 2 → 0.01).',
				} )
			);
			wrap.appendChild(
				fieldRow(
					i18n.numberMin || 'Minimum',
					textInput( opts.min === 0 || opts.min ? String( opts.min ) : '', function ( v ) {
						opts.min = String( v || '' ).trim();
						syncHidden();
					} )
				)
			);
			wrap.appendChild(
				fieldRow(
					i18n.numberMax || 'Maximum',
					textInput( opts.max === 0 || opts.max ? String( opts.max ) : '', function ( v ) {
						opts.max = String( v || '' ).trim();
						syncHidden();
					} )
				)
			);
			wrap.appendChild(
				fieldRow(
					i18n.numberStep || 'Step',
					textInput( opts.step ? String( opts.step ) : '', function ( v ) {
						opts.step = String( v || '' ).trim();
						syncHidden();
					} )
				)
			);
			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text: i18n.numberStepHint || 'Examples: any, 1, 0.5, 0.01. Leave empty to use decimal places.',
				} )
			);
			wrap.appendChild(
				fieldRow(
					i18n.numberDecimals || 'Decimal places',
					numberInput(
						opts.decimals === '' || opts.decimals == null ? '' : opts.decimals,
						function ( v ) {
							if ( v === '' ) {
								opts.decimals = '';
							} else {
								const n = parseInt( v, 10 );
								opts.decimals = isNaN( n ) ? '' : Math.max( 0, Math.min( 6, n ) );
							}
							syncHidden();
						},
						{ min: '0', max: '6' }
					)
				)
			);
			return wrap;
		}

		function ensureCheckboxesLimits( field ) {
			const opts = ensureTypeOptions( field );
			if ( opts.min_selected == null ) {
				opts.min_selected = 0;
			}
			if ( opts.max_selected == null ) {
				opts.max_selected = 0;
			}
			return opts;
		}

		function renderCheckboxesLimitsEditor( field ) {
			const opts = ensureCheckboxesLimits( field );
			const wrap = el( 'div', { className: 'wek-builder__checkbox-limits' } );
			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.checkboxesLimitsHint ||
						'0 minimum = no minimum (unless Required). 0 maximum = unlimited.',
				} )
			);
			wrap.appendChild(
				fieldPair( [
					{
						label: i18n.minSelected || 'Minimum selections',
						control: numberInput(
							opts.min_selected,
							function ( v ) {
								const n = parseInt( v, 10 );
								opts.min_selected = isNaN( n ) ? 0 : Math.max( 0, n );
								if ( opts.max_selected > 0 && opts.min_selected > opts.max_selected ) {
									opts.max_selected = opts.min_selected;
								}
								syncHidden();
							},
							{ min: '0' }
						),
					},
					{
						label: i18n.maxSelected || 'Maximum selections',
						control: numberInput(
							opts.max_selected,
							function ( v ) {
								const n = parseInt( v, 10 );
								opts.max_selected = isNaN( n ) ? 0 : Math.max( 0, n );
								if ( opts.max_selected > 0 && opts.min_selected > opts.max_selected ) {
									opts.min_selected = opts.max_selected;
								}
								syncHidden();
							},
							{ min: '0' }
						),
					},
				] )
			);

			if ( typeof opts.allow_other === 'undefined' ) {
				opts.allow_other = false;
			}
			if ( typeof opts.other_label !== 'string' || ! opts.other_label ) {
				opts.other_label = i18n.otherLabelDefault || 'Add other';
			}
			if ( typeof opts.max_other === 'undefined' || opts.max_other === '' ) {
				opts.max_other = 2;
			}
			let maxOther = parseInt( opts.max_other, 10 );
			if ( isNaN( maxOther ) || maxOther < 1 ) {
				maxOther = 2;
			}
			opts.max_other = Math.min( 5, maxOther );

			const allowOther = el( 'input', { type: 'checkbox' } );
			allowOther.checked = !! opts.allow_other;
			const otherLabelInput = textInput( opts.other_label, function ( v ) {
				opts.other_label = String( v || '' ).trim() || ( i18n.otherLabelDefault || 'Add other' );
				syncHidden();
			} );
			const maxOtherInput = numberInput(
				String( opts.max_other ),
				function ( v ) {
					let n = parseInt( v, 10 );
					if ( isNaN( n ) || n < 1 ) {
						n = 2;
					}
					opts.max_other = Math.min( 5, n );
					maxOtherInput.value = String( opts.max_other );
					syncHidden();
				},
				{ min: '1', max: '5' }
			);
			const otherLabelRow = fieldRow( i18n.otherLabel || 'Add button label', otherLabelInput );
			const maxOtherRow = fieldRow( i18n.maxOther || 'Max custom options', maxOtherInput );
			otherLabelRow.hidden = ! allowOther.checked;
			maxOtherRow.hidden = ! allowOther.checked;

			allowOther.addEventListener( 'change', function () {
				opts.allow_other = allowOther.checked;
				otherLabelRow.hidden = ! allowOther.checked;
				maxOtherRow.hidden = ! allowOther.checked;
				syncHidden();
			} );

			wrap.appendChild(
				toggleRow( i18n.allowOther || 'Allow custom options', allowOther )
			);
			wrap.appendChild( otherLabelRow );
			wrap.appendChild( maxOtherRow );

			return wrap;
		}

		function renderChoiceLabelEditor( field ) {
			const opts = ensureTypeOptions( field );
			const wrap = el( 'div', { className: 'wek-builder__choice-label-editor' } );
			const isConsent = field.type === 'consent';

			if ( typeof opts.choice_label !== 'string' ) {
				opts.choice_label = '';
			}

			const labelKey = isConsent
				? i18n.consentText || 'Consent text'
				: i18n.checkboxText || 'Checkbox text';

			if ( isConsent ) {
				const area = el( 'textarea', {
					className: 'large-text',
					rows: '3',
				} );
				area.value = opts.choice_label || '';
				area.addEventListener( 'input', function () {
					opts.choice_label = area.value;
					syncHidden();
					const selected = getSelected();
					if ( selected && selected.field === field ) {
						updateCanvasFieldLabel( selected, field.label );
					}
				} );
				wrap.appendChild( fieldRow( labelKey, area ) );
				wrap.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.consentTextHint ||
							'Text beside the checkbox. Use {link} where the linked phrase should appear.',
					} )
				);
			} else {
				wrap.appendChild(
					fieldRow(
						labelKey,
						textInput( opts.choice_label || '', function ( v ) {
							opts.choice_label = v;
							syncHidden();
							const selected = getSelected();
							if ( selected && selected.field === field ) {
								updateCanvasFieldLabel( selected, field.label );
							}
						} )
					)
				);
				wrap.appendChild(
					el( 'p', {
						className: 'description',
						text: i18n.checkboxTextHint || 'Text shown beside the checkbox.',
					} )
				);
			}

			return wrap;
		}

		function renderConsentLinkEditor( field ) {
			const opts = ensureTypeOptions( field );
			const wrap = el( 'div', { className: 'wek-builder__consent-link' } );

			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.consentLinkHint ||
						'Put {link} in the consent text where the linked phrase should appear. Without {link}, no link is shown.',
				} )
			);

			if ( typeof opts.link_text !== 'string' ) {
				opts.link_text = '';
			}
			if ( typeof opts.privacy_url !== 'string' ) {
				opts.privacy_url = '';
			}

			wrap.appendChild(
				fieldRow(
					i18n.consentLinkText || 'Link text',
					textInput( opts.link_text || '', function ( v ) {
						opts.link_text = v;
						syncHidden();
						const selected = getSelected();
						if ( selected && selected.field === field ) {
							updateCanvasFieldLabel( selected, field.label );
						}
					}, {
						placeholder: i18n.privacyPolicy || 'Privacy policy',
					} )
				)
			);
			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.consentLinkTextHint ||
						'Defaults to “Privacy policy” when empty.',
				} )
			);

			wrap.appendChild(
				fieldRow(
					i18n.consentLinkUrl || 'Link URL',
					textInput( opts.privacy_url || '', function ( v ) {
						opts.privacy_url = String( v || '' ).trim();
						syncHidden();
					}, {
						type: 'url',
						placeholder: 'https://',
					} )
				)
			);
			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.consentLinkUrlHint ||
						'Leave empty to use the form privacy URL, then the site default.',
				} )
			);

			return wrap;
		}

		function renderContentGuardEditor( field ) {
			const opts = ensureTypeOptions( field );
			const wrap = el( 'div', { className: 'wek-builder__content-guard' } );
			wrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.contentGuardHint ||
						'Reject submissions where this field contains links or an email address (helps against bots).',
				} )
			);

			const linksBox = el( 'input', { type: 'checkbox' } );
			linksBox.checked = !! opts.block_links;
			linksBox.addEventListener( 'change', function () {
				opts.block_links = linksBox.checked;
				syncHidden();
			} );
			wrap.appendChild(
				toggleRow( i18n.blockLinks || 'Reject links (URLs)', linksBox )
			);

			const emailBox = el( 'input', { type: 'checkbox' } );
			emailBox.checked = !! opts.block_emails;
			emailBox.addEventListener( 'change', function () {
				opts.block_emails = emailBox.checked;
				syncHidden();
			} );
			wrap.appendChild(
				toggleRow( i18n.blockEmails || 'Reject email addresses', emailBox )
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
					'radio_image',
					'upload',
					'repeater',
				].indexOf( type ) === -1
			);
		}

		function renderSidebar( aside ) {
			aside.innerHTML = '';

			const scopeBar = el( 'div', {
				className: 'wek-builder-scope',
				role: 'tablist',
				'aria-label': i18n.scopeTabs || 'Settings scope',
			} );
			[
				{ id: 'field', label: i18n.scopeField || 'Field' },
				{ id: 'form', label: i18n.scopeForm || 'Form' },
				{ id: 'integrations', label: i18n.scopeIntegrations || 'Integrations' },
			].forEach( function ( scope ) {
				scopeBar.appendChild(
					el( 'button', {
						type: 'button',
						className:
							'wek-builder-scope__btn' +
							( sidebarScope === scope.id ? ' is-active' : '' ),
						role: 'tab',
						'aria-selected': sidebarScope === scope.id ? 'true' : 'false',
						text: scope.label,
						onClick: function () {
							sidebarScope = scope.id;
							flushSyncHidden();
							refreshSidebar();
						},
					} )
				);
			} );
			aside.appendChild( scopeBar );

			if ( sidebarScope === 'form' ) {
				renderFormScope( aside );
				return;
			}
			if ( sidebarScope === 'integrations' ) {
				renderIntegrationsScope( aside );
				return;
			}
			renderFieldInspector( aside );
		}

		function renderFormScope( aside ) {
			const panel = el( 'div', { className: 'wek-builder-scope-panel' } );
			panel.appendChild(
				el( 'h3', {
					className: 'wek-builder-sidebar__title',
					text: i18n.formScopeTitle || 'Form',
				} )
			);
			panel.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.formScopeLead ||
						'Quick links for this form. Save & Resume and privacy live under Form Settings; colors and layout under Design.',
				} )
			);
			const settingsUrl =
				window.weFormkitAdmin && window.weFormkitAdmin.settingsUrl
					? window.weFormkitAdmin.settingsUrl
					: '';
			const designUrl =
				window.weFormkitAdmin && window.weFormkitAdmin.designUrl
					? window.weFormkitAdmin.designUrl
					: '';
			if ( ! formIdForUi || ! settingsUrl ) {
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.saveFormFirst ||
							'Save the form once to unlock the shortcode and Form Settings link.',
					} )
				);
				aside.appendChild( panel );
				return;
			}
			const links = el( 'div', { className: 'wek-builder-scope-panel__actions' } );
			links.appendChild(
				el( 'a', {
					className: 'button',
					href: settingsUrl,
					text: i18n.openFormSettings || 'Open Form Settings',
				} )
			);
			if ( designUrl ) {
				links.appendChild(
					el( 'a', {
						className: 'button',
						href: designUrl,
						text: i18n.openDesign || 'Open Design',
					} )
				);
			}
			panel.appendChild( links );
			panel.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.formScopePaginationHint ||
						'Pagination (single page / one section per page) is in the toolbar above the canvas.',
				} )
			);
			aside.appendChild( panel );
		}

		function renderIntegrationsScope( aside ) {
			const panel = el( 'div', { className: 'wek-builder-scope-panel' } );
			panel.appendChild(
				el( 'h3', {
					className: 'wek-builder-sidebar__title',
					text: i18n.integrationsTitle || 'Integrations',
				} )
			);

			const integrations =
				window.weFormkitAdmin && Array.isArray( window.weFormkitAdmin.integrations )
					? window.weFormkitAdmin.integrations
					: [];

			if ( ! integrations.length ) {
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.integrationsEmpty ||
							'No integrations connected yet. Add-ons register here via we_formkit_builder_integrations.',
					} )
				);
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.integrationsHint ||
							'Formkit modules and third-party plugins can add settings panels for this form.',
					} )
				);
				aside.appendChild( panel );
				return;
			}

			integrations.forEach( function ( integ ) {
				renderIntegrationPanel( panel, integ );
			} );
			aside.appendChild( panel );
		}

		function renderIntegrationPanel( parent, integ ) {
			if ( ! integ || ! integ.id ) {
				return;
			}

			parent.appendChild(
				el( 'h4', {
					className: 'wek-builder-sidebar__subtitle',
					text: integ.title || integ.id,
				} )
			);

			if ( integ.description ) {
				parent.appendChild(
					el( 'p', {
						className: 'description',
						text: integ.description,
					} )
				);
			}

			const needsFormId = integ.requiresFormId !== false;
			if ( needsFormId && ! formIdForUi ) {
				parent.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.saveFormFirst ||
							'Save the form once to unlock the shortcode and Form Settings link.',
					} )
				);
				return;
			}

			const fields = Array.isArray( integ.fields ) ? integ.fields : [];
			const controls = {};
			const status = el( 'p', {
				className: 'description wek-builder__integration-status',
				text: '',
			} );
			let saveTimer = null;

			function fieldValue( field ) {
				const ctrl = controls[ field.name ];
				if ( ! ctrl ) {
					return field.value;
				}
				if ( field.type === 'checkboxes' ) {
					const ids = [];
					ctrl.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( box ) {
						if ( box.checked ) {
							ids.push( box.value );
						}
					} );
					return ids;
				}
				if ( field.type === 'prompt_override' ) {
					if ( ctrl._promptArea ) {
						return ctrl._promptArea.value;
					}
					const area = ctrl.querySelector( 'textarea' );
					return area ? area.value : field.value;
				}
				const raw = ctrl.value;
				if ( field.format === 'lines' ) {
					return raw
						.split( /[\s,]+/ )
						.map( function ( s ) {
							return s.trim();
						} )
						.filter( Boolean );
				}
				return raw;
			}

			function collectBody() {
				const save = integ.save || {};
				const body = Object.assign( {}, save.body || {} );
				// Keep form_id in sync when the form was saved after localize (new form → first save).
				if ( Object.prototype.hasOwnProperty.call( body, 'form_id' ) && formIdForUi ) {
					body.form_id = formIdForUi;
				}
				fields.forEach( function ( field ) {
					if ( ! field || ! field.name ) {
						return;
					}
					body[ field.name ] = fieldValue( field );
				} );
				return body;
			}

			function saveNow() {
				const save = integ.save;
				if ( ! save || ! save.url ) {
					return;
				}
				status.textContent = i18n.integrationsSaving || 'Saving…';
				window
					.fetch( save.url, {
						method: save.method || 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': save.nonce || '',
						},
						body: JSON.stringify( collectBody() ),
					} )
					.then( function ( res ) {
						if ( ! res.ok ) {
							throw new Error( 'save failed' );
						}
						return res.json();
					} )
					.then( function ( data ) {
						fields.forEach( function ( field ) {
							if (
								field &&
								field.name &&
								Object.prototype.hasOwnProperty.call( data, field.name )
							) {
								field.value = data[ field.name ];
							}
						} );
						status.textContent = i18n.integrationsSaved || 'Saved.';
					} )
					.catch( function () {
						status.textContent = i18n.integrationsSaveFailed || 'Could not save.';
					} );
			}

			function scheduleSave() {
				if ( ! integ.save || ! integ.save.url ) {
					return;
				}
				if ( saveTimer ) {
					window.clearTimeout( saveTimer );
				}
				saveTimer = window.setTimeout( saveNow, 250 );
			}

			fields.forEach( function ( field ) {
				if ( ! field || ! field.name ) {
					return;
				}

				let control;
				if ( field.type === 'select' ) {
					control = el( 'select', { className: 'wek-builder__select widefat' } );
					const choices =
						field.choices && typeof field.choices === 'object' ? field.choices : {};
					Object.keys( choices ).forEach( function ( id ) {
						control.appendChild(
							el( 'option', {
								value: id,
								text: choices[ id ],
								selected: String( field.value || '' ) === String( id ),
							} )
						);
					} );
					control.addEventListener( 'change', function () {
						scheduleSave();
						updatePromptPreviews();
					} );
				} else if ( field.type === 'prompt_override' ) {
					const wrap = el( 'div', { className: 'wek-builder__prompt-override' } );
					const preview = el( 'p', {
						className: 'description wek-builder__prompt-preview',
						text: '',
					} );
					const details = el( 'details', { className: 'wek-builder__prompt-details' } );
					const summary = el( 'summary', {
						text:
							i18n.integrationsEditPrompt ||
							'Show / edit prompt for this form',
					} );
					const area = el( 'textarea', {
						className: 'widefat',
						rows: String( field.rows || 5 ),
						placeholder:
							field.placeholder ||
							i18n.integrationsPromptPlaceholder ||
							'Leave empty to use the form-type prompt from Spamfighterin settings.',
					} );
					area.value = field.value != null ? String( field.value ) : '';
					area.addEventListener( 'change', scheduleSave );
					area.addEventListener( 'blur', scheduleSave );
					details.appendChild( summary );
					details.appendChild(
						el( 'p', {
							className: 'description',
							text:
								i18n.integrationsPromptTypeHint ||
								'Type default (from settings):',
						} )
					);
					details.appendChild( preview );
					details.appendChild(
						el( 'p', {
							className: 'description',
							text:
								field.hint ||
								i18n.integrationsPromptOverrideHint ||
								'Optional override for this form only. Empty = use the type prompt above.',
						} )
					);
					details.appendChild( area );
					wrap.appendChild( details );
					control = wrap;
					control._promptArea = area;
					control._promptPreview = preview;
					control._kindField = field.kindField || 'form_kind';
				} else if ( field.type === 'checkboxes' ) {
					control = el( 'div', { className: 'wek-builder__integration-checks' } );
					const choices = resolveCheckboxChoices( field );
					const checked = Array.isArray( field.value ) ? field.value : [];
					const checkedSet = {};
					checked.forEach( function ( id ) {
						checkedSet[ String( id ) ] = true;
					} );
					const ids = Object.keys( choices );
					if ( ! ids.length ) {
						control.appendChild(
							el( 'p', {
								className: 'description',
								text:
									i18n.integrationsNoFields ||
									'No fields on this form yet.',
							} )
						);
					} else {
						ids.forEach( function ( id ) {
							const input = el( 'input', {
								type: 'checkbox',
								value: id,
							} );
							input.checked = !! checkedSet[ id ];
							input.addEventListener( 'change', scheduleSave );
							control.appendChild(
								el( 'label', { className: 'wek-builder__integration-check' }, [
									input,
									document.createTextNode( ' ' + choices[ id ] ),
								] )
							);
						} );
					}
				} else if ( field.type === 'textarea' ) {
					control = el( 'textarea', {
						className: 'widefat',
						rows: String( field.rows || 3 ),
						placeholder: field.placeholder || '',
					} );
					if ( field.format === 'lines' && Array.isArray( field.value ) ) {
						control.value = field.value.join( '\n' );
					} else {
						control.value = field.value != null ? String( field.value ) : '';
					}
					control.addEventListener( 'change', scheduleSave );
					control.addEventListener( 'blur', scheduleSave );
				} else {
					control = el( 'input', {
						type: 'text',
						className: 'widefat',
						value: field.value != null ? String( field.value ) : '',
						placeholder: field.placeholder || '',
					} );
					control.addEventListener( 'change', scheduleSave );
					control.addEventListener( 'blur', scheduleSave );
				}

				controls[ field.name ] = control;
				parent.appendChild( fieldRow( field.label || field.name, control ) );
				if ( field.hint ) {
					parent.appendChild(
						el( 'p', {
							className: 'description',
							text: field.hint,
						} )
					);
				}
			} );

			if ( integ.save && integ.save.url ) {
				parent.appendChild( status );
			}

			updatePromptPreviews();

			function updatePromptPreviews() {
				const meta = integ.meta && typeof integ.meta === 'object' ? integ.meta : {};
				const prompts = meta.prompts && typeof meta.prompts === 'object' ? meta.prompts : {};
				const defaultKind = meta.defaultFormKind || 'contact';

				fields.forEach( function ( field ) {
					if ( ! field || field.type !== 'prompt_override' ) {
						return;
					}
					const ctrl = controls[ field.name ];
					if ( ! ctrl || ! ctrl._promptPreview ) {
						return;
					}
					const kindName = ctrl._kindField || 'form_kind';
					const kindCtrl = controls[ kindName ];
					let kind = kindCtrl && kindCtrl.value != null ? String( kindCtrl.value ) : '';
					if ( ! kind ) {
						kind = defaultKind;
					}
					const text = prompts[ kind ] || prompts[ defaultKind ] || '';
					ctrl._promptPreview.textContent = text;
				} );
			}
		}

		function collectSchemaFieldChoices() {
			const out = {};
			const skip = { html: true };

			function walk( fields ) {
				( fields || [] ).forEach( function ( f ) {
					if ( ! f || ! f.id ) {
						return;
					}
					const type = f.type || '';
					if ( skip[ type ] ) {
						return;
					}
					const label = ( f.label || f.id ) + ' (' + f.id + ')';
					out[ f.id ] = label;
					if ( type === 'repeater' && Array.isArray( f.fields ) ) {
						walk( f.fields );
					}
				} );
			}

			( schema.sections || [] ).forEach( function ( section ) {
				walk( section.fields );
			} );
			return out;
		}

		function resolveCheckboxChoices( field ) {
			let choices =
				field.choices && typeof field.choices === 'object' ? Object.assign( {}, field.choices ) : {};
			if ( field.choicesSource === 'formFields' ) {
				choices = Object.assign( {}, collectSchemaFieldChoices(), choices );
			}
			( Array.isArray( field.value ) ? field.value : [] ).forEach( function ( id ) {
				const key = String( id );
				if ( key && ! choices[ key ] ) {
					choices[ key ] = key;
				}
			} );
			return choices;
		}

		function renderFieldInspector( aside ) {
			const selected = getSelected();
			if ( ! selected ) {
				aside.appendChild(
					el( 'p', {
						className: 'wek-builder-sidebar__empty',
						text: i18n.selectHint || 'Select a field, section, or the submit button to edit its settings.',
					} )
				);
				return;
			}

			if ( selected.kind === 'submit' ) {
				aside.appendChild(
					el( 'div', { className: 'wek-builder-sidebar__head' }, [
						el( 'h3', {
							className: 'wek-builder-sidebar__title',
							id: 'wek-builder-sidebar-title',
							text: i18n.submitSettings || 'Submit button',
						} ),
					] )
				);
				const panel = el( 'div', {
					className: 'wek-builder-tabs__panel is-active',
					role: 'tabpanel',
				} );
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.submitSettingsHint ||
							'Shown at the end of the form. Save the form to apply changes on the front end.',
					} )
				);
				panel.appendChild(
					fieldRow(
						i18n.submitButtonText || 'Submit button text',
						textInput( submitButton.label || '', function ( v ) {
							submitButton.label = v;
							syncHidden();
							const preview = root.querySelector( '.wek-builder__submit-preview-text' );
							if ( preview ) {
								preview.textContent = v || i18n.submitPreview || 'Submit';
							}
						} )
					)
				);
				panel.appendChild(
					el( 'label', { text: i18n.submitButtonWidth || 'Button width' } )
				);
				panel.appendChild( renderSubmitWidthPicker() );
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.submitButtonWidthHint ||
							'Same column widths as fields. Auto fits the label (recommended).',
					} )
				);
				panel.appendChild(
					fieldRow(
						i18n.submitIconSvg || 'SVG icon (optional)',
						( function () {
							const ta = el( 'textarea', {
								rows: '4',
								className: 'large-text code',
								placeholder: '<svg …>',
							} );
							ta.value = submitButton.icon_svg || '';
							ta.addEventListener( 'input', function () {
								submitButton.icon_svg = ta.value;
								syncHidden();
								updateSubmitPreview();
							} );
							return ta;
						} )()
					)
				);
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.submitIconSvgHint ||
							'Paste inline SVG markup (no scripts). Leave empty for text only.',
					} )
				);
				const posSelect = el( 'select' );
				[
					{ value: 'before', label: i18n.iconBefore || 'Before text' },
					{ value: 'after', label: i18n.iconAfter || 'After text' },
				].forEach( function ( opt ) {
					posSelect.appendChild(
						el( 'option', {
							value: opt.value,
							text: opt.label,
							selected: submitButton.icon_position === opt.value,
						} )
					);
				} );
				posSelect.addEventListener( 'change', function () {
					submitButton.icon_position = posSelect.value === 'after' ? 'after' : 'before';
					syncHidden();
					updateSubmitPreview();
				} );
				panel.appendChild( fieldRow( i18n.iconPosition || 'Icon position', posSelect ) );
				aside.appendChild( panel );
				return;
			}

			const isNested = selected.kind === 'nested';
			const isField = selected.kind === 'field' || isNested;
			const title = isField
				? ( i18n.fieldSettings || 'Field settings' )
				: ( i18n.sectionSettings || 'Section settings' );

			const head = el( 'div', { className: 'wek-builder-sidebar__head' }, [
				el( 'h3', { className: 'wek-builder-sidebar__title', id: 'wek-builder-sidebar-title', text: title } ),
			] );
			aside.appendChild( head );

			const tabs = isNested
				? [
						{ id: 'general', label: i18n.tabGeneral || 'General' },
						{ id: 'appearance', label: i18n.tabAppearance || 'Appearance' },
				  ]
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
							flushSyncHidden();
							refreshSidebar();
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
				typeSelect.className = 'wek-builder__select';
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

				if ( field.role ) {
					const roleLabel = roleLabels[ field.role ] || field.role;
					panel.appendChild(
						fieldRow(
							i18n.semanticRole || 'Semantic role',
							el( 'p', {
								className: 'description',
								text: roleLabel + ' (' + field.role + ')',
							} )
						)
					);
				}

				panel.appendChild(
					fieldRow(
						i18n.id || 'Field ID',
						textInput( field.id || '', function ( v ) {
							field.id = String( v || '' )
								.toLowerCase()
								.replace( /[^a-z0-9_-]/g, '_' );
							syncHidden();
							const titleEl = aside.querySelector( '.wek-builder-sidebar__title' );
							if ( titleEl ) {
								titleEl.textContent = ( i18n.field || 'Field' ) + ': ' + field.id;
							}
						} )
					)
				);
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.idPrefillHint ||
							'Also the URL query parameter for prefill (e.g. ?anliegen=angebot). Prefer short lowercase IDs.',
					} )
				);
				panel.appendChild(
					fieldRow(
						i18n.label || 'Label',
						textInput( field.label || '', function ( v ) {
							field.label = v;
							syncHidden();
							updateCanvasFieldLabel( selected, v );
						} )
					)
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

				const req = el( 'input', { type: 'checkbox' } );
				req.checked = !! field.required;
				req.addEventListener( 'change', function () {
					field.required = !! req.checked;
					syncHidden();
					updateCanvasFieldLabel( selected, field.label );
					refreshSidebar();
				} );
				if ( field.type !== 'matrix' ) {
					panel.appendChild( toggleRow( i18n.required || 'Required', req ) );
				} else {
					ensureMatrixOptions( field );
					syncMatrixRequiredFlag( field );
				}
				panel.appendChild( renderValidationMessagesEditor( field ) );

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
								refreshCanvas();
							} )
						)
					);
					if ( field.type === 'select' ) {
						panel.appendChild(
							el( 'p', {
								className: 'description',
								text:
									i18n.selectPlaceholderHint ||
									'Shown as the first empty choice (empty value) when no default option is selected. Leave blank to use “Please select…”.',
							} )
						);
					}
				}

				if ( [ 'radio', 'checkboxes', 'select', 'radio_image' ].indexOf( field.type ) !== -1 ) {
					panel.appendChild( renderOptionsEditor( field ) );
				}

				if ( field.type === 'radio_image' ) {
					panel.appendChild( renderRadioImageDisplayEditor( field ) );
				}

				if ( ! isNested ) {
					panel.appendChild( renderPrefillEditor( field ) );
				}

				if ( field.type === 'checkboxes' ) {
					panel.appendChild( renderCheckboxesLimitsEditor( field ) );
				}

				if ( field.type === 'number' ) {
					panel.appendChild( renderNumberOptionsEditor( field ) );
				}

				if ( field.type === 'text' || field.type === 'textarea' ) {
					panel.appendChild( renderTextLimitsEditor( field ) );
				}

				if ( field.type === 'matrix' ) {
					panel.appendChild( renderMatrixEditor( field ) );
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

				if ( field.type === 'checkbox' || field.type === 'consent' ) {
					panel.appendChild( renderChoiceLabelEditor( field ) );
				}

				if ( field.type === 'consent' ) {
					panel.appendChild( renderConsentLinkEditor( field ) );
				}

				if ( field.type === 'text' || field.type === 'textarea' ) {
					panel.appendChild( renderContentGuardEditor( field ) );
				}
			} else if ( isField && activeTab === 'appearance' ) {
				appendFieldAppearanceControls( panel, selected.field, { isNested: isNested } );
			} else if ( selected.kind === 'field' && activeTab === 'conditional' ) {
				panel.appendChild( renderConditional( selected.field, selected.field.id ) );
			} else if ( selected.kind === 'section' && activeTab === 'general' ) {
				const section = selected.section;
				if ( typeof section.show_title === 'undefined' ) {
					section.show_title = true;
				}
				if ( typeof section.intro !== 'string' ) {
					section.intro = '';
				}
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
				const showTitle = el( 'input', { type: 'checkbox' } );
				showTitle.checked = section.show_title !== false;
				showTitle.addEventListener( 'change', function () {
					section.show_title = !! showTitle.checked;
					syncHidden();
					refreshCanvas();
				} );
				panel.appendChild(
					toggleRow( i18n.showSectionTitle || 'Show title on form', showTitle )
				);
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.showSectionTitleHint ||
							'When off, the title stays in the builder for reference but is hidden on the front end.',
					} )
				);
				const introArea = el( 'textarea', {
					className: 'large-text',
					rows: '3',
				} );
				introArea.value = section.intro || '';
				introArea.addEventListener( 'input', function () {
					section.intro = introArea.value;
					syncHidden();
					const preview = root.querySelector(
						'.wek-builder__section[data-s="' + selected.sIndex + '"] .wek-builder__section-intro'
					);
					if ( preview ) {
						const text = ( section.intro || '' ).trim();
						preview.textContent = text;
						preview.hidden = ! text;
					} else if ( ( section.intro || '' ).trim() ) {
						refreshCanvas();
					}
				} );
				panel.appendChild( fieldRow( i18n.sectionIntro || 'Section intro', introArea ) );
				panel.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.sectionIntroHint ||
							'Optional text under the section title. Use this for a question or short explanation — not a second title.',
					} )
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

		function bindFieldWidthResize( handle, field, card ) {
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
					flushSyncHidden();
					const picker = ui.aside
						? ui.aside.querySelector( '.wek-builder__columns' )
						: null;
					if ( picker ) {
						Array.prototype.forEach.call(
							picker.querySelectorAll( '.wek-builder__column-btn' ),
							function ( btn ) {
								const isActive = btn.getAttribute( 'data-width' ) === field.width;
								btn.classList.toggle( 'is-active', isActive );
								btn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
							}
						);
					}
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
					selectItem( {
						type: 'nested',
						sIndex: from.s,
						fIndex: from.f,
						nIndex: from.index,
					} );
				} else {
					selectItem( { type: 'field', sIndex: from.s, fIndex: from.index } );
				}
				return;
			}

			const fromPackId = packGroupId( moving );

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

			// Leave a pack when dropped outside its contiguous group (or into a repeater).
			if ( fromPackId && to.scope === 'section' && sameList ) {
				clearPackGroupIfOrphan( toList, insertAt );
			} else if ( fromPackId && ( to.scope !== 'section' || ! sameList ) ) {
				clearPackGroup( item );
			}

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
			flushSyncHidden();
			render( { skipSync: true } );
		}

		function relocatePack( fromS, start, length, toS, toIndex ) {
			if (
				fromS < 0 ||
				toS < 0 ||
				! schema.sections[ fromS ] ||
				! schema.sections[ toS ] ||
				length < 1
			) {
				return;
			}
			const fromList = schema.sections[ fromS ].fields || [];
			const toList = schema.sections[ toS ].fields || [];
			if ( start < 0 || start + length > fromList.length ) {
				return;
			}

			if (
				fromS === toS &&
				( toIndex === start || toIndex === start + length )
			) {
				selection = { type: 'field', sIndex: fromS, fIndex: start };
				return;
			}

			const slice = fromList.splice( start, length );
			let insertAt = typeof toIndex === 'number' ? toIndex : toList.length;
			if ( fromS === toS && start < insertAt ) {
				insertAt -= length;
			}
			insertAt = Math.max( 0, Math.min( insertAt, toList.length ) );
			Array.prototype.splice.apply( toList, [ insertAt, 0 ].concat( slice ) );

			selection = { type: 'field', sIndex: toS, fIndex: insertAt };
			announce( i18n.moved || 'Item moved.' );
			flushSyncHidden();
			render( { skipSync: true } );
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

		const dropUi = {
			line: null,
			target: null,
			raf: 0,
			pending: null,
		};

		function ensureDropLine() {
			if ( ! dropUi.line ) {
				dropUi.line = el( 'div', { className: 'wek-builder__drop-line' } );
			}
			return dropUi.line;
		}

		function clearDropIndicators() {
			if ( dropUi.raf ) {
				window.cancelAnimationFrame( dropUi.raf );
				dropUi.raf = 0;
			}
			dropUi.pending = null;
			if ( dropUi.line && dropUi.line.parentNode ) {
				dropUi.line.parentNode.removeChild( dropUi.line );
			}
			if ( dropUi.target ) {
				dropUi.target.classList.remove( 'is-drop-target' );
				dropUi.target = null;
			}
			Array.prototype.forEach.call( root.querySelectorAll( '.is-drop-target' ), function ( n ) {
				n.classList.remove( 'is-drop-target' );
			} );
		}

		function placeDropLine( parent, beforeNode ) {
			const line = ensureDropLine();
			if ( beforeNode ) {
				parent.insertBefore( line, beforeNode );
			} else {
				parent.appendChild( line );
			}
		}

		function setDropTarget( node ) {
			if ( dropUi.target && dropUi.target !== node ) {
				dropUi.target.classList.remove( 'is-drop-target' );
			}
			dropUi.target = node || null;
			if ( node ) {
				node.classList.add( 'is-drop-target' );
			}
		}

		function scheduleDropResolve( clientX, clientY, dragCard, onResolved ) {
			dropUi.pending = {
				clientX: clientX,
				clientY: clientY,
				dragCard: dragCard,
				onResolved: onResolved,
			};
			if ( dropUi.raf ) {
				return;
			}
			dropUi.raf = window.requestAnimationFrame( function () {
				dropUi.raf = 0;
				const pending = dropUi.pending;
				dropUi.pending = null;
				if ( ! pending ) {
					return;
				}
				if ( dropUi.line && dropUi.line.parentNode ) {
					dropUi.line.parentNode.removeChild( dropUi.line );
				}
				if ( dropUi.target ) {
					dropUi.target.classList.remove( 'is-drop-target' );
					dropUi.target = null;
				}
				const loc = resolveDropLocation(
					pending.clientX,
					pending.clientY,
					pending.dragCard
				);
				if ( pending.onResolved ) {
					pending.onResolved( loc );
				}
			} );
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
				placeDropLine(
					nestedCard.parentNode,
					before ? nestedCard : nestedCard.nextSibling
				);
				return { scope: 'repeater', s: s, f: f, index: before ? n : n + 1 };
			}

			const repeaterCard = elUnder.closest( '.wek-builder__field[data-repeater="1"]' );
			if ( repeaterCard && repeaterCard !== dragCard ) {
				const s = parseInt( repeaterCard.getAttribute( 'data-s' ), 10 );
				const f = parseInt( repeaterCard.getAttribute( 'data-f' ), 10 );
				const grid = repeaterCard.querySelector( '.wek-builder__repeater-canvas' );
				setDropTarget( repeaterCard );
				if ( grid ) {
					placeDropLine( grid, null );
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

			const dragPackId = dragCard && dragCard.getAttribute( 'data-pack-id' );
			const dragMemberId =
				dragCard && dragCard.getAttribute( 'data-pack-member' )
					? dragCard.getAttribute( 'data-pack-member' )
					: '';

			const packEl = elUnder.closest( '.wek-builder__pack' );
			if ( packEl && packEl !== dragCard ) {
				const s = parseInt( packEl.getAttribute( 'data-s' ), 10 );
				const start = parseInt( packEl.getAttribute( 'data-pack-start' ), 10 );
				const len = parseInt( packEl.getAttribute( 'data-pack-len' ), 10 );
				const targetPackId = packEl.getAttribute( 'data-pack-id' ) || '';
				const rect = packEl.getBoundingClientRect();
				const before = clientY < rect.top + rect.height / 2;

				// Reorder inside the same pack (field drag on member cards handled below).
				if ( ! dragPackId && dragMemberId && dragMemberId === targetPackId ) {
					const fieldCard = elUnder.closest( '.wek-builder__field:not([data-n])' );
					if ( fieldCard && fieldCard !== dragCard ) {
						const f = parseInt( fieldCard.getAttribute( 'data-f' ), 10 );
						const fRect = fieldCard.getBoundingClientRect();
						const fBefore = clientY < fRect.top + fRect.height / 2;
						placeDropLine(
							fieldCard.parentNode,
							fBefore ? fieldCard : fieldCard.nextSibling
						);
						return { scope: 'section', s: s, f: null, index: fBefore ? f : f + 1 };
					}
				}

				// Foreign field or whole pack: snap before/after the pack unit.
				placeDropLine( packEl.parentNode, before ? packEl : packEl.nextSibling );
				return {
					scope: 'section',
					s: s,
					f: null,
					index: before ? start : start + len,
				};
			}

			const fieldCard = elUnder.closest( '.wek-builder__field:not([data-n])' );
			if ( fieldCard && fieldCard !== dragCard && fieldCard.getAttribute( 'data-repeater' ) !== '1' ) {
				const s = parseInt( fieldCard.getAttribute( 'data-s' ), 10 );
				const f = parseInt( fieldCard.getAttribute( 'data-f' ), 10 );
				const fields =
					schema.sections[ s ] && Array.isArray( schema.sections[ s ].fields )
						? schema.sections[ s ].fields
						: [];
				const run = getPackRun( fields, f );
				if ( run && ( dragPackId || dragMemberId !== run.id ) ) {
					const wrap = fieldCard.closest( '.wek-builder__pack' );
					const rect = ( wrap || fieldCard ).getBoundingClientRect();
					const before = clientY < rect.top + rect.height / 2;
					placeDropLine(
						( wrap || fieldCard ).parentNode,
						before ? wrap || fieldCard : ( wrap || fieldCard ).nextSibling
					);
					return {
						scope: 'section',
						s: s,
						f: null,
						index: before ? run.start : run.start + run.length,
					};
				}
				const rect = fieldCard.getBoundingClientRect();
				const before = clientY < rect.top + rect.height / 2;
				placeDropLine(
					fieldCard.parentNode,
					before ? fieldCard : fieldCard.nextSibling
				);
				return { scope: 'section', s: s, f: null, index: before ? f : f + 1 };
			}

			const sectionCard = elUnder.closest( '.wek-builder__section' );
			if ( sectionCard ) {
				const s = parseInt( sectionCard.getAttribute( 'data-s' ), 10 );
				const grid = sectionCard.querySelector( ':scope > .wek-builder__fields' );
				setDropTarget( sectionCard );
				if ( grid ) {
					placeDropLine( grid, null );
				}
				const len =
					schema.sections[ s ] && schema.sections[ s ].fields
						? schema.sections[ s ].fields.length
						: 0;
				return { scope: 'section', s: s, f: null, index: len };
			}

			return null;
		}

		function bindPackDrag( handle, packEl, fromS, start, length ) {
			handle.addEventListener( 'pointerdown', function ( event ) {
				if ( event.button !== 0 ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				packEl.classList.add( 'is-dragging' );
				handle.setAttribute( 'aria-grabbed', 'true' );

				const startX = event.clientX;
				const startY = event.clientY;
				let started = false;
				let dropLoc = null;

				const onMove = function ( e ) {
					const dx = e.clientX - startX;
					const dy = e.clientY - startY;
					if ( ! started && Math.abs( dx ) + Math.abs( dy ) < 6 ) {
						return;
					}
					started = true;
					scheduleDropResolve( e.clientX, e.clientY, packEl, function ( loc ) {
						dropLoc = loc;
					} );
				};

				const onUp = function () {
					document.removeEventListener( 'pointermove', onMove );
					document.removeEventListener( 'pointerup', onUp );
					document.removeEventListener( 'pointercancel', onUp );
					handle.setAttribute( 'aria-grabbed', 'false' );
					packEl.classList.remove( 'is-dragging' );
					clearDropIndicators();
					if ( ! started ) {
						selectItem( { type: 'field', sIndex: fromS, fIndex: start } );
						return;
					}
					if ( ! dropLoc || dropLoc.scope !== 'section' ) {
						return;
					}
					relocatePack( fromS, start, length, dropLoc.s, dropLoc.index );
				};

				document.addEventListener( 'pointermove', onMove );
				document.addEventListener( 'pointerup', onUp );
				document.addEventListener( 'pointercancel', onUp );
			} );
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

				const onMove = function ( e ) {
					const dx = e.clientX - startX;
					const dy = e.clientY - startY;
					if ( ! started && Math.abs( dx ) + Math.abs( dy ) < 6 ) {
						return;
					}
					started = true;
					scheduleDropResolve( e.clientX, e.clientY, card, function ( loc ) {
						dropLoc = loc;
					} );
				};

				const onUp = function () {
					document.removeEventListener( 'pointermove', onMove );
					document.removeEventListener( 'pointerup', onUp );
					document.removeEventListener( 'pointercancel', onUp );
					handle.setAttribute( 'aria-grabbed', 'false' );
					card.classList.remove( 'is-dragging' );
					clearDropIndicators();
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

		function fieldBodyChildren( field ) {
			const kids = [];
			if ( ! usesChoiceLabel( field.type ) ) {
				kids.push( buildFieldLabelEl( field ) );
			} else if ( field.show_label !== false ) {
				kids.push( buildFieldLabelEl( field ) );
			}
			kids.push( renderFieldPreview( field ) );
			return kids;
		}

		function renderFieldPreview( field ) {
			const type = field.type || 'text';
			const hint = field.placeholder || field.help || '';

			if ( type === 'checkbox' || type === 'consent' ) {
				const labelText =
					type === 'consent'
						? consentPreviewLabel( field )
						: fieldChoiceLabel( field );
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--toggle',
					'aria-hidden': 'true',
				}, [
					el( 'span', { className: 'wek-builder__choice-mark wek-builder__choice-mark--checkbox' } ),
					el( 'span', {
						className: 'wek-builder__choice-label',
						text:
							labelText ||
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
			if ( type === 'matrix' ) {
				return renderMatrixPreview( field );
			}
			if ( type === 'radio' || type === 'radio_image' ) {
				return previewChoiceRows( field, 'radio' );
			}

			if ( type === 'select' ) {
				const options = Array.isArray( field.options ) ? field.options : [];
				const defaultVal = String( field.default_value || '' );
				let previewText =
					hint || i18n.pleaseSelect || i18n.selectPreview || 'Please select…';
				if ( defaultVal ) {
					const match = options.filter( function ( opt ) {
						return String( ( opt && opt.value ) || '' ) === defaultVal;
					} )[ 0 ];
					previewText =
						( match && ( match.label || match.value ) ) || previewText;
				}
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--select',
					'aria-hidden': 'true',
					text: previewText,
				} );
			}

			if ( type === 'textarea' ) {
				const taRows = Math.min(
					40,
					Math.max(
						1,
						parseInt(
							( field.type_options && field.type_options.rows ) || 3,
							10
						) || 3
					)
				);
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--textarea',
					'aria-hidden': 'true',
					style: '--wek-ta-rows: ' + taRows,
					text: hint,
				} );
			}

			if ( type === 'number' ) {
				return el( 'div', {
					className: 'wek-builder__field-preview wek-builder__field-preview--number',
					'aria-hidden': 'true',
					text: hint || '0',
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
			copy.id = ( copy.type || 'field' ) + '_' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 6 );
			if ( copy.type === 'repeater' && copy.type_options && Array.isArray( copy.type_options.fields ) ) {
				copy.type_options.fields = copy.type_options.fields.map( function ( child ) {
					return cloneFieldDeep( child );
				} );
			}
			return copy;
		}

		function newBuilderUid( prefix ) {
			return (
				String( prefix || 'item' ) +
				'_' +
				Date.now().toString( 36 ) +
				Math.random().toString( 36 ).slice( 2, 7 )
			);
		}

		function remapConditionFieldRef( ref, idMap ) {
			const raw = String( ref || '' );
			if ( ! raw ) {
				return '';
			}
			const dot = raw.indexOf( '.' );
			const base = dot === -1 ? raw : raw.slice( 0, dot );
			const rest = dot === -1 ? '' : raw.slice( dot );
			if ( ! idMap[ base ] ) {
				return '';
			}
			return idMap[ base ] + rest;
		}

		function remapShowWhenRules( showWhen, idMap ) {
			if ( ! showWhen || typeof showWhen !== 'object' ) {
				return null;
			}
			const rulesIn = Array.isArray( showWhen.rules ) ? showWhen.rules : [];
			const rules = [];
			rulesIn.forEach( function ( rule ) {
				if ( ! rule || ! rule.field ) {
					return;
				}
				const nextField = remapConditionFieldRef( rule.field, idMap );
				if ( ! nextField ) {
					return;
				}
				rules.push( {
					field: nextField,
					op: String( rule.op || 'equals' ),
					value: rule.value != null ? String( rule.value ) : '',
				} );
			} );
			if ( ! rules.length ) {
				return null;
			}
			return {
				relation: String( showWhen.relation || 'AND' ).toUpperCase() === 'OR' ? 'OR' : 'AND',
				rules: rules,
			};
		}

		function cloneSectionForPaste( section ) {
			const copy = JSON.parse( JSON.stringify( section || {} ) );
			const idMap = {};

			function reIdField( field ) {
				const oldId = field.id;
				field.id = newBuilderUid( field.type || 'field' );
				if ( oldId ) {
					idMap[ String( oldId ) ] = field.id;
				}
				if (
					field.type === 'repeater' &&
					field.type_options &&
					Array.isArray( field.type_options.fields )
				) {
					field.type_options.fields.forEach( reIdField );
				}
			}

			copy.id = newBuilderUid( 'section' );
			if ( ! Array.isArray( copy.fields ) ) {
				copy.fields = [];
			}
			copy.fields.forEach( reIdField );

			function remapTarget( target ) {
				target.show_when = remapShowWhenRules( target.show_when, idMap );
			}

			remapTarget( copy );
			( function walk( fields ) {
				fields.forEach( function ( field ) {
					remapTarget( field );
					if (
						field.type === 'repeater' &&
						field.type_options &&
						Array.isArray( field.type_options.fields )
					) {
						walk( field.type_options.fields );
					}
				} );
			} )( copy.fields );

			if ( typeof copy.show_title === 'undefined' ) {
				copy.show_title = true;
			}
			if ( typeof copy.intro !== 'string' ) {
				copy.intro = '';
			}
			return copy;
		}

		function buildSectionClipboardPayload( section ) {
			return JSON.stringify( {
				wek_formkit: 'section',
				version: 1,
				section: section,
			} );
		}

		function parseSectionClipboardPayload( text ) {
			if ( ! text || typeof text !== 'string' ) {
				return null;
			}
			let data;
			try {
				data = JSON.parse( text );
			} catch ( e ) {
				return null;
			}
			if ( ! data || data.wek_formkit !== 'section' || ! data.section || typeof data.section !== 'object' ) {
				return null;
			}
			return data.section;
		}

		function copySectionToClipboard( section ) {
			const payload = buildSectionClipboardPayload( section );
			try {
				window.localStorage.setItem( SECTION_CLIP_KEY, payload );
			} catch ( e ) {
				// ignore
			}
			if ( navigator.clipboard && typeof navigator.clipboard.writeText === 'function' ) {
				navigator.clipboard.writeText( payload ).catch( function () {
					// localStorage fallback already set
				} );
			}
			announce( i18n.sectionCopied || 'Section copied. Paste it in this or another form.' );
		}

		function readSectionClipboardText() {
			if ( navigator.clipboard && typeof navigator.clipboard.readText === 'function' ) {
				return navigator.clipboard.readText().catch( function () {
					try {
						return window.localStorage.getItem( SECTION_CLIP_KEY ) || '';
					} catch ( e ) {
						return '';
					}
				} );
			}
			try {
				return Promise.resolve( window.localStorage.getItem( SECTION_CLIP_KEY ) || '' );
			} catch ( e ) {
				return Promise.resolve( '' );
			}
		}

		function pasteSectionFromClipboard() {
			readSectionClipboardText().then( function ( text ) {
				let source = parseSectionClipboardPayload( text );
				if ( ! source ) {
					try {
						source = parseSectionClipboardPayload(
							window.localStorage.getItem( SECTION_CLIP_KEY ) || ''
						);
					} catch ( e ) {
						source = null;
					}
				}
				if ( ! source ) {
					announce(
						text
							? i18n.sectionPasteInvalid || 'Clipboard does not contain a Formkit section.'
							: i18n.sectionPasteEmpty || 'No section in the clipboard. Copy a section first.'
					);
					return;
				}
				const next = cloneSectionForPaste( source );
				schema.sections.push( next );
				setSectionCollapsed( next.id, false );
				selection = { type: 'section', sIndex: schema.sections.length - 1 };
				activeTab = 'general';
				syncHidden();
				render();
				announce( i18n.sectionPasted || 'Section pasted. Review field IDs and conditions, then save.' );
			} );
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
					el( 'div', { className: 'wek-builder__field-body' }, fieldBodyChildren( child ) ),
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
						selection = {
							type: 'nested',
							sIndex: sIndex,
							fIndex: fIndex,
							nIndex: nIndex + 1,
						};
						syncHidden();
						render();
					},
					onMoveUp: function () {
						const next = moveInArray( nestedList, nIndex, nIndex - 1 );
						selection = {
							type: 'nested',
							sIndex: sIndex,
							fIndex: fIndex,
							nIndex: next,
						};
						announce( i18n.moved || 'Item moved.' );
						syncHidden();
						render();
					},
					onMoveDown: function () {
						const next = moveInArray( nestedList, nIndex, nIndex + 1 );
						selection = {
							type: 'nested',
							sIndex: sIndex,
							fIndex: fIndex,
							nIndex: next,
						};
						announce( i18n.moved || 'Item moved.' );
						syncHidden();
						render();
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
					buildFieldLabelEl( field ),
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
						selection = { type: 'field', sIndex: sIndex, fIndex: fIndex + 1 };
						syncHidden();
						render();
					},
					onMoveUp: function () {
						const next = moveInArray( section.fields, fIndex, fIndex - 1 );
						selection = { type: 'field', sIndex: sIndex, fIndex: next };
						announce( i18n.moved || 'Item moved.' );
						syncHidden();
						render();
					},
					onMoveDown: function () {
						const next = moveInArray( section.fields, fIndex, fIndex + 1 );
						selection = { type: 'field', sIndex: sIndex, fIndex: next };
						announce( i18n.moved || 'Item moved.' );
						syncHidden();
						render();
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
			bindFieldWidthResize( resize, field, card );
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
			const memberId = packGroupId( field );
			const run = memberId ? getPackRun( section.fields || [], fIndex ) : null;
			const cardAttrs = {
				className: widthClass( field.width, selected ),
				'data-s': String( sIndex ),
				'data-f': String( fIndex ),
				tabindex: '0',
				role: 'button',
				'aria-pressed': selected ? 'true' : 'false',
			};
			if ( memberId ) {
				cardAttrs['data-pack-member'] = memberId;
			}
			const card = el( 'div', cardAttrs );

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
				el( 'div', { className: 'wek-builder__field-body' }, fieldBodyChildren( field ) ),
			] );
			card.appendChild( main );

			const fieldsLen = ( section.fields || [] ).length;
			const canUp = run ? fIndex > run.start : fIndex > 0;
			const canDown = run
				? fIndex < run.start + run.length - 1
				: fIndex < fieldsLen - 1;

			card.appendChild(
				renderFieldToolbar( {
					canUp: canUp,
					canDown: canDown,
					onEdit: function () {
						selectItem( { type: 'field', sIndex: sIndex, fIndex: fIndex }, 'general' );
					},
					onDuplicate: function () {
						const copy = cloneFieldDeep( field );
						section.fields.splice( fIndex + 1, 0, copy );
						selection = { type: 'field', sIndex: sIndex, fIndex: fIndex + 1 };
						syncHidden();
						render();
					},
					onMoveUp: function () {
						if ( ! canUp ) {
							return;
						}
						const next = moveInArray( section.fields, fIndex, fIndex - 1 );
						selection = { type: 'field', sIndex: sIndex, fIndex: next };
						announce( i18n.moved || 'Item moved.' );
						syncHidden();
						render();
					},
					onMoveDown: function () {
						if ( ! canDown ) {
							return;
						}
						const next = moveInArray( section.fields, fIndex, fIndex + 1 );
						selection = { type: 'field', sIndex: sIndex, fIndex: next };
						announce( i18n.moved || 'Item moved.' );
						syncHidden();
						render();
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
			bindFieldWidthResize( resize, field, card );
			return card;
		}

		function renderPackGroup( section, sIndex, run ) {
			const fields = section.fields || [];
			const wrap = el( 'div', {
				className: 'wek-builder__pack',
				'data-s': String( sIndex ),
				'data-pack-id': run.id,
				'data-pack-start': String( run.start ),
				'data-pack-len': String( run.length ),
			} );

			const handle = el( 'button', {
				type: 'button',
				className: 'wek-builder__handle',
				title: i18n.packDragHandle || 'Drag template group',
				'aria-label': i18n.packDragHandle || 'Drag template group',
				'aria-grabbed': 'false',
				text: '⋮⋮',
			} );

			const head = el( 'div', { className: 'wek-builder__pack-head' }, [
				handle,
				el( 'strong', {
					className: 'wek-builder__pack-title',
					text: packLabel( run.pack ),
				} ),
			] );
			wrap.appendChild( head );

			const canUp = run.start > 0;
			const canDown = run.start + run.length < fields.length;

			wrap.appendChild(
				renderFieldToolbar( {
					canUp: canUp,
					canDown: canDown,
					onEdit: function () {
						selectItem( {
							type: 'field',
							sIndex: sIndex,
							fIndex: run.start,
						}, 'general' );
					},
					onDuplicate: function () {
						const copies = [];
						const newGroup = {
							id: nextFieldId( 'pack' ),
							pack: run.pack,
						};
						for ( let i = 0; i < run.length; i += 1 ) {
							const copy = cloneFieldDeep( fields[ run.start + i ] );
							copy.pack_group = { id: newGroup.id, pack: newGroup.pack };
							copies.push( copy );
						}
						Array.prototype.splice.apply(
							fields,
							[ run.start + run.length, 0 ].concat( copies )
						);
						selection = {
							type: 'field',
							sIndex: sIndex,
							fIndex: run.start + run.length,
						};
						syncHidden();
						render();
					},
					onMoveUp: function () {
						if ( ! canUp ) {
							return;
						}
						const prevRun = getPackRun( fields, run.start - 1 );
						const dest = prevRun ? prevRun.start : run.start - 1;
						relocatePack( sIndex, run.start, run.length, sIndex, dest );
					},
					onMoveDown: function () {
						if ( ! canDown ) {
							return;
						}
						const after = run.start + run.length;
						const nextRun = getPackRun( fields, after );
						const dest = nextRun
							? nextRun.start + nextRun.length
							: after + 1;
						relocatePack( sIndex, run.start, run.length, sIndex, dest );
					},
					onDelete: function () {
						if (
							! window.confirm(
								i18n.packConfirmDelete ||
									'Remove this template group and all its fields?'
							)
						) {
							return;
						}
						fields.splice( run.start, run.length );
						selection = null;
						syncHidden();
						render();
					},
				} )
			);

			const ungroupBtn = toolbarIconButton(
				'dashicons-editor-unlink',
				i18n.packUngroup || 'Ungroup',
				function () {
					for ( let i = run.start; i < run.start + run.length; i += 1 ) {
						clearPackGroup( fields[ i ] );
					}
					announce( i18n.packUngrouped || 'Template group ungrouped.' );
					syncHidden();
					render();
				}
			);
			const toolbar = wrap.querySelector( '.wek-builder__field-toolbar' );
			if ( toolbar && toolbar.lastChild ) {
				toolbar.insertBefore( ungroupBtn, toolbar.lastChild );
			} else if ( toolbar ) {
				toolbar.appendChild( ungroupBtn );
			}

			const inner = el( 'div', { className: 'wek-builder__pack-fields' } );
			for ( let i = 0; i < run.length; i += 1 ) {
				const fIndex = run.start + i;
				inner.appendChild( renderFieldCard( section, fields[ fIndex ], sIndex, fIndex ) );
			}
			wrap.appendChild( inner );

			bindPackDrag( handle, wrap, sIndex, run.start, run.length );
			return wrap;
		}

		function renderSectionCard( section, sIndex ) {
			const selected = selection && selection.type === 'section' && selection.sIndex === sIndex;
			const collapsed = isSectionCollapsed( section );
			const box = el( 'div', {
				className:
					'wek-builder__section' +
					( selected ? ' is-selected' : '' ) +
					( collapsed ? ' is-collapsed' : '' ),
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

			const toggle = el( 'button', {
				type: 'button',
				className: 'wek-builder__section-toggle',
				title: collapsed
					? i18n.expandSection || 'Expand section'
					: i18n.collapseSection || 'Collapse section',
				'aria-label': collapsed
					? i18n.expandSection || 'Expand section'
					: i18n.collapseSection || 'Collapse section',
				'aria-expanded': collapsed ? 'false' : 'true',
			} );
			toggle.appendChild(
				el( 'span', {
					className:
						'dashicons ' +
						( collapsed ? 'dashicons-arrow-right-alt2' : 'dashicons-arrow-down-alt2' ),
					'aria-hidden': 'true',
				} )
			);
			toggle.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				if ( ! section.id ) {
					section.id = newBuilderUid( 'section' );
					syncHidden();
				}
				setSectionCollapsed( section.id, ! collapsed );
				refreshCanvas();
			} );

			const fieldCount = ( section.fields || [] ).length;
			const head = el( 'div', { className: 'wek-builder__section-head' }, [
				handle,
				toggle,
				el( 'strong', {
					className:
						'wek-builder__section-title' +
						( section.show_title === false ? ' is-title-hidden' : '' ),
					text: section.title || section.id || ( i18n.section || 'Section' ) + ' ' + ( sIndex + 1 ),
				} ),
			] );
			if ( section.show_title === false ) {
				head.appendChild(
					el( 'span', {
						className: 'wek-builder__section-title-badge',
						text: i18n.titleHiddenBadge || 'Hidden on form',
					} )
				);
			}
			if ( collapsed && fieldCount > 0 ) {
				head.appendChild(
					el( 'span', {
						className: 'wek-builder__section-count',
						text: String( fieldCount ),
						title: String( fieldCount ),
					} )
				);
			}
			head.addEventListener( 'click', function ( e ) {
				if (
					e.target.closest( '.wek-builder__handle' ) ||
					e.target.closest( '.wek-builder__section-toggle' ) ||
					e.target.closest( '.wek-builder__section-toolbar' )
				) {
					return;
				}
				selectItem( { type: 'section', sIndex: sIndex } );
			} );
			box.appendChild( head );

			const sectionToolbar = el( 'div', {
				className: 'wek-builder__section-toolbar',
				role: 'toolbar',
				'aria-label': i18n.sectionActions || 'Section actions',
			} );
			sectionToolbar.appendChild(
				toolbarIconButton(
					'dashicons-edit',
					i18n.edit || 'Edit',
					function () {
						selectItem( { type: 'section', sIndex: sIndex }, 'general' );
					},
					'edit'
				)
			);
			sectionToolbar.appendChild(
				toolbarIconButton(
					'dashicons-clipboard',
					i18n.copySection || 'Copy section',
					function () {
						copySectionToClipboard( section );
					}
				)
			);
			sectionToolbar.appendChild(
				toolbarIconButton(
					'dashicons-admin-page',
					i18n.duplicate || 'Duplicate',
					function () {
						const next = cloneSectionForPaste( section );
						schema.sections.splice( sIndex + 1, 0, next );
						setSectionCollapsed( next.id, false );
						selection = { type: 'section', sIndex: sIndex + 1 };
						activeTab = 'general';
						syncHidden();
						render();
					}
				)
			);
			const upBtn = toolbarIconButton(
				'dashicons-arrow-up-alt2',
				i18n.moveUp || 'Move up',
				function () {
					const next = moveInArray( schema.sections, sIndex, sIndex - 1 );
					selection = { type: 'section', sIndex: next };
					announce( i18n.moved || 'Item moved.' );
					syncHidden();
					render();
				}
			);
			upBtn.disabled = sIndex <= 0;
			sectionToolbar.appendChild( upBtn );
			const downBtn = toolbarIconButton(
				'dashicons-arrow-down-alt2',
				i18n.moveDown || 'Move down',
				function () {
					const next = moveInArray( schema.sections, sIndex, sIndex + 1 );
					selection = { type: 'section', sIndex: next };
					announce( i18n.moved || 'Item moved.' );
					syncHidden();
					render();
				}
			);
			downBtn.disabled = sIndex >= schema.sections.length - 1;
			sectionToolbar.appendChild( downBtn );
			sectionToolbar.appendChild(
				toolbarIconButton(
					'dashicons-trash',
					i18n.remove || 'Remove',
					function () {
						if ( ! window.confirm( i18n.confirmDel || 'Remove this item?' ) ) {
							return;
						}
						schema.sections.splice( sIndex, 1 );
						selection = null;
						syncHidden();
						render();
					},
					'danger'
				)
			);
			box.appendChild( sectionToolbar );

			const introText = String( section.intro || '' ).trim();
			const introEl = el( 'p', {
				className: 'wek-builder__section-intro',
				text: introText,
			} );
			if ( ! introText ) {
				introEl.hidden = true;
			}
			box.appendChild( introEl );

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
				let i = 0;
				while ( i < fields.length ) {
					const run = getPackRun( fields, i );
					if ( run && run.start === i ) {
						grid.appendChild( renderPackGroup( section, sIndex, run ) );
						i += run.length;
					} else {
						grid.appendChild( renderFieldCard( section, fields[ i ], sIndex, i ) );
						i += 1;
					}
				}
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
				if ( section.id ) {
					setSectionCollapsed( section.id, false );
				}
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
					clearDropIndicators();
				}

				const onMove = function ( e ) {
					const dx = e.clientX - startX;
					const dy = e.clientY - startY;
					if ( ! started && Math.abs( dx ) + Math.abs( dy ) < 6 ) {
						return;
					}
					started = true;
					tile.classList.add( 'is-dragging' );
					scheduleDropResolve( e.clientX, e.clientY, null, function ( loc ) {
						dropLoc = loc;
					} );
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
					show_title: true,
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

		function insertFieldsAt( fields, dropLoc ) {
			if ( ! fields || ! fields.length ) {
				return;
			}

			let sIndex = 0;
			let at = 0;

			if ( dropLoc && dropLoc.scope === 'section' && schema.sections[ dropLoc.s ] ) {
				sIndex = dropLoc.s;
				const section = schema.sections[ sIndex ];
				section.fields = section.fields || [];
				if ( section.id ) {
					setSectionCollapsed( section.id, false );
				}
				at = typeof dropLoc.index === 'number' ? dropLoc.index : section.fields.length;
				at = Math.max( 0, Math.min( at, section.fields.length ) );
			} else {
				if ( ! schema.sections.length ) {
					schema.sections.push( {
						id: 'section_' + Date.now(),
						title: i18n.section || 'Section',
						show_title: true,
						intro: '',
						show_when: null,
						fields: [],
					} );
				}
				if ( selection && selection.type === 'field' && typeof selection.sIndex === 'number' ) {
					sIndex = selection.sIndex;
				} else if ( selection && selection.type === 'section' && typeof selection.sIndex === 'number' ) {
					sIndex = selection.sIndex;
				} else {
					sIndex = schema.sections.length - 1;
				}
				const section = schema.sections[ sIndex ];
				section.fields = section.fields || [];
				at = section.fields.length;
			}

			const section = schema.sections[ sIndex ];
			Array.prototype.splice.apply( section.fields, [ at, 0 ].concat( fields ) );
			selection = { type: 'field', sIndex: sIndex, fIndex: at };
			activeTab = 'general';
			syncHidden();
			render();
		}

		function closePackComposer() {
			const existing = document.querySelector( '.wek-pack-composer' );
			if ( existing && existing.parentNode ) {
				existing.parentNode.removeChild( existing );
			}
		}

		function openPackComposer( packId, dropLoc ) {
			closePackComposer();
			const pack = fieldPacks[ packId ];
			if ( ! pack ) {
				return;
			}

			const slotsState = clonePackSlots( packId );
			const catalog = countryPresetsBoot.catalog || {};
			const initialPreset = countryPresetsBoot.defaultPreset || 'dach';
			const initialMeta = catalog[ initialPreset ] || {};
			const countryOpts = {
				preset: initialPreset,
				includeOther: true,
				priority: Array.isArray( initialMeta.default_priority )
					? initialMeta.default_priority.slice()
					: [],
				defaultCountry: '',
			};
			if ( typeof initialMeta.include_other_default !== 'undefined' ) {
				countryOpts.includeOther = !! initialMeta.include_other_default;
			}
			const home = countryPresetsBoot.homeCountry || '';
			if ( home && countryOpts.priority.indexOf( home ) === -1 ) {
				const presetOpts = initialMeta.options || [];
				if (
					presetOpts.some( function ( o ) {
						return o.value === home;
					} )
				) {
					countryOpts.priority.unshift( home );
				}
			}
			if ( home && countryOpts.priority.indexOf( home ) !== -1 ) {
				countryOpts.defaultCountry = home;
			}

			const overlay = el( 'div', {
				className: 'wek-pack-composer',
				role: 'dialog',
				'aria-modal': 'true',
			} );
			const panel = el( 'div', { className: 'wek-pack-composer__panel' } );
			const title =
				packId === 'address'
					? i18n.addressPackTitle || pack.label
					: i18n.namePackTitle || pack.label;
			panel.appendChild(
				el( 'h2', { className: 'wek-pack-composer__title', text: title } )
			);

			const list = el( 'ul', { className: 'wek-pack-composer__slots' } );

			function refreshList() {
				list.innerHTML = '';
				slotsState.forEach( function ( slot, index ) {
					const row = el( 'li', { className: 'wek-pack-composer__slot' } );
					const check = el( 'input', {
						type: 'checkbox',
						checked: slot.enabled ? 'checked' : undefined,
					} );
					check.addEventListener( 'change', function () {
						slot.enabled = !! check.checked;
						refreshCountryBlock();
					} );
					const labelText = roleLabels[ slot.role ] || slot.role;
					row.appendChild(
						el( 'label', { className: 'wek-pack-composer__slot-label' }, [
							check,
							el( 'span', { text: labelText } ),
						] )
					);

					const up = el( 'button', {
						type: 'button',
						className: 'button-link',
						text: '↑',
						title: i18n.packMoveUp || 'Move up',
						disabled: index === 0 ? 'disabled' : undefined,
					} );
					up.addEventListener( 'click', function () {
						if ( index <= 0 ) {
							return;
						}
						const tmp = slotsState[ index - 1 ];
						slotsState[ index - 1 ] = slotsState[ index ];
						slotsState[ index ] = tmp;
						refreshList();
						refreshCountryBlock();
					} );
					const down = el( 'button', {
						type: 'button',
						className: 'button-link',
						text: '↓',
						title: i18n.packMoveDown || 'Move down',
						disabled: index === slotsState.length - 1 ? 'disabled' : undefined,
					} );
					down.addEventListener( 'click', function () {
						if ( index >= slotsState.length - 1 ) {
							return;
						}
						const tmp = slotsState[ index + 1 ];
						slotsState[ index + 1 ] = slotsState[ index ];
						slotsState[ index ] = tmp;
						refreshList();
						refreshCountryBlock();
					} );
					row.appendChild( el( 'span', { className: 'wek-pack-composer__moves' }, [ up, down ] ) );
					list.appendChild( row );
				} );
			}

			panel.appendChild( list );

			/* Country options only apply to packs that include a country slot (Address). */
			const packHasCountry = slotsState.some( function ( s ) {
				return s.role === 'country';
			} );

			const countryBlock = el( 'div', {
				className: 'wek-pack-composer__country',
				hidden: 'hidden',
			} );
			const presetSelect = el( 'select', {
				className: 'wek-pack-composer__preset',
			} );
			Object.keys( catalog ).forEach( function ( id ) {
				const opt = el( 'option', {
					value: id,
					text: catalog[ id ].label || id,
				} );
				if ( id === countryOpts.preset ) {
					opt.selected = true;
				}
				presetSelect.appendChild( opt );
			} );
			presetSelect.addEventListener( 'change', function () {
				countryOpts.preset = presetSelect.value;
				const meta = catalog[ countryOpts.preset ] || {};
				if ( typeof meta.include_other_default !== 'undefined' ) {
					otherCheck.checked = !! meta.include_other_default;
					countryOpts.includeOther = otherCheck.checked;
				}
				countryOpts.priority = Array.isArray( meta.default_priority )
					? meta.default_priority.slice()
					: [];
				if (
					countryOpts.defaultCountry &&
					countryOpts.priority.indexOf( countryOpts.defaultCountry ) === -1
				) {
					const stillThere = ( meta.options || [] ).some( function ( o ) {
						return o.value === countryOpts.defaultCountry;
					} );
					if ( ! stillThere ) {
						countryOpts.defaultCountry = '';
					}
				}
				refreshPriorityUi();
				refreshDefaultSelect();
			} );
			const otherCheck = el( 'input', {
				type: 'checkbox',
				checked: countryOpts.includeOther ? 'checked' : undefined,
			} );
			otherCheck.addEventListener( 'change', function () {
				countryOpts.includeOther = !! otherCheck.checked;
			} );
			countryBlock.appendChild(
				el( 'label', { className: 'wek-pack-composer__country-preset' }, [
					el( 'span', { text: i18n.countryList || 'Country list' } ),
					presetSelect,
				] )
			);
			countryBlock.appendChild(
				el( 'label', { className: 'wek-pack-composer__country-other' }, [
					otherCheck,
					el( 'span', {
						text:
							i18n.includeOtherCountry ||
							'Include “Other” (shows a text field when selected)',
					} ),
				] )
			);

			const priorityWrap = el( 'div', { className: 'wek-pack-composer__priority' } );
			priorityWrap.appendChild(
				el( 'strong', {
					text: i18n.countryPriority || 'Show first (priority order)',
				} )
			);
			priorityWrap.appendChild(
				el( 'p', {
					className: 'description',
					text:
						i18n.countryPriorityHint ||
						'These countries appear at the top of the list; the rest stay A–Z.',
				} )
			);
			const priorityList = el( 'ul', { className: 'wek-pack-composer__priority-list' } );
			const addPriority = el( 'select', { className: 'wek-pack-composer__priority-add' } );
			addPriority.addEventListener( 'change', function () {
				const code = addPriority.value;
				if ( ! code || countryOpts.priority.indexOf( code ) !== -1 ) {
					addPriority.value = '';
					return;
				}
				countryOpts.priority.push( code );
				addPriority.value = '';
				refreshPriorityUi();
				refreshDefaultSelect();
			} );

			function labelForCode( code ) {
				const meta = catalog[ countryOpts.preset ] || {};
				const hit = ( meta.options || [] ).find( function ( o ) {
					return o.value === code;
				} );
				return hit ? hit.label + ' (' + code + ')' : code;
			}

			function refreshPriorityUi() {
				priorityList.innerHTML = '';
				countryOpts.priority.forEach( function ( code, index ) {
					const row = el( 'li', { className: 'wek-pack-composer__priority-item' } );
					row.appendChild( el( 'span', { text: labelForCode( code ) } ) );
					const up = el( 'button', {
						type: 'button',
						className: 'button-link',
						text: '↑',
						disabled: index === 0 ? 'disabled' : undefined,
					} );
					up.addEventListener( 'click', function () {
						if ( index <= 0 ) {
							return;
						}
						const tmp = countryOpts.priority[ index - 1 ];
						countryOpts.priority[ index - 1 ] = countryOpts.priority[ index ];
						countryOpts.priority[ index ] = tmp;
						refreshPriorityUi();
					} );
					const down = el( 'button', {
						type: 'button',
						className: 'button-link',
						text: '↓',
						disabled:
							index === countryOpts.priority.length - 1 ? 'disabled' : undefined,
					} );
					down.addEventListener( 'click', function () {
						if ( index >= countryOpts.priority.length - 1 ) {
							return;
						}
						const tmp = countryOpts.priority[ index + 1 ];
						countryOpts.priority[ index + 1 ] = countryOpts.priority[ index ];
						countryOpts.priority[ index ] = tmp;
						refreshPriorityUi();
					} );
					const remove = el( 'button', {
						type: 'button',
						className: 'button-link',
						text: '×',
						title: i18n.remove || 'Remove',
					} );
					remove.addEventListener( 'click', function () {
						countryOpts.priority = countryOpts.priority.filter( function ( c ) {
							return c !== code;
						} );
						if ( countryOpts.defaultCountry === code ) {
							countryOpts.defaultCountry = '';
						}
						refreshPriorityUi();
						refreshDefaultSelect();
					} );
					row.appendChild(
						el( 'span', { className: 'wek-pack-composer__moves' }, [ up, down, remove ] )
					);
					priorityList.appendChild( row );
				} );

				addPriority.innerHTML = '';
				addPriority.appendChild(
					el( 'option', {
						value: '',
						text: i18n.countryAddPriority || 'Add country to top',
					} )
				);
				const meta = catalog[ countryOpts.preset ] || {};
				( meta.options || [] ).forEach( function ( o ) {
					if ( countryOpts.priority.indexOf( o.value ) !== -1 ) {
						return;
					}
					addPriority.appendChild(
						el( 'option', {
							value: o.value,
							text: o.label + ' (' + o.value + ')',
						} )
					);
				} );
			}

			priorityWrap.appendChild( priorityList );
			priorityWrap.appendChild( addPriority );
			countryBlock.appendChild( priorityWrap );

			const defaultSelect = el( 'select', { className: 'wek-pack-composer__default' } );
			function refreshDefaultSelect() {
				defaultSelect.innerHTML = '';
				defaultSelect.appendChild(
					el( 'option', {
						value: '',
						text: i18n.countryDefaultNone || 'None (placeholder)',
					} )
				);
				const meta = catalog[ countryOpts.preset ] || {};
				const codes = countryOpts.priority.length
					? countryOpts.priority.concat(
							( meta.options || [] )
								.map( function ( o ) {
									return o.value;
								} )
								.filter( function ( c ) {
									return countryOpts.priority.indexOf( c ) === -1;
								} )
					  )
					: ( meta.options || [] ).map( function ( o ) {
							return o.value;
					  } );
				codes.forEach( function ( code ) {
					defaultSelect.appendChild(
						el( 'option', {
							value: code,
							text: labelForCode( code ),
						} )
					);
				} );
				defaultSelect.value = countryOpts.defaultCountry || '';
			}
			defaultSelect.addEventListener( 'change', function () {
				countryOpts.defaultCountry = defaultSelect.value || '';
				if (
					countryOpts.defaultCountry &&
					countryOpts.priority.indexOf( countryOpts.defaultCountry ) === -1
				) {
					countryOpts.priority.unshift( countryOpts.defaultCountry );
					refreshPriorityUi();
				}
			} );
			countryBlock.appendChild(
				el( 'label', { className: 'wek-pack-composer__country-preset' }, [
					el( 'span', { text: i18n.countryDefault || 'Pre-select' } ),
					defaultSelect,
				] )
			);

			if ( packHasCountry ) {
				panel.appendChild( countryBlock );
			}

			function refreshCountryBlock() {
				if ( ! packHasCountry ) {
					return;
				}
				const countryOn = slotsState.some( function ( s ) {
					return s.role === 'country' && s.enabled;
				} );
				countryBlock.hidden = ! countryOn;
				if ( countryOn ) {
					refreshPriorityUi();
					refreshDefaultSelect();
				}
			}

			refreshList();
			refreshCountryBlock();

			const actions = el( 'div', { className: 'wek-pack-composer__actions' } );
			const cancelBtn = el( 'button', {
				type: 'button',
				className: 'button',
				text: i18n.packCancel || 'Cancel',
			} );
			cancelBtn.addEventListener( 'click', closePackComposer );
			const insertBtn = el( 'button', {
				type: 'button',
				className: 'button button-primary',
				text: i18n.packInsert || 'Insert fields',
			} );
			insertBtn.addEventListener( 'click', function () {
				const enabled = slotsState.filter( function ( s ) {
					return s.enabled;
				} );
				if ( ! enabled.length ) {
					announce( i18n.packNeedOne || 'Enable at least one field.' );
					return;
				}
				const fields = buildPackFields( packId, slotsState, countryOpts );
				stampPackGroup( fields, packId );
				closePackComposer();
				insertFieldsAt( fields, dropLoc || null );
			} );
			actions.appendChild( cancelBtn );
			actions.appendChild( insertBtn );
			panel.appendChild( actions );

			overlay.appendChild( panel );
			overlay.addEventListener( 'click', function ( event ) {
				if ( event.target === overlay ) {
					closePackComposer();
				}
			} );
			document.body.appendChild( overlay );
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

			const tiles = [];

			const templatesHeading = el( 'h3', {
				className: 'wek-builder-library__heading',
				text: i18n.templatesLibrary || 'Templates',
			} );
			panel.appendChild( templatesHeading );
			const packGrid = el( 'div', { className: 'wek-builder-library__grid' } );
			[ 'name', 'address' ].forEach( function ( packId ) {
				const pack = fieldPacks[ packId ];
				if ( ! pack ) {
					return;
				}
				const label = pack.label || packId;
				const iconClass = pack.icon || 'dashicons-plus-alt2';
				const tile = el( 'button', {
					type: 'button',
					className: 'wek-builder-library__item wek-builder-library__item--pack',
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
				tile.addEventListener( 'click', function () {
					openPackComposer( packId, null );
				} );
				tiles.push( { node: tile, haystack: ( label + ' ' + packId + ' template' ).toLowerCase(), heading: templatesHeading } );
				packGrid.appendChild( tile );
			} );
			panel.appendChild( packGrid );

			const fieldsHeading = el( 'h3', {
				className: 'wek-builder-library__heading',
				text: i18n.fieldsLibrary || 'Fields',
			} );
			panel.appendChild( fieldsHeading );

			const grid = el( 'div', { className: 'wek-builder-library__grid' } );
			const empty = el( 'p', {
				className: 'wek-builder-library__empty',
				text: i18n.noFieldsMatch || 'No matching fields.',
			} );
			empty.hidden = true;

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
				tiles.push( { node: tile, haystack: ( label + ' ' + typeId ).toLowerCase(), heading: fieldsHeading } );
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
				templatesHeading.hidden = ! tiles.some( function ( t ) {
					return t.heading === templatesHeading && ! t.node.hidden;
				} );
				fieldsHeading.hidden = ! tiles.some( function ( t ) {
					return t.heading === fieldsHeading && ! t.node.hidden;
				} );
				packGrid.hidden = templatesHeading.hidden;
			}

			search.addEventListener( 'input', applyFilter );

			panel.appendChild( grid );
			panel.appendChild( empty );
			return panel;
		}

		function render( opts ) {
			opts = opts || {};
			ensureShell();
			if ( opts.canvas !== false ) {
				refreshCanvas();
			}
			if ( opts.sidebar !== false ) {
				refreshSidebar();
			}
			applyChrome( ui.layout );
			if ( opts.skipSync ) {
				return;
			}
			flushSyncHidden();
		}

		const ui = {
			layout: null,
			sheet: null,
			aside: null,
		};

		function ensureShell() {
			if ( ui.layout && root.contains( ui.layout ) ) {
				return;
			}

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

			const libWrap = el( 'div', { className: 'wek-builder-col wek-builder-col--lib' } );
			const libToggle = el(
				'button',
				{
					type: 'button',
					className: 'wek-builder-col__toggle',
					title: chrome.libCollapsed
						? i18n.expandLibrary || 'Expand fields library'
						: i18n.collapseLibrary || 'Collapse fields library',
					'aria-expanded': chrome.libCollapsed ? 'false' : 'true',
					'aria-label': chrome.libCollapsed
						? i18n.expandLibrary || 'Expand fields library'
						: i18n.collapseLibrary || 'Collapse fields library',
					onClick: function () {
						chrome.libCollapsed = ! chrome.libCollapsed;
						saveChrome();
						applyChrome( layout );
						libToggle.setAttribute( 'aria-expanded', chrome.libCollapsed ? 'false' : 'true' );
						libToggle.title = chrome.libCollapsed
							? i18n.expandLibrary || 'Expand fields library'
							: i18n.collapseLibrary || 'Collapse fields library';
						libToggle.setAttribute( 'aria-label', libToggle.title );
						const caret = libToggle.querySelector( '.wek-builder-col__caret' );
						if ( caret ) {
							caret.className =
								'wek-builder-col__caret wek-builder-col__caret--' +
								( chrome.libCollapsed ? 'right' : 'left' );
						}
					},
				},
				[
					el( 'span', {
						className:
							'wek-builder-col__caret wek-builder-col__caret--' +
							( chrome.libCollapsed ? 'right' : 'left' ),
						'aria-hidden': 'true',
					} ),
				]
			);
			const libResize = el( 'div', {
				className: 'wek-builder-resize wek-builder-resize--lib',
				role: 'separator',
				'aria-orientation': 'vertical',
				title: i18n.resizeLibrary || 'Drag to resize',
			} );
			bindChromeResize( libResize, 'lib', layout );
			libWrap.appendChild( libToggle );
			libWrap.appendChild( library );
			libWrap.appendChild( libResize );

			const sideWrap = el( 'div', { className: 'wek-builder-col wek-builder-col--side' } );
			const sideToggle = el(
				'button',
				{
					type: 'button',
					className: 'wek-builder-col__toggle',
					title: chrome.sideCollapsed
						? i18n.expandSettings || 'Expand field settings'
						: i18n.collapseSettings || 'Collapse field settings',
					'aria-expanded': chrome.sideCollapsed ? 'false' : 'true',
					'aria-label': chrome.sideCollapsed
						? i18n.expandSettings || 'Expand field settings'
						: i18n.collapseSettings || 'Collapse field settings',
					onClick: function () {
						chrome.sideCollapsed = ! chrome.sideCollapsed;
						saveChrome();
						applyChrome( layout );
						sideToggle.setAttribute( 'aria-expanded', chrome.sideCollapsed ? 'false' : 'true' );
						sideToggle.title = chrome.sideCollapsed
							? i18n.expandSettings || 'Expand field settings'
							: i18n.collapseSettings || 'Collapse field settings';
						sideToggle.setAttribute( 'aria-label', sideToggle.title );
						const caret = sideToggle.querySelector( '.wek-builder-col__caret' );
						if ( caret ) {
							caret.className =
								'wek-builder-col__caret wek-builder-col__caret--' +
								( chrome.sideCollapsed ? 'left' : 'right' );
						}
					},
				},
				[
					el( 'span', {
						className:
							'wek-builder-col__caret wek-builder-col__caret--' +
							( chrome.sideCollapsed ? 'left' : 'right' ),
						'aria-hidden': 'true',
					} ),
				]
			);
			const sideResize = el( 'div', {
				className: 'wek-builder-resize wek-builder-resize--side',
				role: 'separator',
				'aria-orientation': 'vertical',
				title: i18n.resizeSettings || 'Drag to resize',
			} );
			bindChromeResize( sideResize, 'side', layout );
			sideWrap.appendChild( sideResize );
			sideWrap.appendChild( sideToggle );
			sideWrap.appendChild( aside );

			layout.appendChild( libWrap );
			layout.appendChild( canvas );
			layout.appendChild( sideWrap );
			root.appendChild( layout );

			ui.layout = layout;
			ui.sheet = sheet;
			ui.aside = aside;
			applyChrome( layout );
			root.setAttribute( 'data-wek-builder-ready', '1' );
		}

		function refreshCanvas() {
			ensureShell();
			const sheet = ui.sheet;
			sheet.innerHTML = '';

			if ( ! schema.sections.length ) {
				sheet.appendChild(
					el( 'p', {
						className: 'description',
						text:
							i18n.empty ||
							'No sections yet. Add a section or pick a field from the library.',
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
						className: 'wek-builder__toolbar-btn wek-builder__toolbar-btn--accent',
						text: i18n.addSection || 'Add section',
						onClick: function () {
							schema.sections.push( {
								id: 'section_' + Date.now(),
								title: i18n.section || 'Section',
								show_title: true,
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
					el( 'button', {
						type: 'button',
						className: 'wek-builder__toolbar-btn',
						text: i18n.pasteSection || 'Paste section',
						onClick: function () {
							pasteSectionFromClipboard();
						},
					} ),
					el( 'button', {
						type: 'button',
						className: 'wek-builder__toolbar-btn wek-builder__toolbar-btn--quiet',
						text: i18n.collapseAllSections || 'Collapse all',
						onClick: function () {
							( schema.sections || [] ).forEach( function ( section ) {
								if ( section && section.id ) {
									setSectionCollapsed( section.id, true );
								}
							} );
							refreshCanvas();
						},
					} ),
					el( 'button', {
						type: 'button',
						className: 'wek-builder__toolbar-btn wek-builder__toolbar-btn--quiet',
						text: i18n.expandAllSections || 'Expand all',
						onClick: function () {
							( schema.sections || [] ).forEach( function ( section ) {
								if ( section && section.id ) {
									setSectionCollapsed( section.id, false );
								}
							} );
							refreshCanvas();
						},
					} ),
				] )
			);

			if ( schema.sections.length ) {
				const submitSelected = selection && selection.type === 'submit';
				const submitChildren = [];
				const iconSvg = String( submitButton.icon_svg || '' ).trim();
				const hasIcon = iconSvg.indexOf( '<svg' ) !== -1;
				const submitWidth = normalizeSubmitWidth( submitButton.width );
				if ( hasIcon && submitButton.icon_position !== 'after' ) {
					const iconBefore = el( 'span', {
						className: 'wek-builder__submit-preview-icon',
						'aria-hidden': 'true',
					} );
					iconBefore.innerHTML = iconSvg;
					submitChildren.push( iconBefore );
				}
				submitChildren.push(
					el( 'span', {
						className: 'wek-builder__submit-preview-text',
						text: submitButton.label || i18n.submitPreview || 'Submit',
					} )
				);
				if ( hasIcon && submitButton.icon_position === 'after' ) {
					const iconAfter = el( 'span', {
						className: 'wek-builder__submit-preview-icon',
						'aria-hidden': 'true',
					} );
					iconAfter.innerHTML = iconSvg;
					submitChildren.push( iconAfter );
				}
				sheet.appendChild(
					el(
						'button',
						{
							type: 'button',
							className:
								'wek-builder__submit-preview wek-builder__submit-preview--width-' +
								submitWidth +
								( submitSelected ? ' is-selected' : '' ),
							title: i18n.editSubmit || 'Edit submit button',
							'aria-pressed': submitSelected ? 'true' : 'false',
							onClick: function ( event ) {
								event.stopPropagation();
								selectItem( { type: 'submit' }, 'general' );
							},
						},
						submitChildren
					)
				);
			}
		}

		function refreshSidebar() {
			ensureShell();
			renderSidebar( ui.aside );
		}

		try {
			const form = document.getElementById( 'wek-form-editor' );
			if ( form ) {
				form.addEventListener( 'submit', flushSyncHidden );
			}
			if ( titleInput ) {
				titleInput.addEventListener( 'input', syncHidden );
				titleInput.addEventListener( 'blur', flushSyncHidden );
			}
			if ( introInput ) {
				introInput.addEventListener( 'input', syncHidden );
				introInput.addEventListener( 'blur', flushSyncHidden );
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
