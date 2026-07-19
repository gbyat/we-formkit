<?php
/**
 * Form schema helpers and meta keys.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes form definitions via the field-type registry.
 */
final class Form_Schema {

	public const META_SCHEMA               = '_wek_form_schema';
	public const META_SECRET_ENABLED       = '_wek_form_secret_enabled';
	public const META_SECRET_TOKEN         = '_wek_form_secret_token';
	public const META_NOTIFY_EMAIL         = '_wek_form_notify_email';
	public const META_SLUG                 = '_wek_form_slug';
	public const META_PRIVACY_URL          = '_wek_form_privacy_url';
	public const META_CONFIRMATION_MESSAGE = '_wek_form_confirmation_message';

	public const SUB_FORM_ID = '_wek_submission_form_id';
	public const SUB_DATA    = '_wek_submission_data';
	public const SUB_NOTES   = '_wek_submission_notes';
	public const SUB_CONSENT = '_wek_submission_consent';
	public const SUB_IP_HASH = '_wek_submission_ip_hash';

	/**
	 * @param int $form_id Form post ID.
	 * @return array<string, mixed>
	 */
	public static function get( $form_id ) {
		$raw = get_post_meta( (int) $form_id, self::META_SCHEMA, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::normalize( $decoded );
			}
		}
		if ( is_array( $raw ) ) {
			return self::normalize( $raw );
		}
		return self::normalize( array() );
	}

	/**
	 * @param int                  $form_id Form post ID.
	 * @param array<string, mixed> $schema  Schema.
	 * @return bool
	 */
	public static function save( $form_id, array $schema ) {
		$normalized = self::normalize( $schema );
		$json       = wp_json_encode( $normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}
		update_post_meta( (int) $form_id, self::META_SCHEMA, $json );
		return true;
	}

	/**
	 * Normalize a form schema, routing each field through its type class.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $schema ) {
		$sections = array();
		if ( ! empty( $schema['sections'] ) && is_array( $schema['sections'] ) ) {
			foreach ( $schema['sections'] as $section ) {
				if ( ! is_array( $section ) ) {
					continue;
				}
				$fields = array();
				if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
					foreach ( $section['fields'] as $field ) {
						if ( ! is_array( $field ) || empty( $field['id'] ) ) {
							continue;
						}
						$fields[] = self::normalize_field( $field );
					}
				}
				$sections[] = array(
					'id'        => sanitize_key( (string) ( $section['id'] ?? uniqid( 'section_', false ) ) ),
					'title'     => sanitize_text_field( (string) ( $section['title'] ?? '' ) ),
					'intro'     => sanitize_textarea_field( (string) ( $section['intro'] ?? '' ) ),
					'show_when' => self::normalize_rule( $section['show_when'] ?? null ),
					'fields'    => $fields,
				);
			}
		}

		return array(
			'version'  => 1,
			'title'    => sanitize_text_field( (string) ( $schema['title'] ?? '' ) ),
			'intro'    => sanitize_textarea_field( (string) ( $schema['intro'] ?? '' ) ),
			'sections' => $sections,
		);
	}

	/**
	 * Normalize one field via the registered type class.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<string, mixed>
	 */
	private static function normalize_field( array $field ) {
		$type_id  = sanitize_key( (string) ( $field['type'] ?? 'text' ) );
		$registry = Plugin::instance()->field_registry();
		$type     = $registry ? $registry->get( $type_id ) : null;

		if ( null === $type ) {
			$type_id = 'text';
			$type    = $registry ? $registry->get( 'text' ) : null;
		}

		$field['type'] = $type_id;

		if ( null !== $type ) {
			$field = $type->normalize_config( $field );
		}

		$field['id']        = sanitize_key( (string) ( $field['id'] ?? '' ) );
		$field['width']     = self::normalize_width( $field['width'] ?? 'full' );
		$field['show_when'] = self::normalize_rule( $field['show_when'] ?? null );

		return $field;
	}

	/**
	 * @param mixed $width Width token.
	 * @return string
	 */
	private static function normalize_width( $width ) {
		$width   = sanitize_key( (string) $width );
		$allowed = array( 'full', 'half', 'third' );
		return in_array( $width, $allowed, true ) ? $width : 'full';
	}

	/**
	 * @param mixed $rule Rule.
	 * @return array<string, mixed>|null
	 */
	private static function normalize_rule( $rule ) {
		if ( ! is_array( $rule ) || empty( $rule['field'] ) ) {
			return null;
		}
		$op      = sanitize_key( (string) ( $rule['op'] ?? 'equals' ) );
		$allowed = array( 'equals', 'not_equals', 'contains', 'is_checked', 'is_not_empty' );
		if ( ! in_array( $op, $allowed, true ) ) {
			$op = 'equals';
		}
		return array(
			'field' => sanitize_key( (string) $rule['field'] ),
			'op'    => $op,
			'value' => isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
		);
	}

	/**
	 * Flatten fields by id.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields_by_id( array $schema ) {
		$map = array();
		if ( empty( $schema['sections'] ) || ! is_array( $schema['sections'] ) ) {
			return $map;
		}
		foreach ( $schema['sections'] as $section ) {
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field ) {
				if ( ! empty( $field['id'] ) ) {
					$map[ $field['id'] ] = $field;
				}
			}
		}
		return $map;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array{enabled:bool,token:string}
	 */
	public static function get_secret( $form_id ) {
		return array(
			'enabled' => (bool) get_post_meta( (int) $form_id, self::META_SECRET_ENABLED, true ),
			'token'   => (string) get_post_meta( (int) $form_id, self::META_SECRET_TOKEN, true ),
		);
	}

	/**
	 * Confirmation message shown after a successful submit.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function get_confirmation_message( $form_id ) {
		$custom = (string) get_post_meta( (int) $form_id, self::META_CONFIRMATION_MESSAGE, true );
		$custom = trim( $custom );
		if ( '' !== $custom ) {
			return $custom;
		}
		return __( 'Thank you. Your form was submitted successfully.', 'we-formkit' );
	}

	/**
	 * @param int         $form_id Form ID.
	 * @param bool        $enabled Enabled.
	 * @param string|null $token   Token or null to keep/generate.
	 * @return string Token.
	 */
	public static function set_secret( $form_id, $enabled, $token = null ) {
		update_post_meta( (int) $form_id, self::META_SECRET_ENABLED, $enabled ? 1 : 0 );
		$current = (string) get_post_meta( (int) $form_id, self::META_SECRET_TOKEN, true );
		if ( null === $token || '' === $token ) {
			if ( '' === $current ) {
				$token = wp_generate_password( 32, false, false );
			} else {
				$token = $current;
			}
		}
		update_post_meta( (int) $form_id, self::META_SECRET_TOKEN, sanitize_text_field( $token ) );
		return (string) $token;
	}

	/**
	 * @param string $slug Form slug.
	 * @return int Form ID or 0.
	 */
	public static function find_by_slug( $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return 0;
		}
		$query = new \WP_Query(
			array(
				'post_type'      => Post_Types::FORM,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_SLUG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- lookup by unique form slug.
				'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- lookup by unique form slug.
				'no_found_rows'  => true,
			)
		);
		return ! empty( $query->posts[0] ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Validate submitted values against schema + conditionals via the registry.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param array<string, mixed> $values Values.
	 * @return array{ok:bool,errors:array<string,string>,data:array<string,mixed>}
	 */
	public static function validate_submission( array $schema, array $values ) {
		$errors   = array();
		$data     = array();
		$registry = Plugin::instance()->field_registry();

		if ( empty( $schema['sections'] ) || ! is_array( $schema['sections'] ) ) {
			return array(
				'ok'     => true,
				'errors' => $errors,
				'data'   => $data,
			);
		}

		foreach ( $schema['sections'] as $section ) {
			if ( ! Conditional::is_visible( $section['show_when'] ?? null, $values ) ) {
				continue;
			}
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field ) {
				$id = isset( $field['id'] ) ? (string) $field['id'] : '';
				if ( '' === $id ) {
					continue;
				}
				if ( ! Conditional::is_visible( $field['show_when'] ?? null, $values ) ) {
					continue;
				}

				$type_id = isset( $field['type'] ) ? (string) $field['type'] : 'text';
				$type    = $registry ? $registry->get( $type_id ) : null;
				if ( null === $type || ! $type->stores_value() ) {
					continue;
				}

				$raw       = array_key_exists( $id, $values ) ? $values[ $id ] : null;
				$sanitized = $type->sanitize( $raw, $field );

				if ( ! empty( $field['required'] ) && $type->is_empty_value( $sanitized ) ) {
					/* translators: %s: field label */
					$errors[ $id ] = sprintf( __( '%s is required.', 'we-formkit' ), (string) ( $field['label'] ?? $id ) );
					continue;
				}

				if ( ! $type->is_empty_value( $sanitized ) ) {
					$valid = $type->validate( $sanitized, $field );
					if ( is_wp_error( $valid ) ) {
						$errors[ $id ] = $valid->get_error_message();
						continue;
					}
				}

				$data[ $id ] = $sanitized;
			}
		}

		return array(
			'ok'     => empty( $errors ),
			'errors' => $errors,
			'data'   => $data,
		);
	}
}
