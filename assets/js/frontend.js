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

	function applyConditionals( root ) {
		const form = qs( root, '[data-wek-form]' );
		if ( ! form ) {
			return;
		}
		const values = collectValues( form );

		qsa( root, '[data-wek-section], [data-wek-field]' ).forEach( function ( el ) {
			const field = el.getAttribute( 'data-show-field' );
			if ( ! field ) {
				return;
			}
			const op = el.getAttribute( 'data-show-op' ) || 'equals';
			const value = el.getAttribute( 'data-show-value' ) || '';
			const visible = matchesRule( field, op, value, values );
			el.classList.toggle( 'is-hidden', ! visible );
			el.setAttribute( 'aria-hidden', visible ? 'false' : 'true' );

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
		} );
	}

	function clearErrors( form ) {
		qsa( form, '[data-wek-error]' ).forEach( function ( el ) {
			el.hidden = true;
			el.textContent = '';
		} );
		qsa( form, '.is-invalid' ).forEach( function ( el ) {
			el.classList.remove( 'is-invalid' );
		} );
	}

	function showFieldError( form, fieldId, message ) {
		const field = qs( form, '[data-field-id="' + fieldId + '"]' );
		if ( ! field ) {
			return;
		}
		field.classList.add( 'is-invalid' );
		const err = qs( field, '[data-wek-error]' );
		if ( err ) {
			err.hidden = false;
			err.textContent = message;
		}
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

	function initRoot( root ) {
		const form = qs( root, '[data-wek-form]' );
		if ( ! form || form.getAttribute( 'data-wek-ready' ) ) {
			return;
		}
		form.setAttribute( 'data-wek-ready', '1' );

		form.addEventListener( 'change', function () {
			applyConditionals( root );
		} );
		form.addEventListener( 'input', function () {
			applyConditionals( root );
		} );
		applyConditionals( root );
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
			form.setAttribute( 'data-wek-submitting', '1' );

			clearErrors( form );
			setStatus( root, '', false );

			const liveCfg = window.weFormkit || {};
			const values = collectValues( form );
			const honeypot = qs( form, '[name="website_url"]' );
			const useMultipart = formHasFiles( form );

			const submitBtn = qs( form, '[type="submit"]' );
			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.textContent = ( liveCfg.i18n && liveCfg.i18n.submitting ) || 'Submitting…';
			}

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
					if ( submitBtn ) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Submit form';
					}

					if ( result.ok && result.data && result.data.success ) {
						const okMsg =
							wantsAutofill() && liveCfg.autofill
								? ( liveCfg.i18n && liveCfg.i18n.autofillDone ) ||
								  result.data.message
								: result.data.message || 'OK';
						setStatus( root, okMsg, false );
						form.reset();
						applyConditionals( root );
						form.setAttribute( 'hidden', 'hidden' );
						return;
					}

					const errData = result.data || {};
					const message =
						( errData.message ) ||
						( liveCfg.i18n && liveCfg.i18n.error ) ||
						'Something went wrong.';
					setStatus( root, message, true );

					const fieldErrors =
						( errData.data && errData.data.errors ) ||
						( errData.errors ) ||
						{};
					Object.keys( fieldErrors ).forEach( function ( key ) {
						showFieldError( form, key, fieldErrors[ key ] );
					} );
				} )
				.catch( function () {
					form.removeAttribute( 'data-wek-submitting' );
					if ( submitBtn ) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Submit form';
					}
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
