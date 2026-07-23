<?php
/**
 * Checkboxes field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multiple-choice checkbox group.
 */
class Checkboxes_Field extends Abstract_Field_Type {

	public const OTHER_TOKEN = '__other__';

	public const OTHER_PREFIX = 'other:';

	public function get_type(): string {
		return 'checkboxes';
	}

	public function get_label(): string {
		return __( 'Checkboxes', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'min_selected' => array(
				'type'        => 'integer',
				'label'       => __( 'Minimum selections', 'we-formkit' ),
				'description' => __( '0 = no minimum (unless the field is required).', 'we-formkit' ),
				'default'     => 0,
			),
			'max_selected' => array(
				'type'        => 'integer',
				'label'       => __( 'Maximum selections', 'we-formkit' ),
				'description' => __( '0 = unlimited.', 'we-formkit' ),
				'default'     => 0,
			),
			'allow_other'  => array(
				'type'        => 'boolean',
				'label'       => __( 'Allow custom options', 'we-formkit' ),
				'description' => __( 'Visitors can add their own options via a button (like matrix custom rows). New items start checked and can be removed.', 'we-formkit' ),
				'default'     => false,
			),
			'other_label'  => array(
				'type'    => 'string',
				'label'   => __( 'Add button label', 'we-formkit' ),
				'default' => 'Add other',
			),
			'max_other'    => array(
				'type'        => 'integer',
				'label'       => __( 'Max custom options', 'we-formkit' ),
				'description' => __( '1–5. How many visitor-added options are allowed.', 'we-formkit' ),
				'default'     => 2,
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		if ( isset( $field['type_options']['options'] ) ) {
			$field['type_options']['options'] = $this->normalize_options_list( $field['type_options']['options'] );
		}
		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			$field['options'] = $this->normalize_options_list( $field['options'] );
			// Avoid colliding with the reserved Other token.
			foreach ( $field['options'] as $i => $opt ) {
				if ( ! is_array( $opt ) ) {
					continue;
				}
				if ( self::OTHER_TOKEN === ( $opt['value'] ?? '' ) || self::is_other_entry( (string) ( $opt['value'] ?? '' ) ) ) {
					$field['options'][ $i ]['value'] = 'option_' . ( $i + 1 );
				}
			}
		}

		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		$min  = isset( $opts['min_selected'] ) ? max( 0, (int) $opts['min_selected'] ) : 0;
		$max  = isset( $opts['max_selected'] ) ? max( 0, (int) $opts['max_selected'] ) : 0;
		if ( $max > 0 && $min > $max ) {
			$min = $max;
		}
		$field['type_options']['min_selected'] = $min;
		$field['type_options']['max_selected'] = $max;
		$field['type_options']['allow_other']  = ! empty( $opts['allow_other'] );
		$other_label                           = isset( $opts['other_label'] ) ? sanitize_text_field( (string) $opts['other_label'] ) : '';
		$field['type_options']['other_label']  = '' !== $other_label ? $other_label : __( 'Add other', 'we-formkit' );
		$field['type_options']['max_other']    = self::normalize_max_other( $opts['max_other'] ?? 2 );

		return $field;
	}

	/**
	 * Selection limits from field config.
	 *
	 * @param array<string, mixed> $field Field configuration.
	 * @return array{min:int,max:int}
	 */
	public static function selection_limits( array $field ): array {
		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		$min  = isset( $opts['min_selected'] ) ? max( 0, (int) $opts['min_selected'] ) : 0;
		$max  = isset( $opts['max_selected'] ) ? max( 0, (int) $opts['max_selected'] ) : 0;
		if ( $max > 0 && $min > $max ) {
			$min = $max;
		}
		return array(
			'min' => $min,
			'max' => $max,
		);
	}

	/**
	 * Whether Other free-text is enabled.
	 *
	 * @param array<string, mixed> $field Field.
	 */
	public static function allows_other( array $field ): bool {
		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		return ! empty( $opts['allow_other'] );
	}

	/**
	 * Label for the “add custom option” button.
	 *
	 * @param array<string, mixed> $field Field.
	 */
	public static function other_label( array $field ): string {
		$opts  = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		$label = isset( $opts['other_label'] ) ? sanitize_text_field( (string) $opts['other_label'] ) : '';
		return '' !== $label ? $label : __( 'Add other', 'we-formkit' );
	}

	/**
	 * Max visitor-added custom options (1–5).
	 *
	 * @param mixed $raw Raw value.
	 */
	public static function normalize_max_other( $raw ): int {
		$n = (int) $raw;
		if ( $n < 1 ) {
			$n = 2;
		}
		return min( 5, $n );
	}

	/**
	 * Max custom options for a field.
	 *
	 * @param array<string, mixed> $field Field.
	 */
	public static function max_other( array $field ): int {
		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		return self::normalize_max_other( $opts['max_other'] ?? 2 );
	}

	/**
	 * Whether a stored item is an Other free-text entry (or empty Other marker).
	 *
	 * @param string $item Stored item.
	 */
	public static function is_other_entry( $item ): bool {
		$item = (string) $item;
		return self::OTHER_TOKEN === $item || 0 === strpos( $item, self::OTHER_PREFIX );
	}

	/**
	 * Free text from a stored Other entry.
	 *
	 * @param string $item Stored item.
	 */
	public static function other_text_from_entry( $item ): string {
		$item = (string) $item;
		if ( 0 === strpos( $item, self::OTHER_PREFIX ) ) {
			return (string) substr( $item, strlen( self::OTHER_PREFIX ) );
		}
		return '';
	}

	/**
	 * Build stored Other entry from free text.
	 *
	 * @param string $text Free text.
	 */
	public static function other_entry_from_text( $text ): string {
		$text = sanitize_text_field( (string) $text );
		return '' === $text ? self::OTHER_TOKEN : self::OTHER_PREFIX . $text;
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid       = $this->get_valid_option_values( $field );
		$allow_other = self::allows_other( $field );
		$max_other   = self::max_other( $field );
		$out         = array();
		$other_count = 0;

		foreach ( $value as $item ) {
			$item = (string) $item;
			if ( $allow_other && self::is_other_entry( $item ) ) {
				if ( $other_count >= $max_other ) {
					continue;
				}
				$text = self::other_text_from_entry( $item );
				if ( '' === $text ) {
					continue;
				}
				$entry = self::OTHER_PREFIX . sanitize_text_field( $text );
				if ( in_array( $entry, $out, true ) ) {
					continue;
				}
				$out[] = $entry;
				++$other_count;
				continue;
			}

			$item = sanitize_key( $item );
			if ( '' === $item || ! in_array( $item, $valid, true ) ) {
				continue;
			}

			$out[] = $item;
		}

		return array_values( array_unique( $out ) );
	}

	public function validate( $value, array $field ) {
		$selected = is_array( $value ) ? array_values( $value ) : array();
		$count    = count( $selected );
		$limits   = self::selection_limits( $field );
		$min      = $limits['min'];
		$max      = $limits['max'];

		if ( $min > 0 && $count < $min ) {
			return new \WP_Error(
				'we_formkit_checkboxes_too_few',
				sprintf(
					/* translators: 1: field label, 2: minimum number of selections. */
					__( 'Please select at least %2$d option(s) for %1$s.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' ),
					$min
				)
			);
		}

		if ( $max > 0 && $count > $max ) {
			return new \WP_Error(
				'we_formkit_checkboxes_too_many',
				sprintf(
					/* translators: 1: field label, 2: maximum number of selections. */
					__( 'Please select at most %2$d option(s) for %1$s.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' ),
					$max
				)
			);
		}

		if ( 0 === $count ) {
			return true;
		}

		$valid       = $this->get_valid_option_values( $field );
		$allow_other = self::allows_other( $field );
		$max_other   = self::max_other( $field );
		$other_count = 0;

		foreach ( $selected as $item ) {
			$item = (string) $item;
			if ( $allow_other && self::is_other_entry( $item ) ) {
				++$other_count;
				if ( $other_count > $max_other ) {
					return new \WP_Error(
						'we_formkit_checkboxes_too_many_other',
						sprintf(
							/* translators: 1: field label, 2: max custom options. */
							__( '%1$s: please add at most %2$d custom option(s).', 'we-formkit' ),
							(string) ( $field['label'] ?? '' ),
							$max_other
						)
					);
				}
				if ( '' === self::other_text_from_entry( $item ) ) {
					return new \WP_Error(
						'we_formkit_checkboxes_other_label',
						sprintf(
							/* translators: %s: field label. */
							__( '%s: please enter text for each custom option.', 'we-formkit' ),
							(string) ( $field['label'] ?? '' )
						)
					);
				}
				continue;
			}
			if ( ! in_array( $item, $valid, true ) ) {
				return new \WP_Error(
					'we_formkit_checkboxes_invalid',
					sprintf(
						/* translators: %s: field label. */
						__( 'Please choose valid options for %s.', 'we-formkit' ),
						(string) ( $field['label'] ?? '' )
					)
				);
			}
		}

		return true;
	}

	public function format_for_display( $value, array $field ): string {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return '';
		}

		$labels = $this->get_option_label_map( $field );
		$parts  = array();

		foreach ( $value as $item ) {
			$item = (string) $item;
			if ( self::is_other_entry( $item ) ) {
				$text = self::other_text_from_entry( $item );
				if ( '' === $text ) {
					continue;
				}
				$parts[] = esc_html( $text );
				continue;
			}
			$parts[] = esc_html( $labels[ $item ] ?? $item );
		}

		return implode( ', ', $parts );
	}
}
