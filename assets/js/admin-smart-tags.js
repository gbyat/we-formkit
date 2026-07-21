/**
 * Smart tag picker — insert merge tags into inputs, textareas, or TinyMCE.
 */
( function () {
	'use strict';

	const boot = window.weFormkitSmartTags || {};
	const i18n = boot.i18n || {};

	function closest( el, sel ) {
		return el && el.closest ? el.closest( sel ) : null;
	}

	function insertAtCursor( field, text ) {
		if ( ! field ) {
			return;
		}
		field.focus();
		const start = field.selectionStart;
		const end = field.selectionEnd;
		const value = field.value || '';
		if ( typeof start === 'number' && typeof end === 'number' ) {
			field.value = value.slice( 0, start ) + text + value.slice( end );
			const pos = start + text.length;
			field.setSelectionRange( pos, pos );
		} else {
			field.value = value + text;
		}
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	function insertIntoTinymce( editorId, text ) {
		if ( window.tinymce ) {
			const ed = window.tinymce.get( editorId );
			if ( ed && ! ed.isHidden() ) {
				ed.focus();
				ed.execCommand( 'mceInsertContent', false, text );
				return true;
			}
		}
		const ta = document.getElementById( editorId );
		if ( ta ) {
			insertAtCursor( ta, text );
			return true;
		}
		return false;
	}

	function insertTag( root, tag ) {
		const mode = root.getAttribute( 'data-mode' ) || 'input';
		const target = root.getAttribute( 'data-target' ) || '';
		if ( ! target || ! tag ) {
			return;
		}
		if ( mode === 'tinymce' ) {
			insertIntoTinymce( target, tag );
			return;
		}
		const field = document.getElementById( target );
		if ( field ) {
			insertAtCursor( field, tag );
		}
	}

	function closeAll( except ) {
		document.querySelectorAll( '.wek-smart-tags.is-open' ).forEach( function ( el ) {
			if ( el !== except ) {
				el.classList.remove( 'is-open' );
				const panel = el.querySelector( '.wek-smart-tags__panel' );
				if ( panel ) {
					panel.hidden = true;
				}
				const btn = el.querySelector( '.wek-smart-tags__toggle' );
				if ( btn ) {
					btn.setAttribute( 'aria-expanded', 'false' );
				}
			}
		} );
	}

	function toggle( root ) {
		const open = ! root.classList.contains( 'is-open' );
		closeAll( open ? root : null );
		const panel = root.querySelector( '.wek-smart-tags__panel' );
		const btn = root.querySelector( '.wek-smart-tags__toggle' );
		root.classList.toggle( 'is-open', open );
		if ( panel ) {
			panel.hidden = ! open;
		}
		if ( btn ) {
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	function bind( root ) {
		const toggleBtn = root.querySelector( '.wek-smart-tags__toggle' );
		if ( toggleBtn ) {
			toggleBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				toggle( root );
			} );
		}
		root.querySelectorAll( '[data-wek-tag]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				insertTag( root, btn.getAttribute( 'data-wek-tag' ) || '' );
				closeAll( null );
			} );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		if ( ! closest( e.target, '.wek-smart-tags' ) ) {
			closeAll( null );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			closeAll( null );
		}
	} );

	document.querySelectorAll( '[data-wek-smart-tags]' ).forEach( bind );
} )();
