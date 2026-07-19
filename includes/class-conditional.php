<?php
/**
 * Conditional field evaluation.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates show_when rules.
 */
final class Conditional {

	/**
	 * Whether a section or field should be visible given current values.
	 *
	 * @param array<string, mixed>|null $rule   Rule definition.
	 * @param array<string, mixed>      $values Submitted or current values.
	 * @return bool
	 */
	public static function is_visible( $rule, array $values ) {
		if ( empty( $rule ) || ! is_array( $rule ) ) {
			return true;
		}

		$field = isset( $rule['field'] ) ? (string) $rule['field'] : '';
		$op    = isset( $rule['op'] ) ? (string) $rule['op'] : 'equals';
		$value = array_key_exists( 'value', $rule ) ? $rule['value'] : null;

		if ( '' === $field ) {
			return true;
		}

		$current = array_key_exists( $field, $values ) ? $values[ $field ] : null;

		switch ( $op ) {
			case 'equals':
				return self::normalize( $current ) === self::normalize( $value );
			case 'not_equals':
				return self::normalize( $current ) !== self::normalize( $value );
			case 'contains':
				$needle = self::normalize( $value );
				if ( is_array( $current ) ) {
					foreach ( $current as $item ) {
						if ( self::normalize( $item ) === $needle ) {
							return true;
						}
					}
					return false;
				}
				return false !== strpos( self::normalize( $current ), $needle );
			case 'is_checked':
				return self::is_truthy( $current );
			case 'is_not_empty':
				if ( is_array( $current ) ) {
					return count(
						array_filter(
							$current,
							static function ( $item ) {
								return '' !== self::normalize( $item );
							}
						)
					) > 0;
				}
				return '' !== self::normalize( $current );
			default:
				return true;
		}
	}

	/**
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function normalize( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_array( $value ) ) {
			return implode( ',', array_map( array( __CLASS__, 'normalize' ), $value ) );
		}
		return trim( (string) $value );
	}

	/**
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function is_truthy( $value ) {
		if ( is_array( $value ) ) {
			return count( $value ) > 0;
		}
		$normalized = strtolower( self::normalize( $value ) );
		return in_array( $normalized, array( '1', 'yes', 'true', 'on' ), true );
	}
}
