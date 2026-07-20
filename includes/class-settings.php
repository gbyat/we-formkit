<?php
/**
 * Plugin settings helpers.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global settings option.
 */
final class Settings {

	public const OPTION             = 'we_formkit_settings';
	public const DELETE_DATA_OPTION = 'we_formkit_delete_data_on_uninstall';
	public const VERSION_OPTION     = 'we_formkit_version';

	/**
	 * Default settings values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'notify_email'             => '',
			'from_name'                => '',
			'from_email'               => '',
			'mail_transport'           => 'wp_default',
			'smtp_host'                => '',
			'smtp_port'                => 587,
			'smtp_encryption'          => 'tls',
			'smtp_auth'                => true,
			'smtp_username'            => '',
			'smtp_password'            => '',
			'privacy_policy_mode'      => 'wp',
			'privacy_policy_url'       => '',
			'validation_required'      => '',
			'validation_invalid'       => '',
			'retention_days'           => 365,
			'delete_data_on_uninstall' => false,
			'admin_scheme'             => 'brick',
		);
	}

	/**
	 * Allowed admin UI color schemes (CSS data-wek-scheme).
	 *
	 * @return array<string, string> slug => label
	 */
	public static function admin_schemes() {
		return array(
			'brick'  => __( 'Brick', 'we-formkit' ),
			'teal'   => __( 'Teal', 'we-formkit' ),
			'blue'   => __( 'Blue', 'we-formkit' ),
			'slate'  => __( 'Slate', 'we-formkit' ),
			'violet' => __( 'Violet', 'we-formkit' ),
		);
	}

	/**
	 * Current admin scheme slug.
	 *
	 * @return string
	 */
	public static function admin_scheme() {
		$settings = self::get();
		$scheme   = isset( $settings['admin_scheme'] ) ? sanitize_key( (string) $settings['admin_scheme'] ) : 'brick';
		$allowed  = self::admin_schemes();
		return isset( $allowed[ $scheme ] ) ? $scheme : 'brick';
	}

	/**
	 * Get merged settings (legacy privacy URL implies custom mode).
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$merged = array_merge( self::defaults(), $stored );

		if ( ! isset( $stored['privacy_policy_mode'] ) && ! empty( $merged['privacy_policy_url'] ) ) {
			$merged['privacy_policy_mode'] = 'custom';
		}
		if ( 'custom' !== $merged['privacy_policy_mode'] ) {
			$merged['privacy_policy_mode'] = 'wp';
		}

		return $merged;
	}

	/**
	 * Default notification recipient (plugin setting, else site admin email).
	 *
	 * @return string
	 */
	public static function default_notify_email() {
		$settings = self::get();
		$email    = isset( $settings['notify_email'] ) ? (string) $settings['notify_email'] : '';
		if ( is_email( $email ) ) {
			return $email;
		}
		$admin = (string) get_option( 'admin_email' );
		return is_email( $admin ) ? $admin : '';
	}

	/**
	 * Default From name for notification emails.
	 *
	 * @return string
	 */
	public static function default_from_name() {
		$settings = self::get();
		$name     = isset( $settings['from_name'] ) ? trim( (string) $settings['from_name'] ) : '';
		if ( '' !== $name ) {
			return $name;
		}
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Default From email for notification emails.
	 *
	 * @return string
	 */
	public static function default_from_email() {
		$settings = self::get();
		$email    = isset( $settings['from_email'] ) ? (string) $settings['from_email'] : '';
		if ( is_email( $email ) ) {
			return $email;
		}
		$admin = (string) get_option( 'admin_email' );
		return is_email( $admin ) ? $admin : '';
	}

	/**
	 * Resolved privacy policy URL for forms that do not override it.
	 *
	 * @return string
	 */
	public static function privacy_policy_url() {
		$settings = self::get();
		if ( 'custom' === $settings['privacy_policy_mode'] ) {
			$url = isset( $settings['privacy_policy_url'] ) ? (string) $settings['privacy_policy_url'] : '';
			return $url;
		}
		$page = self::wp_privacy_page();
		return $page['url'];
	}

	/**
	 * WordPress privacy policy page details for settings UI and URL resolution.
	 *
	 * @return array{id:int,title:string,url:string,configured:bool}
	 */
	public static function wp_privacy_page() {
		$page_id = (int) get_option( 'wp_page_for_privacy_policy' );
		$empty   = array(
			'id'         => 0,
			'title'      => '',
			'url'        => '',
			'configured' => false,
		);
		if ( $page_id < 1 ) {
			return $empty;
		}

		$post = get_post( $page_id );
		if ( ! $post instanceof \WP_Post || 'trash' === $post->post_status ) {
			return $empty;
		}

		$title = get_the_title( $post );
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			/* translators: %d: page ID */
			$title = sprintf( __( 'Page #%d', 'we-formkit' ), $page_id );
		}

		$url = get_privacy_policy_url();
		if ( ! is_string( $url ) || '' === $url ) {
			$permalink = get_permalink( $post );
			$url       = ( is_string( $permalink ) && '' !== $permalink ) ? $permalink : '';
		}

		return array(
			'id'         => $page_id,
			'title'      => $title,
			'url'        => $url,
			'configured' => true,
		);
	}

	/**
	 * Label for the WordPress privacy policy page (for settings UI).
	 *
	 * @return string Empty when no page is configured.
	 */
	public static function wp_privacy_page_label() {
		$page = self::wp_privacy_page();
		return $page['configured'] ? $page['title'] : '';
	}

	/**
	 * Sanitize and persist-ready settings from raw input.
	 *
	 * Also syncs the standalone uninstall flag used by uninstall.php.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ) {
		$out = self::defaults();

		$admin_email = (string) get_option( 'admin_email' );
		$notify      = isset( $input['notify_email'] ) ? sanitize_email( (string) $input['notify_email'] ) : '';
		// Empty or same as site admin → follow admin email dynamically.
		if ( '' === $notify || ( is_email( $admin_email ) && strtolower( $notify ) === strtolower( $admin_email ) ) ) {
			$out['notify_email'] = '';
		} else {
			$out['notify_email'] = $notify;
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$from_name = isset( $input['from_name'] ) ? sanitize_text_field( (string) $input['from_name'] ) : '';
		if ( '' === $from_name || $from_name === $site_name ) {
			$out['from_name'] = '';
		} else {
			$out['from_name'] = $from_name;
		}

		$from_email = isset( $input['from_email'] ) ? sanitize_email( (string) $input['from_email'] ) : '';
		if ( '' === $from_email || ( is_email( $admin_email ) && strtolower( $from_email ) === strtolower( $admin_email ) ) ) {
			$out['from_email'] = '';
		} else {
			$out['from_email'] = $from_email;
		}

		$current               = self::get();
		$transport             = isset( $input['mail_transport'] ) ? sanitize_key( (string) $input['mail_transport'] ) : 'wp_default';
		$allowed_tr            = array( 'wp_default', 'smtp', 'gmail', 'subscribe_to_posts' );
		$out['mail_transport'] = in_array( $transport, $allowed_tr, true ) ? $transport : 'wp_default';
		if ( 'subscribe_to_posts' === $out['mail_transport'] && ! Mailer::subscribe_to_posts_active() ) {
			$out['mail_transport'] = 'wp_default';
		}

		$out['smtp_host'] = isset( $input['smtp_host'] ) ? sanitize_text_field( (string) $input['smtp_host'] ) : '';
		$port             = isset( $input['smtp_port'] ) ? (int) $input['smtp_port'] : 587;
		$out['smtp_port'] = max( 1, min( 65535, $port ) );

		$enc                    = isset( $input['smtp_encryption'] ) ? sanitize_key( (string) $input['smtp_encryption'] ) : 'tls';
		$allowed_enc            = array( 'none', 'tls', 'ssl' );
		$out['smtp_encryption'] = in_array( $enc, $allowed_enc, true ) ? $enc : 'tls';
		$out['smtp_auth']       = ! empty( $input['smtp_auth'] );
		$out['smtp_username']   = isset( $input['smtp_username'] ) ? sanitize_text_field( (string) $input['smtp_username'] ) : '';

		$raw_password = isset( $input['smtp_password'] ) ? (string) $input['smtp_password'] : '';
		if ( '' === $raw_password ) {
			$out['smtp_password'] = isset( $current['smtp_password'] ) ? (string) $current['smtp_password'] : '';
		} else {
			$out['smtp_password'] = $raw_password;
		}

		if ( 'gmail' === $out['mail_transport'] ) {
			$out['smtp_host']       = 'smtp.gmail.com';
			$out['smtp_port']       = 587;
			$out['smtp_encryption'] = 'tls';
			$out['smtp_auth']       = true;
		}

		$mode                       = isset( $input['privacy_policy_mode'] ) ? sanitize_key( (string) $input['privacy_policy_mode'] ) : 'wp';
		$out['privacy_policy_mode'] = 'custom' === $mode ? 'custom' : 'wp';
		$out['privacy_policy_url']  = '';
		if ( 'custom' === $out['privacy_policy_mode'] ) {
			$out['privacy_policy_url'] = isset( $input['privacy_policy_url'] ) ? esc_url_raw( (string) $input['privacy_policy_url'] ) : '';
		}

		$req_tpl = isset( $input['validation_required'] ) ? sanitize_text_field( (string) $input['validation_required'] ) : '';
		$inv_tpl = isset( $input['validation_invalid'] ) ? sanitize_text_field( (string) $input['validation_invalid'] ) : '';
		// Store empty when matching built-in so locale updates still apply.
		$out['validation_required'] = ( Validation_Messages::builtin_required_template() === $req_tpl ) ? '' : $req_tpl;
		$out['validation_invalid']  = ( Validation_Messages::builtin_invalid_template() === $inv_tpl ) ? '' : $inv_tpl;

		$days                            = isset( $input['retention_days'] ) ? (int) $input['retention_days'] : 365;
		$out['retention_days']           = max( 0, min( 3650, $days ) );
		$out['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		$scheme              = isset( $input['admin_scheme'] ) ? sanitize_key( (string) $input['admin_scheme'] ) : 'brick';
		$allowed             = self::admin_schemes();
		$out['admin_scheme'] = isset( $allowed[ $scheme ] ) ? $scheme : 'brick';

		update_option( self::DELETE_DATA_OPTION, $out['delete_data_on_uninstall'] );

		return $out;
	}
}
