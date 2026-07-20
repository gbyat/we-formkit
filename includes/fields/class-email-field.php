<?php
/**
 * Email field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email address input.
 */
class Email_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'email';
	}

	public function get_label(): string {
		return __( 'Email', 'we-formkit' );
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		return sanitize_email( $value );
	}

	public function validate( $value, array $field ) {
		if ( '' === $value ) {
			return true;
		}

		if ( ! is_string( $value ) || ! is_email( $value ) ) {
			return new \WP_Error(
				'we_formkit_email_invalid',
				$this->invalid_value_message( $field )
			);
		}

		return true;
	}

	public function render_attributes( array $field ): array {
		$attrs         = parent::render_attributes( $field );
		$attrs['type'] = 'email';

		return $attrs;
	}
}
