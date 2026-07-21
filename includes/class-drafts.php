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
	public const META_TTL     = '_wek_form_save_resume_ttl';
	public const OPTION_KEY   = 'wek_form_drafts';
	public const CRON_HOOK    = 'we_formkit_drafts_cleanup';
	public const TTL_DAYS     = 14;

	/**
	 * Allowed draft lifetimes in days.
	 *
	 * @return list<int>
	 */
	public static function allowed_ttl_days() {
		return array( 7, 14, 30, 60, 90 );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return int
	 */
	public static function get_ttl_days( $form_id ) {
		$raw = (int) get_post_meta( (int) $form_id, self::META_TTL, true );
		if ( in_array( $raw, self::allowed_ttl_days(), true ) ) {
			return $raw;
		}
		return self::TTL_DAYS;
	}

	/**
	 * @param int $form_id Form ID.
	 * @param int $days    Days.
	 * @return void
	 */
	public static function set_ttl_days( $form_id, $days ) {
		$days = (int) $days;
		if ( ! in_array( $days, self::allowed_ttl_days(), true ) ) {
			$days = self::TTL_DAYS;
		}
		update_post_meta( (int) $form_id, self::META_TTL, $days );
	}

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

		$email = isset( $params['email'] ) ? sanitize_email( (string) $params['email'] ) : '';
		if ( ! is_email( $email ) ) {
			return new \WP_Error(
				'we_formkit_draft_email',
				__( 'Enter a valid email address to receive your resume link.', 'we-formkit' ),
				array( 'status' => 400 )
			);
		}

		$rate_key = 'wek_draft_mail_' . md5( strtolower( $email ) . '|' . (string) $form_id . '|' . self::client_ip() );
		if ( get_transient( $rate_key ) ) {
			return new \WP_Error(
				'we_formkit_draft_rate',
				__( 'Please wait a moment before requesting another resume email.', 'we-formkit' ),
				array( 'status' => 429 )
			);
		}

		$token = isset( $params['token'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) $params['token'] ) : '';
		if ( '' === $token ) {
			$token = wp_generate_password( 32, false, false );
		}

		$ttl_days = self::get_ttl_days( $form_id );
		$expires  = time() + ( $ttl_days * DAY_IN_SECONDS );

		$payload = array(
			'form_id'    => $form_id,
			'email'      => $email,
			'values'     => isset( $params['values'] ) && is_array( $params['values'] ) ? $params['values'] : array(),
			'page_index' => isset( $params['page_index'] ) ? max( 0, (int) $params['page_index'] ) : 0,
			'updated'    => time(),
			'expires'    => $expires,
		);

		$all           = self::all();
		$all[ $token ] = $payload;
		update_option( self::OPTION_KEY, $all, false );

		$page_url = isset( $params['page_url'] ) ? esc_url_raw( (string) $params['page_url'] ) : home_url( '/' );
		if ( '' === $page_url ) {
			$page_url = home_url( '/' );
		}
		$resume_url = add_query_arg(
			array(
				'wek_resume' => $token,
			),
			$page_url
		);

		$sent = self::send_resume_email( $form_id, $email, $resume_url, $expires, $ttl_days );
		if ( $sent ) {
			set_transient( $rate_key, 1, MINUTE_IN_SECONDS );
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'email'      => $email,
				'email_sent' => $sent,
				'expires'    => $expires,
				'ttl_days'   => $ttl_days,
				// Intentionally omit resume_url — the link is only delivered by email.
			)
		);
	}

	/**
	 * @param int    $form_id    Form ID.
	 * @param string $email      Recipient.
	 * @param string $resume_url Resume URL.
	 * @param int    $expires    Unix expiry.
	 * @param int    $ttl_days   Lifetime in days.
	 * @return bool
	 */
	private static function send_resume_email( $form_id, $email, $resume_url, $expires, $ttl_days ) {
		$title = get_the_title( $form_id );
		if ( ! is_string( $title ) || '' === $title ) {
			$title = __( 'your form', 'we-formkit' );
		}

		$date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires );
		if ( ! is_string( $date ) || '' === $date ) {
			$date = (string) $expires;
		}

		$subject = sprintf(
			/* translators: %s: form title. */
			__( 'Resume your progress: %s', 'we-formkit' ),
			$title
		);

		$safe_title = esc_html( $title );
		$safe_url   = esc_url( $resume_url );
		$safe_date  = esc_html( $date );
		$safe_days  = (int) $ttl_days;

		$body  = '<html><body style="font-family:Arial,Helvetica,sans-serif;line-height:1.5;color:#1c1b19;">';
		$body .= '<p>' . esc_html__( 'Hello,', 'we-formkit' ) . '</p>';
		$body .= '<p>' . sprintf(
			/* translators: %s: form title. */
			esc_html__( 'You saved your progress on “%s”. Use the link below to continue where you left off.', 'we-formkit' ),
			$safe_title
		) . '</p>';
		$body .= '<p><a href="' . $safe_url . '" style="display:inline-block;padding:0.7rem 1.1rem;background:#0f766e;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">'
			. esc_html__( 'Continue form', 'we-formkit' )
			. '</a></p>';
		$body .= '<p style="word-break:break-all;font-size:0.9em;color:#5c574f;">' . $safe_url . '</p>';
		$body .= '<p>' . sprintf(
			/* translators: 1: expiry datetime, 2: number of days. */
			esc_html__( 'This link expires on %1$s (about %2$d days).', 'we-formkit' ),
			$safe_date,
			$safe_days
		) . '</p>';
		$body .= '<p>' . esc_html__( 'If you did not request this, you can ignore this email.', 'we-formkit' ) . '</p>';
		$body .= '</body></html>';

		$headers    = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_email = Settings::default_from_email();
		$from_name  = Settings::default_from_name();
		if ( is_email( $from_email ) ) {
			$headers[] = 'From: ' . ( '' !== $from_name ? sprintf( '%s <%s>', $from_name, $from_email ) : $from_email );
		}

		return Mailer::wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return sanitize_text_field( $ip );
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
