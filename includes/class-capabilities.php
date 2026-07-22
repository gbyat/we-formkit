<?php
/**
 * Capability helpers.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caps for forms and submissions.
 */
final class Capabilities {

	/**
	 * Reserved for future role-sync hooks.
	 *
	 * @return void
	 */
	public static function register() {
		// Caps are added on activation and kept in sync on admin_init.
	}

	/**
	 * Capability list for form and submission CPTs plus manage.
	 *
	 * @return list<string>
	 */
	public static function caps() {
		return array(
			'edit_wek_forms',
			'edit_others_wek_forms',
			'publish_wek_forms',
			'read_private_wek_forms',
			'delete_wek_forms',
			'delete_others_wek_forms',
			'edit_wek_submissions',
			'edit_others_wek_submissions',
			'publish_wek_submissions',
			'read_private_wek_submissions',
			'delete_wek_submissions',
			'delete_others_wek_submissions',
			'manage_we_formkit',
		);
	}

	/**
	 * Grant caps to administrator and editor roles.
	 *
	 * @return void
	 */
	public static function add_caps() {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove caps from administrator and editor roles.
	 *
	 * @return void
	 */
	public static function remove_caps() {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Whether the current user may manage Formkit settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		$allowed = current_user_can( 'manage_we_formkit' ) || current_user_can( 'manage_options' );

		/**
		 * Whether the current user may manage Formkit (settings, modules, full admin UI).
		 *
		 * @param bool $allowed Whether allowed.
		 * @param int  $user_id Current user ID.
		 */
		return (bool) apply_filters( 'we_formkit_can_manage', $allowed, get_current_user_id() );
	}
}
