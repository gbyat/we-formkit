<?php
/**
 * HTML field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static HTML block with no stored submission value.
 */
class Html_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'html';
	}

	public function get_label(): string {
		return __( 'HTML / Note', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'content' => array(
				'label'   => __( 'HTML content', 'we-formkit' ),
				'type'    => 'textarea',
				'default' => '',
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		if ( isset( $field['type_options']['content'] ) ) {
			$field['type_options']['content'] = wp_kses_post( (string) $field['type_options']['content'] );
		}

		return $field;
	}

	public function sanitize( $value, array $field ) {
		return '';
	}

	public function validate( $value, array $field ) {
		return true;
	}

	public function is_empty_value( $value ): bool {
		return true;
	}

	public function format_for_display( $value, array $field ): string {
		return '';
	}

	public function stores_value(): bool {
		return false;
	}

	/**
	 * Get the sanitized HTML content for rendering.
	 *
	 * @param array<string, mixed> $field Field configuration.
	 */
	public function get_rendered_html( array $field ): string {
		$raw = (string) ( $field['type_options']['content'] ?? '' );
		return wp_kses_post( $raw );
	}
}
