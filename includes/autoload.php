<?php

/**
 * Minimal autoloader mapping namespaced classes to WordPress-style file names.
 *
 * Example: Webentwicklerin\WeFormkit\Example_Class => includes/class-example-class.php
 *
 * @package Webentwicklerin\WeFormkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Webentwicklerin\WeFormkit\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$relative = str_replace( '\\', '/', $relative );

		$parts            = explode( '/', $relative );
		$short_class_name = array_pop( $parts );
		$file_name        = 'class-' . str_replace( '_', '-', strtolower( $short_class_name ) ) . '.php';

		$sub_path = empty( $parts ) ? '' : strtolower( implode( '/', $parts ) ) . '/';
		$file     = constant( 'WE_FORMKIT_PATH' ) . 'includes/' . $sub_path . $file_name;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
