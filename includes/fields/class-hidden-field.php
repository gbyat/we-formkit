<?php
/**
 * Hidden field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hidden input for preset values.
 */
class Hidden_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'hidden';
	}

	public function get_label(): string {
		return __( 'Hidden', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'default_value' => array(
				'label'   => __( 'Default value', 'we-formkit' ),
				'type'    => 'text',
				'default' => '',
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		if ( isset( $field['type_options']['default_value'] ) ) {
			$field['type_options']['default_value'] = sanitize_text_field( (string) $field['type_options']['default_value'] );
		}

		return $field;
	}

	public function sanitize( $value, array $field ) {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public function render_attributes( array $field ): array {
		$attrs         = parent::render_attributes( $field );
		$attrs['type'] = 'hidden';

		return $attrs;
	}
}
