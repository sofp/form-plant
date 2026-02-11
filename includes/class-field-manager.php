<?php
/**
 * Field management class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Field_Manager class
 */
class FPLANT_Field_Manager {

	/**
	 * Supported field types
	 *
	 * @var array
	 */
	private $field_types = array(
		'text',
		'textarea',
		'email',
		'tel',
		'url',
		'number',
		'date',
		'time',
		'select',
		'radio',
		'checkbox',
		'file',
		'hidden',
		'html',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// No hooks needed (prevent infinite loop)
	}

	/**
	 * Get field types list
	 *
	 * @return array
	 */
	public function get_field_types() {
		$field_types = array(
			'text'     => array(
				'label'       => __( 'Text', 'form-plant' ),
				'icon'        => 'dashicons-edit',
				'description' => __( 'Single line text input field', 'form-plant' ),
			),
			'textarea' => array(
				'label'       => __( 'Textarea', 'form-plant' ),
				'icon'        => 'dashicons-text',
				'description' => __( 'Multi-line text input field', 'form-plant' ),
			),
			'email'    => array(
				'label'       => __( 'Email', 'form-plant' ),
				'icon'        => 'dashicons-email',
				'description' => __( 'Email address input field', 'form-plant' ),
			),
			'tel'      => array(
				'label'       => __( 'Phone', 'form-plant' ),
				'icon'        => 'dashicons-phone',
				'description' => __( 'Phone number input field', 'form-plant' ),
			),
			'url'      => array(
				'label'       => __( 'URL', 'form-plant' ),
				'icon'        => 'dashicons-admin-links',
				'description' => __( 'URL input field', 'form-plant' ),
			),
			'number'   => array(
				'label'       => __( 'Number', 'form-plant' ),
				'icon'        => 'dashicons-calculator',
				'description' => __( 'Number input field', 'form-plant' ),
			),
			'date'     => array(
				'label'       => __( 'Date', 'form-plant' ),
				'icon'        => 'dashicons-calendar',
				'description' => __( 'Date picker field', 'form-plant' ),
			),
			'time'     => array(
				'label'       => __( 'Time', 'form-plant' ),
				'icon'        => 'dashicons-clock',
				'description' => __( 'Time picker field', 'form-plant' ),
			),
			'select'   => array(
				'label'       => __( 'Select', 'form-plant' ),
				'icon'        => 'dashicons-menu-alt',
				'description' => __( 'Dropdown selection field', 'form-plant' ),
			),
			'radio'    => array(
				'label'       => __( 'Radio', 'form-plant' ),
				'icon'        => 'dashicons-marker',
				'description' => __( 'Single selection field', 'form-plant' ),
			),
			'checkbox' => array(
				'label'       => __( 'Checkbox', 'form-plant' ),
				'icon'        => 'dashicons-yes',
				'description' => __( 'Multiple selection field', 'form-plant' ),
			),
			'file'     => array(
				'label'       => __( 'File Upload', 'form-plant' ),
				'icon'        => 'dashicons-upload',
				'description' => __( 'File upload field', 'form-plant' ),
			),
			'hidden'   => array(
				'label'       => __( 'Hidden', 'form-plant' ),
				'icon'        => 'dashicons-hidden',
				'description' => __( 'Hidden field not displayed on screen', 'form-plant' ),
			),
			'html'     => array(
				'label'       => __( 'HTML', 'form-plant' ),
				'icon'        => 'dashicons-editor-code',
				'description' => __( 'Custom HTML content', 'form-plant' ),
			),
		);

		return apply_filters( 'fplant_field_types', $field_types );
	}

	/**
	 * Get field default settings
	 *
	 * @param string $field_type Field type
	 * @return array
	 */
	public function get_field_defaults( $field_type ) {
		$defaults = array(
			'type'         => $field_type,
			'label'        => '',
			'name'         => '',
			'placeholder'  => '',
			'default'      => '',
			'required'     => false,
			'class'        => '',
			'custom_id'    => '',
			'custom_class' => '',
			'validation'   => array(),
			'conditional'  => array(
				'enabled' => false,
				'field'   => '',
				'value'   => '',
			),
		);

		// Additional settings per field type
		switch ( $field_type ) {
			case 'textarea':
				$defaults['rows'] = 5;
				break;

			case 'select':
				$defaults['options'] = array();
				break;

			case 'radio':
				$defaults['options'] = array();
				$defaults['layout']  = 'vertical';
				break;

			case 'checkbox':
				$defaults['options']   = array();
				$defaults['layout']    = 'vertical';
				$defaults['delimiter'] = ', ';
				break;

			case 'file':
				$defaults['allowed_types'] = array( 'jpg', 'jpeg', 'png', 'gif', 'pdf' );
				$defaults['max_size']      = 5;
				$defaults['multiple']      = false;
				break;

			case 'number':
				$defaults['min']  = '';
				$defaults['max']  = '';
				$defaults['step'] = '';
				break;

			case 'date':
				$defaults['format']   = 'Y-m-d';
				$defaults['min_date'] = '';
				$defaults['max_date'] = '';
				break;

			case 'html':
				$defaults['content'] = '';
				break;
		}

		return apply_filters( 'fplant_field_defaults', $defaults, $field_type );
	}

	/**
	 * Validate field
	 *
	 * @param array $field Field configuration
	 * @return bool|WP_Error
	 */
	public function validate_field( $field ) {
		if ( empty( $field['type'] ) ) {
			return new WP_Error( 'missing_type', __( 'Field type is not specified', 'form-plant' ) );
		}

		if ( empty( $field['name'] ) ) {
			return new WP_Error( 'missing_name', __( 'Field name is not specified', 'form-plant' ) );
		}

		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $field['name'] ) ) {
			return new WP_Error( 'invalid_name', __( 'Field name can only contain alphanumeric characters and underscores', 'form-plant' ) );
		}

		if ( ! in_array( $field['type'], $this->field_types, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Unsupported field type', 'form-plant' ) );
		}

		return true;
	}

	/**
	 * Generate field HTML
	 *
	 * @param array  $field         Field configuration
	 * @param string $value         Value
	 * @param int    $form_id       Form ID
	 * @param array  $form_settings Form settings (allow_url_params, etc.)
	 * @return string
	 */
	public function render_field( $field, $value = '', $form_id = 0, $form_settings = array() ) {
		$field = wp_parse_args( $field, $this->get_field_defaults( $field['type'] ) );

		// Get initial value if $value is empty
		if ( '' === $value || ( is_array( $value ) && empty( $value ) ) ) {
			$value = $this->get_field_initial_value( $field, $form_id, $form_settings );
		}

		// Use template loader for theme override support.
		$template_loader = new FPLANT_Template_Loader();
		$template        = $template_loader->locate_form_field_template( $field['type'] );

		if ( empty( $template ) ) {
			return '';
		}

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Generate confirmation screen field HTML
	 *
	 * @param array        $field    Field configuration
	 * @param string|array $value    Value
	 * @param string       $filename Filename for file fields
	 * @return string
	 */
	public function render_confirm_field( $field, $value = '', $filename = '' ) {
		$field = wp_parse_args( $field, $this->get_field_defaults( $field['type'] ) );

		// Use template loader for theme override support.
		$template_loader = new FPLANT_Template_Loader();
		$template        = $template_loader->locate_confirm_field_template( $field['type'] );

		if ( empty( $template ) ) {
			return '';
		}

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Get field initial value
	 *
	 * Priority:
	 * 1. URL parameter (only if allowed in settings AND default value is {field_name})
	 * 2. Filter fplant_field_initial_value
	 * 3. Field default value
	 *
	 * @param array $field         Field configuration
	 * @param int   $form_id       Form ID
	 * @param array $form_settings Form settings (allow_url_params, etc.)
	 * @return mixed Initial value
	 */
	public function get_field_initial_value( $field, $form_id, $form_settings = array() ) {
		$field_name    = $field['name'];
		$default_value = isset( $field['default'] ) ? $field['default'] : '';

		// 1. Get from URL parameters
		//    Condition: allowed in settings AND default value is {field_name} format
		$allow_url_params = ! empty( $form_settings['allow_url_params'] );
		if ( $allow_url_params && $this->is_url_param_placeholder( $default_value, $field_name ) ) {
			$url_value = $this->get_value_from_url( $field_name, $field['type'] );
			if ( null !== $url_value ) {
				return $url_value;
			}
			// Return empty string if URL parameter doesn't exist (don't show placeholder)
			return '';
		}

		// 2. Initial value via filter
		$filtered_value = apply_filters( 'fplant_field_initial_value', null, $field_name, $field, $form_id );
		$filtered_value = apply_filters( "fplant_field_initial_value_{$field_name}", $filtered_value, $field, $form_id );
		if ( null !== $filtered_value ) {
			return $filtered_value;
		}

		// 3. Default value
		return $default_value;
	}

	/**
	 * Check if default value is URL parameter placeholder
	 *
	 * @param string $default_value Default value
	 * @param string $field_name    Field name
	 * @return bool
	 */
	private function is_url_param_placeholder( $default_value, $field_name ) {
		return '{' . $field_name . '}' === $default_value;
	}

	/**
	 * Get value from URL parameters (with security measures)
	 *
	 * @param string $field_name Field name
	 * @param string $field_type Field type
	 * @return mixed|null Retrieved value, or null if not exists
	 */
	private function get_value_from_url( $field_name, $field_type ) {
		// Field name validation (only allow alphanumeric and underscore)
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $field_name ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ $field_name ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field type below
		$raw_value = wp_unslash( $_GET[ $field_name ] );

		// Sanitize per field type
		switch ( $field_type ) {
			case 'email':
				return sanitize_email( $raw_value );

			case 'url':
				return esc_url_raw( $raw_value );

			case 'number':
				return is_numeric( $raw_value ) ? floatval( $raw_value ) : null;

			case 'checkbox':
				// Support multiple selection with comma separator
				if ( is_string( $raw_value ) && strpos( $raw_value, ',' ) !== false ) {
					$values = explode( ',', $raw_value );
					return array_map( 'sanitize_text_field', $values );
				}
				// If passed as array
				if ( is_array( $raw_value ) ) {
					return array_map( 'sanitize_text_field', $raw_value );
				}
				return array( sanitize_text_field( $raw_value ) );

			case 'date':
			case 'date_select':
				// YYYY-MM-DD format validation
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_value ) ) {
					return sanitize_text_field( $raw_value );
				}
				return null;

			case 'textarea':
				return sanitize_textarea_field( $raw_value );

			default:
				return sanitize_text_field( $raw_value );
		}
	}

	/**
	 * Get field settings by field name
	 *
	 * @param string $field_name Field name
	 * @param array  $fields     Field list
	 * @return array|null
	 */
	public function get_field_by_name( $field_name, $fields ) {
		foreach ( $fields as $field ) {
			if ( $field['name'] === $field_name ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Render confirmation screen HTML
	 *
	 * @param array $form Form data.
	 * @param array $data Submitted data.
	 * @param array $filenames Optional file names for file fields.
	 * @return string Confirmation HTML.
	 */
	public function render_confirmation( $form, $data, $filenames = array() ) {
		$settings            = isset( $form['settings'] ) ? $form['settings'] : array();
		$use_custom_template = ! empty( $settings['use_confirmation_template'] );
		$custom_template     = isset( $settings['confirmation_template'] ) ? $settings['confirmation_template'] : '';

		if ( $use_custom_template && ! empty( $custom_template ) ) {
			// Use custom HTML template.
			return $this->render_custom_confirmation_template( $form, $data, $custom_template, $filenames );
		}

		// Use default template.
		return $this->render_default_confirmation( $form, $data, $filenames );
	}

	/**
	 * Render default confirmation screen
	 *
	 * @param array $form      Form data.
	 * @param array $data      Submitted data.
	 * @param array $filenames Optional file names for file fields.
	 * @return string Confirmation HTML.
	 */
	private function render_default_confirmation( $form, $data, $filenames = array() ) {
		$template_loader = new FPLANT_Template_Loader();

		// Get settings for confirmation screen.
		$settings = isset( $form['settings'] ) ? $form['settings'] : array();

		// Render all fields HTML using all_fields.php template.
		$fields_html = $this->render_all_fields_html( $form['fields'], $data, $filenames );

		// Prepare template variables.
		$title        = isset( $settings['confirmation_title'] ) && '' !== $settings['confirmation_title']
			? $settings['confirmation_title']
			: __( 'Please confirm your input', 'form-plant' );
		$message      = isset( $settings['confirmation_message'] ) && '' !== $settings['confirmation_message']
			? $settings['confirmation_message']
			: __( 'Please review your input below and click submit to complete.', 'form-plant' );
		$back_text    = isset( $settings['back_button_text'] ) && '' !== $settings['back_button_text']
			? $settings['back_button_text']
			: __( 'Back', 'form-plant' );
		$back_class   = isset( $settings['back_button_class'] ) ? $settings['back_button_class'] : '';
		$back_id      = isset( $settings['back_button_id'] ) ? $settings['back_button_id'] : '';
		$submit_text  = isset( $settings['confirm_submit_button_text'] ) && '' !== $settings['confirm_submit_button_text']
			? $settings['confirm_submit_button_text']
			: __( 'Submit', 'form-plant' );
		$submit_class = isset( $settings['confirm_submit_button_class'] ) ? $settings['confirm_submit_button_class'] : '';
		$submit_id    = isset( $settings['confirm_submit_button_id'] ) ? $settings['confirm_submit_button_id'] : '';

		// Locate confirmation template.
		$template = $template_loader->locate_confirmation_template();

		// Render template.
		ob_start();
		// Make variables available to template.
		$fields = $form['fields'];
		$values = $data;
		include $template;
		return ob_get_clean();
	}

	/**
	 * Render custom confirmation template with shortcode replacement
	 *
	 * @param array  $form      Form data.
	 * @param array  $data      Submitted data.
	 * @param string $template  Custom HTML template.
	 * @param array  $filenames Optional file names for file fields.
	 * @return string Confirmation HTML.
	 */
	private function render_custom_confirmation_template( $form, $data, $template, $filenames = array() ) {
		$html     = $template;
		$settings = isset( $form['settings'] ) ? $form['settings'] : array();

		// Replace [fplant_confirmation_title].
		$title = isset( $settings['confirmation_title'] ) && '' !== $settings['confirmation_title']
			? $settings['confirmation_title']
			: __( 'Please confirm your input', 'form-plant' );
		$html  = str_replace( '[fplant_confirmation_title]', esc_html( $title ), $html );

		// Replace [fplant_confirmation_message].
		$message = isset( $settings['confirmation_message'] ) && '' !== $settings['confirmation_message']
			? $settings['confirmation_message']
			: __( 'Please review your input below and click submit to complete.', 'form-plant' );
		$html    = str_replace( '[fplant_confirmation_message]', esc_html( $message ), $html );

		// Replace [fplant_all_fields] using all_fields.php template.
		$all_fields_html = $this->render_all_fields_html( $form['fields'], $data, $filenames );
		$html            = str_replace( '[fplant_all_fields]', $all_fields_html, $html );

		// Replace [fplant_value name="..."] using confirm-fields/{type}.php templates.
		$html = $this->replace_value_shortcodes( $html, $form, $data, $filenames );

		// Replace button shortcodes.
		$html = $this->replace_button_shortcodes( $html, $settings );

		return $html;
	}

	/**
	 * Render all fields HTML using all_fields.php template
	 *
	 * @param array $fields    Form field definitions.
	 * @param array $values    Submitted values.
	 * @param array $filenames Optional. Array of filenames for file fields.
	 * @return string All fields HTML.
	 */
	private function render_all_fields_html( $fields, $values, $filenames = array() ) {
		$template_loader = new FPLANT_Template_Loader();
		$template        = $template_loader->locate_template( 'confirm-fields/all_fields.php' );

		if ( empty( $template ) ) {
			return '';
		}

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Replace [fplant_value name="..."] shortcodes
	 *
	 * @param string $html      HTML content.
	 * @param array  $form      Form data.
	 * @param array  $data      Submitted data.
	 * @param array  $filenames Optional file names for file fields.
	 * @return string HTML with shortcodes replaced.
	 */
	private function replace_value_shortcodes( $html, $form, $data, $filenames = array() ) {
		// Match [fplant_value name="fieldname"].
		if ( ! preg_match_all( '/\[fplant_value\s+name="([^"]+)"\]/', $html, $matches, PREG_SET_ORDER ) ) {
			return $html;
		}

		foreach ( $matches as $match ) {
			$shortcode  = $match[0];
			$field_name = $match[1];

			// Find field definition.
			$field = null;
			foreach ( $form['fields'] as $f ) {
				if ( $f['name'] === $field_name ) {
					$field = $f;
					break;
				}
			}

			if ( ! $field ) {
				$html = str_replace( $shortcode, '', $html );
				continue;
			}

			$value    = isset( $data[ $field_name ] ) ? $data[ $field_name ] : '';
			$filename = isset( $filenames[ $field_name ] ) ? $filenames[ $field_name ] : '';

			// Render using confirm-fields/{type}.php template.
			$field_html = $this->render_confirm_field( $field, $value, $filename );
			$html       = str_replace( $shortcode, $field_html, $html );
		}

		return $html;
	}

	/**
	 * Replace button shortcodes
	 *
	 * @param string $html     HTML content.
	 * @param array  $settings Form settings.
	 * @return string HTML with shortcodes replaced.
	 */
	private function replace_button_shortcodes( $html, $settings ) {
		// Get button text and attributes.
		$back_text    = isset( $settings['back_button_text'] ) && '' !== $settings['back_button_text']
			? $settings['back_button_text']
			: __( 'Back', 'form-plant' );
		$back_class   = isset( $settings['back_button_class'] ) ? $settings['back_button_class'] : '';
		$back_id      = isset( $settings['back_button_id'] ) ? $settings['back_button_id'] : '';
		$submit_text  = isset( $settings['confirm_submit_button_text'] ) && '' !== $settings['confirm_submit_button_text']
			? $settings['confirm_submit_button_text']
			: __( 'Submit', 'form-plant' );
		$submit_class = isset( $settings['confirm_submit_button_class'] ) ? $settings['confirm_submit_button_class'] : '';
		$submit_id    = isset( $settings['confirm_submit_button_id'] ) ? $settings['confirm_submit_button_id'] : '';

		// Replace [fplant_back] with optional text attribute.
		$html = preg_replace_callback(
			'/\[fplant_back(\s+text="([^"]*)")?\]/',
			function ( $matches ) use ( $back_text, $back_class, $back_id ) {
				$text      = ! empty( $matches[2] ) ? $matches[2] : $back_text;
				$class_attr = 'fplant-back-button' . ( ! empty( $back_class ) ? ' ' . esc_attr( $back_class ) : '' );
				$id_attr   = ! empty( $back_id ) ? ' id="' . esc_attr( $back_id ) . '"' : '';
				return '<button type="button" class="' . $class_attr . '"' . $id_attr . '>' . esc_html( $text ) . '</button>';
			},
			$html
		);

		// Replace [fplant_confirm_submit] with optional text attribute.
		$html = preg_replace_callback(
			'/\[fplant_confirm_submit(\s+text="([^"]*)")?\]/',
			function ( $matches ) use ( $submit_text, $submit_class, $submit_id ) {
				$text      = ! empty( $matches[2] ) ? $matches[2] : $submit_text;
				$class_attr = 'fplant-confirm-submit-button' . ( ! empty( $submit_class ) ? ' ' . esc_attr( $submit_class ) : '' );
				$id_attr   = ! empty( $submit_id ) ? ' id="' . esc_attr( $submit_id ) . '"' : '';
				return '<button type="button" class="' . $class_attr . '"' . $id_attr . '>' . esc_html( $text ) . '</button>';
			},
			$html
		);

		return $html;
	}
}
