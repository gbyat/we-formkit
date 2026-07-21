<?php
/**
 * Form schema helpers and meta keys.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes form definitions via the field-type registry.
 */
final class Form_Schema {

	public const META_SCHEMA               = '_wek_form_schema';
	public const META_SECRET_ENABLED       = '_wek_form_secret_enabled';
	public const META_SECRET_TOKEN         = '_wek_form_secret_token';
	public const META_NOTIFY_EMAIL         = '_wek_form_notify_email';
	public const META_SLUG                 = '_wek_form_slug';
	public const META_PRIVACY_URL          = '_wek_form_privacy_url';
	public const META_CONFIRMATION_MESSAGE = '_wek_form_confirmation_message';
	public const META_CONFIRMATION         = '_wek_form_confirmation';
	public const META_PAGINATION           = '_wek_form_pagination';
	public const META_SUBMIT_BUTTON        = '_wek_form_submit_button';
	public const META_APPEARANCE           = '_wek_form_appearance';

	public const SUB_FORM_ID    = '_wek_submission_form_id';
	public const SUB_DATA       = '_wek_submission_data';
	public const SUB_NOTES      = '_wek_submission_notes';
	public const SUB_CONSENT    = '_wek_submission_consent';
	public const SUB_IP_HASH    = '_wek_submission_ip_hash';
	public const SUB_SOURCE_URL = '_wek_submission_source_url';
	public const SUB_READ       = '_wek_submission_read';
	public const SUB_SPAM       = '_wek_submission_spam';
	public const SUB_NOTIFY_LOG = '_wek_submission_notify_log';

	/**
	 * @param int $form_id Form post ID.
	 * @return array<string, mixed>
	 */
	public static function get( $form_id ) {
		$raw = get_post_meta( (int) $form_id, self::META_SCHEMA, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::normalize( $decoded );
			}
		}
		if ( is_array( $raw ) ) {
			return self::normalize( $raw );
		}
		return self::normalize( array() );
	}

	/**
	 * @param int                  $form_id Form post ID.
	 * @param array<string, mixed> $schema  Schema.
	 * @return bool
	 */
	public static function save( $form_id, array $schema ) {
		$normalized = self::normalize( $schema );
		$json       = wp_json_encode( $normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}
		update_post_meta( (int) $form_id, self::META_SCHEMA, $json );
		return true;
	}

	/**
	 * Normalize a form schema, routing each field through its type class.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $schema ) {
		$sections = array();
		if ( ! empty( $schema['sections'] ) && is_array( $schema['sections'] ) ) {
			foreach ( $schema['sections'] as $section ) {
				if ( ! is_array( $section ) ) {
					continue;
				}
				$fields = array();
				if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
					foreach ( $section['fields'] as $field ) {
						if ( ! is_array( $field ) || empty( $field['id'] ) ) {
							continue;
						}
						$fields[] = self::normalize_field( $field );
					}
				}
				$sections[] = array(
					'id'         => sanitize_key( (string) ( $section['id'] ?? uniqid( 'section_', false ) ) ),
					'title'      => sanitize_text_field( (string) ( $section['title'] ?? '' ) ),
					'show_title' => ! array_key_exists( 'show_title', $section ) || ! empty( $section['show_title'] ),
					'intro'      => sanitize_textarea_field( (string) ( $section['intro'] ?? '' ) ),
					'show_when'  => self::normalize_rule( $section['show_when'] ?? null ),
					'fields'     => $fields,
				);
			}
		}

		return array(
			'version'  => 1,
			'title'    => sanitize_text_field( (string) ( $schema['title'] ?? '' ) ),
			'intro'    => sanitize_textarea_field( (string) ( $schema['intro'] ?? '' ) ),
			'sections' => $sections,
		);
	}

	/**
	 * Normalize one field via the registered type class.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<string, mixed>
	 */
	private static function normalize_field( array $field ) {
		$type_id  = sanitize_key( (string) ( $field['type'] ?? 'text' ) );
		$registry = Plugin::instance()->field_registry();
		$type     = $registry ? $registry->get( $type_id ) : null;

		if ( null === $type ) {
			$type_id = 'text';
			$type    = $registry ? $registry->get( 'text' ) : null;
		}

		$field['type'] = $type_id;

		if ( null !== $type ) {
			$field = $type->normalize_config( $field );
		}

		$field['id']        = sanitize_key( (string) ( $field['id'] ?? '' ) );
		$field['width']     = self::normalize_width( $field['width'] ?? 'full' );
		$field['show_when'] = self::normalize_rule( $field['show_when'] ?? null );

		return $field;
	}

	/**
	 * @param mixed $width Width token.
	 * @return string
	 */
	private static function normalize_width( $width ) {
		$width   = sanitize_key( (string) $width );
		$allowed = array( 'full', 'two_thirds', 'half', 'third' );
		return in_array( $width, $allowed, true ) ? $width : 'full';
	}

	/**
	 * Public wrapper for show_when / document condition normalization.
	 *
	 * @param mixed $rule Rule.
	 * @return array{relation:string,rules:array<int,array<string,mixed>>}|null
	 */
	public static function normalize_condition( $rule ) {
		return self::normalize_rule( $rule );
	}

	/**
	 * Normalize show_when: legacy single rule or `{ relation, rules[] }`.
	 *
	 * @param mixed $rule Rule.
	 * @return array{relation:string,rules:array<int,array<string,mixed>>}|null
	 */
	private static function normalize_rule( $rule ) {
		if ( ! is_array( $rule ) || empty( $rule ) ) {
			return null;
		}

		// Legacy single rule { field, op, value }.
		if ( isset( $rule['field'] ) && ! isset( $rule['rules'] ) ) {
			$one = self::normalize_one_rule( $rule );
			return null === $one ? null : array(
				'relation' => 'AND',
				'rules'    => array( $one ),
			);
		}

		$relation = isset( $rule['relation'] ) && 'OR' === strtoupper( (string) $rule['relation'] ) ? 'OR' : 'AND';
		$rules    = array();
		if ( ! empty( $rule['rules'] ) && is_array( $rule['rules'] ) ) {
			foreach ( $rule['rules'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$one = self::normalize_one_rule( $row );
				if ( null !== $one ) {
					$rules[] = $one;
				}
			}
		}

		if ( empty( $rules ) ) {
			return null;
		}

		return array(
			'relation' => $relation,
			'rules'    => $rules,
		);
	}

	/**
	 * @param array<string, mixed> $rule Single rule.
	 * @return array{field:string,op:string,value:string}|null
	 */
	private static function normalize_one_rule( array $rule ) {
		$field = sanitize_key( (string) ( $rule['field'] ?? '' ) );
		if ( '' === $field ) {
			return null;
		}
		$op      = sanitize_key( (string) ( $rule['op'] ?? 'equals' ) );
		$allowed = array( 'equals', 'not_equals', 'contains', 'is_checked', 'is_not_empty' );
		if ( ! in_array( $op, $allowed, true ) ) {
			$op = 'equals';
		}
		return array(
			'field' => $field,
			'op'    => $op,
			'value' => isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
		);
	}

	/**
	 * Flatten fields by id.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields_by_id( array $schema ) {
		$map = array();
		if ( empty( $schema['sections'] ) || ! is_array( $schema['sections'] ) ) {
			return $map;
		}
		foreach ( $schema['sections'] as $section ) {
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field ) {
				if ( ! empty( $field['id'] ) ) {
					$map[ $field['id'] ] = $field;
				}
			}
		}
		return $map;
	}

	/**
	 * First email field ID in schema order, or empty string.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return string
	 */
	public static function first_email_field_id( array $schema ) {
		foreach ( self::fields_by_id( $schema ) as $id => $field ) {
			if ( isset( $field['type'] ) && 'email' === $field['type'] ) {
				return (string) $id;
			}
		}
		return '';
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array{enabled:bool,token:string}
	 */
	public static function get_secret( $form_id ) {
		return array(
			'enabled' => (bool) get_post_meta( (int) $form_id, self::META_SECRET_ENABLED, true ),
			'token'   => (string) get_post_meta( (int) $form_id, self::META_SECRET_TOKEN, true ),
		);
	}

	/**
	 * Confirmation message shown after a successful submit (legacy helper).
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function get_confirmation_message( $form_id ) {
		$conf = self::get_confirmation( $form_id );
		return (string) $conf['message'];
	}

	/**
	 * Structured confirmation settings.
	 *
	 * @param int $form_id Form ID.
	 * @return array{mode:string,message:string,redirect_url:string,page_id:int}
	 */
	public static function get_confirmation( $form_id ) {
		$form_id = (int) $form_id;
		$raw     = get_post_meta( $form_id, self::META_CONFIRMATION, true );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		$mode = isset( $decoded['mode'] ) ? sanitize_key( (string) $decoded['mode'] ) : 'message';
		if ( ! in_array( $mode, array( 'message', 'redirect', 'page' ), true ) ) {
			$mode = 'message';
		}

		$message = isset( $decoded['message'] ) ? (string) $decoded['message'] : '';
		if ( '' === trim( $message ) ) {
			$message = (string) get_post_meta( $form_id, self::META_CONFIRMATION_MESSAGE, true );
		}
		$message = trim( $message );
		if ( '' === $message ) {
			$message = __( 'Thank you. Your form was submitted successfully.', 'we-formkit' );
		}

		return array(
			'mode'         => $mode,
			'message'      => $message,
			'redirect_url' => isset( $decoded['redirect_url'] ) ? esc_url_raw( (string) $decoded['redirect_url'] ) : '',
			'page_id'      => isset( $decoded['page_id'] ) ? absint( $decoded['page_id'] ) : 0,
		);
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Confirmation data.
	 * @return void
	 */
	public static function set_confirmation( $form_id, array $data ) {
		$mode = isset( $data['mode'] ) ? sanitize_key( (string) $data['mode'] ) : 'message';
		if ( ! in_array( $mode, array( 'message', 'redirect', 'page' ), true ) ) {
			$mode = 'message';
		}
		$payload = array(
			'mode'         => $mode,
			'message'      => isset( $data['message'] ) ? sanitize_textarea_field( (string) $data['message'] ) : '',
			'redirect_url' => isset( $data['redirect_url'] ) ? esc_url_raw( (string) $data['redirect_url'] ) : '',
			'page_id'      => isset( $data['page_id'] ) ? absint( $data['page_id'] ) : 0,
		);
		update_post_meta( (int) $form_id, self::META_CONFIRMATION, wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		update_post_meta( (int) $form_id, self::META_CONFIRMATION_MESSAGE, $payload['message'] );
	}

	/**
	 * Submit button label + optional inline SVG icon.
	 *
	 * @param int $form_id Form ID.
	 * @return array{label:string,icon_svg:string,icon_position:string}
	 */
	public static function get_submit_button( $form_id ) {
		$form_id = (int) $form_id;
		$raw     = get_post_meta( $form_id, self::META_SUBMIT_BUTTON, true );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		$label = isset( $decoded['label'] ) ? sanitize_text_field( (string) $decoded['label'] ) : '';
		if ( '' === $label ) {
			$label = __( 'Submit form', 'we-formkit' );
		}

		$position = isset( $decoded['icon_position'] ) ? sanitize_key( (string) $decoded['icon_position'] ) : 'before';
		if ( ! in_array( $position, array( 'before', 'after' ), true ) ) {
			$position = 'before';
		}

		return array(
			'label'         => $label,
			'icon_svg'      => self::sanitize_submit_icon_svg( $decoded['icon_svg'] ?? '' ),
			'icon_position' => $position,
		);
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Submit button data.
	 * @return void
	 */
	public static function set_submit_button( $form_id, array $data ) {
		$label = isset( $data['label'] ) ? sanitize_text_field( (string) $data['label'] ) : '';
		$pos   = isset( $data['icon_position'] ) ? sanitize_key( (string) $data['icon_position'] ) : 'before';
		if ( ! in_array( $pos, array( 'before', 'after' ), true ) ) {
			$pos = 'before';
		}
		$payload = array(
			'label'         => $label,
			'icon_svg'      => self::sanitize_submit_icon_svg( $data['icon_svg'] ?? '' ),
			'icon_position' => $pos,
		);
		update_post_meta( (int) $form_id, self::META_SUBMIT_BUTTON, wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Form-wide label / help / density appearance presets.
	 *
	 * @param int $form_id Form ID.
	 * @return array{label_weight:string,required_mark:string,help_placement:string,help_style:string,font_family:string,spacing:string,control_padding:string,size_section:string,size_label:string,size_input:string}
	 */
	public static function get_appearance( $form_id ) {
		$form_id = (int) $form_id;
		$raw     = get_post_meta( $form_id, self::META_APPEARANCE, true );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}
		return self::normalize_appearance( $decoded );
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Appearance data.
	 * @return void
	 */
	public static function set_appearance( $form_id, array $data ) {
		$payload = self::normalize_appearance( $data );
		update_post_meta( (int) $form_id, self::META_APPEARANCE, wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * @param array<string, mixed> $data Raw.
	 * @return array{label_weight:string,required_mark:string,help_placement:string,help_style:string,font_family:string,spacing:string,control_padding:string,size_section:string,size_label:string,size_input:string}
	 */
	public static function normalize_appearance( array $data ) {
		$weight = isset( $data['label_weight'] ) ? sanitize_key( (string) $data['label_weight'] ) : 'bold';
		if ( ! in_array( $weight, array( 'normal', 'bold' ), true ) ) {
			$weight = 'bold';
		}

		$mark = isset( $data['required_mark'] ) ? sanitize_key( (string) $data['required_mark'] ) : 'asterisk';
		if ( ! in_array( $mark, array( 'asterisk', 'text', 'none' ), true ) ) {
			$mark = 'asterisk';
		}

		$optional = isset( $data['optional_mark'] ) ? sanitize_key( (string) $data['optional_mark'] ) : 'text';
		if ( ! in_array( $optional, array( 'text', 'none' ), true ) ) {
			$optional = 'text';
		}

		$inline = isset( $data['inline_validation'] ) ? sanitize_key( (string) $data['inline_validation'] ) : 'on';
		if ( ! in_array( $inline, array( 'on', 'off' ), true ) ) {
			$inline = 'on';
		}

		$placement = isset( $data['help_placement'] ) ? sanitize_key( (string) $data['help_placement'] ) : 'below_label';
		if ( ! in_array( $placement, array( 'below_label', 'below_field' ), true ) ) {
			$placement = 'below_label';
		}

		$style = isset( $data['help_style'] ) ? sanitize_key( (string) $data['help_style'] ) : 'muted';
		if ( ! in_array( $style, array( 'muted', 'boxed' ), true ) ) {
			$style = 'muted';
		}

		$font = isset( $data['font_family'] ) ? (string) $data['font_family'] : 'inherit';
		if ( 'inherit' !== $font ) {
			$allowed = array();
			foreach ( Form_Style::theme_font_families() as $item ) {
				$allowed[] = (string) $item['slug'];
			}
			if ( ! in_array( $font, $allowed, true ) ) {
				$font = 'inherit';
			}
		}

		$spacing = isset( $data['spacing'] ) ? sanitize_key( (string) $data['spacing'] ) : 'cozy';
		if ( ! in_array( $spacing, array( 'compact', 'cozy', 'comfortable' ), true ) ) {
			$spacing = 'cozy';
		}

		$control = isset( $data['control_padding'] ) ? sanitize_key( (string) $data['control_padding'] ) : 'cozy';
		if ( ! in_array( $control, array( 'compact', 'cozy', 'comfortable' ), true ) ) {
			$control = 'cozy';
		}

		$size_steps   = array( 'sm', 'md', 'lg' );
		$size_section = isset( $data['size_section'] ) ? sanitize_key( (string) $data['size_section'] ) : 'md';
		if ( ! in_array( $size_section, $size_steps, true ) ) {
			$size_section = 'md';
		}
		$size_label = isset( $data['size_label'] ) ? sanitize_key( (string) $data['size_label'] ) : 'md';
		if ( ! in_array( $size_label, $size_steps, true ) ) {
			$size_label = 'md';
		}
		$size_input = isset( $data['size_input'] ) ? sanitize_key( (string) $data['size_input'] ) : 'md';
		if ( ! in_array( $size_input, $size_steps, true ) ) {
			$size_input = 'md';
		}

		$radius_steps = array( 'none', 'sm', 'md', 'lg', 'pill' );
		$radius_input = isset( $data['radius_input'] ) ? sanitize_key( (string) $data['radius_input'] ) : 'md';
		if ( ! in_array( $radius_input, $radius_steps, true ) ) {
			$radius_input = 'md';
		}
		$radius_button = isset( $data['radius_button'] ) ? sanitize_key( (string) $data['radius_button'] ) : 'pill';
		if ( ! in_array( $radius_button, $radius_steps, true ) ) {
			$radius_button = 'pill';
		}
		$radius_section = isset( $data['radius_section'] ) ? sanitize_key( (string) $data['radius_section'] ) : 'md';
		if ( ! in_array( $radius_section, array( 'none', 'sm', 'md', 'lg' ), true ) ) {
			$radius_section = 'md';
		}

		return array(
			'label_weight'      => $weight,
			'required_mark'     => $mark,
			'optional_mark'     => $optional,
			'inline_validation' => $inline,
			'help_placement'    => $placement,
			'help_style'        => $style,
			'font_family'       => $font,
			'spacing'           => $spacing,
			'control_padding'   => $control,
			'size_section'      => $size_section,
			'size_label'        => $size_label,
			'size_input'        => $size_input,
			'radius_input'      => $radius_input,
			'radius_button'     => $radius_button,
			'radius_section'    => $radius_section,
		);
	}

	/**
	 * CSS custom properties for density / type scale (no colors).
	 *
	 * @param int $form_id Form ID.
	 * @return string Semicolon-separated declarations without trailing semicolon requirement.
	 */
	public static function appearance_css_variables( $form_id ): string {
		$a = self::get_appearance( $form_id );

		$spacing = array(
			'compact'     => array(
				'gap-y' => '0.55rem',
				'gap-x' => '0.65rem',
				'space' => '0.85rem',
				'shell' => '1rem',
			),
			'cozy'        => array(
				'gap-y' => '0.85rem',
				'gap-x' => '1rem',
				'space' => '1.25rem',
				'shell' => 'clamp(1.25rem, 3vw, 2rem)',
			),
			'comfortable' => array(
				'gap-y' => '1.2rem',
				'gap-x' => '1.25rem',
				'space' => '1.6rem',
				'shell' => 'clamp(1.5rem, 3.5vw, 2.35rem)',
			),
		);

		$controls = array(
			'compact'     => array(
				'pad-y' => '0.4rem',
				'pad-x' => '0.55rem',
			),
			'cozy'        => array(
				'pad-y' => '0.65rem',
				'pad-x' => '0.75rem',
			),
			'comfortable' => array(
				'pad-y' => '0.85rem',
				'pad-x' => '0.95rem',
			),
		);

		$sections = array(
			'sm' => '1rem',
			'md' => '1.15rem',
			'lg' => '1.35rem',
		);
		$labels   = array(
			'sm' => '0.85rem',
			'md' => '0.95rem',
			'lg' => '1.05rem',
		);
		$inputs   = array(
			'sm' => '0.875rem',
			'md' => '1rem',
			'lg' => '1.0625rem',
		);

		$sp = $spacing[ $a['spacing'] ] ?? $spacing['cozy'];
		$cp = $controls[ $a['control_padding'] ] ?? $controls['cozy'];

		$radius_map         = array(
			'none' => '0',
			'sm'   => '4px',
			'md'   => '8px',
			'lg'   => '14px',
			'pill' => '999px',
		);
		$radius_section_map = array(
			'none' => '0',
			'sm'   => '8px',
			'md'   => '12px',
			'lg'   => '18px',
		);

		$r_input   = $radius_map[ $a['radius_input'] ] ?? $radius_map['md'];
		$r_button  = $radius_map[ $a['radius_button'] ] ?? $radius_map['pill'];
		$r_section = $radius_section_map[ $a['radius_section'] ] ?? $radius_section_map['md'];

		$parts = array(
			'--wek-gap-y:' . $sp['gap-y'],
			'--wek-gap-x:' . $sp['gap-x'],
			'--wek-space:' . $sp['space'],
			'--wek-shell-pad:' . $sp['shell'],
			'--wek-control-pad-y:' . $cp['pad-y'],
			'--wek-control-pad-x:' . $cp['pad-x'],
			'--wek-font-section:' . ( $sections[ $a['size_section'] ] ?? $sections['md'] ),
			'--wek-font-label:' . ( $labels[ $a['size_label'] ] ?? $labels['md'] ),
			'--wek-font-input:' . ( $inputs[ $a['size_input'] ] ?? $inputs['md'] ),
			'--wek-radius-input:' . $r_input,
			'--wek-radius-button:' . $r_button,
			'--wek-radius-section:' . $r_section,
			'--wek-radius:' . $r_section,
		);

		return implode( ';', $parts );
	}

	/**
	 * CSS modifier classes for the form root.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function appearance_root_classes( $form_id ) {
		$a       = self::get_appearance( $form_id );
		$classes = array(
			'we-formkit--label-' . $a['label_weight'],
			'we-formkit--req-' . $a['required_mark'],
			'we-formkit--opt-' . $a['optional_mark'],
			'we-formkit--help-' . $a['help_placement'],
			'we-formkit--help-style-' . $a['help_style'],
		);
		if ( 'on' === $a['inline_validation'] ) {
			$classes[] = 'we-formkit--inline-validation';
		}
		return implode( ' ', $classes );
	}

	/**
	 * Allow a small inline SVG subset for submit button icons (no scripts).
	 *
	 * @param mixed $raw Raw SVG markup.
	 * @return string
	 */
	public static function sanitize_submit_icon_svg( $raw ) {
		$svg = is_string( $raw ) ? trim( $raw ) : '';
		if ( '' === $svg ) {
			return '';
		}
		// Reject obvious script / event handlers before kses.
		if ( preg_match( '/<script|javascript:|on[a-z]+\s*=/i', $svg ) ) {
			return '';
		}
		$clean = wp_kses( $svg, self::submit_icon_svg_allowed_html() );
		$clean = trim( $clean );
		if ( '' === $clean || false === stripos( $clean, '<svg' ) ) {
			return '';
		}
		return $clean;
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	public static function submit_icon_svg_allowed_html() {
		$common = array(
			'fill'             => true,
			'stroke'           => true,
			'stroke-width'     => true,
			'stroke-linecap'   => true,
			'stroke-linejoin'  => true,
			'stroke-dasharray' => true,
			'opacity'          => true,
			'transform'        => true,
			'class'            => true,
		);
		return array(
			'svg'      => array_merge(
				$common,
				array(
					'xmlns'       => true,
					'viewbox'     => true,
					'width'       => true,
					'height'      => true,
					'aria-hidden' => true,
					'role'        => true,
					'focusable'   => true,
				)
			),
			'g'        => $common,
			'path'     => array_merge(
				$common,
				array(
					'd'         => true,
					'fill-rule' => true,
					'clip-rule' => true,
				)
			),
			'circle'   => array_merge(
				$common,
				array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				)
			),
			'ellipse'  => array_merge(
				$common,
				array(
					'cx' => true,
					'cy' => true,
					'rx' => true,
					'ry' => true,
				)
			),
			'rect'     => array_merge(
				$common,
				array(
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
				)
			),
			'line'     => array_merge(
				$common,
				array(
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				)
			),
			'polyline' => array_merge( $common, array( 'points' => true ) ),
			'polygon'  => array_merge( $common, array( 'points' => true ) ),
			'title'    => array(),
			'desc'     => array(),
		);
	}

	/**
	 * Pagination mode: single | per_section.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function get_pagination( $form_id ) {
		$mode = sanitize_key( (string) get_post_meta( (int) $form_id, self::META_PAGINATION, true ) );
		return 'per_section' === $mode ? 'per_section' : 'single';
	}

	/**
	 * @param int    $form_id Form ID.
	 * @param string $mode    Pagination mode.
	 * @return void
	 */
	public static function set_pagination( $form_id, $mode ) {
		$mode = sanitize_key( (string) $mode );
		update_post_meta( (int) $form_id, self::META_PAGINATION, 'per_section' === $mode ? 'per_section' : 'single' );
	}

	/**
	 * @param int         $form_id Form ID.
	 * @param bool        $enabled Enabled.
	 * @param string|null $token   Token or null to keep/generate.
	 * @return string Token.
	 */
	public static function set_secret( $form_id, $enabled, $token = null ) {
		update_post_meta( (int) $form_id, self::META_SECRET_ENABLED, $enabled ? 1 : 0 );
		$current = (string) get_post_meta( (int) $form_id, self::META_SECRET_TOKEN, true );
		if ( null === $token || '' === $token ) {
			if ( '' === $current ) {
				$token = wp_generate_password( 32, false, false );
			} else {
				$token = $current;
			}
		}
		update_post_meta( (int) $form_id, self::META_SECRET_TOKEN, sanitize_text_field( $token ) );
		return (string) $token;
	}

	/**
	 * @param string $slug Form slug.
	 * @return int Form ID or 0.
	 */
	public static function find_by_slug( $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return 0;
		}
		$query = new \WP_Query(
			array(
				'post_type'      => Post_Types::FORM,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_SLUG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- lookup by unique form slug.
				'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- lookup by unique form slug.
				'no_found_rows'  => true,
			)
		);
		return ! empty( $query->posts[0] ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Validate submitted values against schema + conditionals via the registry.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param array<string, mixed> $values Values.
	 * @return array{ok:bool,errors:array<string,string>,data:array<string,mixed>}
	 */
	public static function validate_submission( array $schema, array $values ) {
		$errors   = array();
		$data     = array();
		$registry = Plugin::instance()->field_registry();

		if ( empty( $schema['sections'] ) || ! is_array( $schema['sections'] ) ) {
			return array(
				'ok'     => true,
				'errors' => $errors,
				'data'   => $data,
			);
		}

		foreach ( $schema['sections'] as $section ) {
			if ( ! Conditional::is_visible( $section['show_when'] ?? null, $values ) ) {
				continue;
			}
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field ) {
				$id = isset( $field['id'] ) ? (string) $field['id'] : '';
				if ( '' === $id ) {
					continue;
				}
				if ( ! Conditional::is_visible( $field['show_when'] ?? null, $values ) ) {
					continue;
				}

				$type_id = isset( $field['type'] ) ? (string) $field['type'] : 'text';
				$type    = $registry ? $registry->get( $type_id ) : null;
				if ( null === $type || ! $type->stores_value() ) {
					continue;
				}

				$raw       = array_key_exists( $id, $values ) ? $values[ $id ] : null;
				$sanitized = $type->sanitize( $raw, $field );

				if ( ! empty( $field['required'] ) && $type->is_empty_value( $sanitized ) ) {
					$errors[ $id ] = Validation_Messages::required_for_field( $field );
					continue;
				}

				// Always validate so constraints like checkbox min selections apply to empty values.
				$valid = $type->validate( $sanitized, $field );
				if ( is_wp_error( $valid ) ) {
					$errors[ $id ] = $valid->get_error_message();
					continue;
				}

				$data[ $id ] = $sanitized;
			}
		}

		return array(
			'ok'     => empty( $errors ),
			'errors' => $errors,
			'data'   => $data,
		);
	}
}
