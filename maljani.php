<?php

/**
 * The plugin bootstrap file
 *
 *
 * @link              https://kipdevwp.tech
 * @since             1.0.0
 * @package           Maljani
 *
 * @wordpress-plugin
 * Plugin Name:       maljani travel hub
 * Plugin URI:        https://https://github.com/kanji8210/maljani_insuarance_agregator
 * Description:       Maljani Insurance Aggregator is a modular WordPress plugin designed to streamline the management of insurers and policies within a centralized admin interface. It allows administrators to register, display, and manage custom post types for insurer profiles and insurance policies, complete with logo uploads, product listings, and detailed descriptions. Built on a scalable boilerplate architecture, it supports CRUD operations, role-based permissions, and frontend submissions. The plugin also lays the groundwork for REST API integration and dynamic premium calculations, making it ideal for organizations aiming to offer user-friendly insurance comparison tools while maintaining clean code and extendability.
 * Version:           1.0.0
 * Author:            Dennis kip
 * Author URI:        https://kipdevwp.tech/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       maljani
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'MALJANI_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-maljani-activator.php
 */
function activate_maljani() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-activator.php';
	Maljani_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-maljani-deactivator.php
 */
function deactivate_maljani() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-deactivator.php';
	Maljani_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_maljani' );
register_deactivation_hook( __FILE__, 'deactivate_maljani' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-maljani.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_maljani() {

	$plugin = new Maljani();
	$plugin->run();

}
run_maljani();
