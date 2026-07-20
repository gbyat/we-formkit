<?php
/**
 * Resolve validation message copy (field → settings → built-in).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validation message templates and resolution.
 */
final class Validation_Messages {

	/**
	 * Built-in required template (contains {label}).
	 *
	 * @return string
	 */
	public static function builtin_required_template() {
		/* translators: Keep {label} as a literal placeholder for the field label. */
		return __( '{label} is required.', 'we-formkit' );
	}

	/**
	 * Built-in invalid template (contains {label}).
	 *
	 * @return string
	 */
	public static function builtin_invalid_template() {
		/* translators: Keep {label} as a literal placeholder for the field label. */
		return __( 'Please enter a valid value for {label}.', 'we-formkit' );
	}

	/**
	 * Sanitize optional messages block on a field.
	 *
	 * @param mixed $raw Raw messages value.
	 * @return array{required:string,invalid:string}
	 */
	public static function normalize_field_messages( $raw ) {
		$out = array(
			'required' => '',
			'invalid'  => '',
		);
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		if ( isset( $raw['required'] ) ) {
			$out['required'] = sanitize_text_field( (string) $raw['required'] );
		}
		if ( isset( $raw['invalid'] ) ) {
			$out['invalid'] = sanitize_text_field( (string) $raw['invalid'] );
		}
		return $out;
	}

	/**
	 * Apply {label} (and legacy %s) placeholders.
	 *
	 * @param string $template Template.
	 * @param string $label    Field label.
	 * @return string
	 */
	public static function apply_label( $template, $label ) {
		$template = (string) $template;
		$label    = (string) $label;
		$out      = str_replace( '{label}', $label, $template );
		// Support one sprintf-style %s if an older string was stored.
		if ( false !== strpos( $out, '%s' ) ) {
			$out = sprintf( $out, $label );
		}
		return $out;
	}

	/**
	 * Resolve required message for a field.
	 *
	 * @param array<string, mixed> $field Field config.
	 * @return string
	 */
	public static function required_for_field( array $field ) {
		$label    = self::field_label( $field );
		$messages = isset( $field['messages'] ) && is_array( $field['messages'] ) ? $field['messages'] : array();
		$override = isset( $messages['required'] ) ? trim( (string) $messages['required'] ) : '';
		if ( '' !== $override ) {
			return self::apply_label( $override, $label );
		}

		$settings = Settings::get();
		$global   = isset( $settings['validation_required'] ) ? trim( (string) $settings['validation_required'] ) : '';
		if ( '' !== $global ) {
			return self::apply_label( $global, $label );
		}

		return self::apply_label( self::builtin_required_template(), $label );
	}

	/**
	 * Resolve invalid-value message for a field.
	 *
	 * @param array<string, mixed> $field Field config.
	 * @return string
	 */
	public static function invalid_for_field( array $field ) {
		$label    = self::field_label( $field );
		$messages = isset( $field['messages'] ) && is_array( $field['messages'] ) ? $field['messages'] : array();
		$override = isset( $messages['invalid'] ) ? trim( (string) $messages['invalid'] ) : '';
		if ( '' !== $override ) {
			return self::apply_label( $override, $label );
		}

		$settings = Settings::get();
		$global   = isset( $settings['validation_invalid'] ) ? trim( (string) $settings['validation_invalid'] ) : '';
		if ( '' !== $global ) {
			return self::apply_label( $global, $label );
		}

		return self::apply_label( self::builtin_invalid_template(), $label );
	}

	/**
	 * Unresolved templates for the frontend (client fills {label} per field).
	 *
	 * @return array{required:string,invalid:string}
	 */
	public static function global_templates_for_js() {
		$settings = Settings::get();
		$required = isset( $settings['validation_required'] ) ? trim( (string) $settings['validation_required'] ) : '';
		$invalid  = isset( $settings['validation_invalid'] ) ? trim( (string) $settings['validation_invalid'] ) : '';
		return array(
			'required' => '' !== $required ? $required : self::builtin_required_template(),
			'invalid'  => '' !== $invalid ? $invalid : self::builtin_invalid_template(),
		);
	}

	/**
	 * @param array<string, mixed> $field Field.
	 * @return string
	 */
	private static function field_label( array $field ) {
		$label = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
		if ( '' !== $label ) {
			return $label;
		}
		return isset( $field['id'] ) ? (string) $field['id'] : '';
	}
}
