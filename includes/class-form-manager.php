<?php
/**
 * Form management class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Form_Manager class
 */
class FPLANT_Form_Manager {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook registration
		add_action( 'save_post_fplant_form', array( $this, 'save_form_meta' ), 10, 2 );
	}

	/**
	 * Create form
	 *
	 * @param string $title Form title
	 * @param array  $args  Form data
	 * @return int|WP_Error Form ID or WP_Error
	 */
	public function create_form( $title, $args = array() ) {
		// Get status from args (default is publish)
		$status = 'publish';
		if ( isset( $args['status'] ) ) {
			$allowed_statuses = array( 'publish', 'private', 'draft', 'pending' );
			if ( in_array( $args['status'], $allowed_statuses, true ) ) {
				$status = $args['status'];
			}
		}

		$form_id = wp_insert_post(
			array(
				'post_type'   => 'fplant_form',
				'post_title'  => sanitize_text_field( $title ),
				'post_status' => $status,
			)
		);

		if ( is_wp_error( $form_id ) ) {
			return $form_id;
		}

		// Save form data
		if ( ! empty( $args ) ) {
			$this->update_form( $form_id, $args );
		}

		return $form_id;
	}

	/**
	 * Update form
	 *
	 * @param int   $form_id Form ID
	 * @param array $data    Form data
	 * @return bool
	 */
	public function update_form( $form_id, $data ) {
		$updated = false;

		// Return early if data is invalid
		if ( ! is_array( $data ) || empty( $data ) ) {
			return false;
		}

		// Update title and status
		$post_data = array( 'ID' => $form_id );

		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['title'] );
		}

		if ( isset( $data['status'] ) ) {
			$allowed_statuses = array( 'publish', 'private', 'draft', 'pending' );
			if ( in_array( $data['status'], $allowed_statuses, true ) ) {
				$post_data['post_status'] = $data['status'];
			}
		}

		if ( count( $post_data ) > 1 ) {
			wp_update_post( $post_data );
			$updated = true;
		}

		// Field definitions
		if ( isset( $data['fields'] ) ) {
			// Allow HTML in description field
			$html_allowed_keys = array( 'description', 'content', 'desc_after_label', 'desc_before_input', 'desc_after_input' );
			$sanitized_fields  = self::sanitize_array_recursive( $data['fields'], $html_allowed_keys );

			// The acceptance consent text allows limited inline HTML (links
			// etc.), which the generic recursion strips. Re-sanitize it from
			// the input with the dedicated rules.
			if ( is_array( $data['fields'] ) ) {
				foreach ( $data['fields'] as $fplant_field_idx => $fplant_raw_field ) {
					if ( is_array( $fplant_raw_field )
						&& 'acceptance' === ( $fplant_raw_field['type'] ?? '' )
						&& isset( $fplant_raw_field['acceptance_text'] ) && is_string( $fplant_raw_field['acceptance_text'] ) ) {
						$sanitized_fields[ $fplant_field_idx ]['acceptance_text'] = FPLANT_Field_Manager::sanitize_acceptance_text( $fplant_raw_field['acceptance_text'] );
					}
				}
			}

			FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_FIELDS, $sanitized_fields );
			$updated = true;
		}

		// HTML template
		if ( isset( $data['html_template'] ) ) {
			FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_HTML_TEMPLATE, wp_kses_post( $data['html_template'] ) );
			$updated = true;
		}

		// Form settings
		if ( isset( $data['settings'] ) ) {
			// Allow HTML in keys that store user-authored HTML content
			$html_allowed_keys = array( 'confirmation_message', 'after_submit_html', 'success_page_html', 'confirmation_template' );
			// Allow HTML for custom settings fields declared with 'allow_html'.
			foreach ( self::get_custom_settings_fields( $form_id ) as $custom_field ) {
				if ( $custom_field['allow_html'] ) {
					$html_allowed_keys[] = $custom_field['key'];
				}
			}
			$sanitized_settings = self::sanitize_array_recursive( $data['settings'], $html_allowed_keys );

			// Webhooks need dedicated sanitization (URL / secret rules, row
			// cap) from the raw input — the generic recursion above is not
			// sufficient for URLs.
			if ( isset( $data['settings']['webhooks'] ) ) {
				$sanitized_settings['webhooks'] = FPLANT_Webhook::sanitize_settings( $data['settings']['webhooks'] );
			}

			FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_SETTINGS, $sanitized_settings );

			/**
			 * Fires after a form's settings have been saved.
			 *
			 * MW WP Form's mwform_settings_save equivalent.
			 *
			 * @since 1.2.0
			 * @param int   $form_id            Form ID.
			 * @param array $sanitized_settings Saved settings (includes any custom settings).
			 */
			do_action( 'fplant_form_settings_saved', $form_id, $sanitized_settings );
			$updated = true;
		}

		// Admin email settings
		if ( isset( $data['email_admin'] ) ) {
			$sanitized_email_admin = self::sanitize_array_recursive( $data['email_admin'], array( 'body' ) );
			FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_EMAIL_ADMIN, $sanitized_email_admin );
			$updated = true;
		}

		// Auto-reply email settings
		if ( isset( $data['email_user'] ) ) {
			$sanitized_email_user = self::sanitize_array_recursive( $data['email_user'], array( 'body' ) );
			FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_EMAIL_USER, $sanitized_email_user );
			$updated = true;
		}

		// Spam protection settings
		if ( isset( $data['spam_protection'] ) ) {
			$sanitized_spam_protection = self::sanitize_array_recursive( $data['spam_protection'] );
			FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_SPAM_PROTECTION, $sanitized_spam_protection );
			$updated = true;
		}

		// ACF integration settings
		if ( isset( $data['acf_integration'] ) ) {
			$sanitized_acf_integration = self::sanitize_array_recursive( $data['acf_integration'] );
			FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_ACF_INTEGRATION, $sanitized_acf_integration );
			$updated = true;
		}

		return $updated;
	}

	/**
	 * Recursively sanitize array data
	 *
	 * @param mixed $data             Data to sanitize
	 * @param array $html_allowed_keys Keys that allow HTML content
	 * @return mixed Sanitized data
	 */
	public static function sanitize_array_recursive( $data, $html_allowed_keys = array() ) {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				// Check if this key allows HTML
				if ( in_array( $key, $html_allowed_keys, true ) ) {
					$sanitized[ $key ] = is_string( $value ) ? wp_kses_post( $value ) : $value;
				} else {
					$sanitized[ $key ] = self::sanitize_array_recursive( $value, $html_allowed_keys );
				}
			}
			return $sanitized;
		} elseif ( is_string( $data ) ) {
			return sanitize_textarea_field( $data );
		} elseif ( is_bool( $data ) || is_int( $data ) || is_float( $data ) ) {
			return $data;
		}
		return '';
	}

	/**
	 * Decode JSON input and sanitize the result.
	 *
	 * Combines json_decode() with sanitize_array_recursive() to ensure
	 * decoded data is always sanitized before use.
	 *
	 * @param mixed $raw              Raw input (JSON string or array).
	 * @param array $html_allowed_keys Keys that allow HTML content.
	 * @return array|null Sanitized array on success, null on failure.
	 */
	public static function sanitize_json_input( $raw, $html_allowed_keys = array() ) {
		if ( is_array( $raw ) ) {
			return self::sanitize_array_recursive( $raw, $html_allowed_keys );
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			return null;
		}
		return self::sanitize_array_recursive( $decoded, $html_allowed_keys );
	}

	/**
	 * Get custom settings field definitions registered via the
	 * fplant_custom_settings_fields filter (mwform_settings_extend_fields equivalent).
	 *
	 * Third-party code can add custom fields to the form settings screen. The
	 * values are rendered in the editor, saved with the form settings, and
	 * readable at runtime via $form['settings'][ $key ]. Prefix keys (e.g. 'x_')
	 * to avoid collisions with built-in setting keys.
	 *
	 *     add_filter( 'fplant_custom_settings_fields', function ( $fields, $form_id ) {
	 *         $fields[] = array(
	 *             'key'   => 'x_crm_endpoint',
	 *             'type'  => 'text',
	 *             'label' => 'CRM Endpoint',
	 *         );
	 *         return $fields;
	 *     }, 10, 2 );
	 *
	 * @since 1.2.0
	 * @param int $form_id Form ID.
	 * @return array List of normalized field definitions (key, type, label, default, options, description, allow_html).
	 */
	public static function get_custom_settings_fields( $form_id ) {
		$fields = apply_filters( 'fplant_custom_settings_fields', array(), absint( $form_id ) );

		if ( ! is_array( $fields ) ) {
			return array();
		}

		$allowed_types = array( 'text', 'textarea', 'checkbox', 'select', 'number' );
		$normalized    = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['key'] ) || ! is_string( $field['key'] ) ) {
				continue;
			}

			// Restrict key to safe characters (letters, numbers, underscore).
			$key = preg_replace( '/[^a-zA-Z0-9_]/', '', $field['key'] );
			if ( '' === $key ) {
				continue;
			}

			$type = ( isset( $field['type'] ) && in_array( $field['type'], $allowed_types, true ) ) ? $field['type'] : 'text';

			// Keyed by $key so duplicate keys collapse (last definition wins).
			$normalized[ $key ] = array(
				'key'         => $key,
				'type'        => $type,
				'label'       => isset( $field['label'] ) ? (string) $field['label'] : $key,
				'default'     => isset( $field['default'] ) ? $field['default'] : '',
				'options'     => ( isset( $field['options'] ) && is_array( $field['options'] ) ) ? $field['options'] : array(),
				'description' => isset( $field['description'] ) ? (string) $field['description'] : '',
				'allow_html'  => ! empty( $field['allow_html'] ),
			);
		}

		return array_values( $normalized );
	}

	/**
	 * Delete form
	 *
	 * @param int  $form_id      Form ID
	 * @param bool $force_delete Whether to delete permanently
	 * @return bool
	 */
	public function delete_form( $form_id, $force_delete = false ) {
		$result = wp_delete_post( $form_id, $force_delete );
		return ! empty( $result );
	}

	/**
	 * Duplicate form
	 *
	 * @param int $form_id Form ID
	 * @return int|false New form ID or false
	 */
	public function duplicate_form( $form_id ) {
		$form = FPLANT_Database::get_form( $form_id );

		if ( ! $form ) {
			return false;
		}

		// Create new form
		$new_form_id = wp_insert_post(
			array(
				'post_type'   => 'fplant_form',
				'post_title'  => $form['title'] . ' (Copy)',
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $new_form_id ) ) {
			return false;
		}

		// Copy metadata
		FPLANT_Database::update_form_meta( $new_form_id, FPLANT_Database::META_FIELDS, $form['fields'] );
		FPLANT_Database::update_form_meta( $new_form_id, FPLANT_Database::META_HTML_TEMPLATE, $form['html_template'] );
		FPLANT_Database::update_form_meta( $new_form_id, FPLANT_Database::META_SETTINGS, $form['settings'] );
		FPLANT_Database::update_form_meta( $new_form_id, FPLANT_Database::META_EMAIL_ADMIN, $form['email_admin'] );
		FPLANT_Database::update_form_meta( $new_form_id, FPLANT_Database::META_EMAIL_USER, $form['email_user'] );
		FPLANT_Database::update_form_meta( $new_form_id, FPLANT_Database::META_SPAM_PROTECTION, $form['spam_protection'] );
		FPLANT_Database::update_form_meta( $new_form_id, FPLANT_Database::META_ACF_INTEGRATION, $form['acf_integration'] );

		return $new_form_id;
	}

	/**
	 * Get form list
	 *
	 * @param array $args Query arguments
	 * @return array
	 */
	public function get_forms( $args = array() ) {
		$defaults = array(
			'post_type'      => 'fplant_form',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$query = new WP_Query( $args );

		$forms = array();
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$forms[] = FPLANT_Database::get_form( $post->ID );
			}
		}

		return $forms;
	}

	/**
	 * Save form metadata
	 *
	 * @param int     $post_id Post ID
	 * @param WP_Post $post    Post object
	 */
	public function save_form_meta( $post_id, $post ) {
		// Nonce verification
		if ( ! isset( $_POST['fplant_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fplant_form_nonce'] ) ), 'wfp_save_form' ) ) {
			return;
		}

		// Skip if autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save form data
		if ( isset( $_POST['fplant_form_data'] ) ) {
			// 'acceptance_text' passes this stage with HTML intact so
			// update_form() can apply its dedicated (stricter) kses rules.
			$html_allowed_keys = array( 'acceptance_text', 'description', 'content', 'desc_after_label', 'desc_before_input', 'desc_after_input', 'html_template', 'confirmation_message', 'after_submit_html', 'success_page_html', 'confirmation_template', 'body' );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_json_input().
			$form_data = self::sanitize_json_input( wp_unslash( $_POST['fplant_form_data'] ), $html_allowed_keys );
			if ( null === $form_data ) {
				return;
			}
			$this->update_form( $post_id, $form_data );
		}
	}

	/**
	 * Get form submission count
	 *
	 * @param int $form_id Form ID
	 * @return int
	 */
	public function get_submission_count( $form_id ) {
		return FPLANT_Database::get_submissions_count( $form_id );
	}
}
