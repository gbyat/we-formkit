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
			'privacy_policy_url'       => '',
			'retention_days'           => 365,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
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
		$out                             = self::defaults();
		$out['notify_email']             = isset( $input['notify_email'] ) ? sanitize_email( (string) $input['notify_email'] ) : '';
		$out['privacy_policy_url']       = isset( $input['privacy_policy_url'] ) ? esc_url_raw( (string) $input['privacy_policy_url'] ) : '';
		$days                            = isset( $input['retention_days'] ) ? (int) $input['retention_days'] : 365;
		$out['retention_days']           = max( 0, min( 3650, $days ) );
		$out['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		update_option( self::DELETE_DATA_OPTION, $out['delete_data_on_uninstall'] );

		return $out;
	}
}
