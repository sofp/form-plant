<?php
/**
 * Plugin Name: Form Plant
 * Plugin URI: https://www.sofplant.com/form-plant/
 * Description: A versatile form plugin with an intuitive field editor and flexible customization options.
 * Version: 1.3.0
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
define( 'FPLANT_VERSION', '1.3.0' );
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

/**
 * Get allowed HTML tags for form output.
 *
 * Returns an expanded set of HTML tags/attributes allowed in form output.
 * Used with wp_kses() for do_shortcode() and render_field() output.
 *
 * @return array Allowed HTML tags and their attributes.
 */
function fplant_get_allowed_form_html() {
	$allowed = wp_kses_allowed_html( 'post' );

	$form_tags = array(
		'form'     => array(
			'action'     => true,
			'method'     => true,
			'class'      => true,
			'id'         => true,
			'enctype'    => true,
			'novalidate' => true,
		),
		'input'    => array(
			'type'         => true,
			'name'         => true,
			'value'        => true,
			'class'        => true,
			'id'           => true,
			'placeholder'  => true,
			'required'     => true,
			'checked'      => true,
			'disabled'     => true,
			'readonly'     => true,
			'maxlength'    => true,
			'minlength'    => true,
			'size'         => true,
			'min'          => true,
			'max'          => true,
			'step'         => true,
			'accept'       => true,
			'multiple'     => true,
			'pattern'      => true,
			'autocomplete' => true,
			'style'        => true,
		),
		'textarea' => array(
			'name'        => true,
			'class'       => true,
			'id'          => true,
			'rows'        => true,
			'cols'        => true,
			'placeholder' => true,
			'required'    => true,
			'disabled'    => true,
			'readonly'    => true,
			'maxlength'   => true,
			'style'       => true,
		),
		'select'   => array(
			'name'     => true,
			'class'    => true,
			'id'       => true,
			'required' => true,
			'disabled' => true,
			'multiple' => true,
			'style'    => true,
		),
		'option'   => array(
			'value'    => true,
			'selected' => true,
			'disabled' => true,
		),
		'optgroup' => array(
			'label'    => true,
			'disabled' => true,
		),
		'label'    => array(
			'for'   => true,
			'class' => true,
			'id'    => true,
		),
		'button'   => array(
			'type'     => true,
			'name'     => true,
			'value'    => true,
			'class'    => true,
			'id'       => true,
			'disabled' => true,
			'style'    => true,
		),
		'fieldset' => array(
			'class'    => true,
			'id'       => true,
			'disabled' => true,
		),
		'legend'   => array(
			'class' => true,
		),
	);

	return array_merge( $allowed, $form_tags );
}

/**
 * Replace template values in HTML template.
 *
 * Replaces {{key}} placeholders with values provided via the 'fplant_template_values' filter.
 * Values are automatically escaped with esc_html() for security.
 *
 * @param string $html    HTML template string.
 * @param int    $form_id Form ID.
 * @return string HTML with template values replaced.
 */
function fplant_replace_template_values( $html, $form_id ) {
	$values = apply_filters( 'fplant_template_values', array(), $form_id );

	if ( empty( $values ) ) {
		return $html;
	}

	foreach ( $values as $key => $value ) {
		$html = str_replace( '{{' . $key . '}}', esc_html( $value ), $html );
	}

	return $html;
}
