<?php
/**
 * Datetime field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Combined date and time input.
 */
class Datetime_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'datetime';
	}

	public function get_label(): string {
		return __( 'Date and time', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'constraints' => array(
				'label'       => __( 'Date constraints', 'we-formkit' ),
				'type'        => 'date_constraints',
				'default'     => array(
					'min' => array(
						'enabled'   => false,
						'amount'    => 0,
						'unit'      => Date_Constraints::UNIT_DAYS,
						'direction' => Date_Constraints::DIRECTION_PAST,
					),
					'max' => array(
						'enabled'   => false,
						'amount'    => 0,
						'unit'      => Date_Constraints::UNIT_DAYS,
						'direction' => Date_Constraints::DIRECTION_FUTURE,
					),
				),
				'description' => __( 'Applies to the date portion of the value.', 'we-formkit' ),
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
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T([01]\d|2[0-3]):([0-5]\d)$/', $value ) ) {
			return '';
		}

		list( $date_part, $time_part ) = explode( 'T', $value, 2 );
		$parts = explode( '-', $date_part );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return '';
		}

		return $date_part . 'T' . $time_part;
	}

	public function validate( $value, array $field ) {
		if ( '' === $value ) {
			return true;
		}

		if ( ! is_string( $value ) ) {
			return new \WP_Error(
				'we_formkit_datetime_invalid',
				sprintf(
					/* translators: %s: field label. */
					__( 'Please choose a valid date and time for %s.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' )
				)
			);
		}

		list( $date_part ) = explode( 'T', $value, 2 );
		$date_result       = Date_Constraints::validate_date_against_constraints( $date_part, $field );
		if ( is_wp_error( $date_result ) ) {
			return $date_result;
		}

		return true;
	}

	public function format_for_display( $value, array $field ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		$timestamp = strtotime( str_replace( 'T', ' ', $value ) );
		if ( false === $timestamp ) {
			return esc_html( $value );
		}

		$date_format = get_option( 'date_format', 'Y-m-d' );
		$time_format = get_option( 'time_format', 'H:i' );

		return esc_html( wp_date( $date_format . ' ' . $time_format, $timestamp ) );
	}

	public function render_attributes( array $field ): array {
		$attrs = parent::render_attributes( $field );
		$attrs['type'] = 'datetime-local';

		$constraints = Date_Constraints::get_field_constraints( $field );
		$min_bound     = Date_Constraints::resolve_bound( $constraints['min'] );
		$max_bound     = Date_Constraints::resolve_bound( $constraints['max'] );

		if ( $min_bound instanceof \DateTimeImmutable ) {
			$attrs['min'] = $min_bound->format( 'Y-m-d\T00:00' );
		}

		if ( $max_bound instanceof \DateTimeImmutable ) {
			$attrs['max'] = $max_bound->format( 'Y-m-d\T23:59' );
		}

		return $attrs;
	}
}
