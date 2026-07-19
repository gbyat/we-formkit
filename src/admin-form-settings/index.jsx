/**
 * Form settings screen — DataForm (React).
 *
 * @see https://developer.wordpress.org/news/2026/01/how-to-use-dataform-to-create-plugin-settings-pages/
 */
import { createRoot, render, useCallback, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';
import './style.css';

const boot = window.weFormkitFormSettings || {};

const COLOR_ROLES = [
	{ id: 'color_accent', key: 'accent', label: __( 'Accent', 'we-formkit' ) },
	{
		id: 'color_accent_soft',
		key: 'accent_soft',
		label: __( 'Soft background', 'we-formkit' ),
	},
	{ id: 'color_surface', key: 'surface', label: __( 'Surface', 'we-formkit' ) },
	{ id: 'color_bg', key: 'bg', label: __( 'Background', 'we-formkit' ) },
	{ id: 'color_ink', key: 'ink', label: __( 'Text', 'we-formkit' ) },
	{ id: 'color_muted', key: 'muted', label: __( 'Muted text', 'we-formkit' ) },
	{ id: 'color_line', key: 'line', label: __( 'Borders', 'we-formkit' ) },
	{ id: 'color_danger', key: 'danger', label: __( 'Errors', 'we-formkit' ) },
];

function ColorEdit( { data, field, onChange } ) {
	const value = data[ field.id ] || '#000000';
	return (
		<div className="wek-dataform-color">
			<input
				type="color"
				value={ value }
				aria-label={ field.label }
				onChange={ ( event ) => {
					onChange( { [ field.id ]: event.target.value } );
				} }
			/>
			<code>{ value }</code>
		</div>
	);
}

function colorsFromMap( map ) {
	const out = {};
	COLOR_ROLES.forEach( ( role ) => {
		out[ role.id ] = map && map[ role.key ] ? map[ role.key ] : '#000000';
	} );
	return out;
}

function flattenSettings( payload ) {
	const colors = payload.colors || {};
	return {
		title: payload.title || '',
		slug: payload.slug || '',
		intro: payload.intro || '',
		privacy_url: payload.privacy_url || '',
		secret_enabled: !! payload.secret_enabled,
		style_preset: payload.style_preset || 'theme',
		...colorsFromMap( colors ),
	};
}

function toApiPayload( data ) {
	const colors = {};
	COLOR_ROLES.forEach( ( role ) => {
		colors[ role.key ] = data[ role.id ] || '';
	} );
	return {
		title: data.title || '',
		slug: data.slug || '',
		intro: data.intro || '',
		privacy_url: data.privacy_url || '',
		secret_enabled: !! data.secret_enabled,
		style: {
			preset: data.style_preset || 'theme',
			colors,
		},
	};
}

function FormSettingsApp() {
	const [ data, setData ] = useState( () =>
		flattenSettings( boot.settings || {} )
	);
	const [ formId, setFormId ] = useState( Number( boot.formId ) || 0 );
	const [ secretUrl, setSecretUrl ] = useState( boot.secretUrl || '' );
	const [ secretToken, setSecretToken ] = useState( boot.secretToken || '' );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ dirty, setDirty ] = useState( false );

	const fields = useMemo(
		() => [
			{
				id: 'title',
				label: __( 'Title', 'we-formkit' ),
				type: 'text',
			},
			{
				id: 'slug',
				label: __( 'Slug', 'we-formkit' ),
				type: 'text',
				description: __(
					'Used in secret links and as a stable form key.',
					'we-formkit'
				),
			},
			{
				id: 'intro',
				label: __( 'Intro text', 'we-formkit' ),
				type: 'text',
				Edit: 'textarea',
			},
			{
				id: 'privacy_url',
				label: __( 'Privacy policy URL', 'we-formkit' ),
				type: 'text',
				description: __(
					'Optional. Falls back to the plugin default, then the site privacy policy.',
					'we-formkit'
				),
			},
			{
				id: 'secret_enabled',
				label: __( 'Require secret token in URL', 'we-formkit' ),
				type: 'boolean',
				Edit: 'toggle',
			},
			{
				id: 'style_preset',
				label: __( 'Color preset', 'we-formkit' ),
				type: 'text',
				description: __(
					'Match site theme needs no picking. Edit any color to customize.',
					'we-formkit'
				),
				elements: [
					{
						value: 'theme',
						label: __( 'Match site theme', 'we-formkit' ),
					},
					{
						value: 'formkit',
						label: __( 'Formkit teal', 'we-formkit' ),
					},
					{
						value: 'custom',
						label: __( 'Custom', 'we-formkit' ),
					},
				],
			},
			...COLOR_ROLES.map( ( role ) => ( {
				id: role.id,
				label: role.label,
				type: 'text',
				Edit: ColorEdit,
			} ) ),
		],
		[]
	);

	const form = useMemo(
		() => ( {
			layout: { type: 'regular', labelPosition: 'top' },
			fields: [
				{
					id: 'general',
					label: __( 'General', 'we-formkit' ),
					layout: { type: 'card', isOpened: true },
					children: [
						'title',
						'slug',
						'intro',
						'privacy_url',
						'secret_enabled',
					],
				},
				{
					id: 'colors',
					label: __( 'Colors', 'we-formkit' ),
					layout: { type: 'card', isOpened: true },
					children: [
						'style_preset',
						...COLOR_ROLES.map( ( role ) => role.id ),
					],
				},
			],
		} ),
		[]
	);

	const onChange = useCallback( ( edits ) => {
		setData( ( prev ) => {
			const next = { ...prev, ...edits };
			if (
				Object.prototype.hasOwnProperty.call( edits, 'style_preset' )
			) {
				const preset = edits.style_preset;
				if ( preset === 'theme' ) {
					Object.assign( next, colorsFromMap( boot.themeColors ) );
				} else if ( preset === 'formkit' ) {
					Object.assign( next, colorsFromMap( boot.formkitColors ) );
				}
			} else {
				const colorTouched = COLOR_ROLES.some( ( role ) =>
					Object.prototype.hasOwnProperty.call( edits, role.id )
				);
				if ( colorTouched ) {
					next.style_preset = 'custom';
				}
			}
			return next;
		} );
		setDirty( true );
		setNotice( null );
	}, [] );

	const saveSettings = useCallback( () => {
		setSaving( true );
		setNotice( null );
		const path =
			formId > 0
				? `/we-formkit/v1/forms/${ formId }/settings`
				: '/we-formkit/v1/forms/settings';
		apiFetch( {
			path,
			method: 'POST',
			data: toApiPayload( data ),
		} )
			.then( ( response ) => {
				if ( response.form_id ) {
					setFormId( response.form_id );
					if ( ! formId && response.edit_url ) {
						window.history.replaceState(
							{},
							'',
							response.edit_url
						);
					}
				}
				if ( response.settings ) {
					setData( flattenSettings( response.settings ) );
				}
				if ( typeof response.secret_url === 'string' ) {
					setSecretUrl( response.secret_url );
				}
				if ( typeof response.secret_token === 'string' ) {
					setSecretToken( response.secret_token );
				}
				setDirty( false );
				setNotice( {
					status: 'success',
					message: __( 'Settings saved.', 'we-formkit' ),
				} );
			} )
			.catch( ( error ) => {
				setNotice( {
					status: 'error',
					message:
						( error && error.message ) ||
						__( 'Could not save settings.', 'we-formkit' ),
				} );
			} )
			.finally( () => {
				setSaving( false );
			} );
	}, [ data, formId ] );

	return (
		<div className="wek-dataform-settings">
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<DataForm
				data={ data }
				fields={ fields }
				form={ form }
				onChange={ onChange }
			/>

			{ data.secret_enabled && secretToken ? (
				<div className="wek-dataform-settings__secret">
					<p>
						<strong>{ __( 'Secret token', 'we-formkit' ) }</strong>
						{ ' ' }
						<code>{ secretToken }</code>
					</p>
					{ secretUrl ? (
						<p>
							<label htmlFor="wek-secret-url">
								{ __(
									'Shareable link (open the page that contains the Formkit Form block):',
									'we-formkit'
								) }
							</label>
							<input
								id="wek-secret-url"
								className="large-text"
								type="text"
								readOnly
								value={ secretUrl }
								onFocus={ ( event ) => event.target.select() }
							/>
						</p>
					) : null }
					{ boot.regenUrl ? (
						<p>
							<a className="button" href={ boot.regenUrl }>
								{ __( 'Regenerate token', 'we-formkit' ) }
							</a>
						</p>
					) : null }
				</div>
			) : null }

			<div className="wek-dataform-settings__actions">
				<Button
					variant="primary"
					onClick={ saveSettings }
					disabled={ saving || ( ! dirty && formId > 0 ) }
				>
					{ saving ? (
						<>
							<Spinner />{ ' ' }
							{ __( 'Saving…', 'we-formkit' ) }
						</>
					) : (
						__( 'Save settings', 'we-formkit' )
					) }
				</Button>
			</div>
		</div>
	);
}

const rootEl = document.getElementById( 'wek-form-settings-root' );
if ( rootEl ) {
	if ( typeof createRoot === 'function' ) {
		createRoot( rootEl ).render( <FormSettingsApp /> );
	} else {
		render( <FormSettingsApp />, rootEl );
	}
}
