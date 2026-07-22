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
			'min'      => array(
				'label'   => __( 'Minimum value', 'we-formkit' ),
				'type'    => 'number',
				'default' => '',
			),
			'max'      => array(
				'label'   => __( 'Maximum value', 'we-formkit' ),
				'type'    => 'number',
				'default' => '',
			),
			'step'     => array(
				'label'       => __( 'Step', 'we-formkit' ),
				'type'        => 'text',
				'default'     => 'any',
				'description' => __( 'Use “any”, “1”, or “0.01”. Leave empty to derive from decimal places.', 'we-formkit' ),
			),
			'decimals' => array(
				'label'       => __( 'Decimal places', 'we-formkit' ),
				'type'        => 'number',
				'default'     => '',
				'description' => __( '0–6. Sets the step when Step is empty (0 → 1, 2 → 0.01).', 'we-formkit' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $field Field config.
	 * @return array<string, mixed>
	 */
	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );
		$opts  = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();

		$min = isset( $opts['min'] ) ? trim( (string) $opts['min'] ) : '';
		$max = isset( $opts['max'] ) ? trim( (string) $opts['max'] ) : '';
		if ( '' !== $min && ! is_numeric( $min ) ) {
			$min = '';
		}
		if ( '' !== $max && ! is_numeric( $max ) ) {
			$max = '';
		}
		if ( '' !== $min && '' !== $max && (float) $min > (float) $max ) {
			$swap = $min;
			$min  = $max;
			$max  = $swap;
		}

		$step = isset( $opts['step'] ) ? trim( (string) $opts['step'] ) : '';
		if ( '' !== $step && 'any' !== strtolower( $step ) && ! is_numeric( $step ) ) {
			$step = 'any';
		}
		if ( 'any' === strtolower( $step ) ) {
			$step = 'any';
		}

		$decimals = isset( $opts['decimals'] ) ? trim( (string) $opts['decimals'] ) : '';
		if ( '' !== $decimals ) {
			if ( ! is_numeric( $decimals ) ) {
				$decimals = '';
			} else {
				$decimals = (string) max( 0, min( 6, (int) $decimals ) );
			}
		}

		$field['type_options']['min']      = $min;
		$field['type_options']['max']      = $max;
		$field['type_options']['step']     = $step;
		$field['type_options']['decimals'] = $decimals;

		return $field;
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

		$opts     = $field['type_options'] ?? array();
		$step     = $this->resolve_step( $opts );
		$decimals = isset( $opts['decimals'] ) && '' !== (string) $opts['decimals'] && is_numeric( $opts['decimals'] )
			? max( 0, min( 6, (int) $opts['decimals'] ) )
			: null;

		if ( null !== $decimals ) {
			return round( (float) $value, $decimals );
		}

		if ( '1' === $step || ( is_numeric( $step ) && (float) $step >= 1 && false === strpos( (string) $step, '.' ) ) ) {
			return (int) round( (float) $value );
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

		$decimals = isset( $field['type_options']['decimals'] ) ? trim( (string) $field['type_options']['decimals'] ) : '';
		if ( '' !== $decimals && is_numeric( $decimals ) ) {
			return esc_html( number_format_i18n( (float) $value, max( 0, min( 6, (int) $decimals ) ) ) );
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

		$step = $this->resolve_step( $opts );
		if ( '' !== $step ) {
			$attrs['step'] = $step;
		}

		return $attrs;
	}

	/**
	 * Resolve HTML step from explicit step or decimal places.
	 *
	 * @param array<string, mixed> $opts Type options.
	 */
	private function resolve_step( array $opts ): string {
		$step = isset( $opts['step'] ) ? trim( (string) $opts['step'] ) : '';
		if ( '' !== $step ) {
			return 'any' === strtolower( $step ) ? 'any' : $step;
		}

		$decimals = isset( $opts['decimals'] ) ? trim( (string) $opts['decimals'] ) : '';
		if ( '' === $decimals || ! is_numeric( $decimals ) ) {
			return 'any';
		}

		$decimals = max( 0, min( 6, (int) $decimals ) );
		if ( 0 === $decimals ) {
			return '1';
		}

		return number_format( 1 / pow( 10, $decimals ), $decimals, '.', '' );
	}
}
