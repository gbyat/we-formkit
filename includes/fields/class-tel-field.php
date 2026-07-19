<?php
/**
 * Telephone field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telephone number input.
 */
class Tel_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'tel';
	}

	public function get_label(): string {
		return __( 'Phone', 'we-formkit' );
	}

	public function sanitize( $value, array $field ) {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public function render_attributes( array $field ): array {
		$attrs = parent::render_attributes( $field );
		$attrs['type'] = 'tel';

		return $attrs;
	}
}
