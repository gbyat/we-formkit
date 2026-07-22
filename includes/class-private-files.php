<?php
/**
 * Private file storage outside the Media Library.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores uploads and signatures under dedicated uploads subdirs with gated download.
 */
final class Private_Files {

	public const UPLOADS_SUBDIR    = 'we-formkit-uploads';
	public const SIGNATURES_SUBDIR = 'we-formkit-signatures';

	/**
	 * Ensure base dir exists and is not listable.
	 *
	 * @param string $subdir Subdir constant.
	 * @return string Absolute base path or empty on failure.
	 */
	public static function ensure_base( $subdir ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return '';
		}

		$safe_subdir = sanitize_file_name( (string) $subdir );

		/**
		 * Filter the absolute base directory for private Formkit file storage.
		 *
		 * Default: `{uploads}/{subdir}`. Return empty string to signal failure.
		 *
		 * @param string $path   Absolute path (no trailing slash required).
		 * @param string $subdir Relative subdir (uploads or signatures).
		 */
		$base = apply_filters(
			'we_formkit_private_storage_dir',
			trailingslashit( $upload['basedir'] ) . $safe_subdir,
			$safe_subdir
		);
		$base = is_string( $base ) ? untrailingslashit( $base ) : '';
		if ( '' === $base ) {
			return '';
		}

		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return '';
		}

		self::write_protection_files( $base );
		return $base;
	}

	/**
	 * Store an uploaded file from $_FILES-style array into a token directory.
	 *
	 * @param array{name?:string,tmp_name?:string,size?:int,type?:string} $file File array.
	 * @param array<int, string>                                          $allowed_mimes Allowed MIME types.
	 * @return array{token:string,name:string,size:int,mime:string,path:string}|\WP_Error
	 */
	public static function store_upload( array $file, array $allowed_mimes ) {
		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'we_formkit_invalid_upload', __( 'Invalid upload.', 'we-formkit' ) );
		}

		$orig_name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : 'upload';
		$check     = wp_check_filetype_and_ext( $tmp, $orig_name );
		$mime      = ! empty( $check['type'] ) ? (string) $check['type'] : (string) ( $file['type'] ?? '' );

		if ( '' === $mime || ( ! empty( $allowed_mimes ) && ! in_array( $mime, $allowed_mimes, true ) ) ) {
			return new \WP_Error( 'we_formkit_disallowed_type', __( 'File type not allowed.', 'we-formkit' ) );
		}

		$base = self::ensure_base( self::UPLOADS_SUBDIR );
		if ( '' === $base ) {
			return new \WP_Error( 'we_formkit_storage', __( 'Could not prepare upload storage.', 'we-formkit' ) );
		}

		$token = wp_generate_password( 24, false, false );
		$dir   = trailingslashit( $base ) . $token;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'we_formkit_storage', __( 'Could not prepare upload storage.', 'we-formkit' ) );
		}
		self::write_protection_files( $dir );

		$dest_name = $orig_name ? $orig_name : 'upload';
		$dest      = trailingslashit( $dir ) . $dest_name;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- move_uploaded_file can warn on race.
		if ( ! @move_uploaded_file( $tmp, $dest ) ) {
			return new \WP_Error( 'we_formkit_move_failed', __( 'Failed to store the uploaded file.', 'we-formkit' ) );
		}

		// Restrict download ACL; WP_Filesystem is not required for chmod on private uploads.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.PHP.NoSilencedErrors.Discouraged
		@chmod( $dest, 0640 );

		$size = isset( $file['size'] ) ? (int) $file['size'] : (int) filesize( $dest );

		return array(
			'token' => $token,
			'name'  => $dest_name,
			'size'  => max( 0, $size ),
			'mime'  => $mime,
			'path'  => $dest,
		);
	}

	/**
	 * Persist a PNG signature binary.
	 *
	 * @param string $binary         Raw PNG bytes.
	 * @param int    $submission_id  Submission ID (0 if not yet known).
	 * @param string $field_id       Field id.
	 * @return array{token:string,name:string,size:int,mime:string,path:string}|\WP_Error
	 */
	public static function store_signature_png( $binary, $submission_id, $field_id ) {
		$binary = (string) $binary;
		if ( strlen( $binary ) < 256 ) {
			return new \WP_Error( 'we_formkit_signature_empty', __( 'Signature is empty.', 'we-formkit' ) );
		}

		$base = self::ensure_base( self::SIGNATURES_SUBDIR );
		if ( '' === $base ) {
			return new \WP_Error( 'we_formkit_storage', __( 'Could not prepare signature storage.', 'we-formkit' ) );
		}

		$token = wp_generate_password( 24, false, false );
		$dir   = trailingslashit( $base ) . $token;
		wp_mkdir_p( $dir );
		self::write_protection_files( $dir );

		$safe_field = sanitize_key( (string) $field_id );
		$name       = sprintf(
			'signature-%d-%s.png',
			max( 0, (int) $submission_id ),
			$safe_field ? $safe_field : 'field'
		);
		$dest       = trailingslashit( $dir ) . $name;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $dest, $binary ) ) {
			return new \WP_Error( 'we_formkit_move_failed', __( 'Failed to store the signature.', 'we-formkit' ) );
		}

		// Restrict download ACL; WP_Filesystem is not required for chmod on private uploads.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.PHP.NoSilencedErrors.Discouraged
		@chmod( $dest, 0640 );

		return array(
			'token' => $token,
			'name'  => $name,
			'size'  => (int) strlen( $binary ),
			'mime'  => 'image/png',
			'path'  => $dest,
		);
	}

	/**
	 * Absolute path for a stored file, or empty if invalid.
	 *
	 * @param string $subdir uploads|signatures subdir constant.
	 * @param string $token  Directory token.
	 * @param string $name   Filename.
	 * @return string
	 */
	public static function absolute_path( $subdir, $token, $name ) {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token );
		$name  = sanitize_file_name( (string) $name );
		if ( '' === $token || '' === $name ) {
			return '';
		}

		$base = self::ensure_base( $subdir );
		if ( '' === $base ) {
			return '';
		}

		$path      = trailingslashit( $base ) . $token . '/' . $name;
		$real      = realpath( $path );
		$base_real = realpath( $base );
		if ( false === $real || false === $base_real ) {
			return '';
		}
		if ( 0 !== strpos( $real, $base_real ) ) {
			return '';
		}
		return is_readable( $real ) ? $real : '';
	}

	/**
	 * REST download URL (requires capability when fetched).
	 *
	 * @param string $kind  'upload' or 'signature'.
	 * @param string $token Token.
	 * @param string $name  Filename.
	 * @return string
	 */
	public static function download_url( $kind, $token, $name ) {
		return add_query_arg(
			array(
				'kind'  => 'signature' === $kind ? 'signature' : 'upload',
				'token' => rawurlencode( (string) $token ),
				'name'  => rawurlencode( (string) $name ),
			),
			rest_url( Rest_Api::NAMESPACE . '/file' )
		);
	}

	/**
	 * Whether a filesystem path is inside our private roots.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_private_path( $path ) {
		$path = (string) $path;
		if ( '' === $path ) {
			return false;
		}
		$real = realpath( $path );
		if ( false === $real ) {
			return false;
		}
		foreach ( array( self::UPLOADS_SUBDIR, self::SIGNATURES_SUBDIR ) as $subdir ) {
			$base = self::ensure_base( $subdir );
			if ( '' === $base ) {
				continue;
			}
			$base_real = realpath( $base );
			if ( false !== $base_real && 0 === strpos( $real, $base_real ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Stream a private file for authorized users.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_download( $request ) {
		if ( ! current_user_can( 'edit_wek_submissions' ) && ! current_user_can( 'manage_we_formkit' ) ) {
			return new \WP_Error( 'we_formkit_forbidden', __( 'You cannot download this file.', 'we-formkit' ), array( 'status' => 403 ) );
		}

		$kind   = sanitize_key( (string) $request->get_param( 'kind' ) );
		$token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$name   = sanitize_file_name( (string) $request->get_param( 'name' ) );
		$subdir = 'signature' === $kind ? self::SIGNATURES_SUBDIR : self::UPLOADS_SUBDIR;
		$path   = self::absolute_path( $subdir, $token, $name );

		if ( '' === $path ) {
			return new \WP_Error( 'we_formkit_not_found', __( 'File not found.', 'we-formkit' ), array( 'status' => 404 ) );
		}

		$mime = wp_check_filetype( $path );
		$type = ! empty( $mime['type'] ) ? $mime['type'] : 'application/octet-stream';

		nocache_headers();
		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: inline; filename="' . $name . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}

	/**
	 * @param string $dir Absolute directory.
	 * @return void
	 */
	private static function write_protection_files( $dir ) {
		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, $rules );
		}
	}
}
