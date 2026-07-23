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
 *
 * Supports legacy single rule `{ field, op, value }` and the CPO-style
 * container `{ relation: AND|OR, rules: [ { field, op, value }, ... ] }`.
 */
final class Conditional {

	/**
	 * Whether a section or field should be visible given current values.
	 *
	 * @param array<string, mixed>|null $rule   Rule definition or conditions container.
	 * @param array<string, mixed>      $values Submitted or current values.
	 * @return bool
	 */
	public static function is_visible( $rule, array $values ) {
		if ( empty( $rule ) || ! is_array( $rule ) ) {
			return true;
		}

		// Legacy single rule.
		if ( isset( $rule['field'] ) && ! isset( $rule['rules'] ) ) {
			if ( ! self::is_complete_rule( $rule ) ) {
				return true;
			}
			return self::match_one( $rule, $values );
		}

		$rules = isset( $rule['rules'] ) && is_array( $rule['rules'] ) ? $rule['rules'] : array();
		$rules = array_values(
			array_filter(
				$rules,
				static function ( $one ) {
					return is_array( $one ) && self::is_complete_rule( $one );
				}
			)
		);
		if ( empty( $rules ) ) {
			return true;
		}

		$relation = isset( $rule['relation'] ) && 'OR' === strtoupper( (string) $rule['relation'] ) ? 'OR' : 'AND';

		foreach ( $rules as $one ) {
			$ok = self::match_one( $one, $values );
			if ( 'OR' === $relation && $ok ) {
				return true;
			}
			if ( 'AND' === $relation && ! $ok ) {
				return false;
			}
		}

		return 'AND' === $relation;
	}

	/**
	 * Whether a rule is ready to evaluate (value-required ops need a non-empty value).
	 *
	 * @param array<string, mixed> $rule Single rule.
	 * @return bool
	 */
	private static function is_complete_rule( array $rule ) {
		$field = isset( $rule['field'] ) ? trim( (string) $rule['field'] ) : '';
		if ( '' === $field ) {
			return false;
		}
		$op = isset( $rule['op'] ) ? (string) $rule['op'] : 'equals';
		if ( in_array( $op, array( 'is_checked', 'is_not_checked', 'is_empty', 'is_not_empty' ), true ) ) {
			return true;
		}
		$value = array_key_exists( 'value', $rule ) ? $rule['value'] : '';
		return '' !== trim( (string) ( null === $value ? '' : $value ) );
	}

	/**
	 * Evaluate one rule.
	 *
	 * @param array<string, mixed> $rule   Single rule.
	 * @param array<string, mixed> $values Values.
	 * @return bool
	 */
	private static function match_one( array $rule, array $values ) {
		$field = isset( $rule['field'] ) ? (string) $rule['field'] : '';
		$op    = isset( $rule['op'] ) ? (string) $rule['op'] : 'equals';
		$value = array_key_exists( 'value', $rule ) ? $rule['value'] : null;

		if ( '' === $field ) {
			return true;
		}

		$current = self::resolve_current( $field, $values );

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
			case 'is_not_checked':
				return ! self::is_truthy( $current );
			case 'is_not_empty':
				if ( is_array( $current ) ) {
					return count(
						array_filter(
							$current,
							static function ( $item ) {
								if ( is_array( $item ) ) {
									return ! empty( $item );
								}
								return '' !== self::normalize( $item );
							}
						)
					) > 0;
				}
				return '' !== self::normalize( $current );
			case 'is_empty':
				if ( is_array( $current ) ) {
					return count(
						array_filter(
							$current,
							static function ( $item ) {
								if ( is_array( $item ) ) {
									return ! empty( $item );
								}
								return '' !== self::normalize( $item );
							}
						)
					) === 0;
				}
				return '' === self::normalize( $current );
			default:
				return true;
		}
	}

	/**
	 * Resolve a field key, including matrix row paths (`matrix_id.row_id`).
	 *
	 * @param string               $field  Field id or `id.row`.
	 * @param array<string, mixed> $values Values.
	 * @return mixed
	 */
	private static function resolve_current( $field, array $values ) {
		$field = (string) $field;
		if ( '' === $field ) {
			return null;
		}

		if ( false === strpos( $field, '.' ) ) {
			if ( array_key_exists( $field, $values ) ) {
				return $values[ $field ];
			}
			// Recover refs corrupted when sanitize_key() stripped the matrix row dot.
			$recovered = self::recover_matrix_ref( $field, $values );
			if ( null === $recovered ) {
				return null;
			}
			$field = $recovered;
		}

		$parts = explode( '.', $field, 2 );
		$root  = (string) $parts[0];
		$row   = isset( $parts[1] ) ? (string) $parts[1] : '';
		if ( '' === $root || '' === $row || ! isset( $values[ $root ] ) || ! is_array( $values[ $root ] ) ) {
			return '';
		}

		$matrix = $values[ $root ];
		if ( ! isset( $matrix[ $row ] ) || ! is_array( $matrix[ $row ] ) ) {
			return '';
		}

		return self::matrix_row_signal( $matrix[ $row ] );
	}

	/**
	 * Whether a matrix row counts as selected / answered for conditionals.
	 *
	 * @param array<string, mixed> $row_val Row value map.
	 * @return string '1' or ''.
	 */
	private static function matrix_row_signal( array $row_val ) {
		if ( array_key_exists( 'on', $row_val ) ) {
			return ! empty( $row_val['on'] ) ? '1' : '';
		}

		foreach ( $row_val as $key => $cell ) {
			if ( 'on' === $key ) {
				continue;
			}
			if ( is_bool( $cell ) && $cell ) {
				return '1';
			}
			if ( ! is_bool( $cell ) && '' !== trim( (string) $cell ) ) {
				return '1';
			}
		}

		return '';
	}

	/**
	 * Rebuild `field.row` when an older save mashed them via sanitize_key().
	 *
	 * Prefers the longest matching field id among current values.
	 *
	 * @param string               $mashed Corrupted ref without a dot.
	 * @param array<string, mixed> $values Values.
	 * @return string|null Recovered `id.row` or null.
	 */
	private static function recover_matrix_ref( $mashed, array $values ) {
		$mashed   = (string) $mashed;
		$best     = null;
		$best_len = 0;
		foreach ( $values as $id => $val ) {
			if ( ! is_string( $id ) || ! is_array( $val ) ) {
				continue;
			}
			// Skip sequential lists (e.g. checkboxes); matrix values are row-keyed maps.
			if ( array_values( $val ) === $val ) {
				continue;
			}
			$id_len = strlen( $id );
			if ( $id_len < 1 || $id_len <= $best_len || 0 !== strpos( $mashed, $id ) ) {
				continue;
			}
			$row = substr( $mashed, $id_len );
			if ( '' === $row || ! array_key_exists( $row, $val ) ) {
				continue;
			}
			$best     = $id . '.' . $row;
			$best_len = $id_len;
		}
		return $best;
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
