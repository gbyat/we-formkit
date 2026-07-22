<?php
/**
 * Checkbox field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single boolean confirmation checkbox.
 *
 * `label` = field title (show_label). `type_options.choice_label` = text beside the checkbox.
 */
class Checkbox_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'checkbox';
	}

	public function get_label(): string {
		return __( 'Checkbox', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'choice_label' => array(
				'label'       => __( 'Checkbox text', 'we-formkit' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Text shown beside the checkbox.', 'we-formkit' ),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );
		$field = self::migrate_legacy_choice_label( $field, $this->get_label() );

		$opts                                  = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		$field['type_options']['choice_label'] = isset( $opts['choice_label'] )
			? sanitize_text_field( (string) $opts['choice_label'] )
			: '';

		return $field;
	}

	/**
	 * Text beside the checkbox (falls back to label for legacy schemas).
	 *
	 * @param array<string, mixed> $field Field config.
	 */
	public static function choice_label( array $field ): string {
		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		if ( array_key_exists( 'choice_label', $opts ) ) {
			return trim( (string) $opts['choice_label'] );
		}
		return trim( (string) ( $field['label'] ?? '' ) );
	}

	/**
	 * One-time: old schemas stored the control copy in `label`.
	 *
	 * @param array<string, mixed> $field        Field config.
	 * @param string               $default_title Short title after migration.
	 * @return array<string, mixed>
	 */
	public static function migrate_legacy_choice_label( array $field, $default_title ): array {
		if ( ! isset( $field['type_options'] ) || ! is_array( $field['type_options'] ) ) {
			$field['type_options'] = array();
		}
		if ( array_key_exists( 'choice_label', $field['type_options'] ) ) {
			return $field;
		}

		$old_label                             = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
		$field['type_options']['choice_label'] = $old_label;
		$field['label']                        = (string) $default_title;
		$field['show_label']                   = false;

		return $field;
	}

	public function sanitize( $value, array $field ) {
		return $this->is_truthy( $value ) ? '1' : '';
	}

	public function validate( $value, array $field ) {
		return true;
	}

	public function is_empty_value( $value ): bool {
		return '1' !== (string) $value;
	}

	public function format_for_display( $value, array $field ): string {
		return esc_html(
			'1' === (string) $value
				? __( 'Yes', 'we-formkit' )
				: __( 'No', 'we-formkit' )
		);
	}

	public function render_attributes( array $field ): array {
		$attrs          = parent::render_attributes( $field );
		$attrs['type']  = 'checkbox';
		$attrs['value'] = '1';

		return $attrs;
	}

	/**
	 * @param mixed $value Incoming value.
	 */
	private function is_truthy( $value ): bool {
		if ( true === $value ) {
			return true;
		}

		if ( is_int( $value ) ) {
			return 1 === $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}
}
