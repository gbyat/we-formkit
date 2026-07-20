<?php

/**
 * Plugin Name:       WE Formkit
 * Plugin URI:        https://github.com/gbyat/we-formkit
 * Description:       Modular WordPress form builder with typed fields, entries, and a developer module API.
 * Version: 0.2.11
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            webentwicklerin, Gabriele Laesser
 * Author URI:        https://webentwicklerin.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/gbyat/we-formkit
 * Text Domain:       we-formkit
 * Domain Path:       /languages
 *
 * @package Webentwicklerin\WeFormkit
 */

if (! defined('ABSPATH')) {
	exit;
}

define( 'WE_FORMKIT_VERSION', '0.2.11' );
define('WE_FORMKIT_FILE', __FILE__);
define('WE_FORMKIT_PATH', plugin_dir_path(__FILE__));
define('WE_FORMKIT_URL', plugin_dir_url(__FILE__));
define('WE_FORMKIT_GITHUB_REPO', 'gbyat/we-formkit');

$autoload = WE_FORMKIT_PATH . 'vendor/autoload.php';
if (file_exists($autoload)) {
	require_once $autoload;
} else {
	require_once WE_FORMKIT_PATH . 'includes/autoload.php';
}

register_activation_hook(__FILE__, array(Webentwicklerin\WeFormkit\Plugin::class, 'activate'));
register_deactivation_hook(__FILE__, array(Webentwicklerin\WeFormkit\Plugin::class, 'deactivate'));

add_action(
	'plugins_loaded',
	static function () {
		Webentwicklerin\WeFormkit\Plugin::instance()->init();
	}
);
