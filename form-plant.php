<?php
/**
 * Plugin Name: Form Plant
 * Plugin URI: https://github.com/sofplant/form-plant
 * Description: A versatile form plugin with easy modal-based setup and flexible customization options.
 * Version: 1.0.0
 * Author: SOFPLANT
 * Author URI: https://www.sofplant.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: form-plant
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'FPLANT_VERSION', '1.0.0' );
define( 'FPLANT_PLUGIN_FILE', __FILE__ );
define( 'FPLANT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPLANT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FPLANT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load main plugin class
 */
require_once FPLANT_PLUGIN_DIR . 'includes/class-form-plant.php';

/**
 * Initialize plugin
 */
function fplant_init() {
	return FPLANT_Form_Plant::get_instance();
}

// Plugin initialization
add_action( 'plugins_loaded', 'fplant_init' );

/**
 * Plugin activation handler
 */
function fplant_activate() {
	// Activation process
	require_once FPLANT_PLUGIN_DIR . 'includes/class-form-plant.php';
	FPLANT_Form_Plant::activate();
}
register_activation_hook( __FILE__, 'fplant_activate' );

/**
 * Plugin deactivation handler
 */
function fplant_deactivate() {
	// Deactivation process
	require_once FPLANT_PLUGIN_DIR . 'includes/class-form-plant.php';
	FPLANT_Form_Plant::deactivate();
}
register_deactivation_hook( __FILE__, 'fplant_deactivate' );
