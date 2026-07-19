<?php
/**
 * Consent field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy or terms consent checkbox.
 */
class Consent_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'consent';
	}

	public function get_label(): string {
		return __( 'Consent', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'privacy_url' => array(
				'label'       => __( 'Privacy policy URL override', 'we-formkit' ),
				'type'        => 'url',
				'default'     => '',
				'description' => __( 'Optional. Overrides the site-wide privacy policy link for this field.', 'we-formkit' ),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		if ( isset( $field['type_options']['privacy_url'] ) ) {
			$field['type_options']['privacy_url'] = esc_url_raw( (string) $field['type_options']['privacy_url'] );
		}

		return $field;
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
				? __( 'Accepted', 'we-formkit' )
				: __( 'Not accepted', 'we-formkit' )
		);
	}

	public function render_attributes( array $field ): array {
		$attrs = parent::render_attributes( $field );
		$attrs['type'] = 'checkbox';
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
