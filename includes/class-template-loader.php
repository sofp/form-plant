<?php
/**
 * Template Loader Class
 *
 * WooCommerce-style template override functionality.
 * Allows themes to override plugin templates.
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Template_Loader class
 */
class FPLANT_Template_Loader {

	/**
	 * Plugin template directory
	 */
	const PLUGIN_TEMPLATE_DIR = 'templates/';

	/**
	 * Theme template directory
	 */
	const THEME_TEMPLATE_DIR = 'form-plant/';

	/**
	 * Allowed field types
	 *
	 * @var array
	 */
	private $allowed_field_types = array(
		'text',
		'textarea',
		'email',
		'tel',
		'url',
		'number',
		'date',
		'date_select',
		'time',
		'select',
		'radio',
		'checkbox',
		'file',
		'hidden',
		'html',
	);

	/**
	 * Locate a template file
	 *
	 * Priority:
	 * 1. Child theme: wp-content/themes/child-theme/form-plant/
	 * 2. Parent theme: wp-content/themes/parent-theme/form-plant/
	 * 3. Plugin: wp-content/plugins/form-plant/templates/
	 *
	 * @param string $template_name Template name (e.g., 'form-fields/text.php').
	 * @param string $template_path Optional. Template path in theme. Default 'form-plant/'.
	 * @return string Template file path.
	 */
	public function locate_template( $template_name, $template_path = '' ) {
		// Set default template path.
		if ( empty( $template_path ) ) {
			$template_path = self::THEME_TEMPLATE_DIR;
		}

		// Security: Validate template name.
		$template_name = $this->sanitize_template_name( $template_name );
		if ( empty( $template_name ) ) {
			return '';
		}

		$template = '';

		// 1. Check child theme.
		$theme_template = get_stylesheet_directory() . '/' . $template_path . $template_name;
		if ( $this->is_valid_template_path( $theme_template, get_stylesheet_directory() ) ) {
			$template = $theme_template;
		}

		// 2. Check parent theme (if different from child theme).
		if ( empty( $template ) && get_stylesheet_directory() !== get_template_directory() ) {
			$parent_template = get_template_directory() . '/' . $template_path . $template_name;
			if ( $this->is_valid_template_path( $parent_template, get_template_directory() ) ) {
				$template = $parent_template;
			}
		}

		// 3. Fallback to plugin directory.
		if ( empty( $template ) ) {
			$plugin_template = FPLANT_PLUGIN_DIR . self::PLUGIN_TEMPLATE_DIR . $template_name;
			if ( file_exists( $plugin_template ) ) {
				$template = $plugin_template;
			}
		}

		// Allow customization via filter.
		return apply_filters( 'fplant_locate_template', $template, $template_name, $template_path );
	}

	/**
	 * Locate form field template
	 *
	 * @param string $field_type Field type.
	 * @return string Template file path.
	 */
	public function locate_form_field_template( $field_type ) {
		// Validate field type.
		if ( ! $this->is_valid_field_type( $field_type ) ) {
			return '';
		}

		return $this->locate_template( 'form-fields/' . $field_type . '.php' );
	}

	/**
	 * Locate confirmation field template
	 *
	 * @param string $field_type Field type.
	 * @return string Template file path.
	 */
	public function locate_confirm_field_template( $field_type ) {
		// Validate field type.
		if ( ! $this->is_valid_field_type( $field_type ) ) {
			return '';
		}

		return $this->locate_template( 'confirm-fields/' . $field_type . '.php' );
	}

	/**
	 * Locate confirmation wrapper template
	 *
	 * @return string Template file path.
	 */
	public function locate_confirmation_template() {
		return $this->locate_template( 'confirmation.php' );
	}

	/**
	 * Get template content
	 *
	 * @param string $template_name Template name.
	 * @param array  $args          Variables to pass to template.
	 * @param string $template_path Optional. Template path in theme.
	 * @return string Rendered template content.
	 */
	public function get_template( $template_name, $args = array(), $template_path = '' ) {
		$template = $this->locate_template( $template_name, $template_path );

		if ( empty( $template ) ) {
			return '';
		}

		// Extract variables for use in template.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $args );

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Sanitize template name
	 *
	 * @param string $template_name Template name.
	 * @return string Sanitized template name or empty string if invalid.
	 */
	private function sanitize_template_name( $template_name ) {
		// Prevent path traversal.
		$template_name = str_replace( array( '..', "\0" ), '', $template_name );

		// Only allow alphanumeric characters, hyphens, underscores, slashes, and dots.
		if ( ! preg_match( '/^[a-zA-Z0-9_\-\/\.]+$/', $template_name ) ) {
			return '';
		}

		// Must have .php extension.
		if ( pathinfo( $template_name, PATHINFO_EXTENSION ) !== 'php' ) {
			return '';
		}

		return $template_name;
	}

	/**
	 * Validate field type
	 *
	 * @param string $field_type Field type.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_field_type( $field_type ) {
		// Only alphanumeric and underscores.
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $field_type ) ) {
			return false;
		}

		// Check against allowed types (can be extended via filter).
		$allowed = apply_filters( 'fplant_allowed_field_types', $this->allowed_field_types );
		return in_array( $field_type, $allowed, true );
	}

	/**
	 * Validate template path is within allowed directory
	 *
	 * @param string $template_path Full template path.
	 * @param string $base_dir      Base directory to check against.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_template_path( $template_path, $base_dir ) {
		if ( ! file_exists( $template_path ) ) {
			return false;
		}

		// Normalize paths using realpath.
		$real_path = realpath( $template_path );
		$real_base = realpath( $base_dir );

		if ( false === $real_path || false === $real_base ) {
			return false;
		}

		// Ensure template is within base directory.
		return strpos( $real_path, $real_base . DIRECTORY_SEPARATOR ) === 0;
	}
}
