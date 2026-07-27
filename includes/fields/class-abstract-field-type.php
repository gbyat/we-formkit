<?php
/**
 * Abstract base class for all field types.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

use Webentwicklerin\WeFormkit\Field_Roles;
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
		$field['id']       = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
		$field['type']     = $this->get_type();
		$field['label']    = isset( $field['label'] ) ? sanitize_text_field( (string) $field['label'] ) : '';
		$field['help']     = isset( $field['help'] ) ? sanitize_text_field( (string) $field['help'] ) : '';
		$field['required'] = ! empty( $field['required'] );
		// Default true when key is missing (legacy forms).
		$field['enabled']       = ! array_key_exists( 'enabled', $field ) || ! empty( $field['enabled'] );
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

		if ( isset( $field['type_options']['block_links'] ) ) {
			$field['type_options']['block_links'] = ! empty( $field['type_options']['block_links'] );
		}
		if ( isset( $field['type_options']['block_emails'] ) ) {
			$field['type_options']['block_emails'] = ! empty( $field['type_options']['block_emails'] );
		}

		$field['messages'] = Validation_Messages::normalize_field_messages( $field['messages'] ?? null );

		// URL prefill: on by default; optional alias param name.
		if ( array_key_exists( 'allow_url_prefill', $field ) ) {
			$field['allow_url_prefill'] = ! empty( $field['allow_url_prefill'] );
		} else {
			$field['allow_url_prefill'] = true;
		}
		$field['prefill_param'] = isset( $field['prefill_param'] ) ? sanitize_key( (string) $field['prefill_param'] ) : '';

		$field['role'] = Field_Roles::normalize( $field['role'] ?? '' );

		$pack_group = self::normalize_pack_group( $field['pack_group'] ?? null );
		if ( null === $pack_group ) {
			unset( $field['pack_group'] );
		} else {
			$field['pack_group'] = $pack_group;
		}

		return $field;
	}

	/**
	 * Sanitize builder pack membership (Name/Address template groups).
	 *
	 * @param mixed $raw Raw pack_group.
	 * @return array{id:string,pack:string}|null
	 */
	public static function normalize_pack_group( $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$id   = sanitize_key( (string) ( $raw['id'] ?? '' ) );
		$pack = sanitize_key( (string) ( $raw['pack'] ?? '' ) );
		if ( '' === $id || ! in_array( $pack, array( 'name', 'address' ), true ) ) {
			return null;
		}
		return array(
			'id'   => $id,
			'pack' => $pack,
		);
	}

	/**
	 * Reject links / email addresses in free-text fields when enabled per field.
	 *
	 * @param mixed                $value Sanitized value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return true|\WP_Error
	 */
	protected function content_guard( $value, array $field ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return true;
		}

		$opts  = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		$label = (string) ( $field['label'] ?? '' );

		if ( ! empty( $opts['block_links'] ) && self::contains_link( $value ) ) {
			return new \WP_Error(
				'we_formkit_links_not_allowed',
				sprintf(
					/* translators: %s: field label. */
					__( '%s may not contain links.', 'we-formkit' ),
					$label
				)
			);
		}

		if ( ! empty( $opts['block_emails'] ) && self::contains_email( $value ) ) {
			return new \WP_Error(
				'we_formkit_email_not_allowed',
				sprintf(
					/* translators: %s: field label. */
					__( '%s may not contain an email address.', 'we-formkit' ),
					$label
				)
			);
		}

		return true;
	}

	/**
	 * Heuristic link detection (URLs, anchors, bbcode).
	 *
	 * @param string $value Text to scan.
	 * @return bool
	 */
	protected static function contains_link( $value ) {
		$value = (string) $value;
		return (bool) preg_match( '#(?:https?://|www\.)\S+#i', $value )
			|| (bool) preg_match( '#<a\b#i', $value )
			|| false !== stripos( $value, '[url' );
	}

	/**
	 * Heuristic email-address detection.
	 *
	 * @param string $value Text to scan.
	 * @return bool
	 */
	protected static function contains_email( $value ) {
		return (bool) preg_match( '#[^\s@]+@[^\s@]+\.[^\s@]{2,}#', (string) $value );
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
	 * Run the shared format filter after a field type (or caller) produced display HTML/text.
	 *
	 * @param string               $display Formatted value.
	 * @param mixed                $value   Raw stored value.
	 * @param array<string, mixed> $field   Field config.
	 * @param string               $context `display` | `email` | `export` | `admin`.
	 * @return string
	 */
	public static function apply_format_filter( $display, $value, array $field, $context = 'display' ) {
		/**
		 * Filter a formatted field value for display, email, export, or admin.
		 *
		 * @param string               $display Formatted string (may contain safe HTML depending on context).
		 * @param mixed                $value   Raw value.
		 * @param array<string, mixed> $field   Field config.
		 * @param string               $context Context key.
		 */
		return (string) apply_filters( 'we_formkit_format_field_value', (string) $display, $value, $field, (string) $context );
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

		$ac = Field_Roles::autocomplete( $field['role'] ?? '' );
		if ( '' !== $ac ) {
			$attrs['autocomplete'] = $ac;
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
	 * Build a stable option value from a label (German-friendly transliteration).
	 *
	 * @param string $label    Display label.
	 * @param string $fallback Fallback when empty.
	 * @return string
	 */
	public static function slugify_option_value( $label, $fallback = 'option' ): string {
		$text = (string) $label;
		$map  = array(
			'ä' => 'ae',
			'ö' => 'oe',
			'ü' => 'ue',
			'Ä' => 'ae',
			'Ö' => 'oe',
			'Ü' => 'ue',
			'ß' => 'ss',
			'æ' => 'ae',
			'ø' => 'oe',
			'å' => 'aa',
			'Æ' => 'ae',
			'Ø' => 'oe',
			'Å' => 'aa',
		);
		$text = strtr( $text, $map );
		if ( function_exists( 'remove_accents' ) ) {
			$text = remove_accents( $text );
		}
		$key = sanitize_title( $text );
		$key = str_replace( '-', '_', $key );
		$key = sanitize_key( $key );
		if ( '' === $key ) {
			$key = sanitize_key( (string) $fallback );
		}
		if ( '' === $key ) {
			$key = 'option';
		}
		return substr( $key, 0, 64 );
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

		$out   = array();
		$used  = array();
		$index = 0;

		foreach ( $raw as $line ) {
			++$index;
			$extra = array();

			if ( is_array( $line ) ) {
				$value = isset( $line['value'] ) ? sanitize_key( (string) $line['value'] ) : '';
				$label = isset( $line['label'] ) ? sanitize_text_field( (string) $line['label'] ) : '';
				if ( isset( $line['image_id'] ) ) {
					$extra['image_id'] = absint( $line['image_id'] );
				}
				if ( isset( $line['image_url'] ) ) {
					$extra['image_url'] = esc_url_raw( (string) $line['image_url'] );
				}
			} else {
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}

				$parts = array_map( 'trim', explode( '|', $line, 2 ) );
				if ( isset( $parts[1] ) ) {
					$value = sanitize_key( $parts[0] );
					$label = sanitize_text_field( $parts[1] );
				} else {
					// Single token = label; value is derived below.
					$value = '';
					$label = sanitize_text_field( $parts[0] );
				}
			}

			if ( '' === $label && '' === $value ) {
				continue;
			}

			if ( '' === $label ) {
				$label = $value;
			}

			if ( '' === $value ) {
				$value = self::slugify_option_value( $label, 'option_' . $index );
			}

			$base = $value;
			$n    = 2;
			while ( isset( $used[ $value ] ) ) {
				$value = substr( $base, 0, 60 ) . '_' . $n;
				++$n;
			}
			$used[ $value ] = true;

			$out[] = array_merge(
				array(
					'value' => $value,
					'label' => $label,
				),
				$extra
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
