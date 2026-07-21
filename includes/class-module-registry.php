<?php
/**
 * Module registry for optional Formkit extensions.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects registered modules, tracks activation state, and bootstraps the
 * modules that are both activated and have their dependencies satisfied.
 */
final class Module_Registry {

	/**
	 * Option holding the list of activated module ids.
	 *
	 * @var string
	 */
	const ACTIVE_OPTION = 'we_formkit_active_modules';

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private $modules = array();

	/**
	 * Hook modules, then bootstrap the ones that are active and satisfied.
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
			if ( ! $this->is_active( $id ) || ! $this->dependencies_met( $id ) ) {
				continue;
			}

			if ( ! empty( $definition['bootstrap'] ) && is_callable( $definition['bootstrap'] ) ) {
				call_user_func( $definition['bootstrap'], $this );
			}

			/**
			 * Fires after an active module has been bootstrapped.
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
	 * Expected keys:
	 * - name (string), description (string), version (string)
	 * - bootstrap (callable) — run only when active + dependencies satisfied
	 * - dependencies (array of specs; each spec: label + one of
	 *   check(callable)|class|function|constant)
	 * - docs_url (string), supports (list of strings)
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
				'name'         => $id,
				'description'  => '',
				'version'      => '1.0.0',
				'bootstrap'    => null,
				'dependencies' => array(),
				'docs_url'     => '',
				'supports'     => array(),
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

	/**
	 * Ids of modules the admin has activated (regardless of dependency state).
	 *
	 * @return array<int, string>
	 */
	public function active_ids(): array {
		$stored = get_option( self::ACTIVE_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		return array_values( array_map( 'strval', $stored ) );
	}

	/**
	 * @param string $id Module id.
	 * @return bool
	 */
	public function is_active( string $id ): bool {
		return in_array( $id, $this->active_ids(), true );
	}

	/**
	 * Persist the list of activated modules (kept only if registered).
	 *
	 * @param array<int, string> $ids Module ids to activate.
	 * @return void
	 */
	public function set_active( array $ids ): void {
		$clean = array();
		foreach ( $ids as $id ) {
			$id = sanitize_key( (string) $id );
			if ( '' !== $id && $this->has( $id ) ) {
				$clean[] = $id;
			}
		}

		update_option( self::ACTIVE_OPTION, array_values( array_unique( $clean ) ) );
	}

	/**
	 * Per-dependency status for a module.
	 *
	 * @param string $id Module id.
	 * @return array<int, array{label:string,met:bool}>
	 */
	public function dependency_status( string $id ): array {
		$definition = $this->get( $id );
		if ( null === $definition ) {
			return array();
		}

		$deps = isset( $definition['dependencies'] ) && is_array( $definition['dependencies'] )
			? $definition['dependencies']
			: array();

		$out = array();
		foreach ( $deps as $dep ) {
			if ( ! is_array( $dep ) ) {
				continue;
			}
			$out[] = array(
				'label' => isset( $dep['label'] ) ? (string) $dep['label'] : '',
				'met'   => self::evaluate_dependency( $dep ),
			);
		}

		return $out;
	}

	/**
	 * @param string $id Module id.
	 * @return bool
	 */
	public function dependencies_met( string $id ): bool {
		foreach ( $this->dependency_status( $id ) as $dep ) {
			if ( empty( $dep['met'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a module is activated and currently running (deps satisfied).
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public function is_running( string $id ): bool {
		return $this->is_active( $id ) && $this->dependencies_met( $id );
	}

	/**
	 * Evaluate a single dependency spec.
	 *
	 * @param array<string, mixed> $dep Dependency spec.
	 * @return bool
	 */
	private static function evaluate_dependency( array $dep ): bool {
		if ( isset( $dep['check'] ) && is_callable( $dep['check'] ) ) {
			return (bool) call_user_func( $dep['check'] );
		}
		if ( isset( $dep['constant'] ) ) {
			return defined( (string) $dep['constant'] );
		}
		if ( isset( $dep['class'] ) ) {
			return class_exists( (string) $dep['class'] );
		}
		if ( isset( $dep['function'] ) ) {
			return function_exists( (string) $dep['function'] );
		}

		return true;
	}
}
