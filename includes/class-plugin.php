<?php
/**
 * Main plugin bootstrap.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin singleton.
 */
final class Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var Field_Type_Registry|null
	 */
	private $field_registry = null;

	/**
	 * @var Module_Registry|null
	 */
	private $module_registry = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @return void
	 */
	public function init() {
		load_plugin_textdomain( 'we-formkit', false, dirname( plugin_basename( WE_FORMKIT_FILE ) ) . '/languages' );

		$this->field_registry  = new Field_Type_Registry();
		$this->module_registry = new Module_Registry();
		Modules\Akismet_Spam_Module::register();
		$this->module_registry->register();

		Post_Types::register();
		Capabilities::register();
		Frontend::register();
		Rest_Api::register();
		Mailer::register();
		Notifications::register();
		Retention::register();
		Drafts::register();

		add_action( 'admin_init', array( Capabilities::class, 'add_caps' ), 5 );
		add_action( 'admin_init', array( Submission_List_Export::class, 'maybe_export' ) );

		if ( is_admin() ) {
			Admin\Admin::register();
		}
	}

	/**
	 * @return Field_Type_Registry
	 */
	public function field_registry() {
		if ( null === $this->field_registry ) {
			$this->field_registry = new Field_Type_Registry();
		}

		return $this->field_registry;
	}

	/**
	 * @return Module_Registry
	 */
	public function module_registry() {
		if ( null === $this->module_registry ) {
			$this->module_registry = new Module_Registry();
		}

		return $this->module_registry;
	}

	/**
	 * @return void
	 */
	public static function activate() {
		// Activation runs before init; load domain first so CPT labels do not JIT-load too early.
		load_plugin_textdomain( 'we-formkit', false, dirname( plugin_basename( WE_FORMKIT_FILE ) ) . '/languages' );
		Post_Types::register_types();
		Capabilities::add_caps();
		flush_rewrite_rules();
		update_option( Settings::VERSION_OPTION, WE_FORMKIT_VERSION );
	}

	/**
	 * @return void
	 */
	public static function deactivate() {
		Retention::unschedule();
		Drafts::unschedule();
		flush_rewrite_rules();
	}
}
