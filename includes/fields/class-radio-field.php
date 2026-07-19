<?php
/**
 * Radio field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single-choice radio buttons.
 */
class Radio_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'radio';
	}

	public function get_label(): string {
		return __( 'Radio', 'we-formkit' );
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		if ( isset( $field['type_options']['options'] ) ) {
			$field['type_options']['options'] = $this->normalize_options_list( $field['type_options']['options'] );
		}

		return $field;
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = sanitize_key( $value );
		if ( '' === $value ) {
			return '';
		}

		return in_array( $value, $this->get_valid_option_values( $field ), true ) ? $value : '';
	}

	public function validate( $value, array $field ) {
		if ( '' === $value ) {
			return true;
		}

		if ( ! in_array( $value, $this->get_valid_option_values( $field ), true ) ) {
			return new \WP_Error(
				'we_formkit_radio_invalid',
				sprintf(
					/* translators: %s: field label. */
					__( 'Please choose a valid option for %s.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' )
				)
			);
		}

		return true;
	}

	public function format_for_display( $value, array $field ): string {
		$labels = $this->get_option_label_map( $field );
		if ( is_string( $value ) && isset( $labels[ $value ] ) ) {
			return esc_html( $labels[ $value ] );
		}

		return is_string( $value ) ? esc_html( $value ) : '';
	}
}
