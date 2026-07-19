<?php
/**
 * Checkboxes field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multiple-choice checkbox group.
 */
class Checkboxes_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'checkboxes';
	}

	public function get_label(): string {
		return __( 'Checkboxes', 'we-formkit' );
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		if ( isset( $field['type_options']['options'] ) ) {
			$field['type_options']['options'] = $this->normalize_options_list( $field['type_options']['options'] );
		}

		return $field;
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid = $this->get_valid_option_values( $field );
		$out   = array();

		foreach ( $value as $item ) {
			$item = sanitize_key( (string) $item );
			if ( '' === $item || ! in_array( $item, $valid, true ) ) {
				continue;
			}

			$out[] = $item;
		}

		return array_values( array_unique( $out ) );
	}

	public function validate( $value, array $field ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return true;
		}

		$valid = $this->get_valid_option_values( $field );

		foreach ( $value as $item ) {
			if ( ! in_array( $item, $valid, true ) ) {
				return new \WP_Error(
					'we_formkit_checkboxes_invalid',
					sprintf(
						/* translators: %s: field label. */
						__( 'Please choose valid options for %s.', 'we-formkit' ),
						(string) ( $field['label'] ?? '' )
					)
				);
			}
		}

		return true;
	}

	public function format_for_display( $value, array $field ): string {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return '';
		}

		$labels = $this->get_option_label_map( $field );
		$parts  = array();

		foreach ( $value as $item ) {
			$parts[] = esc_html( $labels[ $item ] ?? (string) $item );
		}

		return implode( ', ', $parts );
	}
}
