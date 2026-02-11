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
			$html_allowed_keys = array( 'description' );
			$sanitized_fields  = self::sanitize_array_recursive( $data['fields'], $html_allowed_keys );
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
			// Allow HTML in confirmation_message and after_submit_html
			$html_allowed_keys = array( 'confirmation_message', 'after_submit_html' );
			$sanitized_settings = self::sanitize_array_recursive( $data['settings'], $html_allowed_keys );
			FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_SETTINGS, $sanitized_settings );
			$updated = true;
		}

		// Admin email settings
		if ( isset( $data['email_admin'] ) ) {
			$sanitized_email_admin = self::sanitize_array_recursive( $data['email_admin'] );
			FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_EMAIL_ADMIN, $sanitized_email_admin );
			$updated = true;
		}

		// Auto-reply email settings
		if ( isset( $data['email_user'] ) ) {
			$sanitized_email_user = self::sanitize_array_recursive( $data['email_user'] );
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
			return sanitize_text_field( $data );
		} elseif ( is_bool( $data ) || is_int( $data ) || is_float( $data ) ) {
			return $data;
		}
		return '';
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
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data, sanitized per field after json_decode in update_form
			$form_data = json_decode( wp_unslash( $_POST['fplant_form_data'] ), true );
			if ( $form_data ) {
				$this->update_form( $post_id, $form_data );
			}
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
