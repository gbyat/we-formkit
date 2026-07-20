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
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );

		if ( isset( $field['type_options']['options'] ) ) {
			$field['type_options']['options'] = $this->normalize_options_list( $field['type_options']['options'] );
		}

		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		$min  = isset( $opts['min_selected'] ) ? max( 0, (int) $opts['min_selected'] ) : 0;
		$max  = isset( $opts['max_selected'] ) ? max( 0, (int) $opts['max_selected'] ) : 0;
		if ( $max > 0 && $min > $max ) {
			$min = $max;
		}
		$field['type_options']['min_selected'] = $min;
		$field['type_options']['max_selected'] = $max;

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

	public function sanitize( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid = $this->get_valid_option_values( $field );
		$out   = array();

		foreach ( $value as $item ) {
			$item = sanitize_key( (string) $item );
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

		$valid = $this->get_valid_option_values( $field );

		foreach ( $selected as $item ) {
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
			$parts[] = esc_html( $labels[ $item ] ?? (string) $item );
		}

		return implode( ', ', $parts );
	}
}
