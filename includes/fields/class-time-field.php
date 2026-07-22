<?php
/**
 * Time field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Time-of-day input (HH:MM).
 */
class Time_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'time';
	}

	public function get_label(): string {
		return __( 'Time', 'we-formkit' );
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( $value );
		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $value ) ) {
			return '';
		}

		return $value;
	}

	public function validate( $value, array $field ) {
		if ( '' === $value ) {
			return true;
		}

		if ( ! is_string( $value ) || ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $value ) ) {
			return new \WP_Error(
				'we_formkit_time_invalid',
				sprintf(
					/* translators: %s: field label. */
					__( 'Please choose a valid time for %s.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' )
				)
			);
		}

		return true;
	}

	public function render_attributes( array $field ): array {
		$attrs         = parent::render_attributes( $field );
		$attrs['type'] = 'time';

		return $attrs;
	}
}
