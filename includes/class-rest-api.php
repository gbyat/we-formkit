<?php
/**
 * REST API for form submission.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

use Webentwicklerin\WeFormkit\Fields\Upload_Field;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public submit endpoint.
 */
final class Rest_Api {

	public const NAMESPACE = 'we-formkit/v1';

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::NAMESPACE,
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);

		Rest_Form_Settings::register_routes();
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function submit( $request ) {
		$params = self::parse_submit_params( $request );

		$nonce = isset( $params['nonce'] ) ? (string) $params['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'we_formkit_forbidden', __( 'Invalid security token. Please reload the page.', 'we-formkit' ), array( 'status' => 403 ) );
		}

		$spam = Spam::validate( $params );
		if ( is_wp_error( $spam ) ) {
			return $spam;
		}

		$form_id = isset( $params['form_id'] ) ? (int) $params['form_id'] : 0;
		$form    = get_post( $form_id );
		if ( ! $form || Post_Types::FORM !== $form->post_type || 'publish' !== $form->post_status ) {
			return new \WP_Error( 'we_formkit_not_found', __( 'Form not found.', 'we-formkit' ), array( 'status' => 404 ) );
		}

		$secret = Form_Schema::get_secret( $form_id );
		if ( $secret['enabled'] ) {
			$token = isset( $params['token'] ) ? (string) $params['token'] : '';
			if ( '' === $secret['token'] || ! hash_equals( $secret['token'], $token ) ) {
				return new \WP_Error( 'we_formkit_forbidden', __( 'This form requires a valid access link.', 'we-formkit' ), array( 'status' => 403 ) );
			}
		}

		$values = self::extract_values( $params );
		$schema = Form_Schema::get( $form_id );

		// Validate non-file fields first (upload keys left empty until files are processed).
		$non_file_values = $values;
		foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
			if ( isset( $field['type'] ) && 'upload' === $field['type'] ) {
				$non_file_values[ $field_id ] = array();
			}
		}

		$result = Form_Schema::validate_submission( $schema, $non_file_values );
		if ( ! $result['ok'] ) {
			// Drop required-upload errors until files are processed; keep other errors.
			$upload_ids = array();
			foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
				if ( isset( $field['type'] ) && 'upload' === $field['type'] ) {
					$upload_ids[ $field_id ] = true;
				}
			}
			$non_upload_errors = array();
			foreach ( $result['errors'] as $field_id => $message ) {
				if ( empty( $upload_ids[ $field_id ] ) ) {
					$non_upload_errors[ $field_id ] = $message;
				}
			}
			if ( ! empty( $non_upload_errors ) ) {
				return new \WP_Error(
					'we_formkit_validation',
					__( 'Please correct the highlighted fields.', 'we-formkit' ),
					array(
						'status' => 422,
						'errors' => $non_upload_errors,
					)
				);
			}
			$result['errors'] = array();
			$result['ok']     = true;
		}

		$upload_handled = self::process_uploads( $schema, $values );
		if ( is_wp_error( $upload_handled ) ) {
			return $upload_handled;
		}

		$values_with_uploads = array_merge( $values, $upload_handled );
		$result              = Form_Schema::validate_submission( $schema, $values_with_uploads );
		if ( ! $result['ok'] ) {
			return new \WP_Error(
				'we_formkit_validation',
				__( 'Please correct the highlighted fields.', 'we-formkit' ),
				array(
					'status' => 422,
					'errors' => $result['errors'],
				)
			);
		}

		$ip = Spam::client_ip();
		Spam::record_attempt( $ip );

		$title = self::submission_title( $schema, $result['data'] );

		$submission_id = wp_insert_post(
			array(
				'post_type'   => Post_Types::SUBMISSION,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $submission_id ) ) {
			return new \WP_Error( 'we_formkit_save_failed', __( 'Could not save the submission.', 'we-formkit' ), array( 'status' => 500 ) );
		}

		update_post_meta( (int) $submission_id, Form_Schema::SUB_FORM_ID, $form_id );
		update_post_meta( (int) $submission_id, Form_Schema::SUB_DATA, wp_json_encode( $result['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		update_post_meta( (int) $submission_id, Form_Schema::SUB_NOTES, '' );
		update_post_meta( (int) $submission_id, Form_Schema::SUB_CONSENT, self::has_consent( $schema, $result['data'] ) ? 1 : 0 );
		update_post_meta( (int) $submission_id, Form_Schema::SUB_IP_HASH, Spam::hash_ip( $ip ) );

		/**
		 * Fires after a submission is stored.
		 *
		 * @param int                  $submission_id Submission ID.
		 * @param array<string, mixed> $context       Context.
		 */
		do_action(
			'we_formkit_submission_created',
			(int) $submission_id,
			array(
				'form_id' => $form_id,
				'data'    => $result['data'],
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => Form_Schema::get_confirmation_message( $form_id ),
			)
		);
	}

	/**
	 * Resolve request params for JSON or multipart bodies.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private static function parse_submit_params( $request ) {
		$content_type = (string) $request->get_header( 'Content-Type' );
		$is_multipart = false !== stripos( $content_type, 'multipart/form-data' );

		if ( $is_multipart ) {
			$params = $request->get_params();
			return is_array( $params ) ? $params : array();
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		return is_array( $params ) ? $params : array();
	}

	/**
	 * Extract values array; multipart may send JSON in a string `values` param.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @return array<string, mixed>
	 */
	private static function extract_values( array $params ) {
		if ( ! isset( $params['values'] ) ) {
			return array();
		}

		$raw = $params['values'];
		if ( is_string( $raw ) ) {
			$decoded = json_decode( wp_unslash( $raw ), true );
			return is_array( $decoded ) ? $decoded : array();
		}

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Process $_FILES for upload fields and return sanitized entry arrays keyed by field id.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param array<string, mixed> $values Existing values (unused; files come from $_FILES).
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function process_uploads( array $schema, array $values ) {
		unset( $values );

		$out      = array();
		$errors   = array();
		$registry = Plugin::instance()->field_registry();

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
			if ( empty( $field['type'] ) || 'upload' !== $field['type'] ) {
				continue;
			}

			$type_obj = $registry ? $registry->get( 'upload' ) : null;
			if ( ! $type_obj instanceof Upload_Field ) {
				continue;
			}

			$file_list = self::collect_uploaded_files( $field_id );
			$max_files = isset( $field['type_options']['max_files'] ) ? max( 1, (int) $field['type_options']['max_files'] ) : 1;
			$max_bytes = $type_obj->get_max_file_size_bytes( $field );
			$allowed   = $type_obj->get_allowed_mime_types( $field );
			$mode      = $type_obj->resolve_storage_mode( $field );

			if ( count( $file_list ) > $max_files ) {
				$errors[ $field_id ] = sprintf(
					/* translators: 1: field label, 2: max number of files. */
					__( '%1$s allows at most %2$d file(s).', 'we-formkit' ),
					(string) ( $field['label'] ?? $field_id ),
					$max_files
				);
				continue;
			}

			$stored = array();
			foreach ( $file_list as $file ) {
				if ( ! empty( $file['error'] ) && UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
					continue;
				}
				if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
					$errors[ $field_id ] = __( 'File upload failed. Please try again.', 'we-formkit' );
					break;
				}

				$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
				if ( $size > $max_bytes ) {
					$errors[ $field_id ] = sprintf(
						/* translators: 1: field label, 2: max size in MB. */
						__( '%1$s exceeds the maximum file size of %2$d MB.', 'we-formkit' ),
						(string) ( $field['label'] ?? $field_id ),
						(int) ceil( $max_bytes / ( 1024 * 1024 ) )
					);
					break;
				}

				$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
				$mime  = ! empty( $check['type'] ) ? (string) $check['type'] : (string) ( $file['type'] ?? '' );
				if ( '' === $mime || ( ! empty( $allowed ) && ! in_array( $mime, $allowed, true ) ) ) {
					$errors[ $field_id ] = sprintf(
						/* translators: %s: field label. */
						__( '%s has an invalid file type.', 'we-formkit' ),
						(string) ( $field['label'] ?? $field_id )
					);
					break;
				}

				$upload = wp_handle_upload(
					$file,
					array(
						'test_form' => false,
						'mimes'     => self::mimes_map_from_list( $allowed ),
					)
				);

				if ( isset( $upload['error'] ) ) {
					$errors[ $field_id ] = (string) $upload['error'];
					break;
				}

				$url  = isset( $upload['url'] ) ? (string) $upload['url'] : '';
				$path = isset( $upload['file'] ) ? (string) $upload['file'] : '';
				$name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';

				if ( Upload_Field::STORAGE_MEDIA_LIBRARY === $mode ) {
					$attachment    = array(
						'post_mime_type' => $mime,
						'post_title'     => preg_replace( '/\.[^.]+$/', '', $name ),
						'post_content'   => '',
						'post_status'    => 'inherit',
					);
					$attachment_id = wp_insert_attachment( $attachment, $path );
					if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
						$errors[ $field_id ] = __( 'Could not save the uploaded file.', 'we-formkit' );
						break;
					}
					$meta = wp_generate_attachment_metadata( (int) $attachment_id, $path );
					if ( is_array( $meta ) ) {
						wp_update_attachment_metadata( (int) $attachment_id, $meta );
					}
					$stored[] = array(
						'attachment_id' => (int) $attachment_id,
						'name'          => $name,
						'size'          => $size,
						'mime'          => $mime,
						'url'           => $url ? $url : (string) wp_get_attachment_url( (int) $attachment_id ),
						'path'          => $path,
					);
				} else {
					$stored[] = array(
						'token' => wp_generate_password( 32, false, false ),
						'name'  => $name,
						'size'  => $size,
						'mime'  => $mime,
						'url'   => $url,
						'path'  => $path,
					);
				}
			}

			if ( isset( $errors[ $field_id ] ) ) {
				continue;
			}

			$out[ $field_id ] = $stored;
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'we_formkit_validation',
				__( 'Please correct the highlighted fields.', 'we-formkit' ),
				array(
					'status' => 422,
					'errors' => $errors,
				)
			);
		}

		return $out;
	}

	/**
	 * Normalize $_FILES entries for a field id (supports id and id[]).
	 *
	 * @param string $field_id Field id.
	 * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
	 */
	private static function collect_uploaded_files( $field_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified via REST nonce earlier.
		$bag = null;
		if ( isset( $_FILES[ $field_id ] ) && is_array( $_FILES[ $field_id ] ) ) {
			$bag = $_FILES[ $field_id ];
		} elseif ( isset( $_FILES[ $field_id . '[]' ] ) && is_array( $_FILES[ $field_id . '[]' ] ) ) {
			$bag = $_FILES[ $field_id . '[]' ];
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( null === $bag || empty( $bag['name'] ) ) {
			return array();
		}

		$files = array();
		if ( is_array( $bag['name'] ) ) {
			$count = count( $bag['name'] );
			for ( $i = 0; $i < $count; $i++ ) {
				$files[] = array(
					'name'     => isset( $bag['name'][ $i ] ) ? (string) $bag['name'][ $i ] : '',
					'type'     => isset( $bag['type'][ $i ] ) ? (string) $bag['type'][ $i ] : '',
					'tmp_name' => isset( $bag['tmp_name'][ $i ] ) ? (string) $bag['tmp_name'][ $i ] : '',
					'error'    => isset( $bag['error'][ $i ] ) ? (int) $bag['error'][ $i ] : UPLOAD_ERR_NO_FILE,
					'size'     => isset( $bag['size'][ $i ] ) ? (int) $bag['size'][ $i ] : 0,
				);
			}
		} else {
			$files[] = array(
				'name'     => (string) $bag['name'],
				'type'     => isset( $bag['type'] ) ? (string) $bag['type'] : '',
				'tmp_name' => isset( $bag['tmp_name'] ) ? (string) $bag['tmp_name'] : '',
				'error'    => isset( $bag['error'] ) ? (int) $bag['error'] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $bag['size'] ) ? (int) $bag['size'] : 0,
			);
		}

		return array_values(
			array_filter(
				$files,
				static function ( array $file ): bool {
					return UPLOAD_ERR_NO_FILE !== (int) $file['error'] && '' !== $file['tmp_name'];
				}
			)
		);
	}

	/**
	 * Build extension => mime map for wp_handle_upload from a MIME list.
	 *
	 * @param array<int, string> $allowed Allowed MIME types.
	 * @return array<string, string>|null
	 */
	private static function mimes_map_from_list( array $allowed ) {
		if ( empty( $allowed ) ) {
			return null;
		}

		$map      = array();
		$wp_mimes = wp_get_mime_types();
		foreach ( $wp_mimes as $exts => $mime ) {
			if ( ! in_array( $mime, $allowed, true ) ) {
				continue;
			}
			foreach ( explode( '|', (string) $exts ) as $ext ) {
				$ext = trim( $ext );
				if ( '' !== $ext ) {
					$map[ $ext ] = $mime;
				}
			}
		}

		return ! empty( $map ) ? $map : null;
	}

	/**
	 * Build a human-readable submission title from submitted data.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param array<string, mixed> $data   Sanitized values.
	 * @return string
	 */
	private static function submission_title( array $schema, array $data ) {
		$preferred = array( 'email', 'name', 'full_name', 'first_name' );
		foreach ( $preferred as $key ) {
			if ( ! empty( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				$title = trim( (string) $data[ $key ] );
				if ( '' !== $title ) {
					return $title;
				}
			}
		}

		$fields = Form_Schema::fields_by_id( $schema );
		foreach ( $fields as $id => $field ) {
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( ! in_array( $type, array( 'email', 'text', 'tel', 'url' ), true ) ) {
				continue;
			}
			if ( empty( $data[ $id ] ) || ! is_scalar( $data[ $id ] ) ) {
				continue;
			}
			$title = trim( (string) $data[ $id ] );
			if ( '' !== $title ) {
				return $title;
			}
		}

		return sprintf(
			/* translators: %s: date */
			__( 'Submission %s', 'we-formkit' ),
			gmdate( 'Y-m-d H:i' )
		);
	}

	/**
	 * Whether any consent field was accepted.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param array<string, mixed> $data   Sanitized values.
	 * @return bool
	 */
	private static function has_consent( array $schema, array $data ) {
		$fields = Form_Schema::fields_by_id( $schema );
		foreach ( $fields as $id => $field ) {
			if ( empty( $field['type'] ) || 'consent' !== $field['type'] ) {
				continue;
			}
			if ( ! empty( $data[ $id ] ) ) {
				return true;
			}
		}
		return false;
	}
}
