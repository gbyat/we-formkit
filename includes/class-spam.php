<?php
/**
 * Spam protection without reCAPTCHA.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Honeypot, timing, and rate limiting.
 */
final class Spam {

	public const MIN_SECONDS = 3;
	public const RATE_LIMIT  = 8;
	public const RATE_WINDOW = 3600;

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return true|\WP_Error
	 */
	public static function validate( array $payload ) {
		$honeypot = isset( $payload['website_url'] ) ? (string) $payload['website_url'] : '';
		if ( '' !== trim( $honeypot ) ) {
			return new \WP_Error( 'we_formkit_spam', __( 'Submission rejected.', 'we-formkit' ), array( 'status' => 400 ) );
		}

		$started = isset( $payload['_wek_started'] ) ? (int) $payload['_wek_started'] : 0;
		$now     = time();
		if ( $started <= 0 || ( $now - $started ) < self::MIN_SECONDS ) {
			return new \WP_Error( 'we_formkit_too_fast', __( 'Please take a moment to complete the form before submitting.', 'we-formkit' ), array( 'status' => 400 ) );
		}

		if ( ( $now - $started ) > WEEK_IN_SECONDS ) {
			return new \WP_Error( 'we_formkit_expired', __( 'This form session expired. Please reload the page and try again.', 'we-formkit' ), array( 'status' => 400 ) );
		}

		$ip = self::client_ip();
		if ( '' !== $ip && self::is_rate_limited( $ip ) ) {
			return new \WP_Error( 'we_formkit_rate_limited', __( 'Too many submissions. Please try again later.', 'we-formkit' ), array( 'status' => 429 ) );
		}

		return true;
	}

	/**
	 * @param string $ip IP address.
	 * @return void
	 */
	public static function record_attempt( $ip ) {
		if ( '' === $ip ) {
			return;
		}
		$key   = 'we_formkit_rate_' . md5( $ip );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::RATE_WINDOW );
	}

	/**
	 * @param string $ip IP address.
	 * @return bool
	 */
	private static function is_rate_limited( $ip ) {
		$key   = 'we_formkit_rate_' . md5( $ip );
		$count = (int) get_transient( $key );
		return $count >= self::RATE_LIMIT;
	}

	/**
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * One-way hash for limited forensic use (not stored as plain IP).
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	public static function hash_ip( $ip ) {
		if ( '' === $ip ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}
}
