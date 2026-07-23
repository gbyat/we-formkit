<?php
/**
 * Textarea field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multi-line text input.
 */
class Textarea_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'textarea';
	}

	public function get_label(): string {
		return __( 'Textarea', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'rows'       => array(
				'label'       => __( 'Height (rows)', 'we-formkit' ),
				'type'        => 'number',
				'default'     => 3,
				'description' => __( 'Visible height of the box. Independent of maximum characters.', 'we-formkit' ),
			),
			'max_length' => array(
				'label'       => __( 'Maximum length', 'we-formkit' ),
				'type'        => 'number',
				'default'     => 0,
				'description' => __( '0 = no limit. Caps how many characters visitors can enter (helps against spam dumps).', 'we-formkit' ),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		$rows                          = isset( $field['type_options']['rows'] ) ? (int) $field['type_options']['rows'] : 3;
		$field['type_options']['rows'] = max( 1, min( 40, $rows ) );

		$max                                 = isset( $field['type_options']['max_length'] ) ? (int) $field['type_options']['max_length'] : 0;
		$field['type_options']['max_length'] = max( 0, min( 100000, $max ) );

		return $field;
	}

	public function sanitize( $value, array $field ) {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	public function validate( $value, array $field ) {
		$guard = $this->content_guard( $value, $field );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$max = isset( $field['type_options']['max_length'] ) ? (int) $field['type_options']['max_length'] : 0;
		if ( $max > 0 && is_string( $value ) && mb_strlen( $value ) > $max ) {
			return new \WP_Error(
				'we_formkit_textarea_too_long',
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
		$attrs = parent::render_attributes( $field );
		unset( $attrs['type'] );

		$rows           = isset( $field['type_options']['rows'] ) ? max( 1, min( 40, (int) $field['type_options']['rows'] ) ) : 3;
		$attrs['rows']  = (string) $rows;
		$attrs['style'] = trim(
			( isset( $attrs['style'] ) ? (string) $attrs['style'] . ' ' : '' ) .
			'--wek-ta-rows: ' . $rows . ';'
		);

		$max = isset( $field['type_options']['max_length'] ) ? (int) $field['type_options']['max_length'] : 0;
		if ( $max > 0 ) {
			$attrs['maxlength'] = (string) $max;
		}

		return $attrs;
	}
}
