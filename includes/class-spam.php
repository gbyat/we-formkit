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
 * Honeypot, timing, and rate limiting (each toggleable in Settings).
 */
final class Spam {

	public const MIN_SECONDS = 3;
	public const RATE_LIMIT  = 8;
	public const RATE_WINDOW = 3600;

	/**
	 * Effective spam settings (booleans + numeric limits).
	 *
	 * @return array{
	 *     honeypot:bool,
	 *     timing:bool,
	 *     timing_min:int,
	 *     rate_limit:bool,
	 *     rate_max:int,
	 *     store_ip_hash:bool
	 * }
	 */
	public static function options() {
		$settings = Settings::get();
		return array(
			'honeypot'      => ! empty( $settings['spam_honeypot'] ),
			'timing'        => ! empty( $settings['spam_timing'] ),
			'timing_min'    => max( 1, min( 60, (int) ( $settings['spam_timing_min'] ?? self::MIN_SECONDS ) ) ),
			'rate_limit'    => ! empty( $settings['spam_rate_limit'] ),
			'rate_max'      => max( 1, min( 100, (int) ( $settings['spam_rate_max'] ?? self::RATE_LIMIT ) ) ),
			'store_ip_hash' => ! empty( $settings['spam_store_ip_hash'] ),
		);
	}

	/**
	 * @return bool
	 */
	public static function honeypot_enabled() {
		return self::options()['honeypot'];
	}

	/**
	 * @return bool
	 */
	public static function store_ip_hash_enabled() {
		return self::options()['store_ip_hash'];
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return true|\WP_Error
	 */
	public static function validate( array $payload ) {
		$opts = self::options();

		if ( $opts['honeypot'] ) {
			$honeypot = isset( $payload['website_url'] ) ? (string) $payload['website_url'] : '';
			if ( '' !== trim( $honeypot ) ) {
				return new \WP_Error( 'we_formkit_spam', __( 'Submission rejected.', 'we-formkit' ), array( 'status' => 400 ) );
			}
		}

		if ( $opts['timing'] ) {
			$started = isset( $payload['_wek_started'] ) ? (int) $payload['_wek_started'] : 0;
			$now     = time();
			$min     = $opts['timing_min'];
			if ( $started <= 0 || ( $now - $started ) < $min ) {
				return new \WP_Error( 'we_formkit_too_fast', __( 'Please take a moment to complete the form before submitting.', 'we-formkit' ), array( 'status' => 400 ) );
			}

			if ( ( $now - $started ) > WEEK_IN_SECONDS ) {
				return new \WP_Error( 'we_formkit_expired', __( 'This form session expired. Please reload the page and try again.', 'we-formkit' ), array( 'status' => 400 ) );
			}
		}

		if ( $opts['rate_limit'] ) {
			$ip = self::client_ip();
			if ( '' !== $ip && self::is_rate_limited( $ip, $opts['rate_max'] ) ) {
				return new \WP_Error( 'we_formkit_rate_limited', __( 'Too many submissions. Please try again later.', 'we-formkit' ), array( 'status' => 429 ) );
			}
		}

		return true;
	}

	/**
	 * @param string $ip IP address.
	 * @return void
	 */
	public static function record_attempt( $ip ) {
		if ( ! self::options()['rate_limit'] || '' === $ip ) {
			return;
		}
		$key   = 'we_formkit_rate_' . md5( $ip );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::RATE_WINDOW );
	}

	/**
	 * @param string $ip       IP address.
	 * @param int    $rate_max Max attempts in the window.
	 * @return bool
	 */
	private static function is_rate_limited( $ip, $rate_max ) {
		$key   = 'we_formkit_rate_' . md5( $ip );
		$count = (int) get_transient( $key );
		return $count >= max( 1, (int) $rate_max );
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
	 * @return string Empty when hashing is disabled or IP missing.
	 */
	public static function hash_ip( $ip ) {
		if ( ! self::store_ip_hash_enabled() || '' === $ip ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}
}
