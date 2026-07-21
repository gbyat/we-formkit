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
	{ id: 'color_input', key: 'input', label: __( 'Input fill', 'we-formkit' ) },
	{
		id: 'color_on_accent',
		key: 'on_accent',
		label: __( 'Button text', 'we-formkit' ),
	},
	{ id: 'color_danger', key: 'danger', label: __( 'Errors', 'we-formkit' ) },
];

const NAMED_SCHEMES = Array.isArray( boot.schemes ) ? boot.schemes : [];

function schemeMap() {
	const map = {};
	NAMED_SCHEMES.forEach( ( scheme ) => {
		if ( scheme && scheme.id ) {
			map[ scheme.id ] = scheme.colors || {};
		}
	} );
	return map;
}

const SCHEME_COLORS = schemeMap();

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

function colorsFromData( data ) {
	const out = {};
	COLOR_ROLES.forEach( ( role ) => {
		out[ role.key ] = data[ role.id ] || '#000000';
	} );
	return out;
}

function resolvePreviewFont( slug ) {
	if ( ! slug || slug === 'inherit' ) {
		return 'inherit';
	}
	const fonts = Array.isArray( boot.fontFamilies ) ? boot.fontFamilies : [];
	const match = fonts.find( ( font ) => font.slug === slug );
	if ( ! match || ! match.fontFamily ) {
		return 'inherit';
	}
	return `var(--wp--preset--font-family--${ slug }, ${ match.fontFamily })`;
}

const SPACING_VARS = {
	compact: {
		gapY: '0.55rem',
		gapX: '0.65rem',
		space: '0.85rem',
		shell: '1rem',
	},
	cozy: {
		gapY: '0.85rem',
		gapX: '1rem',
		space: '1.25rem',
		shell: '1.25rem',
	},
	comfortable: {
		gapY: '1.2rem',
		gapX: '1.25rem',
		space: '1.6rem',
		shell: '1.5rem',
	},
};

const CONTROL_VARS = {
	compact: { padY: '0.4rem', padX: '0.55rem' },
	cozy: { padY: '0.65rem', padX: '0.75rem' },
	comfortable: { padY: '0.85rem', padX: '0.95rem' },
};

const SIZE_SECTION = { sm: '1rem', md: '1.15rem', lg: '1.35rem' };
const SIZE_LABEL = { sm: '0.85rem', md: '0.95rem', lg: '1.05rem' };
const SIZE_INPUT = { sm: '0.875rem', md: '1rem', lg: '1.0625rem' };

function PreviewField( { label, req, help, helpPlacement, helpStyle, children } ) {
	const helpEl =
		help && helpPlacement ? (
			<div
				className={
					'wek-scheme-preview__help' +
					( helpStyle === 'boxed' ? ' is-boxed' : '' )
				}
			>
				{ help }
			</div>
		) : null;

	return (
		<div className="wek-scheme-preview__field">
			<div className="wek-scheme-preview__label">
				{ label }
				{ req ? (
					<span className="wek-scheme-preview__req">
						{ ' ' }
						{ req }
					</span>
				) : null }
			</div>
			{ helpPlacement === 'below_label' ? helpEl : null }
			{ children }
			{ helpPlacement === 'below_field' ? helpEl : null }
		</div>
	);
}

function ColorSchemePreview( { data } ) {
	const c = colorsFromData( data );
	const req =
		data.required_mark === 'text'
			? __( '(required)', 'we-formkit' )
			: data.required_mark === 'none'
				? ''
				: '*';
	const spacing = SPACING_VARS[ data.spacing ] || SPACING_VARS.cozy;
	const control = CONTROL_VARS[ data.control_padding ] || CONTROL_VARS.cozy;
	const labelClass =
		'wek-scheme-preview__card' +
		( data.label_weight === 'normal' ? ' is-label-normal' : '' );

	return (
		<div className="wek-scheme-preview" aria-live="polite">
			<p className="wek-scheme-preview__caption">
				{ __( 'Live preview', 'we-formkit' ) }
			</p>
			<div
				className="wek-scheme-preview__shell"
				style={ {
					'--wek-bg': c.bg,
					'--wek-surface': c.surface,
					'--wek-ink': c.ink,
					'--wek-muted': c.muted,
					'--wek-line': c.line,
					'--wek-accent': c.accent,
					'--wek-accent-soft': c.accent_soft,
					'--wek-input': c.input,
					'--wek-on-accent': c.on_accent,
					'--wek-danger': c.danger,
					'--wek-font-family': resolvePreviewFont( data.font_family ),
					'--wek-gap-y': spacing.gapY,
					'--wek-gap-x': spacing.gapX,
					'--wek-space': spacing.space,
					'--wek-shell-pad': spacing.shell,
					'--wek-control-pad-y': control.padY,
					'--wek-control-pad-x': control.padX,
					'--wek-font-section':
						SIZE_SECTION[ data.size_section ] || SIZE_SECTION.md,
					'--wek-font-label':
						SIZE_LABEL[ data.size_label ] || SIZE_LABEL.md,
					'--wek-font-input':
						SIZE_INPUT[ data.size_input ] || SIZE_INPUT.md,
				} }
			>
				<div className={ labelClass }>
					<div className="wek-scheme-preview__title">
						{ data.title || __( 'Contact request', 'we-formkit' ) }
					</div>
					<div className="wek-scheme-preview__intro">
						{ data.intro ||
							__(
								'We reply within one business day.',
								'we-formkit'
							) }
					</div>

					<div className="wek-scheme-preview__section">
						<div className="wek-scheme-preview__section-title">
							{ __( 'Details', 'we-formkit' ) }
						</div>

						<div className="wek-scheme-preview__fields">
							<PreviewField
								label={ __( 'Email', 'we-formkit' ) }
								req={ req }
								help={ __(
									'Used only for this reply.',
									'we-formkit'
								) }
								helpPlacement={ data.help_placement }
								helpStyle={ data.help_style }
							>
								<div className="wek-scheme-preview__input">
									you@example.com
								</div>
							</PreviewField>

							<PreviewField
								label={ __( 'Topic', 'we-formkit' ) }
								helpPlacement={ data.help_placement }
								helpStyle={ data.help_style }
							>
								<select
									className="wek-scheme-preview__select"
									defaultValue="general"
									aria-label={ __( 'Topic', 'we-formkit' ) }
								>
									<option value="general">
										{ __( 'General', 'we-formkit' ) }
									</option>
									<option value="billing">
										{ __( 'Billing', 'we-formkit' ) }
									</option>
									<option value="support">
										{ __( 'Support', 'we-formkit' ) }
									</option>
								</select>
							</PreviewField>

							<PreviewField
								label={ __( 'Preferred contact', 'we-formkit' ) }
								helpPlacement={ data.help_placement }
								helpStyle={ data.help_style }
							>
								<div className="wek-scheme-preview__choices">
									<label className="wek-scheme-preview__choice">
										<span
											className="wek-scheme-preview__radio is-checked"
											aria-hidden="true"
										/>
										{ __( 'Email', 'we-formkit' ) }
									</label>
									<label className="wek-scheme-preview__choice">
										<span
											className="wek-scheme-preview__radio"
											aria-hidden="true"
										/>
										{ __( 'Phone', 'we-formkit' ) }
									</label>
								</div>
							</PreviewField>

							<label className="wek-scheme-preview__choice wek-scheme-preview__choice--check">
								<span
									className="wek-scheme-preview__check is-checked"
									aria-hidden="true"
								/>
								{ __(
									'I agree to the privacy policy',
									'we-formkit'
								) }
							</label>

							<div className="wek-scheme-preview__error">
								{ __(
									'Please enter a valid email.',
									'we-formkit'
								) }
							</div>
						</div>
					</div>

					<span className="wek-scheme-preview__submit">
						{ __( 'Submit', 'we-formkit' ) }
					</span>
				</div>
			</div>
		</div>
	);
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
		label_weight: payload.label_weight || 'bold',
		required_mark: payload.required_mark || 'asterisk',
		help_placement: payload.help_placement || 'below_label',
		help_style: payload.help_style || 'muted',
		font_family: payload.font_family || 'inherit',
		spacing: payload.spacing || 'cozy',
		control_padding: payload.control_padding || 'cozy',
		size_section: payload.size_section || 'md',
		size_label: payload.size_label || 'md',
		size_input: payload.size_input || 'md',
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
		label_weight: data.label_weight || 'bold',
		required_mark: data.required_mark || 'asterisk',
		help_placement: data.help_placement || 'below_label',
		help_style: data.help_style || 'muted',
		font_family: data.font_family || 'inherit',
		spacing: data.spacing || 'cozy',
		control_padding: data.control_padding || 'cozy',
		size_section: data.size_section || 'md',
		size_label: data.size_label || 'md',
		size_input: data.size_input || 'md',
		style: {
			preset: data.style_preset || 'theme',
			colors,
		},
	};
}

function presetElements() {
	return [
		{
			value: 'theme',
			label: __( 'Match site theme', 'we-formkit' ),
		},
		...NAMED_SCHEMES.map( ( scheme ) => ( {
			value: scheme.id,
			label: scheme.label || scheme.id,
		} ) ),
		{
			value: 'custom',
			label: __( 'Custom', 'we-formkit' ),
		},
	];
}

function ThemeImportPanel( { fillFrom, onFillChange, onImport } ) {
	const themeImport = boot.themeImport || {};
	if ( ! themeImport.hasPalette ) {
		return null;
	}
	const fills = Array.isArray( themeImport.fills ) ? themeImport.fills : [];
	const palette = Array.isArray( themeImport.palette )
		? themeImport.palette
		: [];

	return (
		<div className="wek-theme-import">
			<p className="wek-theme-import__title">
				{ __( 'Import theme colors', 'we-formkit' ) }
			</p>
			<p className="wek-theme-import__help">
				{ __(
					'Maps the theme palette to form roles with contrast checks. Gaps use the fill scheme below.',
					'we-formkit'
				) }
			</p>
			{ palette.length ? (
				<div className="wek-theme-import__swatches" aria-hidden="true">
					{ palette.slice( 0, 12 ).map( ( swatch ) => (
						<span
							key={ swatch.slug + swatch.color }
							title={ swatch.name || swatch.slug || swatch.color }
							style={ { background: swatch.color } }
						/>
					) ) }
				</div>
			) : null }
			<label
				className="wek-theme-import__fill-label"
				htmlFor="wek-theme-fill"
			>
				{ __( 'Fill missing colors from', 'we-formkit' ) }
			</label>
			<select
				id="wek-theme-fill"
				className="wek-theme-import__fill"
				value={ fillFrom }
				onChange={ ( event ) => onFillChange( event.target.value ) }
			>
				{ fills.map( ( fill ) => (
					<option key={ fill.id } value={ fill.id }>
						{ fill.label }
					</option>
				) ) }
			</select>
			<Button variant="secondary" onClick={ onImport }>
				{ __( 'Import theme colors', 'we-formkit' ) }
			</Button>
		</div>
	);
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
	const [ themeFillFrom, setThemeFillFrom ] = useState( 'auto' );

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
				id: 'label_weight',
				label: __( 'Label weight', 'we-formkit' ),
				type: 'text',
				elements: [
					{ value: 'normal', label: __( 'Normal', 'we-formkit' ) },
					{ value: 'bold', label: __( 'Bold', 'we-formkit' ) },
				],
			},
			{
				id: 'required_mark',
				label: __( 'Required mark', 'we-formkit' ),
				type: 'text',
				description: __(
					'Shown next to required field labels.',
					'we-formkit'
				),
				elements: [
					{
						value: 'asterisk',
						label: __( 'Asterisk (*)', 'we-formkit' ),
					},
					{
						value: 'text',
						label: __( 'Text “(required)”', 'we-formkit' ),
					},
					{ value: 'none', label: __( 'None', 'we-formkit' ) },
				],
			},
			{
				id: 'help_placement',
				label: __( 'Help text placement', 'we-formkit' ),
				type: 'text',
				elements: [
					{
						value: 'below_label',
						label: __( 'Below label', 'we-formkit' ),
					},
					{
						value: 'below_field',
						label: __( 'Below field', 'we-formkit' ),
					},
				],
			},
			{
				id: 'help_style',
				label: __( 'Help text style', 'we-formkit' ),
				type: 'text',
				elements: [
					{ value: 'muted', label: __( 'Muted', 'we-formkit' ) },
					{ value: 'boxed', label: __( 'Boxed', 'we-formkit' ) },
				],
			},
			{
				id: 'font_family',
				label: __( 'Form font', 'we-formkit' ),
				type: 'text',
				description: __(
					'Default inherits the theme / Site Editor typography. Optional choices come from theme.json and installed fonts.',
					'we-formkit'
				),
				elements: [
					{
						value: 'inherit',
						label: __( 'Theme default (inherit)', 'we-formkit' ),
					},
					...( Array.isArray( boot.fontFamilies )
						? boot.fontFamilies.map( ( font ) => ( {
								value: font.slug,
								label: font.name || font.slug,
						  } ) )
						: [] ),
				],
			},
			{
				id: 'spacing',
				label: __( 'Field spacing', 'we-formkit' ),
				type: 'text',
				description: __(
					'Vertical and horizontal gaps between fields and section padding.',
					'we-formkit'
				),
				elements: [
					{ value: 'compact', label: __( 'Compact', 'we-formkit' ) },
					{ value: 'cozy', label: __( 'Cozy', 'we-formkit' ) },
					{
						value: 'comfortable',
						label: __( 'Comfortable', 'we-formkit' ),
					},
				],
			},
			{
				id: 'control_padding',
				label: __( 'Input padding', 'we-formkit' ),
				type: 'text',
				description: __(
					'Inner padding for text inputs and selects (same height).',
					'we-formkit'
				),
				elements: [
					{ value: 'compact', label: __( 'Compact', 'we-formkit' ) },
					{ value: 'cozy', label: __( 'Cozy', 'we-formkit' ) },
					{
						value: 'comfortable',
						label: __( 'Comfortable', 'we-formkit' ),
					},
				],
			},
			{
				id: 'size_section',
				label: __( 'Section title size', 'we-formkit' ),
				type: 'text',
				elements: [
					{ value: 'sm', label: __( 'Small', 'we-formkit' ) },
					{ value: 'md', label: __( 'Medium', 'we-formkit' ) },
					{ value: 'lg', label: __( 'Large', 'we-formkit' ) },
				],
			},
			{
				id: 'size_label',
				label: __( 'Label size', 'we-formkit' ),
				type: 'text',
				elements: [
					{ value: 'sm', label: __( 'Small', 'we-formkit' ) },
					{ value: 'md', label: __( 'Medium', 'we-formkit' ) },
					{ value: 'lg', label: __( 'Large', 'we-formkit' ) },
				],
			},
			{
				id: 'size_input',
				label: __( 'Input text size', 'we-formkit' ),
				type: 'text',
				elements: [
					{ value: 'sm', label: __( 'Small', 'we-formkit' ) },
					{ value: 'md', label: __( 'Medium', 'we-formkit' ) },
					{ value: 'lg', label: __( 'Large', 'we-formkit' ) },
				],
			},
			{
				id: 'style_preset',
				label: __( 'Color scheme', 'we-formkit' ),
				type: 'text',
				description: __(
					'Choose a matching palette. Edit any color to switch to Custom — built-in schemes stay selectable.',
					'we-formkit'
				),
				elements: presetElements(),
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

	const generalForm = useMemo(
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
			],
		} ),
		[]
	);

	const appearanceForm = useMemo(
		() => ( {
			layout: { type: 'regular', labelPosition: 'top' },
			fields: [
				{
					id: 'labels',
					label: __( 'Labels & help', 'we-formkit' ),
					layout: { type: 'card', isOpened: true },
					children: [
						'label_weight',
						'required_mark',
						'help_placement',
						'help_style',
					],
				},
				{
					id: 'typography',
					label: __( 'Typography', 'we-formkit' ),
					layout: { type: 'card', isOpened: true },
					children: [
						'font_family',
						'size_section',
						'size_label',
						'size_input',
					],
				},
				{
					id: 'density',
					label: __( 'Spacing & density', 'we-formkit' ),
					layout: { type: 'card', isOpened: true },
					children: [ 'spacing', 'control_padding' ],
				},
			],
		} ),
		[]
	);

	const colorsForm = useMemo(
		() => ( {
			layout: { type: 'regular', labelPosition: 'top' },
			fields: [
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
				} else if ( SCHEME_COLORS[ preset ] ) {
					Object.assign( next, colorsFromMap( SCHEME_COLORS[ preset ] ) );
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

	const importThemeColors = useCallback( () => {
		const byFill =
			( boot.themeImport && boot.themeImport.byFill ) || {};
		const imported = byFill[ themeFillFrom ] || byFill.auto;
		if ( ! imported ) {
			setNotice( {
				status: 'error',
				message: __(
					'No theme color palette is available to import.',
					'we-formkit'
				),
			} );
			return;
		}
		setData( ( prev ) => ( {
			...prev,
			style_preset: 'custom',
			...colorsFromMap( imported ),
		} ) );
		setDirty( true );
		setNotice( {
			status: 'success',
			message: __(
				'Theme colors imported. Review the preview, then save.',
				'we-formkit'
			),
		} );
	}, [ themeFillFrom ] );

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

			<div className="wek-dataform-settings__wide">
				<DataForm
					data={ data }
					fields={ fields }
					form={ generalForm }
					onChange={ onChange }
				/>
				<DataForm
					data={ data }
					fields={ fields }
					form={ appearanceForm }
					onChange={ onChange }
				/>
			</div>

			<div className="wek-dataform-settings__layout">
				<div className="wek-dataform-settings__form">
					<DataForm
						data={ data }
						fields={ fields }
						form={ colorsForm }
						onChange={ onChange }
					/>
				</div>
				<aside className="wek-dataform-settings__preview">
					<ColorSchemePreview data={ data } />
					<ThemeImportPanel
						fillFrom={ themeFillFrom }
						onFillChange={ setThemeFillFrom }
						onImport={ importThemeColors }
					/>
				</aside>
			</div>

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
