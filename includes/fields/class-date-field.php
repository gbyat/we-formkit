<?php
/**
 * Date field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date input with relative min/max constraints.
 */
class Date_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'date';
	}

	public function get_label(): string {
		return __( 'Date', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'constraints' => array(
				'label'       => __( 'Date constraints', 'we-formkit' ),
				'type'        => 'date_constraints',
				'default'     => array(
					'min' => array(
						'enabled'   => true,
						'amount'    => 1,
						'unit'      => Date_Constraints::UNIT_WEEKS,
						'direction' => Date_Constraints::DIRECTION_PAST,
					),
					'max' => array(
						'enabled'   => true,
						'amount'    => 0,
						'unit'      => Date_Constraints::UNIT_DAYS,
						'direction' => Date_Constraints::DIRECTION_FUTURE,
					),
				),
				'description' => __( 'Relative min/max window from today using amount, unit (days, weeks, months, years), and direction (past or future).', 'we-formkit' ),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		if ( isset( $field['constraints'] ) ) {
			$field['constraints'] = Date_Constraints::normalize_constraints( $field['constraints'] );
		} elseif ( isset( $field['type_options']['constraints'] ) ) {
			$field['type_options']['constraints'] = Date_Constraints::normalize_constraints( $field['type_options']['constraints'] );
		}

		return $field;
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		$value = sanitize_text_field( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		$parts = explode( '-', $value );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return '';
		}

		return $value;
	}

	public function validate( $value, array $field ) {
		if ( '' === $value ) {
			return true;
		}

		if ( ! is_string( $value ) ) {
			return new \WP_Error(
				'we_formkit_date_invalid',
				sprintf(
					/* translators: %s: field label. */
					__( 'Please choose a valid date for %s.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' )
				)
			);
		}

		return Date_Constraints::validate_date_against_constraints( $value, $field );
	}

	public function format_for_display( $value, array $field ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return esc_html( $value );
		}

		return esc_html( wp_date( get_option( 'date_format', 'Y-m-d' ), $timestamp ) );
	}

	public function render_attributes( array $field ): array {
		$attrs         = parent::render_attributes( $field );
		$attrs['type'] = 'date';

		$constraints = Date_Constraints::get_field_constraints( $field );
		$min_bound   = Date_Constraints::resolve_bound( $constraints['min'] );
		$max_bound   = Date_Constraints::resolve_bound( $constraints['max'] );

		if ( $min_bound instanceof \DateTimeImmutable ) {
			$attrs['min'] = $min_bound->format( 'Y-m-d' );
		}

		if ( $max_bound instanceof \DateTimeImmutable ) {
			$attrs['max'] = $max_bound->format( 'Y-m-d' );
		}

		return $attrs;
	}

	/**
	 * Resolve a single constraint to an absolute date boundary.
	 *
	 * @param array<string, mixed> $constraint Normalized constraint.
	 */
	public function resolve_bound( array $constraint ): ?\DateTimeImmutable {
		return Date_Constraints::resolve_bound( $constraint );
	}
}
