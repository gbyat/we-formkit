<?php
/**
 * Repeater field type — clonable group of nested fields.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

use Webentwicklerin\WeFormkit\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repeatable field group (e.g. name + URL rows).
 */
class Repeater_Field extends Abstract_Field_Type {

	public const MAX_ITEMS_CAP = 50;

	/**
	 * Nested field types allowed inside a repeater row.
	 *
	 * @return array<int, string>
	 */
	public static function allowed_item_types(): array {
		/**
		 * Filter which field types may appear inside a repeater group.
		 *
		 * @param array<int, string> $types Type ids.
		 */
		return apply_filters(
			'we_formkit_repeater_item_types',
			array(
				'text',
				'email',
				'tel',
				'url',
				'number',
				'textarea',
				'select',
				'date',
				'time',
				'datetime',
			)
		);
	}

	public function get_type(): string {
		return 'repeater';
	}

	public function get_label(): string {
		return __( 'Repeater', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'min_items'        => array(
				'label'   => __( 'Minimum rows', 'we-formkit' ),
				'type'    => 'number',
				'default' => 1,
			),
			'max_items'        => array(
				'label'   => __( 'Maximum rows', 'we-formkit' ),
				'type'    => 'number',
				'default' => 5,
			),
			'add_button_label' => array(
				'label'   => __( 'Add row button label', 'we-formkit' ),
				'type'    => 'text',
				'default' => '',
			),
			'fields'           => array(
				'label'       => __( 'Fields in each row', 'we-formkit' ),
				'type'        => 'repeater_fields',
				'description' => __( 'Define the field group that is cloned for each row.', 'we-formkit' ),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );
		$opts  = $field['type_options'];

		$min = isset( $opts['min_items'] ) ? (int) $opts['min_items'] : 1;
		$max = isset( $opts['max_items'] ) ? (int) $opts['max_items'] : 5;
		$min = max( 0, min( self::MAX_ITEMS_CAP, $min ) );
		$max = max( 1, min( self::MAX_ITEMS_CAP, $max ) );
		if ( $min > $max ) {
			$min = $max;
		}

		$field['type_options']['min_items']        = $min;
		$field['type_options']['max_items']        = $max;
		$field['type_options']['add_button_label'] = isset( $opts['add_button_label'] )
			? sanitize_text_field( (string) $opts['add_button_label'] )
			: '';
		$field['type_options']['fields']           = $this->normalize_item_fields( $opts['fields'] ?? array() );

		return $field;
	}

	/**
	 * @param mixed $raw Raw nested fields.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_item_fields( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$registry = Plugin::instance()->field_registry();
		$allowed  = self::allowed_item_types();
		$seen     = array();
		$out      = array();

		foreach ( $raw as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$type_id = sanitize_key( (string) ( $child['type'] ?? 'text' ) );
			if ( ! in_array( $type_id, $allowed, true ) ) {
				$type_id = 'text';
			}

			$id = sanitize_key( (string) ( $child['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;

			$child['type'] = $type_id;
			$child['id']   = $id;

			$type = $registry ? $registry->get( $type_id ) : null;
			if ( null !== $type ) {
				$child = $type->normalize_config( $child );
			}

			// Nested fields never carry their own show_when / width layout in v1.
			$child['show_when'] = array();
			$child['width']     = 'full';

			$out[] = $child;
		}

		return $out;
	}

	/**
	 * Nested field configs for this repeater.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_item_fields( array $field ): array {
		$fields = $field['type_options']['fields'] ?? array();
		return is_array( $fields ) ? $fields : array();
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$item_fields = $this->get_item_fields( $field );
		if ( empty( $item_fields ) ) {
			return array();
		}

		$registry = Plugin::instance()->field_registry();
		$max      = isset( $field['type_options']['max_items'] ) ? (int) $field['type_options']['max_items'] : 5;
		$max      = max( 1, min( self::MAX_ITEMS_CAP, $max ) );
		$out      = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( count( $out ) >= $max ) {
				break;
			}

			$clean_row = array();
			$has_value = false;

			foreach ( $item_fields as $child ) {
				$cid = (string) ( $child['id'] ?? '' );
				if ( '' === $cid ) {
					continue;
				}

				$type = $registry ? $registry->get( (string) ( $child['type'] ?? 'text' ) ) : null;
				$raw  = array_key_exists( $cid, $row ) ? $row[ $cid ] : null;

				if ( null === $type || ! $type->stores_value() ) {
					$clean_row[ $cid ] = is_string( $raw ) ? sanitize_text_field( $raw ) : '';
				} else {
					$clean_row[ $cid ] = $type->sanitize( $raw, $child );
				}

				if ( null !== $type && ! $type->is_empty_value( $clean_row[ $cid ] ) ) {
					$has_value = true;
				} elseif ( null === $type && '' !== (string) $clean_row[ $cid ] ) {
					$has_value = true;
				}
			}

			if ( $has_value ) {
				$out[] = $clean_row;
			}
		}

		return $out;
	}

	public function validate( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'we_formkit_repeater_invalid',
				$this->invalid_value_message( $field )
			);
		}

		$min = isset( $field['type_options']['min_items'] ) ? (int) $field['type_options']['min_items'] : 0;
		$max = isset( $field['type_options']['max_items'] ) ? (int) $field['type_options']['max_items'] : 5;
		$min = max( 0, min( self::MAX_ITEMS_CAP, $min ) );
		$max = max( 1, min( self::MAX_ITEMS_CAP, $max ) );

		$count = count( $value );

		if ( $count > $max ) {
			return new \WP_Error(
				'we_formkit_repeater_too_many',
				sprintf(
					/* translators: 1: field label, 2: max rows. */
					__( '%1$s allows at most %2$d row(s).', 'we-formkit' ),
					(string) ( $field['label'] ?? '' ),
					$max
				)
			);
		}

		if ( ! empty( $field['required'] ) && $count < max( 1, $min ) ) {
			return new \WP_Error(
				'we_formkit_repeater_too_few',
				sprintf(
					/* translators: 1: field label, 2: min rows. */
					__( '%1$s requires at least %2$d row(s).', 'we-formkit' ),
					(string) ( $field['label'] ?? '' ),
					max( 1, $min )
				)
			);
		}

		if ( empty( $field['required'] ) && $min > 0 && $count > 0 && $count < $min ) {
			return new \WP_Error(
				'we_formkit_repeater_too_few',
				sprintf(
					/* translators: 1: field label, 2: min rows. */
					__( '%1$s requires at least %2$d row(s).', 'we-formkit' ),
					(string) ( $field['label'] ?? '' ),
					$min
				)
			);
		}

		$item_fields = $this->get_item_fields( $field );
		$registry    = Plugin::instance()->field_registry();

		foreach ( $value as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			foreach ( $item_fields as $child ) {
				$cid  = (string) ( $child['id'] ?? '' );
				$type = $registry ? $registry->get( (string) ( $child['type'] ?? 'text' ) ) : null;
				if ( '' === $cid || null === $type || ! $type->stores_value() ) {
					continue;
				}

				$cell = array_key_exists( $cid, $row ) ? $row[ $cid ] : null;

				if ( ! empty( $child['required'] ) && $type->is_empty_value( $cell ) ) {
					return new \WP_Error(
						'we_formkit_repeater_item_required',
						sprintf(
							/* translators: 1: parent label, 2: row number (1-based), 3: nested field label. */
							__( '%1$s — row %2$d: %3$s is required.', 'we-formkit' ),
							(string) ( $field['label'] ?? '' ),
							(int) $index + 1,
							(string) ( $child['label'] ?? $cid )
						)
					);
				}

				if ( $type->is_empty_value( $cell ) ) {
					continue;
				}

				$valid = $type->validate( $cell, $child );
				if ( is_wp_error( $valid ) ) {
					return new \WP_Error(
						'we_formkit_repeater_item_invalid',
						sprintf(
							/* translators: 1: parent label, 2: row number (1-based), 3: nested error. */
							__( '%1$s — row %2$d: %3$s', 'we-formkit' ),
							(string) ( $field['label'] ?? '' ),
							(int) $index + 1,
							$valid->get_error_message()
						)
					);
				}
			}
		}

		return true;
	}

	public function is_empty_value( $value ): bool {
		return ! is_array( $value ) || empty( $value );
	}

	public function format_for_display( $value, array $field ): string {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return '';
		}

		$item_fields = $this->get_item_fields( $field );
		$registry    = Plugin::instance()->field_registry();
		$blocks      = array();

		foreach ( $value as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$parts = array();
			foreach ( $item_fields as $child ) {
				$cid  = (string) ( $child['id'] ?? '' );
				$type = $registry ? $registry->get( (string) ( $child['type'] ?? 'text' ) ) : null;
				if ( '' === $cid ) {
					continue;
				}

				$cell = array_key_exists( $cid, $row ) ? $row[ $cid ] : '';
				if ( null !== $type ) {
					if ( $type->is_empty_value( $cell ) ) {
						continue;
					}
					$formatted = $type->format_for_display( $cell, $child );
				} else {
					$formatted = is_scalar( $cell ) ? esc_html( (string) $cell ) : '';
				}

				if ( '' === $formatted ) {
					continue;
				}

				$label   = (string) ( $child['label'] ?? $cid );
				$parts[] = '<strong>' . esc_html( $label ) . ':</strong> ' . $formatted;
			}

			if ( empty( $parts ) ) {
				continue;
			}

			$blocks[] = sprintf(
				'<div class="we-formkit-repeater-row"><span class="we-formkit-repeater-row__index">%s</span> %s</div>',
				esc_html(
					sprintf(
						/* translators: %d: row number (1-based). */
						__( 'Row %d', 'we-formkit' ),
						(int) $index + 1
					)
				),
				implode( ' · ', $parts )
			);
		}

		return implode( '', $blocks );
	}

	public function render_attributes( array $field ): array {
		return array();
	}
}
