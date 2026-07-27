<?php
/**
 * Radio image field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single-choice radio buttons with an image per option.
 */
class Radio_Image_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'radio_image';
	}

	public function get_label(): string {
		return __( 'Radio (image)', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'options' => array(
				'label'       => __( 'Options', 'we-formkit' ),
				'type'        => 'radio_image_options',
				'default'     => array(),
				'description' => __( 'Each option supports value, label, and an optional image (Media Library id or URL).', 'we-formkit' ),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field            = parent::normalize_config( $field );
		$field['options'] = $this->normalize_image_options( $field['options'] ?? array() );

		if ( ! isset( $field['type_options'] ) || ! is_array( $field['type_options'] ) ) {
			$field['type_options'] = array();
		}

		$size                                = isset( $field['type_options']['image_size'] ) ? sanitize_key( (string) $field['type_options']['image_size'] ) : 'medium';
		$field['type_options']['image_size'] = in_array( $size, array( 'thumbnail', 'medium' ), true ) ? $size : 'medium';

		$cols                             = isset( $field['type_options']['columns'] ) ? (int) $field['type_options']['columns'] : 2;
		$field['type_options']['columns'] = max( 1, min( 4, $cols ) );

		if ( isset( $field['type_options']['options'] ) ) {
			$field['type_options']['options'] = $this->normalize_image_options( $field['type_options']['options'] );
		}

		return $field;
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = sanitize_key( $value );
		if ( '' === $value ) {
			return '';
		}

		return in_array( $value, $this->get_valid_option_values( $field ), true ) ? $value : '';
	}

	public function validate( $value, array $field ) {
		if ( '' === $value ) {
			return true;
		}

		if ( ! in_array( $value, $this->get_valid_option_values( $field ), true ) ) {
			return new \WP_Error(
				'we_formkit_radio_image_invalid',
				sprintf(
					/* translators: %s: field label. */
					__( 'Please choose a valid option for %s.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' )
				)
			);
		}

		return true;
	}

	public function format_for_display( $value, array $field ): string {
		$labels = $this->get_option_label_map( $field );
		if ( is_string( $value ) && isset( $labels[ $value ] ) ) {
			return esc_html( $labels[ $value ] );
		}

		return is_string( $value ) ? esc_html( $value ) : '';
	}

	/**
	 * Normalize image option entries.
	 *
	 * @param mixed $raw Raw options.
	 *
	 * @return array<int, array{value: string, label: string, image_id: int, image_url: string}>
	 */
	protected function normalize_image_options( $raw ): array {
		if ( is_string( $raw ) ) {
			$split = preg_split( '/\r?\n/', $raw );
			$raw   = false === $split ? array() : $split;
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}

			$value = isset( $line['value'] ) ? sanitize_key( (string) $line['value'] ) : '';
			if ( '' === $value ) {
				continue;
			}

			$out[] = array(
				'value'     => $value,
				'label'     => isset( $line['label'] ) ? sanitize_text_field( (string) $line['label'] ) : $value,
				'image_id'  => isset( $line['image_id'] ) ? absint( $line['image_id'] ) : 0,
				'image_url' => isset( $line['image_url'] ) ? esc_url_raw( (string) $line['image_url'] ) : '',
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return array<int, array{value: string, label: string, image_id: int, image_url: string}>
	 */
	protected function get_field_options( array $field ): array {
		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			return $this->normalize_image_options( $field['options'] );
		}

		if ( isset( $field['type_options']['options'] ) ) {
			return $this->normalize_image_options( $field['type_options']['options'] );
		}

		return array();
	}
}
