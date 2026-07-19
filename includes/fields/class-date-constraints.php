<?php
/**
 * Shared date constraint helpers for date and datetime fields.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses and resolves relative date constraints.
 */
final class Date_Constraints {

	public const UNIT_DAYS   = 'days';
	public const UNIT_WEEKS  = 'weeks';
	public const UNIT_MONTHS = 'months';
	public const UNIT_YEARS  = 'years';

	public const DIRECTION_PAST   = 'past';
	public const DIRECTION_FUTURE = 'future';

	/**
	 * Normalize constraints config (min/max entries).
	 *
	 * @param mixed $raw Raw constraints array.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize_constraints( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array(
				'min' => self::default_constraint(),
				'max' => self::default_constraint(),
			);
		}

		return array(
			'min' => self::normalize_constraint( $raw['min'] ?? array() ),
			'max' => self::normalize_constraint( $raw['max'] ?? array() ),
		);
	}

	/**
	 * Resolve a single constraint to an absolute date boundary.
	 *
	 * @param array<string, mixed> $constraint Normalized constraint.
	 */
	public static function resolve_bound( array $constraint ): ?\DateTimeImmutable {
		if ( empty( $constraint['enabled'] ) ) {
			return null;
		}

		$amount    = isset( $constraint['amount'] ) ? max( 0, (int) $constraint['amount'] ) : 0;
		$unit      = self::normalize_unit( (string) ( $constraint['unit'] ?? self::UNIT_DAYS ) );
		$direction = self::normalize_direction( (string) ( $constraint['direction'] ?? self::DIRECTION_FUTURE ) );

		try {
			$base = new \DateTimeImmutable( 'today', wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}

		if ( 0 === $amount ) {
			return $base;
		}

		$interval = self::build_interval( $amount, $unit );
		if ( null === $interval ) {
			return $base;
		}

		$modifier = ( self::DIRECTION_PAST === $direction ) ? '-' : '+';

		try {
			return $base->modify( $modifier . $interval );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Validate a YYYY-MM-DD date against field constraints.
	 *
	 * @param string               $value Sanitized date string.
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return true|\WP_Error
	 */
	public static function validate_date_against_constraints( string $value, array $field ) {
		if ( '' === $value ) {
			return true;
		}

		try {
			$date = new \DateTimeImmutable( $value, wp_timezone() );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'we_formkit_date_invalid',
				self::invalid_date_message( $field )
			);
		}

		$constraints = self::get_field_constraints( $field );
		$min_bound   = self::resolve_bound( $constraints['min'] );
		$max_bound   = self::resolve_bound( $constraints['max'] );

		if ( $min_bound instanceof \DateTimeImmutable && $date < $min_bound ) {
			return new \WP_Error(
				'we_formkit_date_too_early',
				self::invalid_date_message( $field )
			);
		}

		if ( $max_bound instanceof \DateTimeImmutable && $date > $max_bound ) {
			return new \WP_Error(
				'we_formkit_date_too_late',
				self::invalid_date_message( $field )
			);
		}

		return true;
	}

	/**
	 * Get constraints from a field (top-level preferred).
	 *
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_field_constraints( array $field ): array {
		if ( isset( $field['constraints'] ) ) {
			return self::normalize_constraints( $field['constraints'] );
		}

		if ( isset( $field['type_options']['constraints'] ) ) {
			return self::normalize_constraints( $field['type_options']['constraints'] );
		}

		return self::normalize_constraints( array() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function default_constraint(): array {
		return array(
			'enabled'   => false,
			'amount'    => 0,
			'unit'      => self::UNIT_DAYS,
			'direction' => self::DIRECTION_FUTURE,
		);
	}

	/**
	 * @param mixed $raw Raw constraint entry.
	 *
	 * @return array<string, mixed>
	 */
	private static function normalize_constraint( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return self::default_constraint();
		}

		return array(
			'enabled'   => ! empty( $raw['enabled'] ),
			'amount'    => isset( $raw['amount'] ) ? max( 0, (int) $raw['amount'] ) : 0,
			'unit'      => self::normalize_unit( (string) ( $raw['unit'] ?? self::UNIT_DAYS ) ),
			'direction' => self::normalize_direction( (string) ( $raw['direction'] ?? self::DIRECTION_FUTURE ) ),
		);
	}

	private static function normalize_unit( string $unit ): string {
		$allowed = array( self::UNIT_DAYS, self::UNIT_WEEKS, self::UNIT_MONTHS, self::UNIT_YEARS );
		return in_array( $unit, $allowed, true ) ? $unit : self::UNIT_DAYS;
	}

	private static function normalize_direction( string $direction ): string {
		return ( self::DIRECTION_PAST === $direction ) ? self::DIRECTION_PAST : self::DIRECTION_FUTURE;
	}

	private static function build_interval( int $amount, string $unit ): ?string {
		switch ( $unit ) {
			case self::UNIT_WEEKS:
				return $amount . ' weeks';
			case self::UNIT_MONTHS:
				return $amount . ' months';
			case self::UNIT_YEARS:
				return $amount . ' years';
			case self::UNIT_DAYS:
			default:
				return $amount . ' days';
		}
	}

	/**
	 * @param array<string, mixed> $field Field configuration.
	 */
	private static function invalid_date_message( array $field ): string {
		return sprintf(
			/* translators: %s: field label. */
			__( 'Please choose a valid date for %s.', 'we-formkit' ),
			(string) ( $field['label'] ?? '' )
		);
	}
}
