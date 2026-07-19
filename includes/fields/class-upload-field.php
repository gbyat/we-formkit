<?php
/**
 * Upload field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File upload field for form submissions.
 */
class Upload_Field extends Abstract_Field_Type {

	public const STORAGE_MEDIA_LIBRARY = 'media_library';
	public const STORAGE_UPLOADS_ONLY  = 'uploads_only';

	public function get_type(): string {
		return 'upload';
	}

	public function get_label(): string {
		return __( 'File upload', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'max_files' => array(
				'label'   => __( 'Maximum files', 'we-formkit' ),
				'type'    => 'number',
				'default' => 1,
			),
			'max_file_size_mb' => array(
				'label'   => __( 'Maximum size per file (MB)', 'we-formkit' ),
				'type'    => 'number',
				'default' => 5,
			),
			'allowed_mime_types' => array(
				'label'       => __( 'Allowed MIME types', 'we-formkit' ),
				'type'        => 'text',
				'default'     => 'image/jpeg, image/png, application/pdf',
				'description' => __( 'Comma-separated list. Leave empty for the WordPress default whitelist.', 'we-formkit' ),
			),
			'storage_mode' => array(
				'label'       => __( 'Storage mode', 'we-formkit' ),
				'type'        => 'select',
				'default'     => self::STORAGE_UPLOADS_ONLY,
				'enum'        => array( self::STORAGE_UPLOADS_ONLY, self::STORAGE_MEDIA_LIBRARY ),
				'description' => __( 'Choose whether uploaded files are also registered in the Media Library or only stored in the uploads folder.', 'we-formkit' ),
				'options'     => array(
					array(
						'value' => self::STORAGE_UPLOADS_ONLY,
						'label' => __( 'Uploads folder only (no Media Library entry)', 'we-formkit' ),
					),
					array(
						'value' => self::STORAGE_MEDIA_LIBRARY,
						'label' => __( 'Media Library + uploads folder', 'we-formkit' ),
					),
				),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );
		$opts  = $field['type_options'];

		$field['type_options']['max_files'] = isset( $opts['max_files'] ) ? max( 1, (int) $opts['max_files'] ) : 1;
		$field['type_options']['max_file_size_mb'] = isset( $opts['max_file_size_mb'] ) ? max( 1, (int) $opts['max_file_size_mb'] ) : 5;
		$field['type_options']['allowed_mime_types'] = isset( $opts['allowed_mime_types'] ) ? sanitize_text_field( (string) $opts['allowed_mime_types'] ) : '';
		$field['type_options']['storage_mode'] = $this->resolve_storage_mode( $field );

		return $field;
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();

		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( isset( $entry['attachment_id'] ) ) {
				$attachment_id = absint( $entry['attachment_id'] );
				if ( $attachment_id <= 0 ) {
					continue;
				}

				$out[] = array(
					'attachment_id' => $attachment_id,
					'name'          => isset( $entry['name'] ) ? sanitize_file_name( (string) $entry['name'] ) : '',
					'size'          => isset( $entry['size'] ) ? max( 0, (int) $entry['size'] ) : 0,
					'mime'          => isset( $entry['mime'] ) ? sanitize_mime_type( (string) $entry['mime'] ) : '',
					'url'           => isset( $entry['url'] ) ? esc_url_raw( (string) $entry['url'] ) : '',
				);
				continue;
			}

			$token = isset( $entry['token'] ) ? sanitize_text_field( (string) $entry['token'] ) : '';
			if ( '' === $token ) {
				continue;
			}

			$out[] = array(
				'token' => $token,
				'name'  => isset( $entry['name'] ) ? sanitize_file_name( (string) $entry['name'] ) : '',
				'size'  => isset( $entry['size'] ) ? max( 0, (int) $entry['size'] ) : 0,
				'mime'  => isset( $entry['mime'] ) ? sanitize_mime_type( (string) $entry['mime'] ) : '',
				'url'   => isset( $entry['url'] ) ? esc_url_raw( (string) $entry['url'] ) : '',
			);
		}

		return $out;
	}

	public function validate( $value, array $field ) {
		$max = isset( $field['type_options']['max_files'] ) ? max( 1, (int) $field['type_options']['max_files'] ) : 1;

		if ( is_array( $value ) && count( $value ) > $max ) {
			return new \WP_Error(
				'we_formkit_upload_too_many',
				sprintf(
					/* translators: 1: field label, 2: max number of files. */
					__( '%1$s allows at most %2$d file(s).', 'we-formkit' ),
					(string) ( $field['label'] ?? '' ),
					$max
				)
			);
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

		$lines = array();

		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$name = isset( $entry['name'] ) ? (string) $entry['name'] : __( 'File', 'we-formkit' );
			$url  = isset( $entry['url'] ) ? (string) $entry['url'] : '';

			if ( '' === $url && ! empty( $entry['attachment_id'] ) ) {
				$attachment_url = wp_get_attachment_url( (int) $entry['attachment_id'] );
				if ( is_string( $attachment_url ) ) {
					$url = $attachment_url;
				}
			}

			if ( '' !== $url ) {
				$lines[] = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $url ),
					esc_html( $name )
				);
				continue;
			}

			$lines[] = esc_html( $name );
		}

		return implode( '<br />', $lines );
	}

	public function render_attributes( array $field ): array {
		$attrs = parent::render_attributes( $field );
		$attrs['type'] = 'file';

		$max_files = isset( $field['type_options']['max_files'] ) ? max( 1, (int) $field['type_options']['max_files'] ) : 1;
		if ( $max_files > 1 ) {
			$attrs['multiple'] = 'multiple';
		}

		return $attrs;
	}

	/**
	 * Allowed MIME types for this field, with WordPress fallback.
	 *
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return array<int, string>
	 */
	public function get_allowed_mime_types( array $field ): array {
		$raw = (string) ( $field['type_options']['allowed_mime_types'] ?? '' );
		if ( '' === $raw ) {
			return array_values( get_allowed_mime_types() );
		}

		$tokens = array_values(
			array_filter(
				array_map( 'trim', explode( ',', $raw ) ),
				static function ( string $token ): bool {
					return '' !== $token;
				}
			)
		);

		$resolved = array();
		$wp_mimes = wp_get_mime_types();

		foreach ( $tokens as $token ) {
			if ( false !== strpos( $token, '/' ) ) {
				$mime = sanitize_mime_type( $token );
				if ( '' !== $mime ) {
					$resolved[] = $mime;
				}
				continue;
			}

			$ext = ltrim( strtolower( sanitize_text_field( $token ) ), '.' );
			if ( '' === $ext ) {
				continue;
			}

			foreach ( $wp_mimes as $exts => $mime ) {
				$list = array_map( 'trim', explode( '|', (string) $exts ) );
				if ( in_array( $ext, $list, true ) ) {
					$resolved[] = sanitize_mime_type( (string) $mime );
				}
			}
		}

		$resolved = array_values( array_unique( array_filter( $resolved ) ) );

		return ! empty( $resolved ) ? $resolved : array_values( get_allowed_mime_types() );
	}

	/**
	 * Maximum bytes per file for this field.
	 *
	 * @param array<string, mixed> $field Field configuration.
	 */
	public function get_max_file_size_bytes( array $field ): int {
		$mb = isset( $field['type_options']['max_file_size_mb'] ) ? (int) $field['type_options']['max_file_size_mb'] : 5;
		return max( 1, $mb ) * 1024 * 1024;
	}

	/**
	 * Resolve upload storage mode from field options.
	 *
	 * @param array<string, mixed> $field Field configuration.
	 */
	public function resolve_storage_mode( array $field ): string {
		$mode = isset( $field['type_options']['storage_mode'] ) ? sanitize_key( (string) $field['type_options']['storage_mode'] ) : self::STORAGE_UPLOADS_ONLY;
		return in_array( $mode, array( self::STORAGE_UPLOADS_ONLY, self::STORAGE_MEDIA_LIBRARY ), true )
			? $mode
			: self::STORAGE_UPLOADS_ONLY;
	}
}
