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
			if ( type === 'checkboxes' ) {
				values[ id ] = qsa( fieldEl, 'input[type="checkbox"]:checked' ).map( function ( el ) {
					return el.value;
				} );
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
		const n = normalize( value ).toLowerCase();
		return [ '1', 'yes', 'true', 'on' ].indexOf( n ) !== -1;
	}

	function matchesRule( ruleField, op, ruleValue, values ) {
		if ( ! ruleField ) {
			return true;
		}
		const current = values[ ruleField ];
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
			case 'is_not_empty':
				if ( Array.isArray( current ) ) {
					return current.filter( function ( item ) {
						return normalize( item ) !== '';
					} ).length > 0;
				}
				return normalize( current ) !== '';
			default:
				return true;
		}
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
			return matchOneRule( container, values );
		}
		const rules = Array.isArray( container.rules ) ? container.rules : [];
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
		if ( container.field ) {
			deps[ String( container.field ) ] = true;
		}
		const rules = Array.isArray( container.rules ) ? container.rules : [];
		rules.forEach( function ( rule ) {
			if ( rule && rule.field ) {
				deps[ String( rule.field ) ] = true;
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
		const input = qs( fieldEl, 'input, textarea, select' );
		return ! input || ! String( input.value || '' ).trim();
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

	function getFieldValidationMessage( fieldEl ) {
		const type = fieldEl.getAttribute( 'data-field-type' ) || '';
		if ( type === 'html' || type === 'hidden' ) {
			return '';
		}
		const cbMsg = validateCheckboxesField( fieldEl );
		if ( cbMsg ) {
			return cbMsg;
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

	function inlineValidationEnabled( root ) {
		return root && root.getAttribute( 'data-inline-validation' ) === '1';
	}

	function applyFieldValidationResult( form, fieldEl, message, showSuccess ) {
		const id = fieldEl.getAttribute( 'data-field-id' );
		if ( message ) {
			showFieldError( form, id, message );
			return false;
		}
		if ( showSuccess && fieldHasMeaningfulValue( fieldEl ) ) {
			showFieldValid( fieldEl );
		} else {
			clearFieldFeedback( fieldEl );
		}
		return true;
	}

	function validateFieldLive( form, fieldEl, root ) {
		if ( fieldEl.classList.contains( 'is-hidden' ) ) {
			clearFieldFeedback( fieldEl );
			return true;
		}
		const msg = getFieldValidationMessage( fieldEl );
		return applyFieldValidationResult( form, fieldEl, msg, inlineValidationEnabled( root ) );
	}

	function prepareInlineControlRows( form ) {
		qsa( form, '[data-wek-field]' ).forEach( function ( fieldEl ) {
			const type = fieldEl.getAttribute( 'data-field-type' ) || '';
			if ( type === 'html' || type === 'hidden' ) {
				return;
			}
			const icon = qs( fieldEl, '[data-wek-validity]' );
			if ( ! icon ) {
				return;
			}
			if ( icon.parentElement && icon.parentElement.classList.contains( 'we-formkit__control-row' ) ) {
				return;
			}

			let control = null;
			let rowMod = '';

			if ( type === 'radio' || type === 'checkboxes' || type === 'radio_image' ) {
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

	function rowLabelText( index ) {
		const cfg = window.weFormkit || {};
		const base = ( cfg.i18n && cfg.i18n.rowLabel ) || 'Row %d';
		return String( base ).replace( '%d', String( index + 1 ) );
	}

	function reindexRepeater( repeater ) {
		const fieldId = repeater.getAttribute( 'data-field-id' ) || '';
		const rows = qsa( repeater, '[data-wek-repeater-row]' );
		rows.forEach( function ( row, index ) {
			const label = qs( row, '[data-wek-repeater-row-label]' );
			if ( label ) {
				label.textContent = rowLabelText( index );
			}
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
				removeBtn.disabled = rows.length <= Math.max( 1, minItems || 1 );
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
				return '42';
			case 'date':
				return new Date().toISOString().slice( 0, 10 );
			case 'time':
				return '10:30';
			case 'datetime':
				return new Date().toISOString().slice( 0, 16 );
			case 'textarea':
				return 'WE Formkit smoke test — auto-filled textarea (' + stamp + ').';
			case 'hidden':
				return 'smoke-hidden-' + stamp;
			default:
				return 'Smoke ' + ( name || 'value' ) + ' ' + stamp;
		}
	}

	function autofillForm( form, root ) {
		qsa( form, '[data-wek-field]' ).forEach( function ( fieldEl ) {
			if ( fieldEl.classList.contains( 'is-hidden' ) || fieldEl.getAttribute( 'aria-hidden' ) === 'true' ) {
				return;
			}
			const type = fieldEl.getAttribute( 'data-field-type' ) || '';
			const labelEl = qs( fieldEl, '.we-formkit__label, legend' );
			const label = labelEl ? labelEl.textContent.replace( /\*/, '' ).trim() : 'field';

			if ( type === 'html' ) {
				return;
			}

			if ( type === 'upload' ) {
				qsa( fieldEl, 'input[type="file"]' ).forEach( fillFileInput );
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
				if ( radios.length && ! radios.some( function ( r ) { return r.checked; } ) ) {
					radios[ 0 ].checked = true;
					radios[ 0 ].dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				return;
			}

			if ( type === 'checkboxes' ) {
				const boxes = qsa( fieldEl, 'input[type="checkbox"]' );
				if ( boxes.length ) {
					boxes[ 0 ].checked = true;
					boxes[ 0 ].dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				return;
			}

			if ( type === 'repeater' ) {
				qsa( fieldEl, '[data-wek-repeater-input]' ).forEach( function ( input ) {
					if ( input.type === 'checkbox' ) {
						input.checked = true;
					} else if ( input.tagName === 'SELECT' ) {
						if ( input.options.length > 1 ) {
							input.selectedIndex = 1;
						} else if ( input.options.length ) {
							input.selectedIndex = 0;
						}
					} else {
						setNativeValue( input, sampleForType( input.type || 'text', input.getAttribute( 'data-sub-id' ) ) );
					}
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} );
				return;
			}

			const select = qs( fieldEl, 'select' );
			if ( select ) {
				if ( select.options.length > 1 ) {
					select.selectedIndex = 1;
				} else if ( select.options.length ) {
					select.selectedIndex = 0;
				}
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				return;
			}

			const input = qs( fieldEl, 'input:not([type="hidden"]):not([type="file"]), textarea' );
			if ( input ) {
				setNativeValue( input, sampleForType( type || input.type, label ) );
			}

			const hidden = qs( fieldEl, 'input[type="hidden"]' );
			if ( hidden && type === 'hidden' ) {
				setNativeValue( hidden, sampleForType( 'hidden', label ) );
			}
		} );

		applyConditionals( root );
		// Second pass: newly revealed conditional fields.
		qsa( form, '[data-wek-field]' ).forEach( function ( fieldEl ) {
			if ( fieldEl.classList.contains( 'is-hidden' ) ) {
				return;
			}
			const type = fieldEl.getAttribute( 'data-field-type' ) || '';
			if ( type === 'html' || type === 'upload' || type === 'checkbox' || type === 'consent' || type === 'radio' || type === 'radio_image' || type === 'checkboxes' || type === 'repeater' ) {
				return;
			}
			const input = qs( fieldEl, 'input:not([type="file"]), textarea, select' );
			if ( ! input ) {
				return;
			}
			if ( input.tagName === 'SELECT' ) {
				if ( ! input.value && input.options.length ) {
					input.selectedIndex = Math.min( 1, input.options.length - 1 );
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				return;
			}
			if ( ! String( input.value || '' ).trim() ) {
				setNativeValue( input, sampleForType( type || input.type, 'extra' ) );
			}
		} );
	}

	function wantsAutofill() {
		try {
			const params = new URLSearchParams( window.location.search );
			return params.get( 'wek_autofill' ) === '1';
		} catch ( e ) {
			return false;
		}
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
		let index = 0;

		function visibleSections() {
			return sections.filter( function ( section ) {
				return ! section.classList.contains( 'is-hidden' ) || section.getAttribute( 'data-wek-page-active' ) === '1';
			} );
		}

		function pageList() {
			// Pages = sections that are not conditionally hidden by show_when at paint time.
			return sections.filter( function ( section ) {
				const hiddenByRule = section.classList.contains( 'is-hidden' ) && section.getAttribute( 'data-wek-page-forced' ) !== '1';
				// During multipage we manage visibility ourselves; ignore is-hidden from conditionals for counting when aria says visible candidate.
				return true;
			} );
		}

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
		}

		function validatePage() {
			const section = sections[ index ];
			if ( ! section ) {
				return true;
			}
			let ok = true;
			const showSuccess = inlineValidationEnabled( root );
			qsa( section, '[data-wek-field]' ).forEach( function ( fieldEl ) {
				if ( fieldEl.classList.contains( 'is-hidden' ) ) {
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
					qsa( fieldEl, 'input[type="checkbox"]' ).forEach( function ( box ) {
						box.checked = val.indexOf( box.value ) !== -1;
					} );
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
			if ( fieldEl.classList.contains( 'is-hidden' ) ) {
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

		qsa( form, '[data-field-type="checkboxes"]' ).forEach( function ( fieldEl ) {
			syncCheckboxesMaxLock( fieldEl );
		} );

		initConditionals( root, form );
		initRepeaters( form );

		const cfg = window.weFormkit || {};
		if ( cfg.autofill && wantsAutofill() ) {
			autofillForm( form, root );
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
