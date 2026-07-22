<?php
/**
 * Admin bootstrap.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Admin;

use Webentwicklerin\WeFormkit\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin menus and screens.
 */
final class Admin {

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_init', array( Form_Editor::class, 'handle_actions' ) );
		add_action( 'admin_init', array( Submissions::class, 'handle_actions' ) );
		add_action( 'admin_init', array( Settings_Page::class, 'handle_actions' ) );
		add_action( 'admin_init', array( Modules_Page::class, 'handle_actions' ) );
	}

	/**
	 * Add scheme class on Formkit admin screens.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'we-formkit' ) ) {
			return $classes;
		}
		$scheme = \Webentwicklerin\WeFormkit\Settings::admin_scheme();
		return trim( $classes . ' wek-scheme-' . $scheme );
	}

	/**
	 * @return void
	 */
	public static function menu() {
		if ( ! Capabilities::can_manage() ) {
			return;
		}

		add_menu_page(
			__( 'WE Formkit', 'we-formkit' ),
			__( 'WE Formkit', 'we-formkit' ),
			'manage_options',
			'we-formkit',
			array( Form_Editor::class, 'render_list' ),
			'dashicons-clipboard',
			58
		);

		add_submenu_page(
			'we-formkit',
			__( 'Forms', 'we-formkit' ),
			__( 'Forms', 'we-formkit' ),
			'manage_options',
			'we-formkit',
			array( Form_Editor::class, 'render_list' )
		);

		add_submenu_page(
			'we-formkit',
			__( 'Edit Form', 'we-formkit' ),
			__( 'Add Form', 'we-formkit' ),
			'manage_options',
			'we-formkit-form',
			array( Form_Editor::class, 'render_edit' )
		);

		add_submenu_page(
			'we-formkit',
			__( 'Submissions', 'we-formkit' ),
			__( 'Submissions', 'we-formkit' ),
			'manage_options',
			'we-formkit-submissions',
			array( Submissions::class, 'render' )
		);

		add_submenu_page(
			'we-formkit',
			__( 'Settings', 'we-formkit' ),
			__( 'Settings', 'we-formkit' ),
			'manage_options',
			'we-formkit-settings',
			array( Settings_Page::class, 'render' )
		);

		add_submenu_page(
			'we-formkit',
			__( 'Modules', 'we-formkit' ),
			__( 'Modules', 'we-formkit' ),
			'manage_options',
			'we-formkit-modules',
			array( Modules_Page::class, 'render' )
		);
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only.
		$page             = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		$is_plugin_screen = ( 0 === strpos( $page, 'we-formkit' ) )
			|| ( false !== strpos( (string) $hook, 'we-formkit' ) );

		if ( ! $is_plugin_screen ) {
			return;
		}

		wp_enqueue_style(
			'we-formkit-admin',
			WE_FORMKIT_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			WE_FORMKIT_VERSION
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : '';
		if ( 'we-formkit-form' === $page && 'documents' === $view ) {
			wp_enqueue_media();
		}
	}
}
