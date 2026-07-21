<?php
/**
 * Number field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Numeric input (integer or decimal).
 */
class Number_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'number';
	}

	public function get_label(): string {
		return __( 'Number', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'min'  => array(
				'label'   => __( 'Minimum value', 'we-formkit' ),
				'type'    => 'number',
				'default' => '',
			),
			'max'  => array(
				'label'   => __( 'Maximum value', 'we-formkit' ),
				'type'    => 'number',
				'default' => '',
			),
			'step' => array(
				'label'   => __( 'Step', 'we-formkit' ),
				'type'    => 'number',
				'default' => 'any',
			),
		);
	}

	public function sanitize( $value, array $field ) {
		if ( '' === $value || null === $value ) {
			return '';
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
		}

		if ( ! is_numeric( $value ) ) {
			return '';
		}

		$step = (string) ( $field['type_options']['step'] ?? 'any' );
		if ( 'any' !== $step && false !== strpos( (string) $value, '.' ) ) {
			return (float) $value;
		}

		if ( false !== strpos( (string) $value, '.' ) ) {
			return (float) $value;
		}

		return (int) $value;
	}

	public function validate( $value, array $field ) {
		if ( '' === $value ) {
			return true;
		}

		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			return new \WP_Error(
				'we_formkit_number_invalid',
				$this->invalid_value_message( $field )
			);
		}

		$opts = $field['type_options'] ?? array();

		if ( isset( $opts['min'] ) && '' !== $opts['min'] && is_numeric( $opts['min'] ) && $value < (float) $opts['min'] ) {
			return new \WP_Error(
				'we_formkit_number_too_small',
				sprintf(
					/* translators: 1: field label, 2: minimum value. */
					__( '%1$s must be at least %2$s.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' ),
					(string) $opts['min']
				)
			);
		}

		if ( isset( $opts['max'] ) && '' !== $opts['max'] && is_numeric( $opts['max'] ) && $value > (float) $opts['max'] ) {
			return new \WP_Error(
				'we_formkit_number_too_large',
				sprintf(
					/* translators: 1: field label, 2: maximum value. */
					__( '%1$s must be at most %2$s.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' ),
					(string) $opts['max']
				)
			);
		}

		return true;
	}

	public function format_for_display( $value, array $field ): string {
		if ( '' === $value ) {
			return '';
		}

		return esc_html( (string) $value );
	}

	public function render_attributes( array $field ): array {
		$attrs         = parent::render_attributes( $field );
		$attrs['type'] = 'number';

		$opts = $field['type_options'] ?? array();

		if ( isset( $opts['min'] ) && '' !== $opts['min'] && is_numeric( $opts['min'] ) ) {
			$attrs['min'] = (string) $opts['min'];
		}

		if ( isset( $opts['max'] ) && '' !== $opts['max'] && is_numeric( $opts['max'] ) ) {
			$attrs['max'] = (string) $opts['max'];
		}

		if ( isset( $opts['step'] ) && '' !== (string) $opts['step'] ) {
			$attrs['step'] = (string) $opts['step'];
		}

		return $attrs;
	}
}
