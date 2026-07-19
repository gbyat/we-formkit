<?php
/**
 * Retention / GDPR deletion cron.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes old submissions based on retention days.
 */
final class Retention {

	public const CRON_HOOK = 'we_formkit_retention_cleanup';

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function cleanup() {
		$settings = Settings::get();
		$days     = (int) $settings['retention_days'];
		if ( $days <= 0 ) {
			return;
		}

		$before = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$query  = new \WP_Query(
			array(
				'post_type'      => Post_Types::SUBMISSION,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'before' => $before,
					),
				),
				'no_found_rows'  => true,
			)
		);

		foreach ( $query->posts as $id ) {
			wp_delete_post( (int) $id, true );
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
}
