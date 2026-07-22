<?php
/**
 * URL field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL input.
 */
class Url_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'url';
	}

	public function get_label(): string {
		return __( 'URL', 'we-formkit' );
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		return esc_url_raw( $value );
	}

	public function validate( $value, array $field ) {
		if ( '' === $value ) {
			return true;
		}

		if ( ! is_string( $value ) || ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error(
				'we_formkit_url_invalid',
				$this->invalid_value_message( $field )
			);
		}

		return true;
	}

	public function render_attributes( array $field ): array {
		$attrs         = parent::render_attributes( $field );
		$attrs['type'] = 'url';

		return $attrs;
	}
}
