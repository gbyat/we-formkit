<?php
/**
 * Module registry for third-party Formkit extensions.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects registered modules and bootstraps them.
 */
final class Module_Registry {

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private $modules = array();

	/**
	 * Hook modules and fire registration action.
	 *
	 * @return void
	 */
	public function register() {
		/**
		 * Register Formkit modules.
		 *
		 * Modules should call `$registry->add( $id, $definition )`.
		 *
		 * @param Module_Registry $registry Registry instance.
		 */
		do_action( 'we_formkit_register_modules', $this );

		foreach ( $this->modules as $id => $definition ) {
			if ( ! empty( $definition['bootstrap'] ) && is_callable( $definition['bootstrap'] ) ) {
				call_user_func( $definition['bootstrap'], $this );
			}

			/**
			 * Fires after a single module is registered.
			 *
			 * @param string               $id         Module id.
			 * @param array<string, mixed> $definition Module definition.
			 */
			do_action( 'we_formkit_module_registered', $id, $definition );
		}
	}

	/**
	 * Add a module definition.
	 *
	 * Expected keys: name (string), description (string), version (string),
	 * bootstrap (callable), optional supports (list of strings).
	 *
	 * @param string               $id         Module id (slug).
	 * @param array<string, mixed> $definition Definition.
	 * @return void
	 */
	public function add( string $id, array $definition ): void {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return;
		}

		$this->modules[ $id ] = array_merge(
			array(
				'name'        => $id,
				'description' => '',
				'version'     => '1.0.0',
				'bootstrap'   => null,
				'supports'    => array(),
			),
			$definition
		);
	}

	/**
	 * @param string $id Module id.
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * Whether a module id is registered.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->modules[ $id ] );
	}
}
