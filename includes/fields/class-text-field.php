<?php
/**
 * Text field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single-line text input.
 */
class Text_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'text';
	}

	public function get_label(): string {
		return __( 'Text', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'max_length' => array(
				'label'       => __( 'Maximum length', 'we-formkit' ),
				'type'        => 'number',
				'default'     => 0,
				'description' => __( '0 = no limit.', 'we-formkit' ),
			),
		);
	}

	public function sanitize( $value, array $field ) {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public function validate( $value, array $field ) {
		$max = isset( $field['type_options']['max_length'] ) ? (int) $field['type_options']['max_length'] : 0;
		if ( $max > 0 && is_string( $value ) && mb_strlen( $value ) > $max ) {
			return new \WP_Error(
				'we_formkit_text_too_long',
				sprintf(
					/* translators: 1: field label, 2: maximum number of characters. */
					__( '%1$s must be at most %2$d characters.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' ),
					$max
				)
			);
		}

		return true;
	}

	public function render_attributes( array $field ): array {
		$attrs         = parent::render_attributes( $field );
		$attrs['type'] = 'text';

		$max = isset( $field['type_options']['max_length'] ) ? (int) $field['type_options']['max_length'] : 0;
		if ( $max > 0 ) {
			$attrs['maxlength'] = (string) $max;
		}

		return $attrs;
	}
}
