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
			'rows' => array(
				'label'   => __( 'Visible rows', 'we-formkit' ),
				'type'    => 'number',
				'default' => 4,
			),
		);
	}

	public function sanitize( $value, array $field ) {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	public function render_attributes( array $field ): array {
		$attrs = parent::render_attributes( $field );
		unset( $attrs['type'] );

		$rows = isset( $field['type_options']['rows'] ) ? max( 1, (int) $field['type_options']['rows'] ) : 4;
		$attrs['rows'] = (string) $rows;

		return $attrs;
	}
}
