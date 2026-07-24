/**
 * Front-end form: conditionals, validation, submit.
 */
( function () {
	'use strict';

	function qs( root, sel ) {
		return root.querySelector( sel );
	}

	function qsa( root, sel ) {
		return Array.prototype.slice.call( root.querySelectorAll( sel ) );
	}

	function collectValues( form ) {
		const values = {};
		qsa( form, '[data-wek-field]' ).forEach( function ( fieldEl ) {
			const id = fieldEl.getAttribute( 'data-field-id' );
			const type = fieldEl.getAttribute( 'data-field-type' );
			if ( ! id ) {
				return;
			}
			if ( type === 'html' ) {
				return;
			}
			if ( type === 'upload' ) {
				values[ id ] = [];
				return;
			}
			if ( type === 'signature' ) {
				const sigInput = qs( fieldEl, '[data-wek-signature-input]' );
				values[ id ] = sigInput ? sigInput.value : '';
				return;
			}
			if ( type === 'repeater' ) {
				values[ id ] = qsa( fieldEl, '[data-wek-repeater-row]' ).map( function ( row ) {
					const rowVals = {};
					qsa( row, '[data-wek-repeater-input]' ).forEach( function ( input ) {
						const subId = input.getAttribute( 'data-sub-id' );
						if ( ! subId ) {
							return;
						}
						rowVals[ subId ] = input.value;
					} );
					return rowVals;
				} );
				return;
			}
			if ( type === 'matrix' ) {
				const matrix = {};
				qsa( fieldEl, '[data-wek-matrix-row]' ).forEach( function ( row ) {
					const rowId = row.getAttribute( 'data-wek-matrix-row' );
					if ( ! rowId ) {
						return;
					}
					const entry = {};
					const labelInput = qs( row, '[data-wek-matrix-label]' );
					if ( labelInput ) {
						const label = String( labelInput.value || '' ).trim();
						if ( label !== '' ) {
							entry.label = label;
						}
					}
					const onBox = qs( row, '[data-wek-matrix-on]' );
					if ( onBox ) {
						entry.on = !! onBox.checked;
						if ( ! entry.on ) {
							return;
						}
					}
					qsa( row, '[data-wek-matrix-col]' ).forEach( function ( input ) {
						const colId = input.getAttribute( 'data-wek-matrix-col' );
						if ( ! colId ) {
							return;
						}
						if ( input.type === 'checkbox' ) {
							if ( input.checked ) {
								entry[ colId ] = true;
							}
							return;
						}
						if ( input.type === 'radio' ) {
							if ( input.checked ) {
								entry[ colId ] = input.value;
							}
							return;
						}
						const text = String( input.value || '' ).trim();
						if ( text !== '' ) {
							entry[ colId ] = text;
						}
					} );
					const keys = Object.keys( entry );
					if ( ! keys.length ) {
						return;
					}
					if ( keys.length === 1 && Object.prototype.hasOwnProperty.call( entry, 'on' ) && ! entry.on ) {
						return;
					}
					matrix[ rowId ] = entry;
				} );
				values[ id ] = matrix;
				return;
			}
			if ( type === 'checkboxes' ) {
				const selected = [];
				qsa( fieldEl, 'input[type="checkbox"]:checked' ).forEach( function ( box ) {
					if ( box.hasAttribute( 'data-wek-other' ) ) {
						const wrap = box.closest( '[data-wek-checkboxes-custom-item]' );
						const textEl = wrap
							? qs( wrap, '[data-wek-other-text]' )
							: qs( fieldEl, '[data-wek-other-text]' );
						const text = textEl ? String( textEl.value || '' ).trim() : '';
						selected.push( text !== '' ? 'other:' + text : '__other__' );
						return;
					}
					selected.push( box.value );
				} );
				values[ id ] = selected;
				return;
			}
			if ( type === 'radio' || type === 'radio_image' ) {
				const checked = qs( fieldEl, 'input[type="radio"]:checked' );
				values[ id ] = checked ? checked.value : '';
				return;
			}
			if ( type === 'checkbox' || type === 'consent' ) {
				const box = qs( fieldEl, 'input[type="checkbox"]' );
				values[ id ] = box && box.checked ? '1' : '';
				return;
			}
			const input = qs( fieldEl, 'input, textarea, select' );
			if ( input && input.type === 'file' ) {
				values[ id ] = [];
				return;
			}
			values[ id ] = input ? input.value : '';
		} );
		return values;
	}

	function formHasFiles( form ) {
		return qsa( form, 'input[type="file"]' ).some( function ( input ) {
			return input.files && input.files.length > 0;
		} );
	}

	function buildMultipartBody( form, cfg, values, honeypot ) {
		const body = new FormData();
		body.append( 'nonce', cfg.nonce || '' );
		body.append( 'form_id', String( cfg.formId || '' ) );
		body.append( 'token', cfg.token || '' );
		body.append( '_wek_started', String( cfg.started != null ? cfg.started : '' ) );
		body.append( 'website_url', honeypot ? honeypot.value : '' );
		body.append( 'source_url', typeof window !== 'undefined' && window.location ? String( window.location.href ) : '' );
		body.append( 'values', JSON.stringify( values ) );

		qsa( form, '[data-wek-field]' ).forEach( function ( fieldEl ) {
			const id = fieldEl.getAttribute( 'data-field-id' );
			if ( ! id ) {
				return;
			}
			qsa( fieldEl, 'input[type="file"]' ).forEach( function ( input ) {
				if ( ! input.files || ! input.files.length ) {
					return;
				}
				const multi = input.multiple || input.files.length > 1;
				Array.prototype.forEach.call( input.files, function ( file ) {
					body.append( multi ? id + '[]' : id, file, file.name );
				} );
			} );
		} );

		return body;
	}

	function normalize( value ) {
		if ( Array.isArray( value ) ) {
			return value.map( String ).join( ',' );
		}
		if ( typeof value === 'boolean' ) {
			return value ? '1' : '0';
		}
		return String( value == null ? '' : value ).trim();
	}

	function isTruthy( value ) {
		if ( Array.isArray( value ) ) {
			return value.length > 0;
		}
		if ( value && typeof value === 'object' ) {
			return Object.keys( value ).length > 0;
		}
		const n = normalize( value ).toLowerCase();
		return [ '1', 'yes', 'true', 'on' ].indexOf( n ) !== -1;
	}

	function matrixRowSignal( rowVal ) {
		if ( ! rowVal || typeof rowVal !== 'object' ) {
			return '';
		}
		if ( Object.prototype.hasOwnProperty.call( rowVal, 'on' ) ) {
			return rowVal.on ? '1' : '';
		}
		const keys = Object.keys( rowVal );
		for ( let i = 0; i < keys.length; i++ ) {
			const key = keys[ i ];
			if ( key === 'on' ) {
				continue;
			}
			const cell = rowVal[ key ];
			if ( cell === true ) {
				return '1';
			}
			if ( cell !== false && String( cell == null ? '' : cell ).trim() !== '' ) {
				return '1';
			}
		}
		return '';
	}

	/**
	 * Rebuild field.row when an older save mashed them (sanitize_key stripped the dot).
	 */
	function recoverMatrixRef( mashed, values ) {
		let best = null;
		let bestLen = 0;
		Object.keys( values || {} ).forEach( function ( id ) {
			const val = values[ id ];
			if ( ! val || typeof val !== 'object' || Array.isArray( val ) ) {
				return;
			}
			if ( id.length < 1 || id.length <= bestLen || mashed.indexOf( id ) !== 0 ) {
				return;
			}
			const rowId = mashed.slice( id.length );
			if ( ! rowId || ! Object.prototype.hasOwnProperty.call( val, rowId ) ) {
				return;
			}
			best = id + '.' + rowId;
			bestLen = id.length;
		} );
		return best;
	}

	function resolveCurrent( ruleField, values ) {
		if ( ! ruleField ) {
			return null;
		}
		let path = String( ruleField );
		if ( path.indexOf( '.' ) === -1 ) {
			if ( Object.prototype.hasOwnProperty.call( values, path ) ) {
				return values[ path ];
			}
			const recovered = recoverMatrixRef( path, values );
			if ( ! recovered ) {
				return null;
			}
			path = recovered;
		}
		const parts = path.split( '.' );
		const root = parts[ 0 ];
		const rowId = parts[ 1 ] || '';
		const matrix = values[ root ];
		if ( ! matrix || typeof matrix !== 'object' || Array.isArray( matrix ) || ! rowId ) {
			return '';
		}
		return matrixRowSignal( matrix[ rowId ] );
	}

	function matchesRule( ruleField, op, ruleValue, values ) {
		if ( ! ruleField ) {
			return true;
		}
		const current = resolveCurrent( ruleField, values );
		switch ( op ) {
			case 'equals':
				return normalize( current ) === normalize( ruleValue );
			case 'not_equals':
				return normalize( current ) !== normalize( ruleValue );
			case 'contains':
				if ( Array.isArray( current ) ) {
					return current.map( normalize ).indexOf( normalize( ruleValue ) ) !== -1;
				}
				return normalize( current ).indexOf( normalize( ruleValue ) ) !== -1;
			case 'is_checked':
				return isTruthy( current );
			case 'is_not_checked':
				return ! isTruthy( current );
			case 'is_not_empty':
				if ( Array.isArray( current ) ) {
					return current.filter( function ( item ) {
						if ( item && typeof item === 'object' ) {
							return Object.keys( item ).length > 0;
						}
						return normalize( item ) !== '';
					} ).length > 0;
				}
				if ( current && typeof current === 'object' ) {
					return Object.keys( current ).length > 0;
				}
				return normalize( current ) !== '';
			case 'is_empty':
				if ( Array.isArray( current ) ) {
					return current.filter( function ( item ) {
						if ( item && typeof item === 'object' ) {
							return Object.keys( item ).length > 0;
						}
						return normalize( item ) !== '';
					} ).length === 0;
				}
				if ( current && typeof current === 'object' ) {
					return Object.keys( current ).length === 0;
				}
				return normalize( current ) === '';
			default:
				return true;
		}
	}

	function isCompleteShowWhenRule( rule ) {
		if ( ! rule || typeof rule !== 'object' ) {
			return false;
		}
		if ( ! rule.field || ! rule.op ) {
			return false;
		}
		const op = String( rule.op );
		if (
			op === 'is_checked' ||
			op === 'is_not_checked' ||
			op === 'is_empty' ||
			op === 'is_not_empty'
		) {
			return true;
		}
		return String( rule.value != null ? rule.value : '' ).trim() !== '';
	}

	function matchOneRule( rule, values ) {
		if ( ! rule || typeof rule !== 'object' ) {
			return true;
		}
		return matchesRule( rule.field || '', rule.op || 'equals', rule.value || '', values );
	}

	function evaluateShowWhen( raw, values ) {
		if ( ! raw ) {
			return true;
		}
		let container = raw;
		if ( typeof raw === 'string' ) {
			try {
				container = JSON.parse( raw );
			} catch ( e ) {
				return true;
			}
		}
		if ( ! container || typeof container !== 'object' ) {
			return true;
		}
		// Legacy single rule.
		if ( container.field && ! container.rules ) {
			if ( ! isCompleteShowWhenRule( container ) ) {
				return true;
			}
			return matchOneRule( container, values );
		}
		const rules = ( Array.isArray( container.rules ) ? container.rules : [] ).filter(
			isCompleteShowWhenRule
		);
		if ( ! rules.length ) {
			return true;
		}
		const relation = String( container.relation || 'AND' ).toUpperCase() === 'OR' ? 'OR' : 'AND';
		for ( let i = 0; i < rules.length; i++ ) {
			const ok = matchOneRule( rules[ i ], values );
			if ( relation === 'OR' && ok ) {
				return true;
			}
			if ( relation === 'AND' && ! ok ) {
				return false;
			}
		}
		return relation === 'AND';
	}

	const showWhenCache = typeof WeakMap !== 'undefined' ? new WeakMap() : null;

	function parseShowWhenAttr( el ) {
		if ( showWhenCache && showWhenCache.has( el ) ) {
			return showWhenCache.get( el );
		}
		const json = el.getAttribute( 'data-show-when' );
		let parsed = null;
		if ( json ) {
			try {
				parsed = JSON.parse( json );
			} catch ( e ) {
				parsed = null;
			}
		} else {
			const field = el.getAttribute( 'data-show-field' );
			if ( field ) {
				parsed = {
					field: field,
					op: el.getAttribute( 'data-show-op' ) || 'equals',
					value: el.getAttribute( 'data-show-value' ) || '',
				};
			}
		}
		if ( showWhenCache ) {
			showWhenCache.set( el, parsed );
		}
		return parsed;
	}

	function collectRuleDeps( container, deps ) {
		if ( ! container || typeof container !== 'object' ) {
			return;
		}
		function addDep( field ) {
			const raw = String( field || '' );
			if ( ! raw ) {
				return;
			}
			deps[ raw ] = true;
			// Matrix row rules (`matrix.row`) also watch the root field for input events.
			const dot = raw.indexOf( '.' );
			if ( dot > 0 ) {
				deps[ raw.slice( 0, dot ) ] = true;
			}
		}
		if ( container.field ) {
			addDep( container.field );
		}
		const rules = Array.isArray( container.rules ) ? container.rules : [];
		rules.forEach( function ( rule ) {
			if ( rule && rule.field ) {
				addDep( rule.field );
			}
		} );
	}

	function setControlsVisibility( el, visible ) {
		qsa( el, 'input, textarea, select' ).forEach( function ( control ) {
			if ( ! visible ) {
				control.setAttribute( 'tabindex', '-1' );
				control.disabled = true;
				control.required = false;
			} else {
				control.removeAttribute( 'tabindex' );
				control.disabled = false;
				const wrap = control.closest( '[data-wek-field]' );
				if ( wrap && wrap.getAttribute( 'data-required' ) === '1' ) {
					if (
						control.type !== 'checkbox' ||
						wrap.getAttribute( 'data-field-type' ) === 'consent' ||
						wrap.getAttribute( 'data-field-type' ) === 'checkbox'
					) {
						control.required = wrap.getAttribute( 'data-field-type' ) !== 'checkboxes';
					}
				}
			}
		} );
	}

	function applyConditionals( root ) {
		const form = qs( root, '[data-wek-form]' );
		if ( ! form ) {
			return;
		}
		const values = collectValues( form );
		const watched =
			root._wekCondWatched || qsa( root, '[data-wek-section], [data-wek-field]' );

		watched.forEach( function ( el ) {
			const rule = parseShowWhenAttr( el );
			if ( ! rule ) {
				return;
			}
			const visible = evaluateShowWhen( rule, values );
			const wasHidden = el.classList.contains( 'is-hidden' );
			if ( wasHidden === ! visible ) {
				return;
			}
			el.classList.toggle( 'is-hidden', ! visible );
			el.setAttribute( 'aria-hidden', visible ? 'false' : 'true' );
			setControlsVisibility( el, visible );
		} );

		form.dispatchEvent( new Event( 'wek:conditionals' ) );
	}

	function initConditionals( root, form ) {
		const watched = [];
		const deps = {};
		qsa( root, '[data-wek-section], [data-wek-field]' ).forEach( function ( el ) {
			const rule = parseShowWhenAttr( el );
			if ( ! rule ) {
				return;
			}
			watched.push( el );
			collectRuleDeps( rule, deps );
		} );
		root._wekCondWatched = watched;
		root._wekCondDeps = deps;

		let inputTimer = null;
		form.addEventListener( 'change', function ( event ) {
			applyConditionals( root );
			const target = event.target;
			if ( target && target.matches && target.matches( 'input[type="checkbox"]' ) ) {
				const wrap = target.closest( '[data-field-type="checkboxes"]' );
				if ( wrap ) {
					syncCheckboxesMaxLock( wrap );
				}
			}
		} );
		form.addEventListener( 'input', function ( event ) {
			const target = event.target;
			const wrap = target && target.closest ? target.closest( '[data-field-id]' ) : null;
			const fieldId = wrap ? wrap.getAttribute( 'data-field-id' ) : '';
			if ( fieldId && deps && Object.keys( deps ).length && ! deps[ fieldId ] ) {
				return;
			}
			if ( inputTimer ) {
				window.clearTimeout( inputTimer );
			}
			inputTimer = window.setTimeout( function () {
				inputTimer = null;
				applyConditionals( root );
			}, 120 );
		} );
		applyConditionals( root );
	}

	function clearErrors( form ) {
		qsa( form, '[data-wek-error]' ).forEach( function ( el ) {
			el.hidden = true;
			const text = qs( el, '[data-wek-error-text]' );
			if ( text ) {
				text.textContent = '';
			} else {
				el.textContent = '';
			}
		} );
		qsa( form, '.is-invalid' ).forEach( function ( el ) {
			el.classList.remove( 'is-invalid' );
		} );
		qsa( form, '.is-valid' ).forEach( function ( el ) {
			el.classList.remove( 'is-valid' );
		} );
		qsa( form, '[aria-invalid="true"]' ).forEach( function ( el ) {
			el.removeAttribute( 'aria-invalid' );
		} );
	}

	function clearFieldFeedback( field ) {
		if ( ! field ) {
			return;
		}
		field.classList.remove( 'is-invalid', 'is-valid' );
		const err = qs( field, '[data-wek-error]' );
		if ( err ) {
			err.hidden = true;
			const text = qs( err, '[data-wek-error-text]' );
			if ( text ) {
				text.textContent = '';
			} else {
				err.textContent = '';
			}
		}
		qsa( field, 'input, textarea, select' ).forEach( function ( control ) {
			if ( control.classList.contains( 'we-formkit__honeypot' ) ) {
				return;
			}
			control.removeAttribute( 'aria-invalid' );
		} );
	}

	function applyLabelTemplate( template, label ) {
		const tpl = String( template || '' );
		const lab = String( label || '' );
		let out = tpl.split( '{label}' ).join( lab );
		if ( out.indexOf( '%s' ) !== -1 ) {
			out = out.replace( '%s', lab );
		}
		return out;
	}

	function fieldRequiredMessage( fieldEl ) {
		const label = fieldEl.getAttribute( 'data-field-label' ) || '';
		const override = ( fieldEl.getAttribute( 'data-msg-required' ) || '' ).trim();
		if ( override ) {
			return applyLabelTemplate( override, label );
		}
		const cfg = window.weFormkit || {};
		const tpl =
			( cfg.validation && cfg.validation.required ) ||
			( cfg.i18n && cfg.i18n.required ) ||
			'{label} is required.';
		return applyLabelTemplate( tpl, label || 'This field' );
	}

	function fieldInvalidMessage( fieldEl ) {
		const label = fieldEl.getAttribute( 'data-field-label' ) || '';
		const override = ( fieldEl.getAttribute( 'data-msg-invalid' ) || '' ).trim();
		if ( override ) {
			return applyLabelTemplate( override, label );
		}
		const cfg = window.weFormkit || {};
		const tpl =
			( cfg.validation && cfg.validation.invalid ) ||
			( cfg.i18n && cfg.i18n.invalid ) ||
			'{label} is not valid.';
		return applyLabelTemplate( tpl, label || 'This field' );
	}

	function setSubmitBusy( submitBtn, busy, submittingLabel ) {
		if ( ! submitBtn ) {
			return;
		}
		submitBtn.disabled = !! busy;
		const textEl = qs( submitBtn, '[data-wek-submit-text]' );
		if ( busy ) {
			const msg = submittingLabel || 'Submitting…';
			if ( textEl ) {
				textEl.textContent = msg;
			} else {
				submitBtn.textContent = msg;
			}
			return;
		}
		const label =
			submitBtn.getAttribute( 'data-wek-submit-label' ) ||
			( ( window.weFormkit || {} ).i18n && ( window.weFormkit || {} ).i18n.submit ) ||
			'Submit form';
		if ( textEl ) {
			textEl.textContent = label;
		} else {
			submitBtn.textContent = label;
		}
	}

	function checkboxesLimitMessage( fieldEl, kind, n ) {
		const label = fieldEl.getAttribute( 'data-field-label' ) || '';
		const cfg = window.weFormkit || {};
		const i18n = cfg.i18n || {};
		const tpl =
			kind === 'min'
				? i18n.checkboxesMin || 'Please select at least %2$d option(s) for %1$s.'
				: i18n.checkboxesMax || 'Please select at most %2$d option(s) for %1$s.';
		return String( tpl )
			.replace( /%1\$s/g, label || 'This field' )
			.replace( /%2\$d/g, String( n ) );
	}

	function validateCheckboxesField( fieldEl ) {
		if ( ( fieldEl.getAttribute( 'data-field-type' ) || '' ) !== 'checkboxes' ) {
			return '';
		}
		const min = parseInt( fieldEl.getAttribute( 'data-min-selected' ) || '0', 10 );
		const max = parseInt( fieldEl.getAttribute( 'data-max-selected' ) || '0', 10 );
		const count = qsa( fieldEl, 'input[type="checkbox"]:checked' ).length;
		if ( min > 0 && count < min ) {
			return checkboxesLimitMessage( fieldEl, 'min', min );
		}
		if ( max > 0 && count > max ) {
			return checkboxesLimitMessage( fieldEl, 'max', max );
		}
		const otherItems = qsa( fieldEl, '[data-wek-checkboxes-custom-item]' );
		for ( let i = 0; i < otherItems.length; i += 1 ) {
			const wrap = otherItems[ i ];
			const otherBox = qs( wrap, 'input[type="checkbox"][data-wek-other]' );
			const otherText = qs( wrap, '[data-wek-other-text]' );
			if ( otherBox && otherBox.checked && otherText && String( otherText.value || '' ).trim() === '' ) {
				return (
					fieldInvalidMessage( fieldEl ) ||
					( ( window.weFormkit && window.weFormkit.i18n && window.weFormkit.i18n.otherTextRequired ) ||
						'Please enter text for each custom option.' )
				);
			}
		}
		return '';
	}

	function syncCheckboxesMaxLock( fieldEl ) {
		if ( ( fieldEl.getAttribute( 'data-field-type' ) || '' ) !== 'checkboxes' ) {
			return;
		}
		const max = parseInt( fieldEl.getAttribute( 'data-max-selected' ) || '0', 10 );
		if ( max < 1 ) {
			return;
		}
		const boxes = qsa( fieldEl, 'input[type="checkbox"]' );
		const checked = boxes.filter( function ( box ) {
			return box.checked;
		} ).length;
		boxes.forEach( function ( box ) {
			if ( box.checked ) {
				box.disabled = false;
				return;
			}
			box.disabled = checked >= max;
		} );
	}

	function checkboxesCustomCount( fieldEl ) {
		return qsa( fieldEl, '[data-wek-checkboxes-custom-item]' ).length;
	}

	function checkboxesMaxOther( fieldEl ) {
		const fromField = parseInt( fieldEl.getAttribute( 'data-max-other' ) || '0', 10 );
		if ( fromField > 0 ) {
			return fromField;
		}
		const addBtn = qs( fieldEl, '[data-wek-checkboxes-add-other]' );
		const fromBtn = addBtn ? parseInt( addBtn.getAttribute( 'data-max-other' ) || '0', 10 ) : 0;
		return fromBtn > 0 ? fromBtn : 2;
	}

	function syncCheckboxesAddButton( fieldEl ) {
		const addBtn = qs( fieldEl, '[data-wek-checkboxes-add-other]' );
		if ( ! addBtn ) {
			return;
		}
		const atCap = checkboxesCustomCount( fieldEl ) >= checkboxesMaxOther( fieldEl );
		addBtn.disabled = atCap;
		addBtn.setAttribute( 'aria-disabled', atCap ? 'true' : 'false' );
	}

	function newCheckboxesOtherIds() {
		const suffix = Math.random().toString( 36 ).slice( 2, 8 );
		return {
			oid: 'wek-other-' + suffix,
			tid: 'wek-other-text-' + suffix,
		};
	}

	function bindCheckboxesCustomItem( fieldEl, item ) {
		const removeBtn = qs( item, '[data-wek-checkboxes-remove-other]' );
		if ( removeBtn && ! removeBtn.getAttribute( 'data-wek-checkboxes-remove-bound' ) ) {
			removeBtn.setAttribute( 'data-wek-checkboxes-remove-bound', '1' );
			removeBtn.addEventListener( 'click', function () {
				item.remove();
				syncCheckboxesAddButton( fieldEl );
				syncCheckboxesMaxLock( fieldEl );
				const form = fieldEl.closest( '[data-wek-form]' );
				if ( form ) {
					form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );
		}
		const otherBox = qs( item, 'input[type="checkbox"][data-wek-other]' );
		const otherText = qs( item, '[data-wek-other-text]' );
		if ( otherText && otherBox && ! otherText.getAttribute( 'data-wek-other-input-bound' ) ) {
			otherText.setAttribute( 'data-wek-other-input-bound', '1' );
			otherText.addEventListener( 'input', function () {
				if ( String( otherText.value || '' ).trim() !== '' && ! otherBox.checked ) {
					otherBox.checked = true;
					otherBox.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );
		}
	}

	function addCheckboxesCustomItem( fieldEl, preferredText ) {
		const choices = qs( fieldEl, '.we-formkit__choices' );
		const tpl = qs( fieldEl, '[data-wek-checkboxes-other-template]' );
		if ( ! choices || ! tpl || ! tpl.content ) {
			return null;
		}
		if ( checkboxesCustomCount( fieldEl ) >= checkboxesMaxOther( fieldEl ) ) {
			syncCheckboxesAddButton( fieldEl );
			return null;
		}
		const frag = document.importNode( tpl.content, true );
		const item = frag.querySelector( '[data-wek-checkboxes-custom-item]' );
		if ( ! item ) {
			return null;
		}
		const ids = newCheckboxesOtherIds();
		qsa( item, '[id], [for]' ).forEach( function ( node ) {
			[ 'id', 'for' ].forEach( function ( attr ) {
				const val = node.getAttribute( attr );
				if ( ! val ) {
					return;
				}
				if ( val.indexOf( '__OTHER_OID__' ) !== -1 ) {
					node.setAttribute( attr, val.split( '__OTHER_OID__' ).join( ids.oid ) );
				}
				if ( val.indexOf( '__OTHER_TID__' ) !== -1 ) {
					node.setAttribute( attr, val.split( '__OTHER_TID__' ).join( ids.tid ) );
				}
			} );
		} );
		const otherBox = qs( item, 'input[type="checkbox"][data-wek-other]' );
		const otherText = qs( item, '[data-wek-other-text]' );
		if ( otherBox ) {
			otherBox.checked = true;
		}
		if ( otherText && preferredText ) {
			otherText.value = String( preferredText );
		}
		choices.appendChild( item );
		bindCheckboxesCustomItem( fieldEl, item );
		syncCheckboxesAddButton( fieldEl );
		syncCheckboxesMaxLock( fieldEl );
		return item;
	}

	function bindCheckboxesOther( root ) {
		qsa( root, '[data-field-type="checkboxes"]' ).forEach( function ( fieldEl ) {
			if ( fieldEl.getAttribute( 'data-wek-checkboxes-custom' ) !== '1' && ! qs( fieldEl, '[data-wek-checkboxes-add-other]' ) ) {
				return;
			}
			if ( fieldEl.getAttribute( 'data-wek-checkboxes-custom-bound' ) ) {
				syncCheckboxesAddButton( fieldEl );
				return;
			}
			fieldEl.setAttribute( 'data-wek-checkboxes-custom-bound', '1' );

			const addBtn = qs( fieldEl, '[data-wek-checkboxes-add-other]' );
			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					const item = addCheckboxesCustomItem( fieldEl );
					if ( item ) {
						const text = qs( item, '[data-wek-other-text]' );
						if ( text ) {
							text.focus();
						}
						const form = fieldEl.closest( '[data-wek-form]' );
						if ( form ) {
							form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
						}
					}
				} );
			}

			qsa( fieldEl, '[data-wek-checkboxes-custom-item]' ).forEach( function ( item ) {
				bindCheckboxesCustomItem( fieldEl, item );
			} );
			syncCheckboxesAddButton( fieldEl );
		} );
	}

	function fieldIsEmpty( fieldEl ) {
		const type = fieldEl.getAttribute( 'data-field-type' ) || '';
		if ( type === 'checkbox' || type === 'consent' ) {
			const box = qs( fieldEl, 'input[type="checkbox"]' );
			return ! box || ! box.checked;
		}
		if ( type === 'checkboxes' ) {
			return qsa( fieldEl, 'input[type="checkbox"]:checked' ).length === 0;
		}
		if ( type === 'radio' || type === 'radio_image' ) {
			return ! qs( fieldEl, 'input[type="radio"]:checked' );
		}
		if ( type === 'upload' ) {
			const file = qs( fieldEl, 'input[type="file"]' );
			return ! file || ! file.files || ! file.files.length;
		}
		if ( type === 'signature' ) {
			const sig = qs( fieldEl, '[data-wek-signature-input]' );
			return ! sig || ! String( sig.value || '' ).trim();
		}
		if ( type === 'matrix' ) {
			return ! matrixFieldHasAnswer( fieldEl );
		}
		if ( type === 'repeater' ) {
			return ! qsa( fieldEl, '[data-wek-repeater-input]' ).some( function ( input ) {
				return String( input.value || '' ).trim() !== '';
			} );
		}
		const input = qs( fieldEl, 'input, textarea, select' );
		return ! input || ! String( input.value || '' ).trim();
	}

	function clearMatrixRowControls( row ) {
		qsa( row, '[data-wek-matrix-col]' ).forEach( function ( input ) {
			if ( input.type === 'checkbox' || input.type === 'radio' ) {
				input.checked = false;
			} else {
				input.value = '';
			}
		} );
	}

	function syncFieldResetButton( fieldEl ) {
		const btn = qs( fieldEl, '[data-wek-field-reset]' );
		if ( ! btn ) {
			return;
		}
		const type = fieldEl.getAttribute( 'data-field-type' ) || '';
		let has = false;
		if ( type === 'radio' || type === 'radio_image' ) {
			has = !! qs( fieldEl, 'input[type="radio"]:checked' );
		} else if ( type === 'matrix' ) {
			has = matrixFieldHasAnswer( fieldEl );
		}
		btn.hidden = ! has;
	}

	function syncAllFieldResets( root ) {
		qsa( root, '[data-wek-field]' ).forEach( function ( fieldEl ) {
			syncFieldResetButton( fieldEl );
		} );
	}

	function resetChoiceField( fieldEl ) {
		const type = fieldEl.getAttribute( 'data-field-type' ) || '';
		if ( type === 'radio' || type === 'radio_image' ) {
			qsa( fieldEl, 'input[type="radio"]' ).forEach( function ( radio ) {
				radio.checked = false;
			} );
		} else if ( type === 'matrix' ) {
			qsa( fieldEl, '[data-wek-matrix-row]' ).forEach( function ( row ) {
				const onBox = qs( row, '[data-wek-matrix-on]' );
				if ( onBox ) {
					onBox.checked = false;
				}
				const label = qs( row, '[data-wek-matrix-label]' );
				if ( label ) {
					label.value = '';
				}
				clearMatrixRowControls( row );
				if ( onBox ) {
					syncMatrixRowSelect( row );
				}
			} );
		} else {
			return;
		}
		clearFieldFeedback( fieldEl );
		syncFieldResetButton( fieldEl );
		fieldEl.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function bindFieldResets( root ) {
		qsa( root, '[data-wek-field]' ).forEach( function ( fieldEl ) {
			const btn = qs( fieldEl, '[data-wek-field-reset]' );
			if ( ! btn || btn.getAttribute( 'data-wek-bound' ) === '1' ) {
				return;
			}
			btn.setAttribute( 'data-wek-bound', '1' );
			btn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				resetChoiceField( fieldEl );
			} );
			syncFieldResetButton( fieldEl );
		} );

		const form = qs( root, '[data-wek-form]' );
		if ( ! form || form.getAttribute( 'data-wek-reset-sync' ) === '1' ) {
			return;
		}
		form.setAttribute( 'data-wek-reset-sync', '1' );
		form.addEventListener( 'change', function ( event ) {
			const fieldEl =
				event.target && event.target.closest ? event.target.closest( '[data-wek-field]' ) : null;
			if ( fieldEl && form.contains( fieldEl ) ) {
				syncFieldResetButton( fieldEl );
			}
		} );
		form.addEventListener( 'input', function ( event ) {
			const fieldEl =
				event.target && event.target.closest ? event.target.closest( '[data-wek-field]' ) : null;
			if ( fieldEl && form.contains( fieldEl ) && fieldEl.getAttribute( 'data-field-type' ) === 'matrix' ) {
				syncFieldResetButton( fieldEl );
			}
		} );
	}

	function setMatrixRowEnabled( row, enabled ) {
		const isCustom = row.hasAttribute( 'data-wek-matrix-custom-row' );
		qsa( row, '[data-wek-matrix-col]' ).forEach( function ( input ) {
			// Custom rows: leave checkbox columns clickable so checking one can auto-enable the row.
			// Radios stay disabled until the row is on (no auto-enable from radio).
			if ( isCustom && input.type === 'checkbox' ) {
				input.disabled = false;
				return;
			}
			input.disabled = ! enabled;
		} );
		row.classList.toggle( 'is-row-off', ! enabled );
	}

	function syncMatrixRowSelect( row ) {
		const onBox = qs( row, '[data-wek-matrix-on]' );
		if ( ! onBox ) {
			return;
		}
		const on = !! onBox.checked;
		if ( ! on ) {
			clearMatrixRowControls( row );
		}
		setMatrixRowEnabled( row, on );
	}

	/**
	 * Turn on the row-select checkbox (custom rows: also when typing a label or ticking a checkbox column).
	 */
	function ensureMatrixRowOn( row ) {
		const onBox = qs( row, '[data-wek-matrix-on]' );
		if ( ! onBox || onBox.checked ) {
			return false;
		}
		onBox.checked = true;
		syncMatrixRowSelect( row );
		return true;
	}

	function bindMatrixCustomAutoOn( row ) {
		if ( ! row.hasAttribute( 'data-wek-matrix-custom-row' ) ) {
			return;
		}
		if ( row.getAttribute( 'data-wek-matrix-auto-on-bound' ) ) {
			return;
		}
		row.setAttribute( 'data-wek-matrix-auto-on-bound', '1' );

		const labelInput = qs( row, '[data-wek-matrix-label]' );
		if ( labelInput ) {
			labelInput.addEventListener( 'input', function () {
				if ( String( labelInput.value || '' ).trim() === '' ) {
					return;
				}
				if ( ensureMatrixRowOn( row ) ) {
					const form = row.closest( '[data-wek-form]' );
					if ( form ) {
						form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
				}
			} );
		}

		qsa( row, '[data-wek-matrix-col]' ).forEach( function ( input ) {
			if ( input.type !== 'checkbox' ) {
				return;
			}
			input.addEventListener( 'change', function () {
				if ( ! input.checked ) {
					return;
				}
				if ( ensureMatrixRowOn( row ) ) {
					const form = row.closest( '[data-wek-form]' );
					if ( form ) {
						form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
				}
			} );
		} );
	}

	function bindMatrixRowSelect( row ) {
		const onBox = qs( row, '[data-wek-matrix-on]' );
		if ( onBox && ! onBox.getAttribute( 'data-wek-matrix-bound' ) ) {
			onBox.setAttribute( 'data-wek-matrix-bound', '1' );
			syncMatrixRowSelect( row );
			onBox.addEventListener( 'change', function () {
				syncMatrixRowSelect( row );
				const form = row.closest( '[data-wek-form]' );
				if ( form ) {
					form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );
		}
		bindMatrixCustomAutoOn( row );
	}

	function matrixCustomRowCount( fieldEl ) {
		return qsa( fieldEl, '[data-wek-matrix-custom-row]' ).length;
	}

	function matrixMaxCustomRows( fieldEl ) {
		const raw =
			fieldEl.getAttribute( 'data-max-custom-rows' ) ||
			( qs( fieldEl, '[data-wek-matrix-add-row]' ) &&
				qs( fieldEl, '[data-wek-matrix-add-row]' ).getAttribute( 'data-max-custom-rows' ) ) ||
			'2';
		const n = parseInt( raw, 10 );
		return Number.isFinite( n ) && n > 0 ? Math.min( 20, n ) : 2;
	}

	function syncMatrixAddButton( fieldEl ) {
		const addBtn = qs( fieldEl, '[data-wek-matrix-add-row]' );
		if ( ! addBtn ) {
			return;
		}
		const atCap = matrixCustomRowCount( fieldEl ) >= matrixMaxCustomRows( fieldEl );
		addBtn.disabled = atCap;
		addBtn.setAttribute( 'aria-disabled', atCap ? 'true' : 'false' );
	}

	/**
	 * Self-fill matrices (no catalog rows): show inactive example until the visitor adds a real row.
	 */
	function syncMatrixExampleRow( fieldEl ) {
		const example = qs( fieldEl, '[data-wek-matrix-example]' );
		if ( ! example ) {
			return;
		}
		const hasReal = qsa( fieldEl, '[data-wek-matrix-body] > [data-wek-matrix-row]' ).length > 0;
		example.hidden = hasReal;
	}

	function replaceMatrixCustomId( node, customId ) {
		const attrs = [ 'name', 'id', 'for', 'data-wek-matrix-row' ];
		attrs.forEach( function ( attr ) {
			const val = node.getAttribute && node.getAttribute( attr );
			if ( val && val.indexOf( '__CUSTOM_ID__' ) !== -1 ) {
				node.setAttribute( attr, val.split( '__CUSTOM_ID__' ).join( customId ) );
			}
		} );
		if ( node.children && node.children.length ) {
			Array.prototype.forEach.call( node.children, function ( child ) {
				replaceMatrixCustomId( child, customId );
			} );
		}
	}

	function newMatrixCustomId() {
		return (
			'custom_' +
			Math.random()
				.toString( 36 )
				.slice( 2, 8 )
		);
	}

	function addMatrixCustomRow( fieldEl, preferredId ) {
		if ( fieldEl.getAttribute( 'data-wek-matrix-custom' ) !== '1' ) {
			return null;
		}
		if ( matrixCustomRowCount( fieldEl ) >= matrixMaxCustomRows( fieldEl ) ) {
			syncMatrixAddButton( fieldEl );
			return null;
		}
		const tpl = qs( fieldEl, '[data-wek-matrix-custom-template]' );
		const body = qs( fieldEl, '[data-wek-matrix-body]' );
		if ( ! tpl || ! body || ! tpl.content ) {
			return null;
		}
		const customId = preferredId && /^custom_[a-z0-9]+$/.test( preferredId ) ? preferredId : newMatrixCustomId();
		if ( qs( fieldEl, '[data-wek-matrix-row="' + customId + '"]' ) ) {
			return qs( fieldEl, '[data-wek-matrix-row="' + customId + '"]' );
		}
		const frag = document.importNode( tpl.content, true );
		const row = frag.querySelector( '[data-wek-matrix-row]' );
		if ( ! row ) {
			return null;
		}
		replaceMatrixCustomId( row, customId );
		body.appendChild( row );
		bindMatrixRowSelect( row );
		// New custom rows start selected so radios/columns are usable immediately.
		ensureMatrixRowOn( row );
		const removeBtn = qs( row, '[data-wek-matrix-remove-row]' );
		if ( removeBtn && ! removeBtn.getAttribute( 'data-wek-matrix-remove-bound' ) ) {
			removeBtn.setAttribute( 'data-wek-matrix-remove-bound', '1' );
			removeBtn.addEventListener( 'click', function () {
				row.remove();
				syncMatrixAddButton( fieldEl );
				syncMatrixExampleRow( fieldEl );
				const form = fieldEl.closest( '[data-wek-form]' );
				if ( form ) {
					form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );
		}
		syncMatrixAddButton( fieldEl );
		syncMatrixExampleRow( fieldEl );
		return row;
	}

	function bindMatrixRows( root ) {
		qsa( root, '[data-field-type="matrix"]' ).forEach( function ( fieldEl ) {
			qsa( fieldEl, '[data-wek-matrix-row]' ).forEach( bindMatrixRowSelect );

			if ( fieldEl.getAttribute( 'data-wek-matrix-custom' ) !== '1' ) {
				syncMatrixExampleRow( fieldEl );
				return;
			}
			if ( fieldEl.getAttribute( 'data-wek-matrix-custom-bound' ) ) {
				syncMatrixAddButton( fieldEl );
				syncMatrixExampleRow( fieldEl );
				return;
			}
			fieldEl.setAttribute( 'data-wek-matrix-custom-bound', '1' );

			const addBtn = qs( fieldEl, '[data-wek-matrix-add-row]' );
			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					const row = addMatrixCustomRow( fieldEl );
					if ( row ) {
						const label = qs( row, '[data-wek-matrix-label]' );
						if ( label ) {
							label.focus();
						}
						const form = fieldEl.closest( '[data-wek-form]' );
						if ( form ) {
							form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
						}
					}
				} );
			}

			qsa( fieldEl, '[data-wek-matrix-remove-row]' ).forEach( function ( btn ) {
				if ( btn.getAttribute( 'data-wek-matrix-remove-bound' ) ) {
					return;
				}
				btn.setAttribute( 'data-wek-matrix-remove-bound', '1' );
				btn.addEventListener( 'click', function () {
					const row = btn.closest( '[data-wek-matrix-row]' );
					if ( row ) {
						row.remove();
					}
					syncMatrixAddButton( fieldEl );
					syncMatrixExampleRow( fieldEl );
					const form = fieldEl.closest( '[data-wek-form]' );
					if ( form ) {
						form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
				} );
			} );

			syncMatrixAddButton( fieldEl );
			syncMatrixExampleRow( fieldEl );
		} );
	}

	function ensureMatrixCustomRowsFromValues( fieldEl, val ) {
		if ( ! val || typeof val !== 'object' || Array.isArray( val ) ) {
			return;
		}
		Object.keys( val ).forEach( function ( rowId ) {
			if ( ! /^custom_[a-z0-9]+$/.test( rowId ) ) {
				return;
			}
			if ( ! qs( fieldEl, '[data-wek-matrix-row="' + rowId + '"]' ) ) {
				addMatrixCustomRow( fieldEl, rowId );
			}
		} );
		syncMatrixExampleRow( fieldEl );
	}

	function matrixFieldHasAnswer( fieldEl ) {
		return matrixCountAnsweredRows( fieldEl ) > 0;
	}

	function matrixParseIdList( fieldEl, attr ) {
		try {
			const raw = fieldEl.getAttribute( attr ) || '[]';
			const parsed = JSON.parse( raw );
			return Array.isArray( parsed ) ? parsed.map( String ) : [];
		} catch ( e ) {
			return [];
		}
	}

	function matrixRowHasCellAnswers( row ) {
		if ( qs( row, 'input[type="radio"]:checked' ) ) {
			return true;
		}
		if (
			qsa( row, 'input[type="checkbox"]:checked' ).some( function ( box ) {
				return ! box.hasAttribute( 'data-wek-matrix-on' );
			} )
		) {
			return true;
		}
		return qsa( row, '[data-wek-matrix-col]' ).some( function ( input ) {
			if ( input.type === 'checkbox' || input.type === 'radio' ) {
				return false;
			}
			return String( input.value || '' ).trim() !== '';
		} );
	}

	function matrixRowIsActive( fieldEl, row ) {
		const onBox = qs( row, '[data-wek-matrix-on]' );
		if ( onBox ) {
			return !! onBox.checked;
		}
		if ( row.getAttribute( 'data-wek-matrix-row-required' ) === '1' ) {
			return true;
		}
		const labelInput = qs( row, '[data-wek-matrix-label]' );
		if ( labelInput && String( labelInput.value || '' ).trim() !== '' ) {
			return true;
		}
		return matrixRowHasCellAnswers( row );
	}

	function matrixColumnFilled( row, colId ) {
		const inputs = qsa( row, '[data-wek-matrix-col="' + colId + '"]' );
		if ( ! inputs.length ) {
			return false;
		}
		return inputs.some( function ( input ) {
			if ( input.type === 'checkbox' ) {
				return !! input.checked;
			}
			if ( input.type === 'radio' ) {
				return !! input.checked;
			}
			return String( input.value || '' ).trim() !== '';
		} );
	}

	function matrixRequiredColumnsSatisfied( fieldEl, row ) {
		const cols = matrixParseIdList( fieldEl, 'data-matrix-required-cols' );
		for ( let i = 0; i < cols.length; i++ ) {
			if ( ! matrixColumnFilled( row, cols[ i ] ) ) {
				return false;
			}
		}
		return true;
	}

	function matrixRowIsAnswered( fieldEl, row ) {
		if ( ! matrixRowIsActive( fieldEl, row ) ) {
			return false;
		}
		if ( row.hasAttribute( 'data-wek-matrix-custom-row' ) ) {
			const labelInput = qs( row, '[data-wek-matrix-label]' );
			if ( ! labelInput || String( labelInput.value || '' ).trim() === '' ) {
				return false;
			}
		}
		return matrixRequiredColumnsSatisfied( fieldEl, row );
	}

	function matrixCountAnsweredRows( fieldEl ) {
		return qsa( fieldEl, '[data-wek-matrix-row]' ).filter( function ( row ) {
			return matrixRowIsAnswered( fieldEl, row );
		} ).length;
	}

	function matrixColumnLabel( fieldEl, colId ) {
		const cell = qs( fieldEl, '[data-wek-matrix-col="' + colId + '"]' );
		const td = cell ? cell.closest( 'td' ) : null;
		if ( td && td.getAttribute( 'data-wek-col-label' ) ) {
			return td.getAttribute( 'data-wek-col-label' );
		}
		const reqCols = matrixParseIdList( fieldEl, 'data-matrix-required-cols' );
		const idx = reqCols.indexOf( colId );
		const headers = qsa( fieldEl, '.we-formkit__matrix-col.is-required .we-formkit__matrix-col-label' );
		if ( idx >= 0 && headers[ idx ] ) {
			return headers[ idx ].textContent.replace( /\*/g, '' ).trim() || colId;
		}
		return colId;
	}

	function validateMatrixField( fieldEl ) {
		if ( ( fieldEl.getAttribute( 'data-field-type' ) || '' ) !== 'matrix' ) {
			return '';
		}
		const i18n = ( window.weFormkit && window.weFormkit.i18n ) || {};
		const fieldLabel = ( fieldEl.getAttribute( 'data-field-label' ) || '' ).trim() || 'Field';
		const min = parseInt( fieldEl.getAttribute( 'data-min-answered-rows' ) || '0', 10 ) || 0;
		const answered = matrixCountAnsweredRows( fieldEl );

		const rows = qsa( fieldEl, '[data-wek-matrix-row]' );
		for ( let r = 0; r < rows.length; r++ ) {
			const row = rows[ r ];
			if ( ! row.hasAttribute( 'data-wek-matrix-custom-row' ) ) {
				continue;
			}
			const labelInput = qs( row, '[data-wek-matrix-label]' );
			const label = labelInput ? String( labelInput.value || '' ).trim() : '';
			const onBox = qs( row, '[data-wek-matrix-on]' );
			const selected = onBox ? !! onBox.checked : false;
			if ( ( selected || matrixRowHasCellAnswers( row ) ) && label === '' ) {
				const tpl = i18n.matrixCustomLabel || '%s: please enter a label for each added row.';
				return tpl.replace( '%s', fieldLabel );
			}
		}

		if ( min > 0 && answered < min ) {
			const tpl = i18n.matrixMinRows || '%1$s: please answer at least %2$d row(s).';
			return tpl.replace( '%1$s', fieldLabel ).replace( '%2$d', String( min ) );
		}

		const requiredRows = matrixParseIdList( fieldEl, 'data-matrix-required-rows' );
		for ( let i = 0; i < requiredRows.length; i++ ) {
			const rowId = requiredRows[ i ];
			const row = qs( fieldEl, '[data-wek-matrix-row="' + rowId + '"]' );
			if ( ! row || ! matrixRowIsAnswered( fieldEl, row ) ) {
				const rowLab = row
					? ( qs( row, '.we-formkit__matrix-row-label' ) || row )
							.textContent.replace( /\*/g, '' )
							.trim()
					: rowId;
				const tpl = i18n.matrixRowRequired || '%1$s: please complete the required row “%2$s”.';
				return tpl.replace( '%1$s', fieldLabel ).replace( '%2$s', rowLab || rowId );
			}
		}

		const reqCols = matrixParseIdList( fieldEl, 'data-matrix-required-cols' );
		for ( let r = 0; r < rows.length; r++ ) {
			const row = rows[ r ];
			if ( ! matrixRowIsActive( fieldEl, row ) ) {
				continue;
			}
			for ( let c = 0; c < reqCols.length; c++ ) {
				const colId = reqCols[ c ];
				if ( ! matrixColumnFilled( row, colId ) ) {
					const colLab = matrixColumnLabel( fieldEl, colId );
					const tpl =
						i18n.matrixColRequired ||
						'%1$s: please fill in “%2$s” for each selected row.';
					return tpl.replace( '%1$s', fieldLabel ).replace( '%2$s', colLab );
				}
			}
		}

		return '';
	}

	function fieldHasMeaningfulValue( fieldEl ) {
		const type = fieldEl.getAttribute( 'data-field-type' ) || '';
		if ( type === 'html' || type === 'hidden' ) {
			return false;
		}
		return ! fieldIsEmpty( fieldEl );
	}

	function fieldFormatError( fieldEl ) {
		const type = fieldEl.getAttribute( 'data-field-type' ) || '';
		if ( fieldIsEmpty( fieldEl ) ) {
			return '';
		}
		if ( type === 'email' || type === 'url' || type === 'number' || type === 'tel' ) {
			const input = qs( fieldEl, 'input' );
			if ( input && typeof input.checkValidity === 'function' && ! input.checkValidity() ) {
				return fieldInvalidMessage( fieldEl );
			}
		}
		return '';
	}

	function fieldIsEffectivelyVisible( fieldEl ) {
		if ( ! fieldEl ) {
			return false;
		}
		if ( fieldEl.classList.contains( 'is-hidden' ) || fieldEl.getAttribute( 'aria-hidden' ) === 'true' ) {
			return false;
		}
		const section = fieldEl.closest( '[data-wek-section]' );
		if ( section && ( section.classList.contains( 'is-hidden' ) || section.getAttribute( 'aria-hidden' ) === 'true' ) ) {
			return false;
		}
		return true;
	}

	function getFieldValidationMessage( fieldEl ) {
		const type = fieldEl.getAttribute( 'data-field-type' ) || '';
		if ( type === 'html' || type === 'hidden' ) {
			return '';
		}
		const cbMsg = validateCheckboxesField( fieldEl );
		if ( cbMsg ) {
			return cbMsg;
		}
		const matrixMsg = validateMatrixField( fieldEl );
		if ( matrixMsg ) {
			return matrixMsg;
		}
		if ( fieldEl.getAttribute( 'data-required' ) === '1' && fieldIsEmpty( fieldEl ) ) {
			return fieldRequiredMessage( fieldEl );
		}
		return fieldFormatError( fieldEl );
	}

	function showFieldError( form, fieldId, message ) {
		const field = qs( form, '[data-field-id="' + fieldId + '"]' );
		if ( ! field ) {
			return;
		}
		field.classList.remove( 'is-valid' );
		field.classList.add( 'is-invalid' );
		const err = qs( field, '[data-wek-error]' );
		if ( err ) {
			err.hidden = false;
			const text = qs( err, '[data-wek-error-text]' );
			if ( text ) {
				text.textContent = message;
			} else {
				err.textContent = message;
			}
		}
		qsa( field, 'input, textarea, select' ).forEach( function ( control ) {
			if ( control.classList.contains( 'we-formkit__honeypot' ) ) {
				return;
			}
			control.setAttribute( 'aria-invalid', 'true' );
		} );
	}

	function showFieldValid( field ) {
		if ( ! field ) {
			return;
		}
		field.classList.remove( 'is-invalid' );
		field.classList.add( 'is-valid' );
		const err = qs( field, '[data-wek-error]' );
		if ( err ) {
			err.hidden = true;
			const text = qs( err, '[data-wek-error-text]' );
			if ( text ) {
				text.textContent = '';
			} else {
				err.textContent = '';
			}
		}
		qsa( field, 'input, textarea, select' ).forEach( function ( control ) {
			if ( control.classList.contains( 'we-formkit__honeypot' ) ) {
				return;
			}
			control.removeAttribute( 'aria-invalid' );
		} );
	}

	function inlineValidationMode( root ) {
		if ( ! root ) {
			return 'off';
		}
		const raw = String( root.getAttribute( 'data-inline-validation' ) || 'off' );
		if ( raw === '0' || raw === 'off' ) {
			return 'off';
		}
		if ( raw === '1' || raw === 'on' ) {
			return 'both';
		}
		if ( raw === 'icon' || raw === 'border' || raw === 'both' ) {
			return raw;
		}
		return 'off';
	}

	function inlineValidationScope( root ) {
		if ( ! root ) {
			return 'required';
		}
		const raw = String( root.getAttribute( 'data-inline-scope' ) || 'required' );
		if ( raw === 'off' || raw === '0' ) {
			return 'off';
		}
		return raw === 'all' ? 'all' : 'required';
	}

	function inlineValidationEnabled( root ) {
		return (
			inlineValidationMode( root ) !== 'off' &&
			inlineValidationScope( root ) !== 'off'
		);
	}

	function inlineShowsIcons( root ) {
		if ( ! inlineValidationEnabled( root ) ) {
			return false;
		}
		const mode = inlineValidationMode( root );
		return mode === 'icon' || mode === 'both';
	}

	function applyFieldValidationResult( form, fieldEl, message, showSuccess ) {
		const id = fieldEl.getAttribute( 'data-field-id' );
		const root = form ? form.closest( '[data-we-formkit]' ) : null;
		if ( message ) {
			showFieldError( form, id, message );
			return false;
		}
		const required = fieldEl.getAttribute( 'data-required' ) === '1';
		const allowSuccess = inlineValidationScope( root ) === 'all' || required;
		if ( showSuccess && allowSuccess && fieldHasMeaningfulValue( fieldEl ) ) {
			showFieldValid( fieldEl );
		} else {
			clearFieldFeedback( fieldEl );
		}
		return true;
	}

	function validateFieldLive( form, fieldEl, root ) {
		if ( ! fieldIsEffectivelyVisible( fieldEl ) ) {
			clearFieldFeedback( fieldEl );
			return true;
		}
		// Optional fields stay quiet when scope is "required only".
		if (
			inlineValidationScope( root ) === 'required' &&
			fieldEl.getAttribute( 'data-required' ) !== '1'
		) {
			clearFieldFeedback( fieldEl );
			return true;
		}
		const msg = getFieldValidationMessage( fieldEl );
		return applyFieldValidationResult( form, fieldEl, msg, inlineValidationEnabled( root ) );
	}

	function prepareInlineControlRows( form ) {
		const root = form.closest( '[data-we-formkit]' );
		const showIcons = inlineShowsIcons( root );
		qsa( form, '[data-wek-field]' ).forEach( function ( fieldEl ) {
			const type = fieldEl.getAttribute( 'data-field-type' ) || '';
			if ( type === 'html' || type === 'hidden' ) {
				return;
			}
			const icon = qs( fieldEl, '[data-wek-validity]' );
			if ( ! icon ) {
				return;
			}
			if ( ! showIcons ) {
				return;
			}
			if ( icon.parentElement && icon.parentElement.classList.contains( 'we-formkit__control-row' ) ) {
				return;
			}

			let control = null;
			let rowMod = '';

			if ( type === 'radio' || type === 'checkboxes' || type === 'radio_image' || type === 'matrix' ) {
				control = qs( fieldEl, '.we-formkit__fieldset' );
				rowMod = ' we-formkit__control-row--choices';
			} else if ( type === 'checkbox' || type === 'consent' ) {
				control = qs( fieldEl, '.we-formkit__control--choice' );
				rowMod = ' we-formkit__control-row--choices';
			} else {
				const kids = fieldEl.children;
				for ( let i = 0; i < kids.length; i++ ) {
					const el = kids[ i ];
					const tag = el.tagName;
					if ( tag === 'TEXTAREA' || tag === 'SELECT' ) {
						control = el;
						break;
					}
					if ( tag === 'INPUT' ) {
						const t = ( el.type || '' ).toLowerCase();
						if ( t !== 'checkbox' && t !== 'radio' && t !== 'hidden' ) {
							control = el;
							break;
						}
					}
				}
			}

			if ( ! control || control.parentElement !== fieldEl ) {
				return;
			}

			const wrap = document.createElement( 'div' );
			wrap.className = 'we-formkit__control-row' + rowMod;
			fieldEl.insertBefore( wrap, control );
			wrap.appendChild( control );
			wrap.appendChild( icon );
		} );
	}

	function bindInlineValidation( root, form ) {
		if ( ! inlineValidationEnabled( root ) ) {
			return;
		}
		prepareInlineControlRows( form );
		const touched = new WeakSet();

		form.addEventListener(
			'focusout',
			function ( event ) {
				const fieldEl = event.target && event.target.closest ? event.target.closest( '[data-wek-field]' ) : null;
				if ( ! fieldEl || ! form.contains( fieldEl ) ) {
					return;
				}
				touched.add( fieldEl );
				validateFieldLive( form, fieldEl, root );
			},
			true
		);

		form.addEventListener( 'change', function ( event ) {
			const fieldEl = event.target && event.target.closest ? event.target.closest( '[data-wek-field]' ) : null;
			if ( ! fieldEl || ! form.contains( fieldEl ) ) {
				return;
			}
			touched.add( fieldEl );
			validateFieldLive( form, fieldEl, root );
			if ( ( fieldEl.getAttribute( 'data-field-type' ) || '' ) === 'checkboxes' ) {
				syncCheckboxesMaxLock( fieldEl );
			}
		} );

		form.addEventListener( 'input', function ( event ) {
			const fieldEl = event.target && event.target.closest ? event.target.closest( '[data-wek-field]' ) : null;
			if ( ! fieldEl || ! form.contains( fieldEl ) || ! touched.has( fieldEl ) ) {
				return;
			}
			validateFieldLive( form, fieldEl, root );
		} );
	}

	function setStatus( root, message, isError ) {
		const status = qs( root, '[data-wek-status]' );
		if ( ! status ) {
			return;
		}
		status.textContent = message || '';
		status.classList.toggle( 'is-error', !! isError );
		status.classList.toggle( 'is-success', ! isError && !! message );
	}

	function setInfoDocuments( root, docs ) {
		const box = qs( root, '[data-wek-info-docs]' );
		if ( ! box ) {
			return;
		}
		box.textContent = '';
		const list = Array.isArray( docs ) ? docs : [];
		if ( ! list.length ) {
			box.hidden = true;
			return;
		}
		const heading = document.createElement( 'p' );
		heading.className = 'we-formkit__info-docs-title';
		heading.textContent =
			( window.weFormkit && window.weFormkit.i18n && window.weFormkit.i18n.infoDocuments ) ||
			'Downloads';
		box.appendChild( heading );
		const ul = document.createElement( 'ul' );
		ul.className = 'we-formkit__info-docs-list';
		list.forEach( function ( doc ) {
			if ( ! doc || ! doc.url ) {
				return;
			}
			const li = document.createElement( 'li' );
			const a = document.createElement( 'a' );
			a.href = doc.url;
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.textContent = doc.title || doc.url;
			li.appendChild( a );
			ul.appendChild( li );
		} );
		if ( ! ul.children.length ) {
			box.hidden = true;
			return;
		}
		box.appendChild( ul );
		box.hidden = false;
	}

	function reindexRepeater( repeater ) {
		const fieldId = repeater.getAttribute( 'data-field-id' ) || '';
		const rows = qsa( repeater, '[data-wek-repeater-row]' );
		rows.forEach( function ( row, index ) {
			qsa( row, '[data-wek-repeater-input]' ).forEach( function ( input ) {
				const subId = input.getAttribute( 'data-sub-id' );
				if ( ! subId ) {
					return;
				}
				input.name = fieldId + '[' + index + '][' + subId + ']';
				const newId = 'wek-field-' + fieldId + '-' + index + '-' + subId;
				input.id = newId;
				const wrap = input.closest( '.we-formkit__repeater-control' );
				const lab = wrap ? qs( wrap, 'label' ) : null;
				if ( lab ) {
					lab.setAttribute( 'for', newId );
				}
			} );
		} );

		const minItems = parseInt( repeater.getAttribute( 'data-min-items' ) || '0', 10 );
		const maxItems = parseInt( repeater.getAttribute( 'data-max-items' ) || '50', 10 );
		const addBtn = qs( repeater, '[data-wek-repeater-add]' );
		if ( addBtn ) {
			addBtn.disabled = rows.length >= maxItems;
			addBtn.hidden = rows.length >= maxItems;
		}
		rows.forEach( function ( row ) {
			const removeBtn = qs( row, '[data-wek-repeater-remove]' );
			if ( removeBtn ) {
				const canRemove = rows.length > Math.max( 1, minItems || 1 );
				removeBtn.hidden = ! canRemove;
				removeBtn.disabled = ! canRemove;
				row.classList.toggle( 'has-remove', canRemove );
			}
		} );
	}

	function initRepeaters( form ) {
		qsa( form, '[data-wek-repeater]' ).forEach( function ( repeater ) {
			if ( repeater.getAttribute( 'data-wek-repeater-ready' ) ) {
				return;
			}
			repeater.setAttribute( 'data-wek-repeater-ready', '1' );

			const rowsHost = qs( repeater, '[data-wek-repeater-rows]' );
			const template = qs( repeater, '[data-wek-repeater-template]' );
			if ( ! rowsHost || ! template ) {
				return;
			}

			reindexRepeater( repeater );

			repeater.addEventListener( 'click', function ( event ) {
				const addBtn = event.target.closest( '[data-wek-repeater-add]' );
				const removeBtn = event.target.closest( '[data-wek-repeater-remove]' );

				if ( addBtn && repeater.contains( addBtn ) ) {
					event.preventDefault();
					const maxItems = parseInt( repeater.getAttribute( 'data-max-items' ) || '50', 10 );
					if ( qsa( repeater, '[data-wek-repeater-row]' ).length >= maxItems ) {
						return;
					}
					const frag = template.content.cloneNode( true );
					rowsHost.appendChild( frag );
					reindexRepeater( repeater );
					return;
				}

				if ( removeBtn && repeater.contains( removeBtn ) ) {
					event.preventDefault();
					const row = removeBtn.closest( '[data-wek-repeater-row]' );
					const minItems = parseInt( repeater.getAttribute( 'data-min-items' ) || '0', 10 );
					const rows = qsa( repeater, '[data-wek-repeater-row]' );
					if ( ! row || rows.length <= Math.max( 1, minItems || 1 ) ) {
						return;
					}
					row.parentNode.removeChild( row );
					reindexRepeater( repeater );
				}
			} );
		} );
	}

	function setNativeValue( input, value ) {
		const proto = Object.getPrototypeOf( input );
		const desc = Object.getOwnPropertyDescriptor( proto, 'value' );
		if ( desc && desc.set ) {
			desc.set.call( input, value );
		} else {
			input.value = value;
		}
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function fillFileInput( input ) {
		if ( typeof DataTransfer === 'undefined' || typeof File === 'undefined' ) {
			return;
		}
		try {
			const file = new File(
				[ 'WE Formkit smoke test\n' ],
				'wek-smoke-test.txt',
				{ type: 'text/plain' }
			);
			const dt = new DataTransfer();
			dt.items.add( file );
			input.files = dt.files;
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} catch ( e ) {
			// File fill not supported in this browser context.
		}
	}

	function sampleForType( type, name ) {
		const stamp = String( Date.now() ).slice( -5 );
		switch ( type ) {
			case 'email':
				return 'smoke+' + stamp + '@example.com';
			case 'tel':
				return '+43 1 234 5678';
			case 'url':
				return 'https://example.com/wek-smoke';
			case 'number':
			case 'range':
				return '42';
			case 'date':
				return new Date().toISOString().slice( 0, 10 );
			case 'time':
				return '10:30';
			case 'datetime':
			case 'datetime-local':
				return new Date().toISOString().slice( 0, 16 );
			case 'textarea':
				return 'WE Formkit smoke test — auto-filled textarea (' + stamp + ').';
			case 'hidden':
				return 'smoke-hidden-' + stamp;
			default:
				return 'Smoke ' + ( name || 'value' ) + ' ' + stamp;
		}
	}

	function smokeStamp() {
		return String( Date.now() ).slice( -5 );
	}

	function checkboxesCheckedCount( fieldEl ) {
		return qsa( fieldEl, 'input[type="checkbox"]' ).filter( function ( box ) {
			return box.checked;
		} ).length;
	}

	function checkboxesSelectionRoom( fieldEl ) {
		const max = parseInt( fieldEl.getAttribute( 'data-max-selected' ) || '0', 10 );
		if ( max < 1 ) {
			return Number.POSITIVE_INFINITY;
		}
		return Math.max( 0, max - checkboxesCheckedCount( fieldEl ) );
	}

	function autofillSignature( fieldEl ) {
		const wrap = qs( fieldEl, '[data-wek-signature]' );
		const canvas = wrap ? qs( wrap, 'canvas' ) : null;
		const input = qs( fieldEl, '[data-wek-signature-input]' );
		if ( ! canvas || ! input ) {
			return;
		}
		const ctx = canvas.getContext( '2d' );
		if ( ! ctx ) {
			return;
		}
		const bg = ( wrap && wrap.getAttribute( 'data-bg' ) ) || '#ffffff';
		const pen = ( wrap && wrap.getAttribute( 'data-pen' ) ) || '#222222';
		ctx.fillStyle = bg;
		ctx.fillRect( 0, 0, canvas.width, canvas.height );
		ctx.strokeStyle = pen;
		ctx.lineWidth = 2.2;
		ctx.lineCap = 'round';
		ctx.lineJoin = 'round';
		ctx.beginPath();
		ctx.moveTo( Math.max( 12, canvas.width * 0.08 ), canvas.height * 0.62 );
		ctx.quadraticCurveTo(
			canvas.width * 0.42,
			canvas.height * 0.18,
			canvas.width * 0.88,
			canvas.height * 0.58
		);
		ctx.stroke();
		input.value = canvas.toDataURL( 'image/png' );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function autofillMatrixRow( row, stamp ) {
		ensureMatrixRowOn( row );
		const label = qs( row, '[data-wek-matrix-label]' );
		if ( label && ! String( label.value || '' ).trim() ) {
			setNativeValue( label, 'Smoke custom row ' + stamp );
		}
		const seenRadioNames = {};
		qsa( row, '[data-wek-matrix-col]' ).forEach( function ( input ) {
			if ( input.disabled ) {
				return;
			}
			if ( input.type === 'radio' ) {
				const name = input.name || '';
				if ( seenRadioNames[ name ] ) {
					return;
				}
				const already = qsa( row, 'input[type="radio"]' ).some( function ( radio ) {
					return radio.name === name && radio.checked;
				} );
				seenRadioNames[ name ] = true;
				if ( already ) {
					return;
				}
				input.checked = true;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				return;
			}
			if ( input.type === 'checkbox' ) {
				if ( ! input.checked ) {
					input.checked = true;
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				return;
			}
			if ( ! String( input.value || '' ).trim() ) {
				setNativeValue( input, sampleForType( input.type || 'text', 'matrix' ) );
			}
		} );
	}

	function autofillCheckboxesField( fieldEl, stamp ) {
		const canCustom =
			fieldEl.getAttribute( 'data-wek-checkboxes-custom' ) === '1' ||
			!! qs( fieldEl, '[data-wek-checkboxes-add-other]' );
		if ( canCustom ) {
			let n = 0;
			const maxOther = checkboxesMaxOther( fieldEl );
			while ( n < maxOther && checkboxesSelectionRoom( fieldEl ) > 0 ) {
				const item = addCheckboxesCustomItem(
					fieldEl,
					'Smoke custom option ' + ( n + 1 ) + ' ' + stamp
				);
				if ( ! item ) {
					break;
				}
				n += 1;
			}
		}
		qsa( fieldEl, 'input[type="checkbox"]' ).forEach( function ( box ) {
			if ( box.checked || box.disabled ) {
				return;
			}
			if ( checkboxesSelectionRoom( fieldEl ) <= 0 ) {
				return;
			}
			box.checked = true;
			box.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		syncCheckboxesMaxLock( fieldEl );
		syncCheckboxesAddButton( fieldEl );
	}

	function autofillRepeaterField( fieldEl ) {
		const addBtn = qs( fieldEl, '[data-wek-repeater-add]' );
		const maxItems = parseInt( fieldEl.getAttribute( 'data-max-items' ) || '50', 10 );
		const rows = qsa( fieldEl, '[data-wek-repeater-row]' );
		if (
			addBtn &&
			! addBtn.disabled &&
			rows.length < maxItems &&
			fieldEl.getAttribute( 'data-wek-smoke-repeater-added' ) !== '1'
		) {
			fieldEl.setAttribute( 'data-wek-smoke-repeater-added', '1' );
			addBtn.click();
		}
		qsa( fieldEl, '[data-wek-repeater-input]' ).forEach( function ( input ) {
			if ( input.type === 'checkbox' ) {
				input.checked = true;
			} else if ( input.tagName === 'SELECT' ) {
				if ( input.options.length > 1 ) {
					input.selectedIndex = 1;
				} else if ( input.options.length ) {
					input.selectedIndex = 0;
				}
			} else if ( ! String( input.value || '' ).trim() ) {
				setNativeValue( input, sampleForType( input.type || 'text', input.getAttribute( 'data-sub-id' ) ) );
			}
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	}

	function autofillSelect( select ) {
		if ( ! select || ! select.options.length ) {
			return;
		}
		let pick = -1;
		for ( let i = 0; i < select.options.length; i++ ) {
			const opt = select.options[ i ];
			if ( opt.disabled ) {
				continue;
			}
			if ( String( opt.value || '' ).trim() === '' ) {
				continue;
			}
			pick = i;
			break;
		}
		if ( pick < 0 ) {
			pick = 0;
		}
		select.selectedIndex = pick;
		select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	/**
	 * Cap-gated smoke helper: fill every reachable control (including matrix/checkboxes
	 * custom add items, repeater rows, signature stroke, uploads).
	 */
	function autofillForm( form, root ) {
		const stamp = smokeStamp();

		function fillField( fieldEl ) {
			if ( fieldEl.classList.contains( 'is-hidden' ) || fieldEl.getAttribute( 'aria-hidden' ) === 'true' ) {
				return;
			}
			const type = fieldEl.getAttribute( 'data-field-type' ) || '';
			const labelEl = qs( fieldEl, '.we-formkit__label, legend' );
			const label = labelEl ? labelEl.textContent.replace( /\*/g, '' ).trim() : 'field';

			if ( type === 'html' ) {
				return;
			}

			if ( type === 'upload' ) {
				qsa( fieldEl, 'input[type="file"]' ).forEach( fillFileInput );
				return;
			}

			if ( type === 'signature' ) {
				autofillSignature( fieldEl );
				return;
			}

			if ( type === 'checkbox' || type === 'consent' ) {
				const box = qs( fieldEl, 'input[type="checkbox"]' );
				if ( box && ! box.checked ) {
					box.checked = true;
					box.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				return;
			}

			if ( type === 'radio' || type === 'radio_image' ) {
				const radios = qsa( fieldEl, 'input[type="radio"]' );
				if ( radios.length && ! radios.some( function ( r ) {
					return r.checked;
				} ) ) {
					radios[ 0 ].checked = true;
					radios[ 0 ].dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				return;
			}

			if ( type === 'checkboxes' ) {
				autofillCheckboxesField( fieldEl, stamp );
				return;
			}

			if ( type === 'matrix' ) {
				let customN = 0;
				const maxCustom = matrixMaxCustomRows( fieldEl );
				while ( customN < maxCustom ) {
					const added = addMatrixCustomRow( fieldEl );
					if ( ! added ) {
						break;
					}
					customN += 1;
				}
				qsa( fieldEl, '[data-wek-matrix-row]' ).forEach( function ( row ) {
					autofillMatrixRow( row, stamp );
				} );
				return;
			}

			if ( type === 'repeater' ) {
				autofillRepeaterField( fieldEl );
				return;
			}

			const select = qs( fieldEl, 'select' );
			if ( select ) {
				autofillSelect( select );
				return;
			}

			qsa( fieldEl, 'input:not([type="hidden"]):not([type="file"]):not([type="checkbox"]):not([type="radio"]), textarea' ).forEach(
				function ( input ) {
					if ( String( input.value || '' ).trim() ) {
						return;
					}
					setNativeValue( input, sampleForType( type || input.type, label ) );
				}
			);

			if ( type === 'hidden' ) {
				const hidden = qs( fieldEl, 'input[type="hidden"]' );
				if ( hidden ) {
					setNativeValue( hidden, sampleForType( 'hidden', label ) );
				}
			}
		}

		qsa( form, '[data-wek-field]' ).forEach( fillField );
		applyConditionals( root );
		// Second pass: fields revealed by conditionals (full fill again for complex types).
		qsa( form, '[data-wek-field]' ).forEach( fillField );
		applyConditionals( root );
	}

	function goToLastMultipage( root ) {
		const form = qs( root, '[data-wek-form]' );
		if ( ! form ) {
			return;
		}
		const sections = qsa( form, '[data-wek-section]' );
		if ( sections.length < 2 ) {
			return;
		}
		root.dispatchEvent(
			new CustomEvent( 'wek-go-page', {
				detail: { index: sections.length - 1 },
			} )
		);
	}

	function wantsAutofill() {
		try {
			const params = new URLSearchParams( window.location.search );
			return params.get( 'wek_autofill' ) === '1';
		} catch ( e ) {
			return false;
		}
	}

	/**
	 * Prefill fields from URL query (Field ID = param). Skipped when resuming a draft or smoke autofill.
	 *
	 * @param {HTMLFormElement} form Form.
	 * @return {void}
	 */
	function applyUrlPrefill( form ) {
		let params;
		try {
			params = new URLSearchParams( window.location.search );
		} catch ( e ) {
			return;
		}
		if ( params.get( 'wek_resume' ) || params.get( 'wek_autofill' ) === '1' ) {
			return;
		}

		qsa( form, '[data-wek-field][data-prefill-param]' ).forEach( function ( fieldEl ) {
			const param = fieldEl.getAttribute( 'data-prefill-param' );
			if ( ! param ) {
				return;
			}
			const type = fieldEl.getAttribute( 'data-field-type' ) || '';
			const all = params.getAll( param );
			let raw = '';
			if ( all.length > 1 ) {
				raw = all.join( ',' );
			} else if ( all.length === 1 ) {
				raw = all[ 0 ];
			} else {
				return;
			}
			raw = String( raw || '' ).trim();
			if ( ! raw ) {
				return;
			}

			if ( type === 'checkboxes' ) {
				const wanted = raw.split( /[\s,]+/ ).filter( Boolean );
				qsa( fieldEl, 'input[type="checkbox"]' ).forEach( function ( input ) {
					if ( input.getAttribute( 'data-wek-other' ) ) {
						return;
					}
					input.checked = wanted.indexOf( String( input.value ) ) !== -1;
				} );
				syncCheckboxesMaxLock( fieldEl );
				fieldEl.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				return;
			}

			if ( type === 'checkbox' || type === 'consent' ) {
				const input = qs( fieldEl, 'input[type="checkbox"]' );
				if ( input ) {
					input.checked = [ '1', 'true', 'yes', 'on', 'checked' ].indexOf( raw.toLowerCase() ) !== -1;
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				return;
			}

			if ( type === 'radio' || type === 'radio_image' ) {
				const radios = qsa( fieldEl, 'input[type="radio"]' );
				let match = null;
				radios.forEach( function ( input ) {
					if ( String( input.value ) === raw ) {
						match = input;
					}
				} );
				if ( match ) {
					match.checked = true;
					match.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				return;
			}

			if ( type === 'select' ) {
				const select = qs( fieldEl, 'select' );
				if ( select ) {
					const opt = Array.prototype.find.call( select.options, function ( o ) {
						return String( o.value ) === raw;
					} );
					if ( opt ) {
						select.value = raw;
						select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
				}
				return;
			}

			if ( type === 'textarea' ) {
				const ta = qs( fieldEl, 'textarea' );
				if ( ta ) {
					ta.value = raw;
					ta.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
				return;
			}

			const input = qs( fieldEl, 'input:not([type="hidden"]):not([type="file"]), input[type="hidden"]' );
			if ( input && input.type !== 'file' ) {
				input.value = raw;
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
	}

	/**
	 * Whether the URL carries a prefill value for any field on this form.
	 *
	 * @param {HTMLFormElement} form Form.
	 * @return {boolean}
	 */
	function formHasUrlPrefillParams( form ) {
		let params;
		try {
			params = new URLSearchParams( window.location.search );
		} catch ( e ) {
			return false;
		}
		if ( params.get( 'wek_resume' ) || params.get( 'wek_autofill' ) === '1' ) {
			return false;
		}
		return qsa( form, '[data-wek-field][data-prefill-param]' ).some( function ( fieldEl ) {
			const param = fieldEl.getAttribute( 'data-prefill-param' );
			if ( ! param ) {
				return false;
			}
			return params.getAll( param ).some( function ( value ) {
				return String( value || '' ).trim() !== '';
			} );
		} );
	}

	/**
	 * Smooth-scroll the form into view, honouring theme sticky/admin-bar scroll-padding.
	 *
	 * @param {HTMLElement} root Form root.
	 * @return {void}
	 */
	function scrollFormIntoView( root ) {
		if ( ! root || typeof root.getBoundingClientRect !== 'function' ) {
			return;
		}
		const reduce =
			window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		const padRaw = window.getComputedStyle( document.documentElement ).scrollPaddingTop;
		const pad = padRaw ? parseFloat( padRaw ) || 0 : 0;
		const rect = root.getBoundingClientRect();
		const viewH = window.innerHeight || document.documentElement.clientHeight || 0;

		// Already sitting under the sticky bar, or fully visible — skip.
		if ( rect.top >= pad - 4 && rect.top <= pad + 96 ) {
			return;
		}
		if ( rect.top >= pad && rect.bottom <= viewH ) {
			return;
		}

		const top = ( window.scrollY || window.pageYOffset || 0 ) + rect.top - pad - 8;
		window.scrollTo( {
			top: Math.max( 0, top ),
			behavior: reduce ? 'auto' : 'smooth',
		} );
	}

	/**
	 * After layout + theme sticky padding settle, scroll to the form when URL prefill is present.
	 *
	 * @param {HTMLElement} root Form root.
	 * @return {void}
	 */
	function scheduleScrollFormAfterPrefill( root ) {
		window.requestAnimationFrame( function () {
			window.setTimeout( function () {
				scrollFormIntoView( root );
			}, 80 );
		} );
	}

	function scheduleAutofillSubmit( form, root, cfg ) {
		const started = Number( cfg.started ) || Math.floor( Date.now() / 1000 );
		const waitMs = Math.max( 500, ( 3.2 - ( Date.now() / 1000 - started ) ) * 1000 );
		setStatus(
			root,
			( cfg.i18n && cfg.i18n.autofillReady ) || 'Test fill applied. Submitting automatically…',
			false
		);
		window.setTimeout( function () {
			if ( form.getAttribute( 'data-wek-submitting' ) === '1' ) {
				return;
			}
			// Multipage: submit control lives on the last step.
			goToLastMultipage( root );
			if ( typeof form.requestSubmit === 'function' ) {
				form.requestSubmit();
			} else {
				form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
			}
		}, waitMs );
	}

	function wantsAutosubmit() {
		try {
			const params = new URLSearchParams( window.location.search );
			return params.get( 'wek_autosubmit' ) === '1';
		} catch ( e ) {
			return false;
		}
	}

	/**
	 * Multipage deep link: ?wek_page=2 (1-based). Optional ?wek_page_form={id} when several forms.
	 * Do not reuse ?wek_form — that is reserved for secret embed routing (slug + token).
	 *
	 * @param {number} formId Form post ID.
	 * @param {number} maxPages Section count.
	 * @return {number} Zero-based page index.
	 */
	function readMultipageIndexFromUrl( formId, maxPages ) {
		try {
			const params = new URLSearchParams( window.location.search );
			const formFilter = params.get( 'wek_page_form' );
			if ( formFilter && String( formId ) !== String( formFilter ) ) {
				return 0;
			}
			const raw = params.get( 'wek_page' );
			if ( null === raw || '' === raw ) {
				return 0;
			}
			const oneBased = parseInt( raw, 10 );
			if ( ! Number.isFinite( oneBased ) || oneBased < 1 ) {
				return 0;
			}
			return Math.min( Math.max( maxPages, 1 ) - 1, oneBased - 1 );
		} catch ( err ) {
			return 0;
		}
	}

	/**
	 * Persist current step in the query string (replaceState — refresh-friendly, no history spam).
	 *
	 * @param {number} formId Form post ID.
	 * @param {number} index Zero-based page index.
	 * @return {void}
	 */
	function writeMultipageIndexToUrl( formId, index ) {
		try {
			const url = new URL( window.location.href );
			const params = url.searchParams;
			const formFilter = params.get( 'wek_page_form' );
			if ( formFilter && String( formId ) !== String( formFilter ) ) {
				return;
			}
			if ( index <= 0 ) {
				params.delete( 'wek_page' );
				if ( formFilter && String( formId ) === String( formFilter ) ) {
					params.delete( 'wek_page_form' );
				}
			} else {
				params.set( 'wek_page', String( index + 1 ) );
				const multipageForms = document.querySelectorAll(
					'[data-we-formkit][data-pagination="per_section"]'
				);
				if ( multipageForms.length > 1 && formId ) {
					params.set( 'wek_page_form', String( formId ) );
				}
			}
			const qs = params.toString();
			const next = url.pathname + ( qs ? '?' + qs : '' ) + url.hash;
			window.history.replaceState( null, '', next );
		} catch ( err ) {
			/* ignore */
		}
	}

	function initPagination( root ) {
		const mode = root.getAttribute( 'data-pagination' ) || ( window.weFormkit && window.weFormkit.pagination ) || 'single';
		if ( mode !== 'per_section' ) {
			return;
		}
		const form = qs( root, '[data-wek-form]' );
		const sections = qsa( form, '[data-wek-section]' );
		if ( sections.length < 2 ) {
			return;
		}

		const nav = qs( form, '[data-wek-nav]' );
		const prevBtn = qs( form, '[data-wek-prev]' );
		const nextBtn = qs( form, '[data-wek-next]' );
		const submitWrap = qs( form, '[data-wek-submit-wrap]' );
		const progress = qs( root, '[data-wek-progress]' );
		const i18n = ( window.weFormkit && window.weFormkit.i18n ) || {};
		const formId = parseInt( root.getAttribute( 'data-form-id' ) || '0', 10 ) || 0;
		let index = readMultipageIndexFromUrl( formId, sections.length );

		function sync() {
			const pages = sections;
			pages.forEach( function ( section, i ) {
				const on = i === index;
				section.classList.toggle( 'is-page-hidden', ! on );
				section.setAttribute( 'data-wek-page-active', on ? '1' : '0' );
				if ( on ) {
					section.removeAttribute( 'hidden' );
				}
			} );
			if ( nav ) {
				nav.hidden = false;
			}
			if ( prevBtn ) {
				prevBtn.disabled = index <= 0;
			}
			if ( nextBtn ) {
				nextBtn.hidden = index >= pages.length - 1;
			}
			if ( submitWrap ) {
				submitWrap.hidden = index < pages.length - 1;
			}
			if ( progress ) {
				progress.hidden = false;
				const tpl = i18n.pageOf || 'Step %1$d of %2$d';
				progress.textContent = tpl
					.replace( '%1$d', String( index + 1 ) )
					.replace( '%2$d', String( pages.length ) );
			}
			writeMultipageIndexToUrl( formId, index );
		}

		function validatePage() {
			const section = sections[ index ];
			if ( ! section ) {
				return true;
			}
			let ok = true;
			const showSuccess = inlineValidationEnabled( root );
			qsa( section, '[data-wek-field]' ).forEach( function ( fieldEl ) {
				if ( ! fieldIsEffectivelyVisible( fieldEl ) ) {
					return;
				}
				const msg = getFieldValidationMessage( fieldEl );
				if ( ! applyFieldValidationResult( form, fieldEl, msg, showSuccess ) ) {
					ok = false;
				}
			} );
			return ok;
		}

		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				clearErrors( form );
				if ( ! validatePage() ) {
					return;
				}
				if ( index < sections.length - 1 ) {
					index += 1;
					sync();
				}
			} );
		}
		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				if ( index > 0 ) {
					index -= 1;
					sync();
				}
			} );
		}

		root.classList.add( 'we-formkit--multipage' );
		root.addEventListener( 'wek-go-page', function ( event ) {
			const next = event && event.detail ? event.detail.index : null;
			if ( typeof next === 'number' && next >= 0 && next < sections.length ) {
				index = next;
				sync();
			}
		} );
		sync();
	}

	function bindSaveResume( root ) {
		const cfg = window.weFormkit || {};
		if ( ! cfg.saveResume || ! cfg.draftUrl ) {
			return;
		}
		const form = qs( root, '[data-wek-form]' );
		const btn = qs( form, '[data-wek-save-progress]' );
		const emailInput = qs( form, '[data-wek-save-email]' );
		const remindInput = qs( form, '[data-wek-save-remind]' );
		const remindLead = qs( form, '[data-wek-save-remind-lead]' );
		const remindLeadWrap = qs( form, '[data-wek-save-remind-lead-wrap]' );
		const saveUi = qsa( form, '[data-wek-save-ui]' );
		const i18n = cfg.i18n || {};
		const minFilled = Math.max( 0, parseInt( cfg.saveMinFilled, 10 ) || 0 );
		const SKIP_TYPES = { html: true, hidden: true };
		const formId = parseInt( root.getAttribute( 'data-form-id' ) || cfg.formId || 0, 10 ) || 0;
		const draftStorageKey = 'wek_draft_token_' + String( formId );
		let draftToken = '';
		try {
			draftToken = String( window.sessionStorage.getItem( draftStorageKey ) || '' );
		} catch ( err ) {
			draftToken = '';
		}

		function fieldIsFilled( fieldEl ) {
			const type = fieldEl.getAttribute( 'data-field-type' ) || '';
			if ( SKIP_TYPES[ type ] ) {
				return false;
			}
			if ( type === 'checkbox' || type === 'consent' ) {
				const box = qs( fieldEl, 'input[type="checkbox"]' );
				return !!( box && box.checked );
			}
			if ( type === 'checkboxes' ) {
				return qsa( fieldEl, 'input[type="checkbox"]:checked' ).length > 0;
			}
			if ( type === 'radio' || type === 'radio_image' ) {
				return !!qs( fieldEl, 'input[type="radio"]:checked' );
			}
			if ( type === 'upload' ) {
				const input = qs( fieldEl, 'input[type="file"]' );
				return !!( input && input.files && input.files.length > 0 );
			}
			if ( type === 'signature' ) {
				const sig = qs( fieldEl, '[data-wek-signature-input]' );
				return !!( sig && String( sig.value || '' ).trim() );
			}
			if ( type === 'repeater' ) {
				return qsa( fieldEl, '[data-wek-repeater-input]' ).some( function ( input ) {
					return String( input.value || '' ).trim() !== '';
				} );
			}
			if ( type === 'matrix' ) {
				return matrixFieldHasAnswer( fieldEl );
			}
			const input = qs( fieldEl, 'input, textarea, select' );
			if ( ! input || input.type === 'file' ) {
				return false;
			}
			return String( input.value || '' ).trim() !== '';
		}

		function countFilledFields() {
			let count = 0;
			qsa( form, '[data-wek-field]' ).forEach( function ( fieldEl ) {
				if ( fieldEl.classList.contains( 'is-hidden' ) ) {
					return;
				}
				const section = fieldEl.closest( '[data-wek-section]' );
				if ( section && section.classList.contains( 'is-hidden' ) ) {
					return;
				}
				if ( fieldIsFilled( fieldEl ) ) {
					count += 1;
				}
			} );
			return count;
		}

		/**
		 * True when every visible required field is filled/valid — submit is enough.
		 */
		function formIsComplete() {
			let sawRequired = false;
			let complete = true;
			qsa( form, '[data-wek-field]' ).forEach( function ( fieldEl ) {
				const type = fieldEl.getAttribute( 'data-field-type' ) || '';
				if ( SKIP_TYPES[ type ] ) {
					return;
				}
				if ( fieldEl.classList.contains( 'is-hidden' ) ) {
					return;
				}
				const section = fieldEl.closest( '[data-wek-section]' );
				if ( section && section.classList.contains( 'is-hidden' ) ) {
					return;
				}
				if ( fieldEl.getAttribute( 'data-required' ) !== '1' ) {
					return;
				}
				sawRequired = true;
				if ( getFieldValidationMessage( fieldEl ) ) {
					complete = false;
				}
			} );
			return sawRequired && complete;
		}

		function syncSaveUnlock() {
			if ( ! saveUi.length ) {
				return;
			}
			const unlocked = minFilled <= 0 || countFilledFields() >= minFilled;
			const show = unlocked && ! formIsComplete();
			saveUi.forEach( function ( el ) {
				if ( show ) {
					el.removeAttribute( 'hidden' );
				} else {
					el.setAttribute( 'hidden', '' );
				}
			} );
		}

		function syncRemindLead() {
			const on = !!( remindInput && remindInput.checked );
			if ( remindLeadWrap ) {
				if ( on ) {
					remindLeadWrap.removeAttribute( 'hidden' );
				} else {
					remindLeadWrap.setAttribute( 'hidden', '' );
				}
			}
			if ( remindLead ) {
				remindLead.disabled = ! on;
			}
		}

		function firstFormEmail() {
			const fields = qsa( form, '[data-field-type="email"]' );
			for ( let i = 0; i < fields.length; i++ ) {
				if ( fields[ i ].classList.contains( 'is-hidden' ) ) {
					continue;
				}
				const input = qs( fields[ i ], 'input[type="email"], input' );
				const val = input ? String( input.value || '' ).trim() : '';
				if ( val.indexOf( '@' ) !== -1 ) {
					return val;
				}
			}
			return '';
		}

		function syncSaveEmailPrefill() {
			if ( ! emailInput || String( emailInput.value || '' ).trim() ) {
				return;
			}
			const fromForm = firstFormEmail();
			if ( fromForm ) {
				emailInput.value = fromForm;
			}
		}

		function fillValues( values ) {
			if ( ! values || typeof values !== 'object' ) {
				return;
			}
			Object.keys( values ).forEach( function ( id ) {
				const fieldEl = qs( form, '[data-field-id="' + id + '"]' );
				if ( ! fieldEl ) {
					return;
				}
				const type = fieldEl.getAttribute( 'data-field-type' );
				const val = values[ id ];
				if ( type === 'checkbox' || type === 'consent' ) {
					const box = qs( fieldEl, 'input[type="checkbox"]' );
					if ( box ) {
						box.checked = val === '1' || val === true;
					}
					return;
				}
				if ( type === 'checkboxes' && Array.isArray( val ) ) {
					const otherTexts = [];
					qsa( fieldEl, 'input[type="checkbox"]' ).forEach( function ( box ) {
						if ( box.hasAttribute( 'data-wek-other' ) ) {
							return;
						}
						box.checked = val.indexOf( box.value ) !== -1;
					} );
					qsa( fieldEl, '[data-wek-checkboxes-custom-item]' ).forEach( function ( item ) {
						item.remove();
					} );
					val.forEach( function ( item ) {
						const s = String( item || '' );
						if ( s === '__other__' || s.indexOf( 'other:' ) === 0 ) {
							otherTexts.push( s.indexOf( 'other:' ) === 0 ? s.slice( 6 ) : '' );
						}
					} );
					otherTexts.forEach( function ( text ) {
						addCheckboxesCustomItem( fieldEl, text );
					} );
					syncCheckboxesAddButton( fieldEl );
					return;
				}
				if ( ( type === 'radio' || type === 'radio_image' ) && val ) {
					qsa( fieldEl, 'input[type="radio"]' ).forEach( function ( radio ) {
						radio.checked = radio.value === String( val );
					} );
					return;
				}
				if ( type === 'signature' || type === 'upload' || type === 'repeater' ) {
					return;
				}
				if ( type === 'matrix' && val && typeof val === 'object' && ! Array.isArray( val ) ) {
					ensureMatrixCustomRowsFromValues( fieldEl, val );
					qsa( fieldEl, '[data-wek-matrix-row]' ).forEach( function ( row ) {
						const rowId = row.getAttribute( 'data-wek-matrix-row' );
						const rowVal = rowId && val[ rowId ] ? val[ rowId ] : null;
						const labelInput = qs( row, '[data-wek-matrix-label]' );
						if ( labelInput ) {
							labelInput.value = rowVal && rowVal.label != null ? String( rowVal.label ) : '';
						}
						const onBox = qs( row, '[data-wek-matrix-on]' );
						if ( onBox ) {
							onBox.checked = !!( rowVal && rowVal.on );
						}
						qsa( row, '[data-wek-matrix-col]' ).forEach( function ( input ) {
							const colId = input.getAttribute( 'data-wek-matrix-col' );
							if ( ! colId ) {
								return;
							}
							const cell = rowVal && Object.prototype.hasOwnProperty.call( rowVal, colId ) ? rowVal[ colId ] : null;
							if ( input.type === 'checkbox' ) {
								input.checked = !! cell;
								return;
							}
							if ( input.type === 'radio' ) {
								input.checked = cell != null && String( cell ) === String( input.value );
								return;
							}
							input.value = cell != null ? String( cell ) : '';
						} );
						if ( onBox ) {
							setMatrixRowEnabled( row, !! onBox.checked );
						}
					} );
					return;
				}
				const input = qs( fieldEl, 'input, textarea, select' );
				if ( input ) {
					input.value = val != null ? String( val ) : '';
				}
			} );
			applyConditionals( root );
			syncSaveEmailPrefill();
			syncSaveUnlock();
		}

		form.addEventListener( 'input', syncSaveUnlock );
		form.addEventListener( 'change', syncSaveUnlock );
		form.addEventListener( 'wek:conditionals', syncSaveUnlock );
		syncSaveUnlock();

		if ( remindInput ) {
			remindInput.addEventListener( 'change', syncRemindLead );
			syncRemindLead();
		}

		if ( emailInput ) {
			form.addEventListener( 'change', function ( event ) {
				const t = event.target;
				if ( t && t.matches && t.matches( 'input[type="email"]' ) && t !== emailInput ) {
					syncSaveEmailPrefill();
				}
			} );
			syncSaveEmailPrefill();
		}

		if ( btn ) {
			btn.addEventListener( 'click', function () {
				syncSaveUnlock();
				if ( minFilled > 0 && countFilledFields() < minFilled ) {
					setStatus(
						root,
						i18n.saveTooEarly || 'Fill in a few fields before saving your progress.',
						true
					);
					return;
				}
				syncSaveEmailPrefill();
				const email = emailInput ? String( emailInput.value || '' ).trim() : firstFormEmail();
				if ( ! email || email.indexOf( '@' ) === -1 ) {
					setStatus(
						root,
						i18n.saveEmailNeeded || 'Enter an email address to receive your resume link.',
						true
					);
					if ( emailInput ) {
						emailInput.focus();
					}
					return;
				}

				const values = collectValues( form );
				const sections = qsa( form, '[data-wek-section]' );
				let pageIndex = 0;
				sections.forEach( function ( section, i ) {
					if ( section.getAttribute( 'data-wek-page-active' ) === '1' ) {
						pageIndex = i;
					}
				} );

				const prevLabel = btn.textContent;
				btn.disabled = true;
				btn.textContent = i18n.savingProgress || 'Saving…';

				fetch( cfg.draftUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': cfg.nonce || '',
					},
					body: JSON.stringify( {
						nonce: cfg.nonce,
						form_id: formId || cfg.formId,
						email: email,
						token: draftToken || undefined,
						values: values,
						page_index: pageIndex,
						page_url: window.location.href.split( '#' )[ 0 ],
						remind: !!( remindInput && remindInput.checked ),
						remind_lead: remindLead
							? parseInt( remindLead.value, 10 ) || 0
							: 0,
					} ),
				} )
					.then( function ( response ) {
						return response.json().then( function ( data ) {
							return { ok: response.ok, data: data };
						} );
					} )
					.then( function ( result ) {
						btn.disabled = false;
						btn.textContent = prevLabel;
						const data = result.data || {};
						if ( result.ok && data.success ) {
							if ( data.token ) {
								draftToken = String( data.token );
								try {
									window.sessionStorage.setItem( draftStorageKey, draftToken );
								} catch ( err ) {
									// Ignore quota / private mode.
								}
							}
							if ( data.email_sent ) {
								const tpl =
									i18n.savedProgress ||
									'Progress saved. We sent a resume link to %s.';
								setStatus( root, tpl.replace( '%s', email ), false );
							} else if ( data.email_skipped ) {
								setStatus(
									root,
									i18n.saveUpdated ||
										'Progress updated. Use the resume link from your earlier email.',
									false
								);
							} else {
								setStatus(
									root,
									i18n.saveMailFailed ||
										'Progress was saved, but the resume email could not be sent. Please try again or contact the site owner.',
									true
								);
							}
							return;
						}
						const msg =
							data.message ||
							( data.data && data.data.message ) ||
							i18n.error ||
							'Error';
						setStatus( root, msg, true );
					} )
					.catch( function () {
						btn.disabled = false;
						btn.textContent = prevLabel;
						setStatus( root, i18n.error || 'Error', true );
					} );
			} );
		}

		try {
			const params = new URLSearchParams( window.location.search );
			const resume = params.get( 'wek_resume' );
			if ( ! resume ) {
				return;
			}
			fetch( cfg.draftUrl.replace( /\/?$/, '/' ) + encodeURIComponent( resume ), {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': cfg.nonce || '' },
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					if ( ! result.ok || ! result.data ) {
						return;
					}
					draftToken = String( resume );
					try {
						window.sessionStorage.setItem( draftStorageKey, draftToken );
					} catch ( err ) {
						// Ignore quota / private mode.
					}
					fillValues( result.data.values );
					syncAllFieldResets( root );
					if ( typeof result.data.page_index === 'number' && result.data.page_index > 0 ) {
						root.dispatchEvent(
							new CustomEvent( 'wek-go-page', {
								detail: { index: result.data.page_index },
							} )
						);
					}
					setStatus( root, i18n.resumeLoaded || 'Your saved progress was restored.', false );
				} )
				.catch( function () {
					/* ignore */
				} );
		} catch ( e ) {
			/* ignore */
		}
	}

	function bindSignatures( root ) {
		qsa( root, '[data-wek-signature]' ).forEach( function ( wrap ) {
			const canvas = qs( wrap, 'canvas' );
			const input = qs( wrap, '[data-wek-signature-input]' );
			const clearBtn = qs( wrap, '.we-formkit__signature-clear' );
			if ( ! canvas || ! input || canvas.getAttribute( 'data-wek-bound' ) ) {
				return;
			}
			canvas.setAttribute( 'data-wek-bound', '1' );

			const ctx = canvas.getContext( '2d' );
			if ( ! ctx ) {
				return;
			}

			const pen = wrap.getAttribute( 'data-pen' ) || '#222222';
			const bg = wrap.getAttribute( 'data-bg' ) || '#ffffff';
			let drawing = false;
			let dirty = false;

			function paintBg() {
				ctx.fillStyle = bg;
				ctx.fillRect( 0, 0, canvas.width, canvas.height );
			}

			function syncValue() {
				if ( ! dirty ) {
					input.value = '';
					return;
				}
				input.value = canvas.toDataURL( 'image/png' );
			}

			function pos( event ) {
				const rect = canvas.getBoundingClientRect();
				const clientX = event.touches ? event.touches[ 0 ].clientX : event.clientX;
				const clientY = event.touches ? event.touches[ 0 ].clientY : event.clientY;
				return {
					x: ( ( clientX - rect.left ) / rect.width ) * canvas.width,
					y: ( ( clientY - rect.top ) / rect.height ) * canvas.height,
				};
			}

			function start( event ) {
				event.preventDefault();
				drawing = true;
				const p = pos( event );
				ctx.beginPath();
				ctx.moveTo( p.x, p.y );
			}

			function move( event ) {
				if ( ! drawing ) {
					return;
				}
				event.preventDefault();
				const p = pos( event );
				ctx.strokeStyle = pen;
				ctx.lineWidth = 2.2;
				ctx.lineCap = 'round';
				ctx.lineJoin = 'round';
				ctx.lineTo( p.x, p.y );
				ctx.stroke();
				dirty = true;
			}

			function end() {
				if ( ! drawing ) {
					return;
				}
				drawing = false;
				syncValue();
			}

			paintBg();
			canvas.addEventListener( 'mousedown', start );
			canvas.addEventListener( 'mousemove', move );
			canvas.addEventListener( 'mouseup', end );
			canvas.addEventListener( 'mouseleave', end );
			canvas.addEventListener( 'touchstart', start, { passive: false } );
			canvas.addEventListener( 'touchmove', move, { passive: false } );
			canvas.addEventListener( 'touchend', end );

			if ( clearBtn ) {
				clearBtn.addEventListener( 'click', function () {
					dirty = false;
					paintBg();
					input.value = '';
				} );
			}
		} );
	}

	function validateVisibleFields( form, root ) {
		let ok = true;
		const showSuccess = inlineValidationEnabled( root );
		qsa( form, '[data-wek-field]' ).forEach( function ( fieldEl ) {
			if ( ! fieldIsEffectivelyVisible( fieldEl ) ) {
				return;
			}
			const msg = getFieldValidationMessage( fieldEl );
			if ( ! applyFieldValidationResult( form, fieldEl, msg, showSuccess ) ) {
				ok = false;
			}
		} );
		return ok;
	}

	function initRoot( root ) {
		const form = qs( root, '[data-wek-form]' );
		if ( ! form || form.getAttribute( 'data-wek-ready' ) ) {
			return;
		}
		form.setAttribute( 'data-wek-ready', '1' );
		bindSignatures( root );
		initPagination( root );
		bindSaveResume( root );
		bindInlineValidation( root, form );
		bindMatrixRows( root );
		bindCheckboxesOther( root );
		bindFieldResets( root );

		qsa( form, '[data-field-type="checkboxes"]' ).forEach( function ( fieldEl ) {
			syncCheckboxesMaxLock( fieldEl );
		} );

		initConditionals( root, form );
		initRepeaters( form );
		applyUrlPrefill( form );
		if ( formHasUrlPrefillParams( form ) ) {
			scheduleScrollFormAfterPrefill( root );
		}

		const cfg = window.weFormkit || {};
		if ( cfg.autofill && wantsAutofill() ) {
			autofillForm( form, root );
			syncAllFieldResets( root );
			if ( wantsAutosubmit() ) {
				scheduleAutofillSubmit( form, root, cfg );
			} else {
				setStatus(
					root,
					( cfg.i18n && cfg.i18n.autofillManual ) ||
						'Test fill applied. Click Submit form when ready.',
					false
				);
			}
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			event.stopPropagation();

			if ( form.getAttribute( 'data-wek-submitting' ) === '1' ) {
				return;
			}

			clearErrors( form );
			setStatus( root, '', false );

			if ( ! validateVisibleFields( form, root ) ) {
				setStatus(
					root,
					( ( window.weFormkit || {} ).i18n && ( window.weFormkit || {} ).i18n.correctFields ) ||
						'Please correct the highlighted fields.',
					true
				);
				return;
			}

			form.setAttribute( 'data-wek-submitting', '1' );

			const liveCfg = window.weFormkit || {};
			const values = collectValues( form );
			const honeypot = qs( form, '[name="website_url"]' );
			const useMultipart = formHasFiles( form );

			const submitBtn = qs( form, '[data-wek-submit], [type="submit"]' );
			setSubmitBusy(
				submitBtn,
				true,
				( liveCfg.i18n && liveCfg.i18n.submitting ) || 'Submitting…'
			)

			const fetchOpts = {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					// Must be a wp_rest nonce so cookie auth keeps the same user as page render.
					'X-WP-Nonce': liveCfg.nonce || '',
				},
			};

			if ( useMultipart ) {
				fetchOpts.body = buildMultipartBody( form, liveCfg, values, honeypot );
			} else {
				fetchOpts.headers[ 'Content-Type' ] = 'application/json';
				fetchOpts.body = JSON.stringify( {
					nonce: liveCfg.nonce,
					form_id: liveCfg.formId,
					token: liveCfg.token || '',
					_wek_started: liveCfg.started,
					website_url: honeypot ? honeypot.value : '',
					source_url: typeof window !== 'undefined' && window.location ? String( window.location.href ) : '',
					values: values,
				} );
			}

			fetch( liveCfg.restUrl, fetchOpts )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, status: response.status, data: data };
					} );
				} )
				.then( function ( result ) {
					form.removeAttribute( 'data-wek-submitting' );
					setSubmitBusy( submitBtn, false );

					if ( result.ok && result.data && result.data.success ) {
						const conf = result.data.confirmation || {};
						const mode = conf.mode || 'message';
						if ( mode === 'redirect' && conf.redirect_url ) {
							window.location.href = conf.redirect_url;
							return;
						}
						if ( mode === 'page' && conf.page_url ) {
							window.location.href = conf.page_url;
							return;
						}
						const okMsg =
							wantsAutofill() && liveCfg.autofill
								? ( liveCfg.i18n && liveCfg.i18n.autofillDone ) ||
								  result.data.message
								: result.data.message || 'OK';
						setStatus( root, okMsg, false );
						setInfoDocuments( root, result.data.info_documents || [] );
						form.reset();
						applyConditionals( root );
						form.setAttribute( 'hidden', 'hidden' );
						const pageProgress = qs(
							root,
							'[data-wek-progress]'
						);
						if ( pageProgress ) {
							pageProgress.setAttribute( 'hidden', 'hidden' );
						}
						return;
					}

					const errData = result.data || {};
					const fieldErrors =
						( errData.data && errData.data.errors ) ||
						( errData.errors ) ||
						{};
					const hasFieldErrors = Object.keys( fieldErrors ).length > 0;
					const message = hasFieldErrors
						? ( liveCfg.i18n && liveCfg.i18n.correctFields ) ||
						  errData.message ||
						  'Please correct the highlighted fields.'
						: ( errData.message ) ||
						  ( liveCfg.i18n && liveCfg.i18n.error ) ||
						  'Something went wrong.';
					setStatus( root, message, true );
					setInfoDocuments( root, [] );

					Object.keys( fieldErrors ).forEach( function ( key ) {
						showFieldError( form, key, fieldErrors[ key ] );
					} );
				} )
				.catch( function () {
					form.removeAttribute( 'data-wek-submitting' );
					setSubmitBusy( submitBtn, false );
					setStatus(
						root,
						( liveCfg.i18n && liveCfg.i18n.error ) || 'Something went wrong.',
						true
					);
				} );
		} );
	}

	function boot() {
		qsa( document, '[data-we-formkit]' ).forEach( initRoot );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
