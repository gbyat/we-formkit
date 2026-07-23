<?php
/**
 * Prefill form fields from URL query args (and shortcode/block attributes).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps query parameters / embed attributes onto field defaults.
 */
final class Url_Prefill {

	/**
	 * Field types that accept URL / attribute prefill.
	 *
	 * @return list<string>
	 */
	public static function supported_types(): array {
		return array(
			'text',
			'email',
			'tel',
			'url',
			'textarea',
			'number',
			'select',
			'radio',
			'radio_image',
			'checkbox',
			'checkboxes',
			'consent',
			'date',
			'time',
			'datetime',
			'hidden',
		);
	}

	/**
	 * Whether this field may be filled from the URL / embed attributes.
	 *
	 * @param array<string, mixed> $field Field config.
	 * @return bool
	 */
	public static function is_allowed( array $field ): bool {
		$type = sanitize_key( (string) ( $field['type'] ?? '' ) );
		if ( ! in_array( $type, self::supported_types(), true ) ) {
			return false;
		}

		// Explicit opt-out (default: allowed).
		if ( array_key_exists( 'allow_url_prefill', $field ) && empty( $field['allow_url_prefill'] ) ) {
			return false;
		}

		return '' !== self::param_name( $field );
	}

	/**
	 * Query parameter name for a field (Field ID, or optional prefill_param).
	 *
	 * @param array<string, mixed> $field Field config.
	 * @return string
	 */
	public static function param_name( array $field ): string {
		$custom = isset( $field['prefill_param'] ) ? sanitize_key( (string) $field['prefill_param'] ) : '';
		if ( '' !== $custom ) {
			return $custom;
		}

		return sanitize_key( (string) ( $field['id'] ?? '' ) );
	}

	/**
	 * Read prefill map from the current request query string.
	 *
	 * @return array<string, string> Param name => raw value (checkboxes may be comma-separated).
	 */
	public static function from_query(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only form prefill.
		if ( empty( $_GET ) || ! is_array( $_GET ) ) {
			return array();
		}

		$out = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( $_GET as $key => $value ) {
			$param = sanitize_key( (string) $key );
			if ( '' === $param || self::is_reserved_param( $param ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$parts = array();
				foreach ( $value as $item ) {
					if ( ! is_scalar( $item ) ) {
						continue;
					}
					$parts[] = sanitize_text_field( wp_unslash( (string) $item ) );
				}
				$joined = implode(
					',',
					array_filter(
						$parts,
						static function ( $part ) {
							return '' !== $part;
						}
					)
				);
				if ( '' !== $joined ) {
					$out[ $param ] = $joined;
				}
				continue;
			}

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$raw = sanitize_text_field( wp_unslash( (string) $value ) );
			if ( '' === $raw ) {
				continue;
			}
			$out[ $param ] = $raw;
		}

		return $out;
	}

	/**
	 * Parse shortcode/block prefill attribute.
	 *
	 * Formats:
	 * - `anliegen:angebot,email:a@b.c`
	 * - `anliegen=angebot&email=a@b.c`
	 *
	 * @param mixed $raw Attribute string.
	 * @return array<string, string>
	 */
	public static function parse_attr( $raw ): array {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}

		$out = array();

		if ( false !== strpos( $raw, '=' ) && false === strpos( $raw, ':' ) ) {
			$pairs = explode( '&', $raw );
			foreach ( $pairs as $pair ) {
				$pair = trim( $pair );
				if ( '' === $pair || false === strpos( $pair, '=' ) ) {
					continue;
				}
				list( $key, $val ) = array_pad( explode( '=', $pair, 2 ), 2, '' );
				$param             = sanitize_key( rawurldecode( $key ) );
				$val               = sanitize_text_field( rawurldecode( $val ) );
				if ( '' === $param || '' === $val || self::is_reserved_param( $param ) ) {
					continue;
				}
				$out[ $param ] = $val;
			}
			return $out;
		}

		$chunks = preg_split( '/\s*,\s*/', $raw );
		if ( ! is_array( $chunks ) ) {
			return array();
		}
		foreach ( $chunks as $chunk ) {
			$chunk = trim( (string) $chunk );
			if ( '' === $chunk || false === strpos( $chunk, ':' ) ) {
				continue;
			}
			list( $key, $val ) = array_pad( explode( ':', $chunk, 2 ), 2, '' );
			$param             = sanitize_key( $key );
			$val               = sanitize_text_field( $val );
			if ( '' === $param || '' === $val || self::is_reserved_param( $param ) ) {
				continue;
			}
			$out[ $param ] = $val;
		}

		return $out;
	}

	/**
	 * Merge maps; later entries win.
	 *
	 * @param array<string, string> ...$maps Prefill maps.
	 * @return array<string, string>
	 */
	public static function merge( array ...$maps ): array {
		$out = array();
		foreach ( $maps as $map ) {
			foreach ( $map as $key => $value ) {
				$param = sanitize_key( (string) $key );
				$val   = sanitize_text_field( (string) $value );
				if ( '' === $param || '' === $val || self::is_reserved_param( $param ) ) {
					continue;
				}
				$out[ $param ] = $val;
			}
		}
		return $out;
	}

	/**
	 * Apply a prefill map onto a field config (mutates default_value / hidden default).
	 *
	 * @param array<string, mixed>  $field Field config.
	 * @param array<string, string> $map   Prefill map.
	 * @return array<string, mixed>
	 */
	public static function apply( array $field, array $map ): array {
		if ( empty( $map ) || ! self::is_allowed( $field ) ) {
			return $field;
		}

		$param = self::param_name( $field );
		if ( '' === $param || ! isset( $map[ $param ] ) ) {
			return $field;
		}

		$raw  = (string) $map[ $param ];
		$type = sanitize_key( (string) ( $field['type'] ?? '' ) );

		$registry = Plugin::instance()->field_registry();
		$type_obj = $registry ? $registry->get( $type ) : null;

		if ( 'checkboxes' === $type ) {
			$parts = preg_split( '/\s*,\s*/', $raw );
			$parts = is_array( $parts ) ? $parts : array( $raw );
			$parts = array_values(
				array_filter(
					array_map(
						static function ( $part ) {
							return sanitize_key( (string) $part );
						},
						$parts
					)
				)
			);
			if ( empty( $parts ) || null === $type_obj ) {
				return $field;
			}
			$valid = $type_obj->sanitize( $parts, $field );
			if ( ! is_array( $valid ) || empty( $valid ) ) {
				return $field;
			}
			$field['default_value'] = implode( ',', $valid );
			return $field;
		}

		if ( in_array( $type, array( 'checkbox', 'consent' ), true ) ) {
			$truthy                 = in_array( strtolower( $raw ), array( '1', 'true', 'yes', 'on', 'checked' ), true );
			$field['default_value'] = $truthy ? '1' : '';
			return $field;
		}

		if ( 'hidden' === $type ) {
			if ( null !== $type_obj ) {
				$sanitized = $type_obj->sanitize( $raw, $field );
				if ( is_string( $sanitized ) && '' !== $sanitized ) {
					$field['type_options']['default_value'] = $sanitized;
				}
			} else {
				$field['type_options']['default_value'] = sanitize_text_field( $raw );
			}
			return $field;
		}

		if ( null !== $type_obj ) {
			$sanitized = $type_obj->sanitize( $raw, $field );
			if ( ! is_string( $sanitized ) && ! is_numeric( $sanitized ) ) {
				return $field;
			}
			$sanitized = (string) $sanitized;
			if ( '' === $sanitized ) {
				return $field;
			}
			$validated = $type_obj->validate( $sanitized, $field );
			if ( is_wp_error( $validated ) ) {
				return $field;
			}
			$field['default_value'] = $sanitized;
			return $field;
		}

		$field['default_value'] = sanitize_text_field( $raw );
		return $field;
	}

	/**
	 * Core / Formkit query keys that must never map to fields.
	 *
	 * @param string $param Param name.
	 * @return bool
	 */
	public static function is_reserved_param( string $param ): bool {
		if ( 0 === strpos( $param, 'wek_' ) ) {
			return true;
		}

		$reserved = array(
			'form_id',
			'token',
			'website_url',
			'page_id',
			'p',
			'page',
			'paged',
			'preview',
			'preview_id',
			'preview_nonce',
			'doing_wp_cron',
			'rest_route',
		);

		return in_array( $param, $reserved, true );
	}
}
