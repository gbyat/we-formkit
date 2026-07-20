<?php
/**
 * Save & Resume drafts.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists in-progress form payloads keyed by resume token.
 */
final class Drafts {

	public const META_ENABLED = '_wek_form_save_resume';
	public const OPTION_KEY   = 'wek_form_drafts';
	public const CRON_HOOK    = 'we_formkit_drafts_cleanup';
	public const TTL_DAYS     = 14;

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function is_enabled( $form_id ) {
		return (bool) get_post_meta( (int) $form_id, self::META_ENABLED, true );
	}

	/**
	 * @param int  $form_id Form ID.
	 * @param bool $enabled Enabled.
	 * @return void
	 */
	public static function set_enabled( $form_id, $enabled ) {
		update_post_meta( (int) $form_id, self::META_ENABLED, $enabled ? 1 : 0 );
	}

	/**
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			Rest_Api::NAMESPACE,
			'/drafts',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_save' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Rest_Api::NAMESPACE,
			'/drafts/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_get' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_save( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$nonce = isset( $params['nonce'] ) ? (string) $params['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'we_formkit_forbidden', __( 'Invalid security token.', 'we-formkit' ), array( 'status' => 403 ) );
		}

		$form_id = isset( $params['form_id'] ) ? absint( $params['form_id'] ) : 0;
		if ( $form_id <= 0 || ! self::is_enabled( $form_id ) ) {
			return new \WP_Error( 'we_formkit_drafts_disabled', __( 'Save & Resume is not enabled for this form.', 'we-formkit' ), array( 'status' => 400 ) );
		}

		$token = isset( $params['token'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) $params['token'] ) : '';
		if ( '' === $token ) {
			$token = wp_generate_password( 32, false, false );
		}

		$payload = array(
			'form_id'    => $form_id,
			'values'     => isset( $params['values'] ) && is_array( $params['values'] ) ? $params['values'] : array(),
			'page_index' => isset( $params['page_index'] ) ? max( 0, (int) $params['page_index'] ) : 0,
			'updated'    => time(),
			'expires'    => time() + ( self::TTL_DAYS * DAY_IN_SECONDS ),
		);

		$all           = self::all();
		$all[ $token ] = $payload;
		update_option( self::OPTION_KEY, $all, false );

		$resume_url = add_query_arg(
			array(
				'wek_resume' => $token,
			),
			isset( $params['page_url'] ) ? esc_url_raw( (string) $params['page_url'] ) : home_url( '/' )
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'token'      => $token,
				'resume_url' => $resume_url,
				'expires'    => $payload['expires'],
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_get( $request ) {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $request['token'] );
		$all   = self::all();
		if ( empty( $all[ $token ] ) || ! is_array( $all[ $token ] ) ) {
			return new \WP_Error( 'we_formkit_draft_missing', __( 'Draft not found or expired.', 'we-formkit' ), array( 'status' => 404 ) );
		}
		$draft = $all[ $token ];
		if ( empty( $draft['expires'] ) || (int) $draft['expires'] < time() ) {
			unset( $all[ $token ] );
			update_option( self::OPTION_KEY, $all, false );
			return new \WP_Error( 'we_formkit_draft_missing', __( 'Draft not found or expired.', 'we-formkit' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'form_id'    => (int) ( $draft['form_id'] ?? 0 ),
				'values'     => isset( $draft['values'] ) && is_array( $draft['values'] ) ? $draft['values'] : array(),
				'page_index' => isset( $draft['page_index'] ) ? (int) $draft['page_index'] : 0,
				'expires'    => (int) ( $draft['expires'] ?? 0 ),
			)
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function all() {
		$raw = get_option( self::OPTION_KEY, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @return void
	 */
	public static function cleanup() {
		$all     = self::all();
		$now     = time();
		$changed = false;
		foreach ( $all as $token => $draft ) {
			if ( ! is_array( $draft ) || empty( $draft['expires'] ) || (int) $draft['expires'] < $now ) {
				unset( $all[ $token ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			update_option( self::OPTION_KEY, $all, false );
		}
	}
}
