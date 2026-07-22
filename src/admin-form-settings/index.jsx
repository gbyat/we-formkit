/**
 * Form settings screen — DataForm (React).
 *
 * @see https://developer.wordpress.org/news/2026/01/how-to-use-dataform-to-create-plugin-settings-pages/
 */
import { createRoot, render, useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';
import './style.css';

const boot = window.weFormkitFormSettings || {};
const PANEL = boot.panel === 'design' ? 'design' : 'general';
const DRAFT_TTL_DAYS = Array.isArray( boot.draftTtlDays ) && boot.draftTtlDays.length
	? boot.draftTtlDays.map( ( day ) => Number( day ) ).filter( ( day ) => day > 0 )
	: [ 7, 14, 30, 60, 90 ];
const INTRO_EDITOR_ID = 'wek_form_settings_intro';

function ttlDayElements() {
	return DRAFT_TTL_DAYS.map( ( days ) => ( {
		value: String( days ),
		label: sprintf(
			/* translators: %d: number of days. */
			_n( '%d day', '%d days', days, 'we-formkit' ),
			days
		),
	} ) );
}

/**
 * Classic TinyMCE (wp.editor) for form intro HTML.
 *
 * @param {{ data: Object, field: { id: string, label?: string }, onChange: Function }} props Props.
 */
function IntroWysiwygEdit( { data, field, onChange } ) {
	const value = data[ field.id ] || '';
	const onChangeRef = useRef( onChange );
	const valueRef = useRef( value );
	const readyRef = useRef( false );
	onChangeRef.current = onChange;
	valueRef.current = value;

	useEffect( () => {
		const wpEditor = window.wp && window.wp.editor;
		if ( ! wpEditor || typeof wpEditor.initialize !== 'function' ) {
			return undefined;
		}

		readyRef.current = false;
		wpEditor.initialize( INTRO_EDITOR_ID, {
			tinymce: {
				wpautop: true,
				height: 180,
				toolbar1:
					'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo,removeformat',
				toolbar2: '',
				block_formats: 'Paragraph=p;Heading 3=h3',
				setup( editor ) {
					editor.on( 'init', () => {
						if ( valueRef.current ) {
							editor.setContent( valueRef.current );
						}
						readyRef.current = true;
					} );
					const push = () => {
						if ( ! readyRef.current ) {
							return;
						}
						onChangeRef.current( {
							[ field.id ]: editor.getContent(),
						} );
					};
					editor.on( 'change keyup Undo Redo', push );
				},
			},
			quicktags: true,
			mediaButtons: false,
		} );

		return () => {
			readyRef.current = false;
			if ( window.wp && window.wp.editor && typeof window.wp.editor.remove === 'function' ) {
				window.wp.editor.remove( INTRO_EDITOR_ID );
			}
		};
	}, [ field.id ] );

	// Keep TinyMCE in sync when intro is loaded/saved from outside the editor.
	useEffect( () => {
		if ( ! readyRef.current || ! window.tinymce ) {
			return;
		}
		const editor = window.tinymce.get( INTRO_EDITOR_ID );
		if ( ! editor || editor.isHidden() ) {
			return;
		}
		const current = editor.getContent();
		if ( current !== value ) {
			editor.setContent( value || '' );
		}
	}, [ value ] );

	return (
		<div className="wek-dataform-wysiwyg">
			<textarea
				id={ INTRO_EDITOR_ID }
				name={ INTRO_EDITOR_ID }
				className="wek-dataform-wysiwyg__textarea large-text"
				defaultValue={ value }
				aria-label={ field.label }
				rows={ 8 }
				onChange={ ( event ) => {
					// Fallback when TinyMCE is unavailable or Text tab is active.
					if ( window.tinymce && window.tinymce.get( INTRO_EDITOR_ID ) ) {
						return;
					}
					onChange( { [ field.id ]: event.target.value } );
				} }
			/>
		</div>
	);
}

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

const CHROME_GAP_VARS = {
	none: { header: '0', status: '0' },
	sm: { header: '0.75rem', status: '0.5rem' },
	md: { header: '1.25rem', status: '0.75rem' },
	lg: { header: '1.75rem', status: '1rem' },
};

const CONTROL_VARS = {
	compact: { padY: '0.4rem', padX: '0.55rem' },
	cozy: { padY: '0.65rem', padX: '0.75rem' },
	comfortable: { padY: '0.85rem', padX: '0.95rem' },
};

const SIZE_SECTION = { sm: '1rem', md: '1.15rem', lg: '1.35rem' };
const SIZE_LABEL = { sm: '0.85rem', md: '0.95rem', lg: '1.05rem' };
const SIZE_INPUT = { sm: '0.875rem', md: '1rem', lg: '1.0625rem' };

const RADIUS_CONTROL = {
	none: '0',
	sm: '4px',
	md: '8px',
	lg: '14px',
	pill: '999px',
};
const RADIUS_SECTION = {
	none: '0',
	sm: '8px',
	md: '12px',
	lg: '18px',
};

const RADIUS_FIELD_ELEMENTS = [
	{ value: 'none', label: __( 'None', 'we-formkit' ) },
	{ value: 'sm', label: __( 'Small', 'we-formkit' ) },
	{ value: 'md', label: __( 'Medium', 'we-formkit' ) },
	{ value: 'lg', label: __( 'Large', 'we-formkit' ) },
	{ value: 'pill', label: __( 'Pill', 'we-formkit' ) },
];

const RADIUS_SECTION_ELEMENTS = [
	{ value: 'none', label: __( 'None', 'we-formkit' ) },
	{ value: 'sm', label: __( 'Small', 'we-formkit' ) },
	{ value: 'md', label: __( 'Medium', 'we-formkit' ) },
	{ value: 'lg', label: __( 'Large', 'we-formkit' ) },
];

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
					<span
						className={
							String( req ).indexOf( 'optional' ) !== -1
								? 'wek-scheme-preview__opt'
								: 'wek-scheme-preview__req'
						}
					>
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
	const opt =
		data.optional_mark === 'none' ? '' : __( '(optional)', 'we-formkit' );
	const spacing = SPACING_VARS[ data.spacing ] || SPACING_VARS.cozy;
	const chromeGap = CHROME_GAP_VARS[ data.chrome_gap ] || CHROME_GAP_VARS.none;
	const control = CONTROL_VARS[ data.control_padding ] || CONTROL_VARS.cozy;
	const labelClass =
		'wek-scheme-preview__card' +
		( data.label_weight === 'normal' ? ' is-label-normal' : '' );
	const showInline = data.inline_validation && data.inline_validation !== 'off';
	const showInlineIcon =
		data.inline_validation === 'icon' ||
		data.inline_validation === 'both' ||
		data.inline_validation === 'on';
	const showInlineBorder =
		data.inline_validation === 'border' ||
		data.inline_validation === 'both' ||
		data.inline_validation === 'on';

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
					'--wek-header-gap': chromeGap.header,
					'--wek-status-gap': chromeGap.status,
					'--wek-control-pad-y': control.padY,
					'--wek-control-pad-x': control.padX,
					'--wek-font-section':
						SIZE_SECTION[ data.size_section ] || SIZE_SECTION.md,
					'--wek-font-label':
						SIZE_LABEL[ data.size_label ] || SIZE_LABEL.md,
					'--wek-font-input':
						SIZE_INPUT[ data.size_input ] || SIZE_INPUT.md,
					'--wek-radius-input':
						RADIUS_CONTROL[ data.radius_input ] ||
						RADIUS_CONTROL.md,
					'--wek-radius-button':
						RADIUS_CONTROL[ data.radius_button ] ||
						RADIUS_CONTROL.pill,
					'--wek-radius-section':
						RADIUS_SECTION[ data.radius_section ] ||
						RADIUS_SECTION.md,
				} }
			>
				<div className={ labelClass }>
					<div className="wek-scheme-preview__title">
						{ data.title || __( 'Contact request', 'we-formkit' ) }
					</div>
					<div
						className="wek-scheme-preview__intro"
						dangerouslySetInnerHTML={ {
							__html:
								data.intro ||
								__(
									'We reply within one business day.',
									'we-formkit'
								),
						} }
					/>

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
								<div className="wek-scheme-preview__control-row">
									<div className="wek-scheme-preview__input is-valid">
										you@example.com
									</div>
									{ showInlineIcon ? (
										<span
											className="wek-scheme-preview__validity is-valid"
											aria-hidden="true"
										/>
									) : null }
								</div>
							</PreviewField>

							<PreviewField
								label={ __( 'Topic', 'we-formkit' ) }
								req={ opt }
								helpPlacement={ data.help_placement }
								helpStyle={ data.help_style }
							>
								<div className="wek-scheme-preview__control-row">
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
									{ showInlineIcon ? (
										<span
											className="wek-scheme-preview__validity is-valid"
											aria-hidden="true"
										/>
									) : null }
								</div>
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

/** Default / clamp for Save & Resume min filled (0 = always show; empty → 1). */
function normalizeSaveResumeMin( value ) {
	if ( value === 0 || value === '0' ) {
		return 0;
	}
	if ( value === '' || value === null || value === undefined ) {
		return 1;
	}
	const n = Number( value );
	if ( Number.isNaN( n ) ) {
		return 1;
	}
	return Math.max( 0, Math.min( 100, Math.floor( n ) ) );
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
		optional_mark: payload.optional_mark || 'text',
		inline_validation: payload.inline_validation || 'both',
		inline_scope: payload.inline_scope || 'required',
		help_placement: payload.help_placement || 'below_label',
		help_style: payload.help_style || 'muted',
		font_family: payload.font_family || 'inherit',
		spacing: payload.spacing || 'cozy',
		chrome_gap: payload.chrome_gap || 'none',
		control_padding: payload.control_padding || 'cozy',
		size_section: payload.size_section || 'md',
		size_label: payload.size_label || 'md',
		size_input: payload.size_input || 'md',
		radius_input: payload.radius_input || 'md',
		radius_button: payload.radius_button || 'pill',
		radius_section: payload.radius_section || 'md',
		save_resume: !! payload.save_resume,
		save_resume_ttl: String(
			payload.save_resume_ttl || DRAFT_TTL_DAYS[ 0 ] || 14
		),
		save_resume_min: normalizeSaveResumeMin( payload.save_resume_min ),
		save_resume_reminders: !! payload.save_resume_reminders,
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
		optional_mark: data.optional_mark || 'text',
		inline_validation: data.inline_validation || 'both',
		inline_scope: data.inline_scope || 'required',
		help_placement: data.help_placement || 'below_label',
		help_style: data.help_style || 'muted',
		font_family: data.font_family || 'inherit',
		spacing: data.spacing || 'cozy',
		chrome_gap: data.chrome_gap || 'none',
		control_padding: data.control_padding || 'cozy',
		size_section: data.size_section || 'md',
		size_label: data.size_label || 'md',
		size_input: data.size_input || 'md',
		radius_input: data.radius_input || 'md',
		radius_button: data.radius_button || 'pill',
		radius_section: data.radius_section || 'md',
		save_resume: !! data.save_resume,
		save_resume_ttl: Number( data.save_resume_ttl ) || 14,
		save_resume_min: normalizeSaveResumeMin( data.save_resume_min ),
		save_resume_reminders: !! data.save_resume_reminders,
		style: {
			preset: data.style_preset || 'theme',
			colors,
		},
	};
}

function presetElements( hasCustom ) {
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
			label: hasCustom
				? __( 'Custom (saved)', 'we-formkit' )
				: __( 'Custom', 'we-formkit' ),
		},
	];
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
	const [ savedCustom, setSavedCustom ] = useState( () => {
		const colors = boot.customColors;
		if ( colors && typeof colors === 'object' && Object.keys( colors ).length ) {
			return colors;
		}
		return null;
	} );

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
				description: __(
					'Shown under the form title. Use the toolbar for basic formatting.',
					'we-formkit'
				),
				Edit: IntroWysiwygEdit,
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
				id: 'save_resume',
				label: __( 'Enable Save & Resume', 'we-formkit' ),
				type: 'boolean',
				Edit: 'toggle',
				description: __(
					'Visitors can save progress and continue later via email link.',
					'we-formkit'
				),
			},
			{
				id: 'save_resume_ttl',
				label: __( 'Keep drafts for', 'we-formkit' ),
				type: 'text',
				elements: ttlDayElements(),
				description: __(
					'How long a resume link stays valid.',
					'we-formkit'
				),
			},
			{
				id: 'save_resume_min',
				label: __( 'Show save after filled fields', 'we-formkit' ),
				type: 'integer',
				description: __(
					'Minimum filled fields before Save progress appears. 0 = always show.',
					'we-formkit'
				),
			},
			{
				id: 'save_resume_reminders',
				label: __( 'Allow calendar reminder', 'we-formkit' ),
				type: 'boolean',
				Edit: 'toggle',
				description: __(
					'Lets visitors attach an .ics calendar appointment to the resume email. No further reminder emails are sent.',
					'we-formkit'
				),
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
				id: 'optional_mark',
				label: __( 'Optional mark', 'we-formkit' ),
				type: 'text',
				description: __(
					'Shown next to optional field labels.',
					'we-formkit'
				),
				elements: [
					{
						value: 'text',
						label: __( 'Text “(optional)”', 'we-formkit' ),
					},
					{ value: 'none', label: __( 'None', 'we-formkit' ) },
				],
			},
			{
				id: 'inline_validation',
				label: __( 'Inline validation', 'we-formkit' ),
				type: 'text',
				description: __(
					'Live feedback after the visitor leaves a field. Prefer Border on dense fields such as matrices.',
					'we-formkit'
				),
				elements: [
					{ value: 'off', label: __( 'Off', 'we-formkit' ) },
					{
						value: 'border',
						label: __( 'Border color', 'we-formkit' ),
					},
					{ value: 'icon', label: __( 'Icons', 'we-formkit' ) },
					{
						value: 'both',
						label: __( 'Icons and border', 'we-formkit' ),
					},
				],
			},
			{
				id: 'inline_scope',
				label: __( 'Inline validation applies to', 'we-formkit' ),
				type: 'text',
				description: __(
					'Required only keeps optional fields (e.g. matrix) quiet until submit.',
					'we-formkit'
				),
				elements: [
					{
						value: 'required',
						label: __( 'Required fields only', 'we-formkit' ),
					},
					{
						value: 'all',
						label: __( 'All fields', 'we-formkit' ),
					},
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
				id: 'chrome_gap',
				label: __( 'Space below intro', 'we-formkit' ),
				type: 'text',
				description: __(
					'Gap between the title/intro block and the first section (also below status messages).',
					'we-formkit'
				),
				elements: [
					{ value: 'none', label: __( 'None', 'we-formkit' ) },
					{ value: 'sm', label: __( 'Small', 'we-formkit' ) },
					{ value: 'md', label: __( 'Medium', 'we-formkit' ) },
					{ value: 'lg', label: __( 'Large', 'we-formkit' ) },
				],
			},
			{
				id: 'control_padding',
				label: __( 'Input padding', 'we-formkit' ),
				type: 'text',
				description: __(
					'Inner padding for text inputs, selects, and radio/checkbox frames.',
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
				id: 'radius_input',
				label: __( 'Input corners', 'we-formkit' ),
				type: 'text',
				description: __(
					'Applies to text fields, textareas, selects, and radio/checkbox frames.',
					'we-formkit'
				),
				elements: RADIUS_FIELD_ELEMENTS,
			},
			{
				id: 'radius_button',
				label: __( 'Button corners', 'we-formkit' ),
				type: 'text',
				elements: RADIUS_FIELD_ELEMENTS,
			},
			{
				id: 'radius_section',
				label: __( 'Section corners', 'we-formkit' ),
				type: 'text',
				elements: RADIUS_SECTION_ELEMENTS,
			},
			{
				id: 'style_preset',
				label: __( 'Color scheme', 'we-formkit' ),
				type: 'text',
				description: __(
					'Match site theme follows the site palette. Edit a swatch to switch to Custom — save to lock it.',
					'we-formkit'
				),
				elements: presetElements( !! savedCustom ),
			},
			...COLOR_ROLES.map( ( role ) => ( {
				id: role.id,
				label: role.label,
				type: 'text',
				Edit: ColorEdit,
			} ) ),
		],
		[ savedCustom ]
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
						'chrome_gap',
						'privacy_url',
						'secret_enabled',
					],
				},
				{
					id: 'save_resume_card',
					label: __( 'Save & Resume', 'we-formkit' ),
					layout: { type: 'card', isOpened: true },
					children: data.save_resume
						? [
								'save_resume',
								'save_resume_ttl',
								'save_resume_min',
								'save_resume_reminders',
						  ]
						: [ 'save_resume' ],
				},
			],
		} ),
		[ data.save_resume ]
	);

	const visualForm = useMemo(
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
						'optional_mark',
						'inline_validation',
						'inline_scope',
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
					children: [
						'spacing',
						'chrome_gap',
						'control_padding',
						'radius_input',
						'radius_button',
						'radius_section',
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
				Object.prototype.hasOwnProperty.call( edits, 'save_resume' ) &&
				edits.save_resume
			) {
				next.save_resume_min = normalizeSaveResumeMin(
					next.save_resume_min
				);
				if (
					! next.save_resume_ttl &&
					next.save_resume_ttl !== 0 &&
					next.save_resume_ttl !== '0'
				) {
					next.save_resume_ttl = String( DRAFT_TTL_DAYS[ 0 ] || 14 );
				}
			}
			if (
				Object.prototype.hasOwnProperty.call( edits, 'style_preset' )
			) {
				const preset = edits.style_preset;
				if ( preset === 'theme' ) {
					Object.assign( next, colorsFromMap( boot.themeColors ) );
				} else if ( preset === 'custom' ) {
					if ( savedCustom ) {
						Object.assign( next, colorsFromMap( savedCustom ) );
					}
					// No saved custom yet: keep current swatches as a draft.
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
	}, [ savedCustom ] );

	const saveSettings = useCallback( () => {
		setSaving( true );
		setNotice( null );
		let payloadData = data;
		if ( PANEL === 'general' && window.tinymce ) {
			const ed = window.tinymce.get( INTRO_EDITOR_ID );
			if ( ed ) {
				const html = ed.getContent();
				payloadData = { ...data, intro: html };
				setData( payloadData );
			}
		}
		const path =
			formId > 0
				? `/we-formkit/v1/forms/${ formId }/settings`
				: '/we-formkit/v1/forms/settings';
		apiFetch( {
			path,
			method: 'POST',
			data: toApiPayload( payloadData ),
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
					if ( response.settings.style_preset === 'custom' ) {
						setSavedCustom( response.settings.colors || null );
					}
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
					message:
						PANEL === 'design'
							? __( 'Design saved.', 'we-formkit' )
							: __( 'Settings saved.', 'we-formkit' ),
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
		<div
			className={
				'wek-dataform-settings' +
				( PANEL === 'design' ? ' wek-dataform-settings--design' : '' )
			}
		>
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ PANEL === 'general' ? (
				<>
					<div className="wek-dataform-settings__wide">
						<DataForm
							data={ data }
							fields={ fields }
							form={ generalForm }
							onChange={ onChange }
						/>
					</div>

					{ data.secret_enabled && secretToken ? (
						<div className="wek-dataform-settings__secret">
							<p>
								<strong>
									{ __( 'Secret token', 'we-formkit' ) }
								</strong>
								{ ' ' }
								<code>{ secretToken }</code>
							</p>
							{ secretUrl ? (
								<p>
									<label htmlFor="wek-secret-url">
										{ __(
											'Append to the URL of the page that embeds this form:',
											'we-formkit'
										) }
									</label>
									<input
										id="wek-secret-url"
										className="large-text code"
										type="text"
										readOnly
										value={ secretUrl }
										onFocus={ ( event ) =>
											event.target.select()
										}
									/>
									<span className="description">
										{ __(
											'If that page URL already has a ?, replace the leading ? below with &.',
											'we-formkit'
										) }
									</span>
								</p>
							) : null }
							{ boot.regenUrl ? (
								<p>
									<a
										className="button"
										href={ boot.regenUrl }
									>
										{ __(
											'Regenerate token',
											'we-formkit'
										) }
									</a>
								</p>
							) : null }
						</div>
					) : null }
				</>
			) : (
				<div className="wek-dataform-settings__layout">
					<div className="wek-dataform-settings__form">
						<DataForm
							data={ data }
							fields={ fields }
							form={ visualForm }
							onChange={ onChange }
						/>
					</div>
					<aside className="wek-dataform-settings__preview">
						<ColorSchemePreview data={ data } />
					</aside>
				</div>
			) }

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
					) : PANEL === 'design' ? (
						__( 'Save design', 'we-formkit' )
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
