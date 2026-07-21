<?php
/**
 * Signature field type.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

use Webentwicklerin\WeFormkit\Private_Files;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canvas signature — submitted as PNG data URL, stored as a private file.
 */
class Signature_Field extends Abstract_Field_Type {

	public function get_type(): string {
		return 'signature';
	}

	public function get_label(): string {
		return __( 'Signature', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'pen_color'        => array(
				'label'   => __( 'Pen color', 'we-formkit' ),
				'type'    => 'text',
				'default' => '#222222',
			),
			'background_color' => array(
				'label'   => __( 'Canvas background', 'we-formkit' ),
				'type'    => 'text',
				'default' => '#ffffff',
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field                                     = parent::normalize_config( $field );
		$opts                                      = $field['type_options'];
		$pen                                       = isset( $opts['pen_color'] ) ? sanitize_hex_color( (string) $opts['pen_color'] ) : '';
		$bg                                        = isset( $opts['background_color'] ) ? sanitize_hex_color( (string) $opts['background_color'] ) : '';
		$field['type_options']['pen_color']        = $pen ? $pen : '#222222';
		$field['type_options']['background_color'] = $bg ? $bg : '#ffffff';
		return $field;
	}

	public function sanitize( $value, array $field ) {
		// Already persisted file meta.
		if ( is_array( $value ) && ! empty( $value['token'] ) ) {
			return array(
				'token' => sanitize_text_field( (string) $value['token'] ),
				'name'  => sanitize_file_name( (string) ( $value['name'] ?? '' ) ),
				'size'  => isset( $value['size'] ) ? max( 0, (int) $value['size'] ) : 0,
				'mime'  => 'image/png',
				'path'  => isset( $value['path'] ) && Private_Files::is_private_path( (string) $value['path'] )
					? (string) $value['path']
					: '',
			);
		}

		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( 0 !== strpos( $value, 'data:image/png;base64,' ) ) {
			return '';
		}
		return $value;
	}

	public function validate( $value, array $field ) {
		if ( is_array( $value ) && ! empty( $value['token'] ) ) {
			return true;
		}
		if ( '' === $value || null === $value ) {
			return true;
		}
		if ( ! is_string( $value ) || 0 !== strpos( $value, 'data:image/png;base64,' ) ) {
			return new \WP_Error(
				'we_formkit_signature_invalid',
				sprintf(
					/* translators: %s: field label. */
					__( 'Signature for %s is not in the expected format.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' )
				)
			);
		}
		$encoded = substr( $value, strlen( 'data:image/png;base64,' ) );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- PNG data-URL payload from signature canvas.
		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded || strlen( $decoded ) < 256 ) {
			return new \WP_Error(
				'we_formkit_signature_empty',
				sprintf(
					/* translators: %s: field label. */
					__( 'Signature for %s appears to be empty.', 'we-formkit' ),
					(string) ( $field['label'] ?? '' )
				)
			);
		}
		return true;
	}

	public function is_empty_value( $value ): bool {
		if ( is_array( $value ) ) {
			return empty( $value['token'] );
		}
		return ! is_string( $value ) || '' === trim( $value );
	}

	public function format_for_display( $value, array $field ): string {
		if ( ! is_array( $value ) || empty( $value['token'] ) || empty( $value['name'] ) ) {
			return '';
		}
		$url = Private_Files::download_url( 'signature', (string) $value['token'], (string) $value['name'] );
		return sprintf(
			'<img class="wek-signature-img" src="%s" alt="%s" style="max-width:280px;height:auto;border:1px solid #ddd;" />',
			esc_url( $url ),
			esc_attr__( 'Signature', 'we-formkit' )
		);
	}

	/**
	 * Decode data URL and store under private signatures dir.
	 *
	 * @param string $data_url      PNG data URL.
	 * @param int    $submission_id Submission ID (0 ok).
	 * @param string $field_id      Field id.
	 * @return array{token:string,name:string,size:int,mime:string,path:string}|\WP_Error
	 */
	public function persist_data_url( $data_url, $submission_id, $field_id ) {
		if ( ! is_string( $data_url ) || 0 !== strpos( $data_url, 'data:image/png;base64,' ) ) {
			return new \WP_Error( 'we_formkit_signature_invalid', __( 'Invalid signature data.', 'we-formkit' ) );
		}
		$encoded = substr( $data_url, strlen( 'data:image/png;base64,' ) );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- PNG data-URL payload from signature canvas.
		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded ) {
			return new \WP_Error( 'we_formkit_signature_invalid', __( 'Invalid signature data.', 'we-formkit' ) );
		}
		return Private_Files::store_signature_png( $decoded, (int) $submission_id, (string) $field_id );
	}
}
