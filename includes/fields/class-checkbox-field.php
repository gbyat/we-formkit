<?php
/**
 * Checkbox field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single boolean confirmation checkbox.
 */
class Checkbox_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'checkbox';
	}

	public function get_label(): string {
		return __( 'Checkbox', 'we-formkit' );
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
				? __( 'Yes', 'we-formkit' )
				: __( 'No', 'we-formkit' )
		);
	}

	public function render_attributes( array $field ): array {
		$attrs          = parent::render_attributes( $field );
		$attrs['type']  = 'checkbox';
		$attrs['value'] = '1';

		return $attrs;
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
