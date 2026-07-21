<?php
/**
 * Abstract base class for all field types.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

use Webentwicklerin\WeFormkit\Validation_Messages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field-type abstraction for the form builder.
 */
abstract class Abstract_Field_Type {

	/**
	 * Type identifier (e.g. "text", "date", "upload").
	 */
	abstract public function get_type(): string;

	/**
	 * Human-readable label for the admin UI.
	 */
	abstract public function get_label(): string;

	/**
	 * Type-specific options that appear in the admin field editor.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_admin_schema(): array {
		return array();
	}

	/**
	 * Normalize type-specific keys onto the field configuration.
	 *
	 * @param array<string, mixed> $field Raw field configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function normalize_config( array $field ): array {
		$field['id']            = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
		$field['type']          = $this->get_type();
		$field['label']         = isset( $field['label'] ) ? sanitize_text_field( (string) $field['label'] ) : '';
		$field['help']          = isset( $field['help'] ) ? sanitize_text_field( (string) $field['help'] ) : '';
		$field['required']      = ! empty( $field['required'] );
		$field['placeholder']   = isset( $field['placeholder'] ) ? sanitize_text_field( (string) $field['placeholder'] ) : '';
		$field['default_value'] = isset( $field['default_value'] ) ? sanitize_text_field( (string) $field['default_value'] ) : '';
		$field['width']         = isset( $field['width'] ) ? sanitize_key( (string) $field['width'] ) : 'full';
		if ( ! in_array( $field['width'], array( 'full', 'two_thirds', 'half', 'third' ), true ) ) {
			$field['width'] = 'full';
		}

		if ( isset( $field['options'] ) ) {
			$field['options'] = $this->normalize_options_list( $field['options'] );
		}

		if ( isset( $field['show_when'] ) && is_array( $field['show_when'] ) ) {
			$field['show_when'] = $field['show_when'];
		} else {
			$field['show_when'] = array();
		}

		if ( ! isset( $field['type_options'] ) || ! is_array( $field['type_options'] ) ) {
			$field['type_options'] = array();
		}

		$field['messages'] = Validation_Messages::normalize_field_messages( $field['messages'] ?? null );

		return $field;
	}

	/**
	 * Sanitize a value coming from the client (before validation/storage).
	 *
	 * @param mixed                $value Raw value.
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return mixed
	 */
	public function sanitize( $value, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- field reserved for type overrides.
		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		return $value;
	}

	/**
	 * Validate a (sanitized) value. Return WP_Error on failure, true on success.
	 *
	 * @param mixed                $value Sanitized value.
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return true|\WP_Error
	 */
	public function validate( $value, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- defaults; types override.
		return true;
	}

	/**
	 * Determine if a value counts as "empty" for required-checks.
	 *
	 * @param mixed $value Value.
	 */
	public function is_empty_value( $value ): bool {
		if ( null === $value || '' === $value ) {
			return true;
		}

		if ( is_array( $value ) && empty( $value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Format a stored value for display.
	 *
	 * @param mixed                $value Stored value.
	 * @param array<string, mixed> $field Field configuration.
	 */
	public function format_for_display( $value, array $field ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- field reserved for type overrides.
		if ( is_scalar( $value ) ) {
			return esc_html( (string) $value );
		}

		return '';
	}

	/**
	 * HTML attributes for frontend rendering (type, min, max, etc.).
	 *
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return array<string, scalar>
	 */
	public function render_attributes( array $field ): array {
		$attrs = array(
			'type' => $this->get_type(),
		);

		$placeholder = (string) ( $field['placeholder'] ?? '' );
		if ( '' !== $placeholder ) {
			$attrs['placeholder'] = $placeholder;
		}

		if ( ! empty( $field['required'] ) ) {
			$attrs['required']      = 'required';
			$attrs['aria-required'] = 'true';
		}

		return $attrs;
	}

	/**
	 * Whether this field type persists a submitted value.
	 */
	public function stores_value(): bool {
		return true;
	}

	/**
	 * Normalize an options list to [{value, label}] shape.
	 *
	 * @param mixed $raw Raw options from config.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	protected function normalize_options_list( $raw ): array {
		if ( is_string( $raw ) ) {
			$split = preg_split( '/\r?\n/', $raw );
			$raw   = false === $split ? array() : $split;
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $line ) {
			if ( is_array( $line ) ) {
				$value = isset( $line['value'] ) ? sanitize_key( (string) $line['value'] ) : '';
				$label = isset( $line['label'] ) ? sanitize_text_field( (string) $line['label'] ) : $value;
			} else {
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}

				$parts = array_map( 'trim', explode( '|', $line, 2 ) );
				$value = sanitize_key( $parts[0] );
				$label = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : $value;
			}

			if ( '' === $value ) {
				continue;
			}

			$out[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		return $out;
	}

	/**
	 * Resolve normalized options for a field (top-level `options` preferred).
	 *
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	protected function get_field_options( array $field ): array {
		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			return $this->normalize_options_list( $field['options'] );
		}

		if ( isset( $field['type_options']['options'] ) ) {
			return $this->normalize_options_list( $field['type_options']['options'] );
		}

		return array();
	}

	/**
	 * Valid option values for a field.
	 *
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return array<int, string>
	 */
	protected function get_valid_option_values( array $field ): array {
		return array_map(
			static function ( array $opt ): string {
				return $opt['value'];
			},
			$this->get_field_options( $field )
		);
	}

	/**
	 * Map option value => label for display.
	 *
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return array<string, string>
	 */
	protected function get_option_label_map( array $field ): array {
		$out = array();

		foreach ( $this->get_field_options( $field ) as $opt ) {
			$out[ $opt['value'] ] = $opt['label'];
		}

		return $out;
	}

	/**
	 * Build a generic invalid-value message for a field.
	 *
	 * @param array<string, mixed> $field Field configuration.
	 */
	protected function invalid_value_message( array $field ): string {
		return Validation_Messages::invalid_for_field( $field );
	}
}
