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
			'date_select' => array(
				'label'       => __( 'Date (Dropdown)', 'form-plant' ),
				'icon'        => 'dashicons-calendar-alt',
				'description' => __( 'Date input field with year/month/day dropdowns', 'form-plant' ),
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
			'name_parts' => array(
				'label'       => __( 'Name', 'form-plant' ),
				'icon'        => 'dashicons-admin-users',
				'description' => __( 'Name input field with multiple parts', 'form-plant' ),
			),
			'name_kana' => array(
				'label'       => __( 'Name (Kana)', 'form-plant' ),
				// Furigana is a Japanese-only concept; show a custom kana mark
				// (assets/icons/name-kana-ja.svg via .fplant-icon-namekana-ja in admin.css)
				// in the Japanese admin, falling back to the spellcheck dashicon elsewhere.
				'icon'        => ( 0 === strpos( get_user_locale(), 'ja' ) ) ? 'fplant-icon-namekana-ja' : 'dashicons-editor-spellcheck',
				'description' => __( 'Kana name input field with multiple parts', 'form-plant' ),
			),
			'password' => array(
				'label'       => __( 'Password', 'form-plant' ),
				'icon'        => 'dashicons-lock',
				'description' => __( 'Password input field', 'form-plant' ),
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
			'postal_code' => array(
				'label'       => __( 'Postal Code', 'form-plant' ),
				// Use the Japanese postal mark (〒) when the admin UI is in Japanese; a
				// custom CSS class swaps the dashicon glyph for assets/icons/postal-code-ja.svg
				// (see .fplant-icon-postal-ja in admin.css). Other locales keep the generic pin.
				'icon'        => ( 0 === strpos( get_user_locale(), 'ja' ) ) ? 'fplant-icon-postal-ja' : 'dashicons-location',
				'description' => __( 'Postal code input field with auto-fill support', 'form-plant' ),
			),
			'prefecture' => array(
				'label'       => __( 'Prefecture', 'form-plant' ),
				'icon'        => 'dashicons-location-alt',
				'description' => __( 'Japanese prefecture selection field', 'form-plant' ),
			),
			'address'    => array(
				'label'       => __( 'Address', 'form-plant' ),
				'icon'        => 'dashicons-admin-home',
				'description' => __( 'Address input field with multiple parts', 'form-plant' ),
			),
			'custom_mail_tag' => array(
				'label'       => __( 'Custom Mail Tag', 'form-plant' ),
				'icon'        => 'dashicons-tag',
				'description' => __( 'Dynamic value provided via PHP filter hook (for email body / data)', 'form-plant' ),
			),
			'acceptance' => array(
				'label'       => __( 'Acceptance', 'form-plant' ),
				// Custom consent-check mark (assets/icons/acceptance.svg via
				// .fplant-icon-acceptance in admin.css).
				'icon'        => 'fplant-icon-acceptance',
				'description' => __( 'Consent checkbox with a linked label (privacy policy, terms of service)', 'form-plant' ),
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
			'type'              => $field_type,
			'label'             => '',
			'name'              => '',
			'placeholder'       => '',
			'default'           => '',
			'required'          => false,
			'class'             => '',
			'custom_id'         => '',
			'custom_class'      => '',
			'desc_after_label'  => '',
			'desc_before_input' => '',
			'desc_after_input'  => '',
			'validation'        => array(),
			'conditional'       => array(
				'enabled' => false,
				'field'   => '',
				'value'   => '',
			),
		);

		// Additional settings per field type
		switch ( $field_type ) {
			case 'tel':
				$defaults['tel_format'] = 'single';
				break;

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

			case 'name_parts':
				$defaults['name_format']       = '2';
				$defaults['name_labels']       = array(
					'family' => __( 'Last Name', 'form-plant' ),
					'given'  => __( 'First Name', 'form-plant' ),
					'middle' => __( 'Middle Name', 'form-plant' ),
				);
				$defaults['name_placeholders'] = array(
					'family' => '',
					'given'  => '',
					'middle' => '',
				);
				break;

			case 'name_kana':
				$defaults['name_format']        = '2';
				$defaults['name_labels']        = array(
					'family' => __( 'Last Name (Kana)', 'form-plant' ),
					'given'  => __( 'First Name (Kana)', 'form-plant' ),
					'middle' => __( 'Middle Name (Kana)', 'form-plant' ),
				);
				$defaults['name_placeholders']  = array(
					'family' => '',
					'given'  => '',
					'middle' => '',
				);
				$defaults['kana_validation']    = 'katakana';
				$defaults['kana_error_message'] = '';
				break;

			case 'password':
				$defaults['password_min_length']     = '';
				$defaults['password_mask_email']     = false;
				$defaults['password_mask_save']      = false;
				$defaults['password_strength_meter'] = false;
				$defaults['password_strength_level'] = 'none';
				break;

			case 'html':
				$defaults['content'] = '';
				break;

			case 'postal_code':
				$defaults['postal_format']         = 'single';
				$defaults['postal_autofill']       = false;
				$defaults['postal_show_search_btn'] = false;
				$defaults['postal_target_pref']    = '';
				$defaults['postal_target_addr1']   = '';
				$defaults['postal_target_addr2']   = '';
				break;

			case 'prefecture':
				$defaults['pref_display_type'] = 'select';
				$defaults['layout']            = 'vertical';
				$defaults['options']           = array_map(
					function ( $pref ) {
						return array(
							'value' => $pref,
							'label' => $pref,
						);
					},
					self::get_prefectures()
				);
				break;

			case 'custom_mail_tag':
				$defaults['display_in_form'] = true;
				$defaults['display_wrapper'] = 'span';
				break;

			case 'acceptance':
				// Consent must always be explicit: the required flag is fixed ON
				// (the field editor renders the toggle checked and disabled).
				// The label is a plain item name like every other field type;
				// acceptance_text is the wording shown next to the checkbox and
				// may contain limited inline HTML (see sanitize_acceptance_text()).
				$defaults['required']                     = true;
				$defaults['acceptance_text']              = '';
				$defaults['acceptance_show_label']        = false;
				$defaults['acceptance_show_confirmation'] = false;
				$defaults['acceptance_show_email']        = false;
				$defaults['acceptance_save_submission']   = false;
				break;

			case 'address':
				$defaults['postal_format']         = 'single';
				$defaults['postal_show_search_btn'] = false;
				$defaults['pref_display_type']     = 'select';
				$defaults['address_labels']    = array(
					'postal_code' => __( 'Postal Code', 'form-plant' ),
					'prefecture'  => __( 'Prefecture', 'form-plant' ),
					'city'        => __( 'City', 'form-plant' ),
					'street'      => __( 'Street Address', 'form-plant' ),
					'building'    => __( 'Building / Apartment', 'form-plant' ),
					'country'     => __( 'Country', 'form-plant' ),
					'state'       => __( 'State / Province', 'form-plant' ),
					'address2'    => __( 'Address Line 2', 'form-plant' ),
				);
				$defaults['address_placeholders'] = array(
					'postal_code' => '',
					'city'        => '',
					'street'      => '',
					'building'    => '',
					'country'     => '',
					'state'       => '',
					'address2'    => '',
				);
				$defaults['address_validation_messages'] = array(
					'postal_code' => '',
					'prefecture'  => '',
					'city'        => '',
					'street'      => '',
					'building'    => '',
					'country'     => '',
					'state'       => '',
					'address2'    => '',
				);
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

		// Validate against the single source of truth (filtered via fplant_field_types),
		// so Pro-registered types pass validation just like built-in types.
		if ( ! array_key_exists( $field['type'], $this->get_field_types() ) ) {
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

		// Apply dynamic choices for select/radio/checkbox (fplant_field_choices).
		if ( in_array( $field['type'], array( 'select', 'radio', 'checkbox' ), true ) ) {
			$field['options'] = $this->get_field_choices( $field, $form_id );
		}

		// Get initial value if $value is empty
		if ( '' === $value || ( is_array( $value ) && empty( $value ) ) ) {
			$value = $this->get_field_initial_value( $field, $form_id, $form_settings );
			// The resolved value is authoritative: clear the raw default so field
			// templates never fall back to it (an unresolved {placeholder} must stay hidden).
			$field['default'] = '';
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
	 * Render a field description slot
	 *
	 * @param array  $field    Field configuration
	 * @param string $position after_label | before_input | after_input
	 * @return string HTML ('' when the description is empty)
	 */
	public function render_field_description( $field, $position ) {
		$key = 'desc_' . $position;
		if ( empty( $field[ $key ] ) ) {
			return '';
		}
		return '<div class="fplant-field-desc fplant-field-desc-' . str_replace( '_', '-', $position ) . '">'
			. wp_kses_post( $field[ $key ] ) . '</div>';
	}

	/**
	 * Allowed inline HTML for the acceptance consent text.
	 *
	 * @since 1.4.0
	 * @return array wp_kses allowed-HTML map.
	 */
	public static function acceptance_text_allowed_html() {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
		);
	}

	/**
	 * Sanitize the acceptance consent text (limited inline HTML).
	 *
	 * Keeps links and simple emphasis, strips everything else. No rel is
	 * force-added to target="_blank" links: browsers imply noopener for
	 * target="_blank" (the reason WP 6.7 deprecated wp_targeted_link_rel),
	 * and an author-written rel attribute is preserved by the kses rules.
	 *
	 * @since 1.4.0
	 * @param string $text Raw consent text.
	 * @return string Sanitized text.
	 */
	public static function sanitize_acceptance_text( $text ) {
		return wp_kses( (string) $text, self::acceptance_text_allowed_html() );
	}

	/**
	 * Consent text HTML for front-end output (next to the checkbox).
	 *
	 * Falls back to the escaped plain label when no consent text is set, so
	 * the checkbox never renders without wording. Stored text is sanitized on
	 * save; the kses here is defense in depth for field data arriving from
	 * other sources (import, filters).
	 *
	 * @since 1.4.0
	 * @param array $field Field configuration.
	 * @return string Safe HTML.
	 */
	public static function acceptance_text_html( $field ) {
		$text = isset( $field['acceptance_text'] ) ? (string) $field['acceptance_text'] : '';
		if ( '' === trim( $text ) ) {
			return esc_html( isset( $field['label'] ) ? (string) $field['label'] : '' );
		}
		return wp_kses( $text, self::acceptance_text_allowed_html() );
	}

	/**
	 * Whether the field-group label (item name) is rendered on the form.
	 *
	 * Acceptance fields hide it unless acceptance_show_label is enabled; every
	 * other type shows it whenever a label exists.
	 *
	 * @since 1.4.0
	 * @param array $field Field configuration.
	 * @return bool
	 */
	public static function shows_group_label( $field ) {
		if ( empty( $field['label'] ) ) {
			return false;
		}
		if ( isset( $field['type'] ) && 'acceptance' === $field['type'] ) {
			return ! empty( $field['acceptance_show_label'] );
		}
		return true;
	}

	/**
	 * Display value for a checked acceptance field.
	 *
	 * Shared by the confirmation screen, emails, CSV export and the admin
	 * submission detail so all outputs render the same wording.
	 *
	 * @since 1.4.0
	 * @return string
	 */
	public static function acceptance_display_value() {
		return __( 'Agreed', 'form-plant' );
	}

	/**
	 * Format a stored submission value as plain text for one output context.
	 *
	 * Shared boundary used by the confirmation screen (all-fields tables),
	 * emails, CSV export and the admin submission detail so structured
	 * values (nested arrays from extension field types such as repeaters
	 * and groups) render consistently everywhere. The default keeps flat
	 * arrays identical to the historical implode() output; nested values
	 * become numbered "label: value" lines.
	 *
	 * The return value is PLAIN TEXT — HTML escaping and newline-to-<br>
	 * conversion are the caller's responsibility, so the XSS boundary
	 * stays at the output site.
	 *
	 * @since 1.4.0
	 * @param mixed  $value   Stored submission value (may be nested).
	 * @param array  $field   Root field configuration (may include sub_fields).
	 * @param string $context Output context: 'confirmation' | 'email_all_fields' |
	 *                        'email_tag' | 'admin_detail' | 'csv_single' | 'csv_all'.
	 * @param int    $form_id Form ID (0 when unknown).
	 * @return string
	 */
	public static function format_submission_value( $value, $field, $context, $form_id = 0 ) {
		$formatted = self::default_format_submission_value( $value, $field, $context );

		/**
		 * Filters the plain-text representation of a stored submission value.
		 *
		 * Lets extensions replace the formatting of their own field types
		 * (e.g. repeater rows) per output context. Return plain text; the
		 * caller escapes it for its output medium.
		 *
		 * @since 1.4.0
		 * @param string $formatted Default plain-text formatting.
		 * @param mixed  $value     Raw stored value.
		 * @param array  $field     Root field configuration.
		 * @param string $context   Output context (see format_submission_value()).
		 * @param int    $form_id   Form ID (0 when unknown).
		 */
		return apply_filters( 'fplant_format_submission_value', $formatted, $value, $field, $context, $form_id );
	}

	/**
	 * Default plain-text formatting for a submission value.
	 *
	 * - Scalars are cast to string.
	 * - File-info arrays render their filename (historical behavior).
	 * - Flat arrays (checkbox etc.) keep the historical delimiter join.
	 * - Lists of rows (repeater shape) become numbered lines:
	 *   "1. Sub label: value / Sub label: value".
	 * - Associative arrays (group shape) become one "label: value / ..." line.
	 *
	 * @param mixed  $value   Stored value.
	 * @param array  $field   Root field configuration (sub_fields used for labels).
	 * @param string $context Output context (CSV / admin detail historically join
	 *                        flat arrays with ', ' regardless of the field's
	 *                        delimiter setting; that behavior is preserved).
	 * @return string
	 */
	private static function default_format_submission_value( $value, $field, $context ) {
		if ( ! is_array( $value ) ) {
			return (string) $value;
		}

		// File-info array: expose the filename only.
		if ( isset( $value['filename'] ) ) {
			return (string) $value['filename'];
		}

		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );

		if ( $is_list && ! empty( $value ) ) {
			$has_array_items = false;
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					$has_array_items = true;
					break;
				}
			}

			if ( ! $has_array_items ) {
				// Flat array (checkbox etc.): historical join. CSV and the
				// admin detail always used ', '; the other contexts honor
				// the field's delimiter setting.
				$fixed_join = in_array( $context, array( 'csv_single', 'csv_all', 'admin_detail' ), true );
				$delimiter  = ( ! $fixed_join && isset( $field['delimiter'] ) ) ? $field['delimiter'] : ', ';
				return implode( $delimiter, array_map( 'strval', $value ) );
			}

			// Rows (repeater shape): one numbered line per row.
			$lines = array();
			$row_number = 1;
			foreach ( $value as $row ) {
				$lines[] = $row_number . '. ' . self::format_row_as_line( is_array( $row ) ? $row : array( $row ), $field );
				$row_number++;
			}
			return implode( "\n", $lines );
		}

		// Associative array (group shape): single "label: value / ..." line.
		return self::format_row_as_line( $value, $field );
	}

	/**
	 * Format one row / group as a "label: value / label: value" line.
	 *
	 * @param array $row   Sub-name => value map (values may be arrays).
	 * @param array $field Root field configuration (sub_fields used for labels).
	 * @return string
	 */
	private static function format_row_as_line( $row, $field ) {
		$parts = array();
		foreach ( $row as $sub_name => $sub_value ) {
			if ( is_array( $sub_value ) ) {
				$sub_value = implode( ', ', array_map( 'strval', $sub_value ) );
			}
			$parts[] = self::resolve_sub_label( $sub_name, $field ) . ': ' . (string) $sub_value;
		}
		return implode( ' / ', $parts );
	}

	/**
	 * Resolve a sub-field label from the root field's sub_fields definition.
	 *
	 * @param string $sub_name Sub-field name.
	 * @param array  $field    Root field configuration.
	 * @return string Label, or the sub-field name when undefined.
	 */
	private static function resolve_sub_label( $sub_name, $field ) {
		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			foreach ( $field['sub_fields'] as $sub ) {
				if ( isset( $sub['name'] ) && (string) $sub['name'] === (string) $sub_name ) {
					return ! empty( $sub['label'] ) ? (string) $sub['label'] : (string) $sub_name;
				}
			}
		}
		return (string) $sub_name;
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
	 * 2. Post property / custom field of the post given via ?post_id=
	 *    (only if allowed in settings AND default value is a {key} placeholder)
	 * 3. Filter fplant_field_initial_value
	 * 4. Field default value
	 *
	 * @param array $field         Field configuration
	 * @param int   $form_id       Form ID
	 * @param array $form_settings Form settings (allow_url_params, etc.)
	 * @return mixed Initial value
	 */
	public function get_field_initial_value( $field, $form_id, $form_settings = array() ) {
		$field_name    = $field['name'];
		$default_value = isset( $field['default'] ) ? $field['default'] : '';

		// 0. Custom mail tag — value is provided exclusively via filter hook.
		if ( isset( $field['type'] ) && 'custom_mail_tag' === $field['type'] ) {
			$value = apply_filters( 'fplant_custom_mail_tag_value', '', $field_name, $field, $form_id );
			$value = apply_filters( "fplant_custom_mail_tag_value_{$field_name}", $value, $field, $form_id );
			return $value;
		}

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

		// 2. Post property / custom field via ?post_id= (MW WP Form querystring equivalent)
		//    Condition: allowed in settings AND ?post_id= is present AND default value is a {key} placeholder
		if ( $allow_url_params && $this->is_post_property_placeholder( $default_value ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$url_post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
			if ( $url_post_id > 0 ) {
				$post_value = $this->get_post_property_value( $url_post_id, trim( $default_value, '{}' ) );
				// Don't show the raw placeholder when post_id is present but unresolvable
				return ( null !== $post_value ) ? $post_value : '';
			}
		}

		// 3. Initial value via filter
		$filtered_value = apply_filters( 'fplant_field_initial_value', null, $field_name, $field, $form_id );
		$filtered_value = apply_filters( "fplant_field_initial_value_{$field_name}", $filtered_value, $field, $form_id );
		if ( null !== $filtered_value ) {
			return $filtered_value;
		}

		// 4. Default value
		return $default_value;
	}

	/**
	 * Get the choice options for a select/radio/checkbox field.
	 *
	 * Mirrors get_field_initial_value(): applies the fplant_field_choices filters so
	 * third-party code can populate options dynamically (e.g. from posts or terms).
	 * MW WP Form's mwform_choices equivalent. Non-choice field types are returned unchanged.
	 *
	 * @since 1.2.0
	 * @param array $field   Field configuration.
	 * @param int   $form_id Form ID.
	 * @return array Choice options (each option is array( 'label' => ..., 'value' => ... )).
	 */
	public function get_field_choices( $field, $form_id = 0 ) {
		$options = ( isset( $field['options'] ) && is_array( $field['options'] ) ) ? $field['options'] : array();

		if ( ! isset( $field['type'] ) || ! in_array( $field['type'], array( 'select', 'radio', 'checkbox' ), true ) ) {
			return $options;
		}

		$field_name = isset( $field['name'] ) ? $field['name'] : '';

		// Common hook, then field-specific hook (same two-stage design as field initial value).
		$options = apply_filters( 'fplant_field_choices', $options, $field_name, $field, $form_id );
		if ( '' !== $field_name ) {
			$options = apply_filters( "fplant_field_choices_{$field_name}", $options, $field, $form_id );
		}

		return is_array( $options ) ? $options : array();
	}

	/**
	 * Apply fplant_field_choices to every choice field in a form's fields array.
	 *
	 * Used at confirmation entry points so the confirmation screen labels match the
	 * dynamically-populated form choices.
	 *
	 * @since 1.2.0
	 * @param array $form Form data.
	 * @return array Form data with filtered choice options.
	 */
	public function apply_field_choices( $form ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $form;
		}
		$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
		foreach ( $form['fields'] as $index => $field ) {
			if ( isset( $field['type'] ) && in_array( $field['type'], array( 'select', 'radio', 'checkbox' ), true ) ) {
				$form['fields'][ $index ]['options'] = $this->get_field_choices( $field, $form_id );
			}
		}
		return $form;
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

			case 'name_parts':
			case 'name_kana':
				return sanitize_text_field( $raw_value );

			case 'textarea':
				return sanitize_textarea_field( $raw_value );

			default:
				return sanitize_text_field( $raw_value );
		}
	}

	/**
	 * Check if the default value is a single {key} placeholder
	 *
	 * @param string $default_value Default value
	 * @return bool
	 */
	private function is_post_property_placeholder( $default_value ) {
		return is_string( $default_value ) && (bool) preg_match( '/\A\{[A-Za-z0-9_-]+\}\z/', $default_value );
	}

	/**
	 * Resolve a {key} placeholder against the post given via ?post_id=
	 *
	 * Post properties are limited to a whitelist. Custom fields exclude protected
	 * meta and non-scalar values. Only publicly viewable posts without password
	 * protection can be read, so private content is never exposed via the URL.
	 *
	 * @param int    $post_id Post ID from the URL.
	 * @param string $key     WP_Post property name or custom field key.
	 * @return string|null Resolved value, or null if unresolvable.
	 */
	private function get_post_property_value( $post_id, $key ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		if ( ! is_post_publicly_viewable( $post ) || post_password_required( $post ) ) {
			return null;
		}

		$allowed_properties = array( 'ID', 'post_title', 'post_name', 'post_date', 'post_excerpt', 'post_content' );
		if ( in_array( $key, $allowed_properties, true ) ) {
			return (string) $post->$key;
		}

		if ( is_protected_meta( $key, 'post' ) ) {
			return null;
		}

		$meta = get_post_meta( $post->ID, $key, true );
		if ( is_array( $meta ) || is_object( $meta ) ) {
			return null;
		}

		return (string) $meta;
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
		// Apply dynamic choices so confirmation labels match the form (fplant_field_choices).
		$form = $this->apply_field_choices( $form );

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

		// Render all fields HTML using all_fields.php or all_fields_div.php template.
		$fields_html = $this->render_all_fields_html( $form['fields'], $data, $filenames, $form );

		// Prepare template variables.
		$title        = isset( $settings['confirmation_title'] ) && '' !== $settings['confirmation_title']
			? $settings['confirmation_title']
			: __( 'Please confirm your input', 'form-plant' );
		$message      = isset( $settings['confirmation_message'] ) && '' !== $settings['confirmation_message']
			? $settings['confirmation_message']
			: __( 'Please review your input below and click submit to complete.', 'form-plant' );
		$back_text    = isset( $settings['confirmation_back_text'] ) && '' !== $settings['confirmation_back_text']
			? $settings['confirmation_back_text']
			: __( 'Back', 'form-plant' );
		$back_class   = isset( $settings['confirmation_back_class'] ) ? $settings['confirmation_back_class'] : '';
		$back_id      = isset( $settings['confirmation_back_id'] ) ? $settings['confirmation_back_id'] : '';
		$submit_text  = isset( $settings['confirmation_submit_text'] ) && '' !== $settings['confirmation_submit_text']
			? $settings['confirmation_submit_text']
			: __( 'Submit', 'form-plant' );
		$submit_class = isset( $settings['confirmation_submit_class'] ) ? $settings['confirmation_submit_class'] : '';
		$submit_id    = isset( $settings['confirmation_submit_id'] ) ? $settings['confirmation_submit_id'] : '';

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
		$html     = fplant_replace_template_values( $template, $form['id'] );
		// Expand shortcodes before submitted values are injected, so shortcode-like text in field values is never executed.
		$html     = do_shortcode( $html );
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

		// Replace [fplant_all_fields] using all_fields.php or all_fields_div.php template.
		$all_fields_html = $this->render_all_fields_html( $form['fields'], $data, $filenames, $form );
		$html            = str_replace( '[fplant_all_fields]', $all_fields_html, $html );

		// Replace [fplant_value name="..."] using confirm-fields/{type}.php templates.
		$html = $this->replace_value_shortcodes( $html, $form, $data, $filenames );

		// Replace button shortcodes.
		$html = $this->replace_button_shortcodes( $html, $settings );

		return $html;
	}

	/**
	 * Render all fields HTML using all_fields.php or all_fields_div.php template
	 *
	 * @param array $fields    Form field definitions.
	 * @param array $values    Submitted values.
	 * @param array $filenames Optional. Array of filenames for file fields.
	 * @param array $form      Optional. Form data (used for design_type detection).
	 * @return string All fields HTML.
	 */
	private function render_all_fields_html( $fields, $values, $filenames = array(), $form = array() ) {
		$template_loader = new FPLANT_Template_Loader();
		$design_type     = $form['settings']['design_type'] ?? 'default';
		$template_name   = ( 'default' !== $design_type ) ? 'confirm-fields/all_fields_div.php' : 'confirm-fields/all_fields.php';
		$template        = $template_loader->locate_template( $template_name );

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
		$back_text    = isset( $settings['confirmation_back_text'] ) && '' !== $settings['confirmation_back_text']
			? $settings['confirmation_back_text']
			: __( 'Back', 'form-plant' );
		$back_class   = isset( $settings['confirmation_back_class'] ) ? $settings['confirmation_back_class'] : '';
		$back_id      = isset( $settings['confirmation_back_id'] ) ? $settings['confirmation_back_id'] : '';
		$submit_text  = isset( $settings['confirmation_submit_text'] ) && '' !== $settings['confirmation_submit_text']
			? $settings['confirmation_submit_text']
			: __( 'Submit', 'form-plant' );
		$submit_class = isset( $settings['confirmation_submit_class'] ) ? $settings['confirmation_submit_class'] : '';
		$submit_id    = isset( $settings['confirmation_submit_id'] ) ? $settings['confirmation_submit_id'] : '';

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

	/**
	 * Get Japanese prefectures list
	 *
	 * @return array
	 */
	public static function get_prefectures() {
		return array(
			'北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
			'茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
			'新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
			'静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
			'奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
			'徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
			'熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
		);
	}

	/**
	 * Get countries list (ISO 3166-1)
	 *
	 * @return array
	 */
	public static function get_countries() {
		return array(
			'AF' => __( 'Afghanistan', 'form-plant' ),
			'AL' => __( 'Albania', 'form-plant' ),
			'DZ' => __( 'Algeria', 'form-plant' ),
			'AD' => __( 'Andorra', 'form-plant' ),
			'AO' => __( 'Angola', 'form-plant' ),
			'AG' => __( 'Antigua and Barbuda', 'form-plant' ),
			'AR' => __( 'Argentina', 'form-plant' ),
			'AM' => __( 'Armenia', 'form-plant' ),
			'AU' => __( 'Australia', 'form-plant' ),
			'AT' => __( 'Austria', 'form-plant' ),
			'AZ' => __( 'Azerbaijan', 'form-plant' ),
			'BS' => __( 'Bahamas', 'form-plant' ),
			'BH' => __( 'Bahrain', 'form-plant' ),
			'BD' => __( 'Bangladesh', 'form-plant' ),
			'BB' => __( 'Barbados', 'form-plant' ),
			'BY' => __( 'Belarus', 'form-plant' ),
			'BE' => __( 'Belgium', 'form-plant' ),
			'BZ' => __( 'Belize', 'form-plant' ),
			'BJ' => __( 'Benin', 'form-plant' ),
			'BT' => __( 'Bhutan', 'form-plant' ),
			'BO' => __( 'Bolivia', 'form-plant' ),
			'BA' => __( 'Bosnia and Herzegovina', 'form-plant' ),
			'BW' => __( 'Botswana', 'form-plant' ),
			'BR' => __( 'Brazil', 'form-plant' ),
			'BN' => __( 'Brunei', 'form-plant' ),
			'BG' => __( 'Bulgaria', 'form-plant' ),
			'BF' => __( 'Burkina Faso', 'form-plant' ),
			'BI' => __( 'Burundi', 'form-plant' ),
			'CV' => __( 'Cabo Verde', 'form-plant' ),
			'KH' => __( 'Cambodia', 'form-plant' ),
			'CM' => __( 'Cameroon', 'form-plant' ),
			'CA' => __( 'Canada', 'form-plant' ),
			'CF' => __( 'Central African Republic', 'form-plant' ),
			'TD' => __( 'Chad', 'form-plant' ),
			'CL' => __( 'Chile', 'form-plant' ),
			'CN' => __( 'China', 'form-plant' ),
			'CO' => __( 'Colombia', 'form-plant' ),
			'KM' => __( 'Comoros', 'form-plant' ),
			'CG' => __( 'Congo', 'form-plant' ),
			'CD' => __( 'Congo (Democratic Republic)', 'form-plant' ),
			'CR' => __( 'Costa Rica', 'form-plant' ),
			'CI' => __( 'Côte d\'Ivoire', 'form-plant' ),
			'HR' => __( 'Croatia', 'form-plant' ),
			'CU' => __( 'Cuba', 'form-plant' ),
			'CY' => __( 'Cyprus', 'form-plant' ),
			'CZ' => __( 'Czech Republic', 'form-plant' ),
			'DK' => __( 'Denmark', 'form-plant' ),
			'DJ' => __( 'Djibouti', 'form-plant' ),
			'DM' => __( 'Dominica', 'form-plant' ),
			'DO' => __( 'Dominican Republic', 'form-plant' ),
			'EC' => __( 'Ecuador', 'form-plant' ),
			'EG' => __( 'Egypt', 'form-plant' ),
			'SV' => __( 'El Salvador', 'form-plant' ),
			'GQ' => __( 'Equatorial Guinea', 'form-plant' ),
			'ER' => __( 'Eritrea', 'form-plant' ),
			'EE' => __( 'Estonia', 'form-plant' ),
			'SZ' => __( 'Eswatini', 'form-plant' ),
			'ET' => __( 'Ethiopia', 'form-plant' ),
			'FJ' => __( 'Fiji', 'form-plant' ),
			'FI' => __( 'Finland', 'form-plant' ),
			'FR' => __( 'France', 'form-plant' ),
			'GA' => __( 'Gabon', 'form-plant' ),
			'GM' => __( 'Gambia', 'form-plant' ),
			'GE' => __( 'Georgia', 'form-plant' ),
			'DE' => __( 'Germany', 'form-plant' ),
			'GH' => __( 'Ghana', 'form-plant' ),
			'GR' => __( 'Greece', 'form-plant' ),
			'GD' => __( 'Grenada', 'form-plant' ),
			'GT' => __( 'Guatemala', 'form-plant' ),
			'GN' => __( 'Guinea', 'form-plant' ),
			'GW' => __( 'Guinea-Bissau', 'form-plant' ),
			'GY' => __( 'Guyana', 'form-plant' ),
			'HT' => __( 'Haiti', 'form-plant' ),
			'HN' => __( 'Honduras', 'form-plant' ),
			'HU' => __( 'Hungary', 'form-plant' ),
			'IS' => __( 'Iceland', 'form-plant' ),
			'IN' => __( 'India', 'form-plant' ),
			'ID' => __( 'Indonesia', 'form-plant' ),
			'IR' => __( 'Iran', 'form-plant' ),
			'IQ' => __( 'Iraq', 'form-plant' ),
			'IE' => __( 'Ireland', 'form-plant' ),
			'IL' => __( 'Israel', 'form-plant' ),
			'IT' => __( 'Italy', 'form-plant' ),
			'JM' => __( 'Jamaica', 'form-plant' ),
			'JP' => __( 'Japan', 'form-plant' ),
			'JO' => __( 'Jordan', 'form-plant' ),
			'KZ' => __( 'Kazakhstan', 'form-plant' ),
			'KE' => __( 'Kenya', 'form-plant' ),
			'KI' => __( 'Kiribati', 'form-plant' ),
			'KP' => __( 'Korea (North)', 'form-plant' ),
			'KR' => __( 'Korea (South)', 'form-plant' ),
			'KW' => __( 'Kuwait', 'form-plant' ),
			'KG' => __( 'Kyrgyzstan', 'form-plant' ),
			'LA' => __( 'Laos', 'form-plant' ),
			'LV' => __( 'Latvia', 'form-plant' ),
			'LB' => __( 'Lebanon', 'form-plant' ),
			'LS' => __( 'Lesotho', 'form-plant' ),
			'LR' => __( 'Liberia', 'form-plant' ),
			'LY' => __( 'Libya', 'form-plant' ),
			'LI' => __( 'Liechtenstein', 'form-plant' ),
			'LT' => __( 'Lithuania', 'form-plant' ),
			'LU' => __( 'Luxembourg', 'form-plant' ),
			'MG' => __( 'Madagascar', 'form-plant' ),
			'MW' => __( 'Malawi', 'form-plant' ),
			'MY' => __( 'Malaysia', 'form-plant' ),
			'MV' => __( 'Maldives', 'form-plant' ),
			'ML' => __( 'Mali', 'form-plant' ),
			'MT' => __( 'Malta', 'form-plant' ),
			'MH' => __( 'Marshall Islands', 'form-plant' ),
			'MR' => __( 'Mauritania', 'form-plant' ),
			'MU' => __( 'Mauritius', 'form-plant' ),
			'MX' => __( 'Mexico', 'form-plant' ),
			'FM' => __( 'Micronesia', 'form-plant' ),
			'MD' => __( 'Moldova', 'form-plant' ),
			'MC' => __( 'Monaco', 'form-plant' ),
			'MN' => __( 'Mongolia', 'form-plant' ),
			'ME' => __( 'Montenegro', 'form-plant' ),
			'MA' => __( 'Morocco', 'form-plant' ),
			'MZ' => __( 'Mozambique', 'form-plant' ),
			'MM' => __( 'Myanmar', 'form-plant' ),
			'NA' => __( 'Namibia', 'form-plant' ),
			'NR' => __( 'Nauru', 'form-plant' ),
			'NP' => __( 'Nepal', 'form-plant' ),
			'NL' => __( 'Netherlands', 'form-plant' ),
			'NZ' => __( 'New Zealand', 'form-plant' ),
			'NI' => __( 'Nicaragua', 'form-plant' ),
			'NE' => __( 'Niger', 'form-plant' ),
			'NG' => __( 'Nigeria', 'form-plant' ),
			'MK' => __( 'North Macedonia', 'form-plant' ),
			'NO' => __( 'Norway', 'form-plant' ),
			'OM' => __( 'Oman', 'form-plant' ),
			'PK' => __( 'Pakistan', 'form-plant' ),
			'PW' => __( 'Palau', 'form-plant' ),
			'PA' => __( 'Panama', 'form-plant' ),
			'PG' => __( 'Papua New Guinea', 'form-plant' ),
			'PY' => __( 'Paraguay', 'form-plant' ),
			'PE' => __( 'Peru', 'form-plant' ),
			'PH' => __( 'Philippines', 'form-plant' ),
			'PL' => __( 'Poland', 'form-plant' ),
			'PT' => __( 'Portugal', 'form-plant' ),
			'QA' => __( 'Qatar', 'form-plant' ),
			'RO' => __( 'Romania', 'form-plant' ),
			'RU' => __( 'Russia', 'form-plant' ),
			'RW' => __( 'Rwanda', 'form-plant' ),
			'KN' => __( 'Saint Kitts and Nevis', 'form-plant' ),
			'LC' => __( 'Saint Lucia', 'form-plant' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'form-plant' ),
			'WS' => __( 'Samoa', 'form-plant' ),
			'SM' => __( 'San Marino', 'form-plant' ),
			'ST' => __( 'Sao Tome and Principe', 'form-plant' ),
			'SA' => __( 'Saudi Arabia', 'form-plant' ),
			'SN' => __( 'Senegal', 'form-plant' ),
			'RS' => __( 'Serbia', 'form-plant' ),
			'SC' => __( 'Seychelles', 'form-plant' ),
			'SL' => __( 'Sierra Leone', 'form-plant' ),
			'SG' => __( 'Singapore', 'form-plant' ),
			'SK' => __( 'Slovakia', 'form-plant' ),
			'SI' => __( 'Slovenia', 'form-plant' ),
			'SB' => __( 'Solomon Islands', 'form-plant' ),
			'SO' => __( 'Somalia', 'form-plant' ),
			'ZA' => __( 'South Africa', 'form-plant' ),
			'SS' => __( 'South Sudan', 'form-plant' ),
			'ES' => __( 'Spain', 'form-plant' ),
			'LK' => __( 'Sri Lanka', 'form-plant' ),
			'SD' => __( 'Sudan', 'form-plant' ),
			'SR' => __( 'Suriname', 'form-plant' ),
			'SE' => __( 'Sweden', 'form-plant' ),
			'CH' => __( 'Switzerland', 'form-plant' ),
			'SY' => __( 'Syria', 'form-plant' ),
			'TW' => __( 'Taiwan', 'form-plant' ),
			'TJ' => __( 'Tajikistan', 'form-plant' ),
			'TZ' => __( 'Tanzania', 'form-plant' ),
			'TH' => __( 'Thailand', 'form-plant' ),
			'TL' => __( 'Timor-Leste', 'form-plant' ),
			'TG' => __( 'Togo', 'form-plant' ),
			'TO' => __( 'Tonga', 'form-plant' ),
			'TT' => __( 'Trinidad and Tobago', 'form-plant' ),
			'TN' => __( 'Tunisia', 'form-plant' ),
			'TR' => __( 'Turkey', 'form-plant' ),
			'TM' => __( 'Turkmenistan', 'form-plant' ),
			'TV' => __( 'Tuvalu', 'form-plant' ),
			'UG' => __( 'Uganda', 'form-plant' ),
			'UA' => __( 'Ukraine', 'form-plant' ),
			'AE' => __( 'United Arab Emirates', 'form-plant' ),
			'GB' => __( 'United Kingdom', 'form-plant' ),
			'US' => __( 'United States', 'form-plant' ),
			'UY' => __( 'Uruguay', 'form-plant' ),
			'UZ' => __( 'Uzbekistan', 'form-plant' ),
			'VU' => __( 'Vanuatu', 'form-plant' ),
			'VA' => __( 'Vatican City', 'form-plant' ),
			'VE' => __( 'Venezuela', 'form-plant' ),
			'VN' => __( 'Vietnam', 'form-plant' ),
			'YE' => __( 'Yemen', 'form-plant' ),
			'ZM' => __( 'Zambia', 'form-plant' ),
			'ZW' => __( 'Zimbabwe', 'form-plant' ),
		);
	}
}
