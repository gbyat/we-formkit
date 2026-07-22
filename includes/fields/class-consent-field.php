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
 * Consent checkbox with optional inline {link} placeholder in the label.
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
			'link_text'   => array(
				'label'       => __( 'Link text', 'we-formkit' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Shown where {link} appears in the label. Defaults to “Privacy policy”.', 'we-formkit' ),
			),
			'privacy_url' => array(
				'label'       => __( 'Link URL', 'we-formkit' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional. Leave empty to use the form privacy URL, then the site default.', 'we-formkit' ),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		if ( ! isset( $field['type_options'] ) || ! is_array( $field['type_options'] ) ) {
			$field['type_options'] = array();
		}

		$opts = $field['type_options'];

		$link_text                          = isset( $opts['link_text'] ) ? sanitize_text_field( (string) $opts['link_text'] ) : '';
		$field['type_options']['link_text'] = $link_text;

		if ( isset( $opts['privacy_url'] ) ) {
			$field['type_options']['privacy_url'] = esc_url_raw( (string) $opts['privacy_url'] );
		} else {
			$field['type_options']['privacy_url'] = '';
		}

		return $field;
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
	 * Link anchor text for {link} in the label.
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
	 * Whether the label uses the {link} placeholder.
	 *
	 * @param array $field Field config.
	 */
	public static function label_has_link_placeholder( array $field ): bool {
		$label = isset( $field['label'] ) ? (string) $field['label'] : '';
		return false !== strpos( $label, '{link}' );
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
