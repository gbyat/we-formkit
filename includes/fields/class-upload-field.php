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
			'max_files'          => array(
				'label'   => __( 'Maximum files', 'we-formkit' ),
				'type'    => 'number',
				'default' => 1,
			),
			'max_file_size_mb'   => array(
				'label'   => __( 'Maximum size per file (MB)', 'we-formkit' ),
				'type'    => 'number',
				'default' => 5,
			),
			'allowed_mime_types' => array(
				'label'       => __( 'Allowed MIME types', 'we-formkit' ),
				'type'        => 'text',
				'default'     => 'image/jpeg, image/png, application/pdf',
				'description' => __( 'Select one or more types. Leave empty to use the WordPress default whitelist.', 'we-formkit' ),
			),
			'storage_mode'       => array(
				'label'       => __( 'Storage mode', 'we-formkit' ),
				'type'        => 'select',
				'default'     => self::STORAGE_UPLOADS_ONLY,
				'enum'        => array( self::STORAGE_UPLOADS_ONLY, self::STORAGE_MEDIA_LIBRARY ),
				'description' => __( 'Default: private Formkit folder (not in Media Library). Media Library is discouraged for personal data.', 'we-formkit' ),
				'options'     => array(
					array(
						'value' => self::STORAGE_UPLOADS_ONLY,
						'label' => __( 'Private Formkit folder (recommended)', 'we-formkit' ),
					),
					array(
						'value' => self::STORAGE_MEDIA_LIBRARY,
						'label' => __( 'Media Library (not recommended for personal data)', 'we-formkit' ),
					),
				),
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );
		$opts  = $field['type_options'];

		$field['type_options']['max_files']          = isset( $opts['max_files'] ) ? max( 1, (int) $opts['max_files'] ) : 1;
		$field['type_options']['max_file_size_mb']   = isset( $opts['max_file_size_mb'] ) ? max( 1, (int) $opts['max_file_size_mb'] ) : 5;
		$field['type_options']['allowed_mime_types'] = isset( $opts['allowed_mime_types'] ) ? sanitize_text_field( (string) $opts['allowed_mime_types'] ) : '';
		$field['type_options']['storage_mode']       = $this->resolve_storage_mode( $field );

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
				'path'  => self::sanitize_private_path( $entry['path'] ?? '' ),
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
			$url  = '';

			if ( ! empty( $entry['token'] ) && ! empty( $entry['name'] ) ) {
				$url = \Webentwicklerin\WeFormkit\Private_Files::download_url( 'upload', (string) $entry['token'], (string) $entry['name'] );
			} elseif ( ! empty( $entry['attachment_id'] ) ) {
				$attachment_url = wp_get_attachment_url( (int) $entry['attachment_id'] );
				if ( is_string( $attachment_url ) ) {
					$url = $attachment_url;
				}
			} elseif ( ! empty( $entry['url'] ) ) {
				$url = (string) $entry['url'];
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
		$attrs         = parent::render_attributes( $field );
		$attrs['type'] = 'file';

		$max_files = isset( $field['type_options']['max_files'] ) ? max( 1, (int) $field['type_options']['max_files'] ) : 1;
		if ( $max_files > 1 ) {
			$attrs['multiple'] = 'multiple';
		}

		return $attrs;
	}

	/**
	 * Curated MIME choices for the form builder multi-select.
	 *
	 * @return array<int, array{value:string,label:string}>
	 */
	public static function common_mime_choices(): array {
		return array(
			array(
				'value' => 'image/jpeg',
				'label' => 'JPEG',
			),
			array(
				'value' => 'image/png',
				'label' => 'PNG',
			),
			array(
				'value' => 'image/webp',
				'label' => 'WebP',
			),
			array(
				'value' => 'image/gif',
				'label' => 'GIF',
			),
			array(
				'value' => 'application/pdf',
				'label' => 'PDF',
			),
			array(
				'value' => 'text/plain',
				'label' => __( 'Text', 'we-formkit' ),
			),
			array(
				'value' => 'text/csv',
				'label' => 'CSV',
			),
			array(
				'value' => 'application/msword',
				'label' => 'DOC',
			),
			array(
				'value' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'label' => 'DOCX',
			),
			array(
				'value' => 'application/vnd.ms-excel',
				'label' => 'XLS',
			),
			array(
				'value' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'label' => 'XLSX',
			),
			array(
				'value' => 'application/zip',
				'label' => 'ZIP',
			),
			array(
				'value' => 'audio/mpeg',
				'label' => 'MP3',
			),
			array(
				'value' => 'video/mp4',
				'label' => 'MP4',
			),
		);
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

	/**
	 * Keep path only if it is inside Formkit private storage.
	 *
	 * @param mixed $path Candidate path.
	 * @return string
	 */
	private static function sanitize_private_path( $path ): string {
		$path = is_string( $path ) ? $path : '';
		if ( '' === $path ) {
			return '';
		}
		return \Webentwicklerin\WeFormkit\Private_Files::is_private_path( $path ) ? $path : '';
	}
}
