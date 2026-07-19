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

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors( form );
			setStatus( root, '', false );

			const cfg = window.weFormkit || {};
			const values = collectValues( form );
			const honeypot = qs( form, '[name="website_url"]' );
			const useMultipart = formHasFiles( form );

			const submitBtn = qs( form, '[type="submit"]' );
			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.textContent = ( cfg.i18n && cfg.i18n.submitting ) || 'Submitting…';
			}

			const fetchOpts = {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': cfg.nonce,
				},
			};

			if ( useMultipart ) {
				fetchOpts.body = buildMultipartBody( form, cfg, values, honeypot );
			} else {
				fetchOpts.headers[ 'Content-Type' ] = 'application/json';
				fetchOpts.body = JSON.stringify( {
					nonce: cfg.nonce,
					form_id: cfg.formId,
					token: cfg.token || '',
					_wek_started: cfg.started,
					website_url: honeypot ? honeypot.value : '',
					values: values,
				} );
			}

			fetch( cfg.restUrl, fetchOpts )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, status: response.status, data: data };
					} );
				} )
				.then( function ( result ) {
					if ( submitBtn ) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Submit form';
					}

					if ( result.ok && result.data && result.data.success ) {
						setStatus( root, result.data.message || 'OK', false );
						form.reset();
						applyConditionals( root );
						form.setAttribute( 'hidden', 'hidden' );
						return;
					}

					const errData = result.data || {};
					const message =
						( errData.message ) ||
						( cfg.i18n && cfg.i18n.error ) ||
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
					if ( submitBtn ) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Submit form';
					}
					setStatus(
						root,
						( cfg.i18n && cfg.i18n.error ) || 'Something went wrong.',
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
