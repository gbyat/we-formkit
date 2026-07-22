<?php
/**
 * Consent field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consent checkbox with optional inline {link} in the consent text.
 *
 * `label` = field title (show_label). `type_options.choice_label` = text beside the checkbox.
 */
class Consent_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'consent';
	}

	public function get_label(): string {
		return __( 'Consent', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'choice_label' => array(
				'label'       => __( 'Consent text', 'we-formkit' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( 'Text beside the checkbox. Use {link} for the optional linked phrase.', 'we-formkit' ),
			),
			'link_text'    => array(
				'label'       => __( 'Link text', 'we-formkit' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Shown where {link} appears in the consent text. Defaults to “Privacy policy”.', 'we-formkit' ),
			),
			'privacy_url'  => array(
				'label'       => __( 'Link URL', 'we-formkit' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional. Leave empty to use the form privacy URL, then the site default.', 'we-formkit' ),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );
		$field = Checkbox_Field::migrate_legacy_choice_label( $field, $this->get_label() );

		if ( ! isset( $field['type_options'] ) || ! is_array( $field['type_options'] ) ) {
			$field['type_options'] = array();
		}

		$opts = $field['type_options'];

		$field['type_options']['choice_label'] = isset( $opts['choice_label'] )
			? sanitize_textarea_field( (string) $opts['choice_label'] )
			: '';

		$link_text                          = isset( $opts['link_text'] ) ? sanitize_text_field( (string) $opts['link_text'] ) : '';
		$field['type_options']['link_text'] = $link_text;

		if ( isset( $opts['privacy_url'] ) ) {
			$field['type_options']['privacy_url'] = esc_url_raw( (string) $opts['privacy_url'] );
		} else {
			$field['type_options']['privacy_url'] = '';
		}

		return $field;
	}

	/**
	 * Text beside the checkbox (falls back to label for legacy schemas).
	 *
	 * @param array<string, mixed> $field Field config.
	 */
	public static function choice_label( array $field ): string {
		return Checkbox_Field::choice_label( $field );
	}

	public function sanitize( $value, array $field ) {
		return $this->is_truthy( $value ) ? '1' : '';
	}

	public function validate( $value, array $field ) {
		return true;
	}

	public function is_empty_value( $value ): bool {
		return '1' !== (string) $value;
	}

	public function format_for_display( $value, array $field ): string {
		return esc_html(
			'1' === (string) $value
				? __( 'Accepted', 'we-formkit' )
				: __( 'Not accepted', 'we-formkit' )
		);
	}

	public function render_attributes( array $field ): array {
		$attrs          = parent::render_attributes( $field );
		$attrs['type']  = 'checkbox';
		$attrs['value'] = '1';

		return $attrs;
	}

	/**
	 * Resolved link URL for a consent field (field override → form/site fallback).
	 *
	 * @param array  $field        Field config.
	 * @param string $fallback_url Form or site privacy URL.
	 */
	public static function resolve_link_url( array $field, $fallback_url ): string {
		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		$own  = isset( $opts['privacy_url'] ) ? trim( (string) $opts['privacy_url'] ) : '';
		if ( '' !== $own ) {
			return $own;
		}
		return trim( (string) $fallback_url );
	}

	/**
	 * Link anchor text for {link} in the consent text.
	 *
	 * @param array $field Field config.
	 */
	public static function resolve_link_text( array $field ): string {
		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		$text = isset( $opts['link_text'] ) ? trim( (string) $opts['link_text'] ) : '';
		if ( '' !== $text ) {
			return $text;
		}
		return __( 'Privacy policy', 'we-formkit' );
	}

	/**
	 * Whether the consent text uses the {link} placeholder.
	 *
	 * @param array $field Field config.
	 */
	public static function text_has_link_placeholder( array $field ): bool {
		return false !== strpos( self::choice_label( $field ), '{link}' );
	}

	/**
	 * @param mixed $value Incoming value.
	 */
	private function is_truthy( $value ): bool {
		if ( true === $value ) {
			return true;
		}

		if ( is_int( $value ) ) {
			return 1 === $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}
}
