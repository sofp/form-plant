<?php
/**
 * Submission Manager class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Submission_Manager class
 */
class FPLANT_Submission_Manager {

	/**
	 * Custom upload path
	 *
	 * @var array
	 */
	private $custom_upload_path = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// AJAX actions
		add_action( 'wp_ajax_fplant_submit_form', array( $this, 'handle_ajax_submission' ) );
		add_action( 'wp_ajax_nopriv_fplant_submit_form', array( $this, 'handle_ajax_submission' ) );

		// Validation-only AJAX action
		add_action( 'wp_ajax_fplant_validate_form', array( $this, 'handle_ajax_validation' ) );
		add_action( 'wp_ajax_nopriv_fplant_validate_form', array( $this, 'handle_ajax_validation' ) );
	}

	/**
	 * Process form submission
	 *
	 * @param int   $form_id Form ID.
	 * @param array $data Submission data.
	 * @return array
	 */
	public function process_submission( $form_id, $data ) {
		// Get form data
		$form = FPLANT_Database::get_form( $form_id );

		if ( ! $form ) {
			return array(
				'success' => false,
				'message' => __( 'Form not found', 'form-plant' ),
			);
		}

		// Hook: before submission
		do_action( 'fplant_before_submission', $form_id, $data );

		// Validation
		$validator = new FPLANT_Validator();
		$validation_result = $validator->validate( $form['fields'], $data, $form_id );

		if ( ! $validation_result['valid'] ) {
			return array(
				'success' => false,
				'message' => __( 'There are errors in your input', 'form-plant' ),
				'errors'  => $validation_result['errors'],
			);
		}

		// Sanitize data
		$sanitized_data = $this->sanitize_submission_data( $data, $form['fields'] );

		// Filter: modify submission data
		$sanitized_data = apply_filters( 'fplant_submission_data', $sanitized_data, $form_id );

		// Check submission save settings (default is 'full' = save all)
		$save_submission = isset( $form['settings']['save_submission'] ) ? $form['settings']['save_submission'] : 'full';

		// Backward compatibility: convert from old format (true/false)
		if ( true === $save_submission || 'true' === $save_submission || '1' === $save_submission || 1 === $save_submission ) {
			$save_submission = 'full';
		} elseif ( false === $save_submission || 'false' === $save_submission || '' === $save_submission || '0' === $save_submission || 0 === $save_submission ) {
			$save_submission = 'none';
		}

		// Use default for invalid values
		if ( ! in_array( $save_submission, array( 'none', 'metadata_only', 'full' ), true ) ) {
			$save_submission = 'full';
		}

		$submission_id = 0;

		// Save to database (if not 'none')
		if ( 'none' !== $save_submission ) {
			// Filter: transform data before database save
			$save_data = apply_filters( 'fplant_before_save_submission_data', $sanitized_data, $form_id );

			// Clear input data for 'metadata_only'
			if ( 'metadata_only' === $save_submission ) {
				$save_data = array();
			}

			$submission_id = FPLANT_Database::save_submission( $form_id, $save_data );

			if ( ! $submission_id ) {
				return array(
					'success' => false,
					'message' => __( 'Failed to save data', 'form-plant' ),
				);
			}
		}

		// Hook: after submission
		do_action( 'fplant_after_submission', $submission_id, $form_id, $sanitized_data );

		// Send emails
		$this->send_emails( $form, $sanitized_data, $submission_id );

		// Action: custom processing after submission (external API integration, etc.)
		do_action( 'fplant_after_submission_complete', $sanitized_data, $form_id, $form, $submission_id );

		// ACF integration
		if ( ! empty( $form['acf_integration']['enabled'] ) && class_exists( 'ACF' ) ) {
			$this->handle_acf_integration( $form, $sanitized_data );
		}

		// Success message
		$success_message = ! empty( $form['settings']['success_message'] )
			? $form['settings']['success_message']
			: __( 'Submission completed', 'form-plant' );

		// Get action type
		$action_type = ! empty( $form['settings']['action_type'] ) ? $form['settings']['action_type'] : 'message';

		$result = array(
			'success'       => true,
			'message'       => $success_message,
			'submission_id' => $submission_id,
			'action_type'   => $action_type,
		);

		// Set additional data based on action type
		if ( 'redirect' === $action_type && ! empty( $form['settings']['redirect_url'] ) ) {
			$result['redirect_url'] = $form['settings']['redirect_url'];
		} elseif ( 'custom_page' === $action_type && ! empty( $form['settings']['success_page_html'] ) ) {
			$result['success_page_html'] = $form['settings']['success_page_html'];
		}

		return $result;
	}

	/**
	 * Handle AJAX submission
	 */
	public function handle_ajax_submission() {
		// Nonce check
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fplant_form_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid request', 'form-plant' ),
				)
			);
		}

		// Get form ID and data
		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data, sanitized after json_decode below
		$data    = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

		// Decode JSON if sent via FormData
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		// Sanitize decoded JSON data
		if ( is_array( $data ) ) {
			$data = FPLANT_Form_Manager::sanitize_array_recursive( $data );
		}

		// Verify reCAPTCHA
		$form = FPLANT_Database::get_form( $form_id );
		if ( $form && ! empty( $form['settings']['recaptcha_enabled'] ) ) {
			$recaptcha_result = $this->verify_recaptcha( $form );
			if ( is_wp_error( $recaptcha_result ) ) {
				wp_send_json_error(
					array(
						'message' => $recaptcha_result->get_error_message(),
					)
				);
			}
		}

		// Handle file uploads
		$uploaded_files = $this->handle_file_uploads( $form_id );
		if ( is_wp_error( $uploaded_files ) ) {
			wp_send_json_error(
				array(
					'message' => $uploaded_files->get_error_message(),
				)
			);
		}

		// Add uploaded file info to data
		$data = array_merge( $data, $uploaded_files );

		// Process submission
		$result = $this->process_submission( $form_id, $data );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Handle validation only (AJAX)
	 */
	public function handle_ajax_validation() {
		// Nonce check
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fplant_form_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid request', 'form-plant' ),
				)
			);
		}

		// Get form ID and data
		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data, sanitized after json_decode below
		$data    = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

		// Decode JSON if sent via FormData
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		// Sanitize decoded JSON data
		if ( is_array( $data ) ) {
			$data = FPLANT_Form_Manager::sanitize_array_recursive( $data );
		}

		// Get form data
		$form = FPLANT_Database::get_form( $form_id );

		if ( ! $form ) {
			wp_send_json_error(
				array(
					'message' => __( 'Form not found', 'form-plant' ),
				)
			);
		}

		// Validate file fields only (no actual upload)
		if ( ! empty( $_FILES ) ) {
			$file_validation_result = $this->validate_files( $form['fields'] );
			if ( is_wp_error( $file_validation_result ) ) {
				wp_send_json_error(
					array(
						'message' => $file_validation_result->get_error_message(),
						'errors'  => array(),
					)
				);
			}
		}

		// Run validation
		$validator = new FPLANT_Validator();
		$validation_result = $validator->validate( $form['fields'], $data, $form_id );

		if ( ! $validation_result['valid'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'There are errors in your input', 'form-plant' ),
					'errors'  => $validation_result['errors'],
				)
			);
		}

		// Validation success - generate confirmation HTML.
		$confirmation_html = $this->render_confirmation( $form, $data );

		wp_send_json_success(
			array(
				'message'           => __( 'Validation successful', 'form-plant' ),
				'confirmation_html' => $confirmation_html,
			)
		);
	}

	/**
	 * Sanitize submission data
	 *
	 * @param array $data Submission data.
	 * @param array $fields Field settings.
	 * @return array
	 */
	private function sanitize_submission_data( $data, $fields ) {
		$sanitized = array();

		foreach ( $fields as $field ) {
			$field_name = $field['name'];

			if ( ! isset( $data[ $field_name ] ) ) {
				continue;
			}

			$value = $data[ $field_name ];

			// Sanitize by field type
			switch ( $field['type'] ) {
				case 'email':
					$sanitized[ $field_name ] = sanitize_email( $value );
					break;

				case 'url':
					$sanitized[ $field_name ] = esc_url_raw( $value );
					break;

				case 'number':
					$sanitized[ $field_name ] = floatval( $value );
					break;

				case 'textarea':
					$sanitized[ $field_name ] = sanitize_textarea_field( $value );
					break;

				case 'checkbox':
					$sanitized[ $field_name ] = is_array( $value )
						? array_map( 'sanitize_text_field', $value )
						: sanitize_text_field( $value );
					break;

				case 'file':
					// Save file as array (url, file, type, filename)
					if ( is_array( $value ) ) {
						$sanitized[ $field_name ] = array(
							'url'      => esc_url_raw( $value['url'] ),
							'file'     => sanitize_text_field( $value['file'] ),
							'type'     => sanitize_text_field( $value['type'] ),
							'filename' => isset( $value['filename'] ) ? sanitize_text_field( $value['filename'] ) : '',
						);
					}
					break;

				default:
					$sanitized[ $field_name ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Send emails
	 *
	 * @param array $form Form data.
	 * @param array $data Submission data.
	 * @param int   $submission_id Submission ID.
	 */
	private function send_emails( $form, $data, $submission_id = 0 ) {
		$email_handler = new FPLANT_Email_Handler();

		// Admin notification
		if ( ! empty( $form['email_admin']['enabled'] ) ) {
			$email_handler->send_admin_email( $form, $data, $submission_id );
		}

		// Auto-reply
		if ( ! empty( $form['email_user']['enabled'] ) ) {
			$email_handler->send_user_email( $form, $data, $submission_id );
		}
	}

	/**
	 * Handle ACF integration
	 *
	 * @param array $form Form data.
	 * @param array $data Submission data.
	 */
	private function handle_acf_integration( $form, $data ) {
		if ( ! class_exists( 'FPLANT_ACF_Integration' ) ) {
			return;
		}

		$acf_integration = new FPLANT_ACF_Integration();
		$acf_integration->create_post_from_submission( $form, $data );
	}

	/**
	 * Export submissions (CSV)
	 *
	 * @param int $form_id Form ID.
	 */
	public function export_submissions_csv( $form_id = 0, $args = array() ) {
		$form = null;
		if ( $form_id > 0 ) {
			$form = FPLANT_Database::get_form( $form_id );
			if ( ! $form ) {
				return;
			}
		}

		// Set filter conditions (no limit)
		$query_args = array(
			'limit'     => 10000,
			'offset'    => 0,
			'date_from' => isset( $args['date_from'] ) ? $args['date_from'] : '',
			'date_to'   => isset( $args['date_to'] ) ? $args['date_to'] : '',
			'search'    => isset( $args['search'] ) ? $args['search'] : '',
		);

		$submissions = FPLANT_Database::get_submissions( $form_id, $query_args );

		// Generate filename
		$filename = 'submissions';
		if ( $form_id > 0 ) {
			$filename .= '-' . $form_id;
		}
		$filename .= '-' . gmdate( 'Y-m-d' ) . '.csv';

		// CSV headers
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );

		// Add BOM (Excel support)
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		if ( $form ) {
			// Single form export
			// Header row
			$headers = array( 'ID', 'Submitted At' );
			foreach ( $form['fields'] as $field ) {
				if ( 'html' !== $field['type'] ) {
					$headers[] = $this->sanitize_csv_value( $field['label'] );
				}
			}
			$headers[] = 'IP Address';

			fputcsv( $output, $headers );

			// Data rows
			foreach ( $submissions as $submission ) {
				$row = array(
					$submission['id'],
					$submission['created_at'],
				);

				foreach ( $form['fields'] as $field ) {
					if ( 'html' !== $field['type'] ) {
						$value = isset( $submission['data'][ $field['name'] ] ) ? $submission['data'][ $field['name'] ] : '';

						// Convert array to comma-separated
						if ( is_array( $value ) ) {
							$value = implode( ', ', $value );
						}

						$row[] = $this->sanitize_csv_value( $value );
					}
				}

				$row[] = $this->sanitize_csv_value( $submission['ip_address'] );

				fputcsv( $output, $row );
			}
		} else {
			// All forms export (generic format)
			$headers = array( 'ID', 'Form', 'Submitted At', 'Data', 'IP Address' );
			fputcsv( $output, $headers );

			foreach ( $submissions as $submission ) {
				$submission_form = FPLANT_Database::get_form( $submission['form_id'] );
				$form_title      = $submission_form ? $submission_form['title'] : '';

				// Export data in JSON format
				$data_str = '';
				if ( ! empty( $submission['data'] ) ) {
					$data_parts = array();
					foreach ( $submission['data'] as $key => $value ) {
						if ( is_array( $value ) ) {
							$value = implode( ', ', $value );
						}
						$data_parts[] = $key . ': ' . $value;
					}
					$data_str = implode( ' | ', $data_parts );
				}

				$row = array(
					$submission['id'],
					$this->sanitize_csv_value( $form_title ),
					$submission['created_at'],
					$this->sanitize_csv_value( $data_str ),
					$this->sanitize_csv_value( $submission['ip_address'] ),
				);

				fputcsv( $output, $row );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Required for CSV output stream
		fclose( $output );
		exit;
	}

	/**
	 * Sanitize cell value to prevent CSV Injection
	 *
	 * Prevents execution as formula in spreadsheets like Excel
	 *
	 * @param string $value Cell value.
	 * @return string Sanitized value.
	 */
	private function sanitize_csv_value( $value ) {
		if ( is_string( $value ) && preg_match( '/^[\=\+\-\@\t\r]/', $value ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Verify reCAPTCHA
	 *
	 * @param array $form Form data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function verify_recaptcha( $form ) {
		$secret_key = get_option( 'fplant_recaptcha_secret_key' );

		if ( empty( $secret_key ) ) {
			return new WP_Error( 'recaptcha_error', __( 'reCAPTCHA configuration is incomplete', 'form-plant' ) );
		}

		// Get reCAPTCHA v3 token
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_ajax_submission
		$token = isset( $_POST['fplant_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['fplant_recaptcha_token'] ) ) : '';

		if ( empty( $token ) ) {
			return new WP_Error( 'recaptcha_error', __( 'reCAPTCHA verification failed', 'form-plant' ) );
		}

		// Send verification request to Google API
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body'    => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => $this->get_client_ip(),
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'recaptcha_error', __( 'Failed to communicate with reCAPTCHA server', 'form-plant' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'recaptcha_error', __( 'reCAPTCHA verification failed', 'form-plant' ) );
		}

		// Check v3 score (only if score is included in response)
		if ( isset( $body['score'] ) ) {
			$threshold = floatval( get_option( 'fplant_recaptcha_v3_threshold', 0.5 ) );
			$score     = floatval( $body['score'] );

			if ( $score < $threshold ) {
				return new WP_Error( 'recaptcha_error', __( 'Suspected spam detected', 'form-plant' ) );
			}
		}

		return true;
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Validated by filter_var, sanitized after validation
			if ( isset( $_SERVER[ $key ] ) && filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}

		return '';
	}

	/**
	 * Handle file uploads
	 *
	 * @param int $form_id Form ID.
	 * @return array|WP_Error Array of uploaded file info, or error.
	 */
	private function handle_file_uploads( $form_id = 0 ) {
		$uploaded_files = array();

		// Skip if $_FILES is empty
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling AJAX handler (handle_submission, handle_submit_ajax)
		if ( empty( $_FILES ) ) {
			return $uploaded_files;
		}

		// Load WordPress file upload functions
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Create custom upload directory
		$custom_dir = $this->create_upload_directory( $form_id );
		if ( is_wp_error( $custom_dir ) ) {
			return $custom_dir;
		}

		// Blacklist of dangerous extensions (executable files)
		$dangerous_extensions = array(
			'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'phps',
			'cgi', 'pl', 'asp', 'aspx', 'jsp', 'exe', 'sh', 'bat', 'cmd',
			'com', 'htaccess', 'htpasswd', 'ini', 'py', 'rb', 'js', 'mjs',
		);

		// Whitelist of allowed MIME types (extension => MIME type mapping for wp_handle_upload)
		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);

		// Process each file field
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling AJAX handler (handle_submission, handle_submit_ajax)
		foreach ( $_FILES as $field_name => $file ) {
			// Skip if no file uploaded
			if ( empty( $file['name'] ) || $file['error'] === UPLOAD_ERR_NO_FILE ) {
				continue;
			}

			// Check for upload errors
			if ( $file['error'] !== UPLOAD_ERR_OK ) {
				return new WP_Error(
					'upload_error',
					sprintf(
						/* translators: %s: field name */
						__( 'Failed to upload %s', 'form-plant' ),
						$field_name
					)
				);
			}

			// Sanitize filename
			$file_info     = pathinfo( $file['name'] );
			$file_ext      = isset( $file_info['extension'] ) ? strtolower( $file_info['extension'] ) : '';
			$file_basename = sanitize_file_name( $file_info['filename'] );

			// Security: check if extension is in blacklist
			if ( in_array( $file_ext, $dangerous_extensions, true ) ) {
				return new WP_Error(
					'invalid_file_type',
					__( 'This file type cannot be uploaded', 'form-plant' )
				);
			}

			// Security: prevent double extension attacks (e.g., malicious.php.jpg)
			foreach ( $dangerous_extensions as $dangerous_ext ) {
				if ( preg_match( '/\.' . preg_quote( $dangerous_ext, '/' ) . '$/i', $file_basename ) ) {
					return new WP_Error(
						'invalid_filename',
						__( 'Invalid filename', 'form-plant' )
					);
				}
			}

			// Security: validate MIME type server-side (don't trust client-provided value)
			$real_mime_type = '';
			if ( function_exists( 'finfo_open' ) ) {
				$finfo = finfo_open( FILEINFO_MIME_TYPE );
				if ( $finfo ) {
					$real_mime_type = finfo_file( $finfo, $file['tmp_name'] );
					finfo_close( $finfo );
				}
			} elseif ( function_exists( 'mime_content_type' ) ) {
				$real_mime_type = mime_content_type( $file['tmp_name'] );
			}

			// Check if MIME type is in whitelist (values of the mapping array)
			if ( ! empty( $real_mime_type ) && ! in_array( $real_mime_type, array_values( $allowed_mimes ), true ) ) {
				return new WP_Error(
					'invalid_mime_type',
					__( 'This file type cannot be uploaded', 'form-plant' )
				);
			}

			$new_filename  = $file_basename . '_' . wp_generate_password( 6, false, false ) . '.' . $file_ext;

			// Set destination path
			$upload_path = $custom_dir['path'] . '/' . $new_filename;
			$upload_url  = $custom_dir['url'] . '/' . $new_filename;

			// WordPress upload processing (using custom directory)
			add_filter( 'upload_dir', array( $this, 'custom_upload_dir' ) );
			$this->custom_upload_path = $custom_dir;

			// Use WordPress file upload handler
			$upload_overrides = array(
				'test_form'                => false,
				'mimes'                    => $allowed_mimes,
				'unique_filename_callback' => function ( $dir, $name, $ext ) use ( $new_filename ) {
					return $new_filename;
				},
			);

			$upload_result = wp_handle_upload( $file, $upload_overrides );

			remove_filter( 'upload_dir', array( $this, 'custom_upload_dir' ) );

			if ( isset( $upload_result['error'] ) ) {
				return new WP_Error(
					'upload_error',
					sprintf(
						/* translators: %s: field name */
						__( 'Failed to upload %s', 'form-plant' ),
						$field_name
					) . ': ' . $upload_result['error']
				);
			}

			// Add upload info to array (using verified MIME type)
			$uploaded_files[ $field_name ] = array(
				'url'      => $upload_result['url'],
				'file'     => $upload_result['file'],
				'type'     => ! empty( $upload_result['type'] ) ? $upload_result['type'] : ( ! empty( $real_mime_type ) ? $real_mime_type : $file['type'] ),
				'filename' => basename( $upload_result['file'] ),
			);
		}

		return $uploaded_files;
	}

	/**
	 * Create custom upload directory
	 *
	 * @param int $form_id Form ID.
	 * @return array|WP_Error Directory info array, or error.
	 */
	private function create_upload_directory( $form_id ) {
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'] . '/fplant_uploads';

		// Reuse existing directory for the same form ID
		$existing_dirs = glob( $base_dir . '/fplant_' . $form_id . '_*_uploads', GLOB_ONLYDIR );
		if ( ! empty( $existing_dirs ) ) {
			$custom_dir_path = $existing_dirs[0];
			$custom_dir_name = basename( $custom_dir_path );
			$custom_dir_url  = $upload_dir['baseurl'] . '/fplant_uploads/' . $custom_dir_name;

			return array(
				'path' => $custom_dir_path,
				'url'  => $custom_dir_url,
			);
		}

		// Create new directory with random string for security
		$random_string   = wp_generate_password( 6, false, false );
		$custom_dir_name = 'fplant_' . $form_id . '_' . $random_string . '_uploads';
		$custom_dir_path = $base_dir . '/' . $custom_dir_name;
		$custom_dir_url  = $upload_dir['baseurl'] . '/fplant_uploads/' . $custom_dir_name;

		// Create directory if it doesn't exist
		if ( ! file_exists( $custom_dir_path ) ) {
			if ( ! wp_mkdir_p( $custom_dir_path ) ) {
				return new WP_Error(
					'dir_creation_failed',
					__( 'Failed to create upload directory', 'form-plant' )
				);
			}

			// Create .htaccess to prevent directory listing
			$htaccess_file    = $custom_dir_path . '/.htaccess';
			$htaccess_content = "Options -Indexes\n<Files *>\n  Order Allow,Deny\n  Deny from all\n</Files>\n";
			file_put_contents( $htaccess_file, $htaccess_content );

			// Create index.php
			$index_file = $custom_dir_path . '/index.php';
			file_put_contents( $index_file, '<?php // Silence is golden' );
		}

		return array(
			'path' => $custom_dir_path,
			'url'  => $custom_dir_url,
		);
	}

	/**
	 * Custom upload directory filter
	 *
	 * @param array $dirs Upload directory info.
	 * @return array
	 */
	public function custom_upload_dir( $dirs ) {
		if ( ! empty( $this->custom_upload_path ) ) {
			$dirs['path']   = $this->custom_upload_path['path'];
			$dirs['url']    = $this->custom_upload_path['url'];
			$dirs['subdir'] = '';
		}
		return $dirs;
	}

	/**
	 * Render confirmation screen HTML
	 *
	 * @param array $form Form data.
	 * @param array $data Submitted data.
	 * @return string Confirmation HTML.
	 */
	private function render_confirmation( $form, $data ) {
		$settings            = isset( $form['settings'] ) ? $form['settings'] : array();
		$use_custom_template = ! empty( $settings['use_confirmation_template'] );
		$custom_template     = isset( $settings['confirmation_template'] ) ? $settings['confirmation_template'] : '';

		if ( $use_custom_template && ! empty( $custom_template ) ) {
			// Use custom HTML template.
			return $this->render_custom_confirmation_template( $form, $data, $custom_template );
		}

		// Use default template.
		return $this->render_default_confirmation( $form, $data );
	}

	/**
	 * Render default confirmation screen
	 *
	 * @param array $form Form data.
	 * @param array $data Submitted data.
	 * @return string Confirmation HTML.
	 */
	private function render_default_confirmation( $form, $data ) {
		$template_loader = new FPLANT_Template_Loader();

		// Get settings for confirmation screen.
		$settings = isset( $form['settings'] ) ? $form['settings'] : array();

		// Get filenames for file fields.
		$filenames = $this->get_file_field_names( $form['fields'] );

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
		$fields  = $form['fields'];
		$values  = $data;
		include $template;
		return ob_get_clean();
	}

	/**
	 * Render custom confirmation template with shortcode replacement
	 *
	 * @param array  $form     Form data.
	 * @param array  $data     Submitted data.
	 * @param string $template Custom HTML template.
	 * @return string Confirmation HTML.
	 */
	private function render_custom_confirmation_template( $form, $data, $template ) {
		$html     = $template;
		$settings = isset( $form['settings'] ) ? $form['settings'] : array();

		// Get filenames for file fields.
		$filenames = $this->get_file_field_names( $form['fields'] );

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
	 * Get filenames for file fields from $_FILES
	 *
	 * @param array $fields Form field definitions.
	 * @return array Array of field_name => filename.
	 */
	private function get_file_field_names( $fields ) {
		$filenames = array();
		foreach ( $fields as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling AJAX handler
			if ( 'file' === $field['type'] && ! empty( $_FILES[ $field['name'] ]['name'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling AJAX handler
				$filenames[ $field['name'] ] = sanitize_file_name( $_FILES[ $field['name'] ]['name'] );
			}
		}
		return $filenames;
	}

	/**
	 * Replace [fplant_value name="..."] shortcodes
	 *
	 * @param string $html      HTML template.
	 * @param array  $form      Form data.
	 * @param array  $data      Submitted data.
	 * @param array  $filenames Array of filenames for file fields.
	 * @return string HTML with shortcodes replaced.
	 */
	private function replace_value_shortcodes( $html, $form, $data, $filenames ) {
		$field_manager = new FPLANT_Field_Manager();

		// Find all [fplant_value name="..."] shortcodes.
		preg_match_all( '/\[fplant_value\s+name="([^"]+)"\]/', $html, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$shortcode  = $match[0];
			$field_name = $match[1];

			// Find the field definition.
			$field = null;
			foreach ( $form['fields'] as $f ) {
				if ( $f['name'] === $field_name ) {
					$field = $f;
					break;
				}
			}

			if ( $field ) {
				$value    = isset( $data[ $field_name ] ) ? $data[ $field_name ] : '';
				$filename = isset( $filenames[ $field_name ] ) ? $filenames[ $field_name ] : '';

				// Render the field value using confirm-fields/{type}.php template.
				$field_html = $field_manager->render_confirm_field( $field, $value, $filename );
				$html       = str_replace( $shortcode, $field_html, $html );
			} else {
				// Field not found, remove the shortcode.
				$html = str_replace( $shortcode, '', $html );
			}
		}

		return $html;
	}

	/**
	 * Replace button shortcodes
	 *
	 * @param string $html     HTML template.
	 * @param array  $settings Form settings.
	 * @return string HTML with shortcodes replaced.
	 */
	private function replace_button_shortcodes( $html, $settings ) {
		// Default values.
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

		// Replace [fplant_back] or [fplant_back text="..." class="..." id="..."].
		$html = preg_replace_callback(
			'/\[fplant_back(?:\s+([^\]]*))?\]/',
			function ( $matches ) use ( $back_text, $back_class, $back_id ) {
				$attrs = isset( $matches[1] ) ? $matches[1] : '';
				$text  = $back_text;
				$class = $back_class;
				$id    = $back_id;

				// Parse attributes.
				if ( preg_match( '/text="([^"]*)"/', $attrs, $m ) ) {
					$text = $m[1];
				}
				if ( preg_match( '/class="([^"]*)"/', $attrs, $m ) ) {
					$class = $m[1];
				}
				if ( preg_match( '/id="([^"]*)"/', $attrs, $m ) ) {
					$id = $m[1];
				}

				$id_attr = ! empty( $id ) ? ' id="' . esc_attr( $id ) . '"' : '';
				return '<button type="button" class="fplant-back-button ' . esc_attr( $class ) . '"' . $id_attr . '>' . esc_html( $text ) . '</button>';
			},
			$html
		);

		// Replace [fplant_confirm_submit] or [fplant_confirm_submit text="..." class="..." id="..."].
		$html = preg_replace_callback(
			'/\[fplant_confirm_submit(?:\s+([^\]]*))?\]/',
			function ( $matches ) use ( $submit_text, $submit_class, $submit_id ) {
				$attrs = isset( $matches[1] ) ? $matches[1] : '';
				$text  = $submit_text;
				$class = $submit_class;
				$id    = $submit_id;

				// Parse attributes.
				if ( preg_match( '/text="([^"]*)"/', $attrs, $m ) ) {
					$text = $m[1];
				}
				if ( preg_match( '/class="([^"]*)"/', $attrs, $m ) ) {
					$class = $m[1];
				}
				if ( preg_match( '/id="([^"]*)"/', $attrs, $m ) ) {
					$id = $m[1];
				}

				$id_attr = ! empty( $id ) ? ' id="' . esc_attr( $id ) . '"' : '';
				return '<button type="button" class="fplant-confirm-submit-button ' . esc_attr( $class ) . '"' . $id_attr . '>' . esc_html( $text ) . '</button>';
			},
			$html
		);

		return $html;
	}

	/**
	 * Validate files only (no upload)
	 *
	 * @param array $fields Field settings.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function validate_files( $fields ) {
		// Skip if $_FILES is empty
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling AJAX handler (handle_confirm_ajax)
		if ( empty( $_FILES ) ) {
			return true;
		}

		// Get file field settings
		$file_fields = array();
		foreach ( $fields as $field ) {
			if ( 'file' === $field['type'] ) {
				$file_fields[ $field['name'] ] = $field;
			}
		}

		// Check each file field
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling AJAX handler (handle_confirm_ajax)
		foreach ( $_FILES as $field_name => $file ) {
			// Skip if no file uploaded
			if ( empty( $file['name'] ) || $file['error'] === UPLOAD_ERR_NO_FILE ) {
				continue;
			}

			// Check for upload errors
			if ( $file['error'] !== UPLOAD_ERR_OK ) {
				return new WP_Error(
					'upload_error',
					sprintf(
						/* translators: %s: field name */
						__( 'Failed to upload %s', 'form-plant' ),
						$field_name
					)
				);
			}

			// Check size and extension if field settings exist
			if ( isset( $file_fields[ $field_name ] ) ) {
				$field = $file_fields[ $field_name ];

				// File size check
				$max_size = isset( $field['max_size'] ) ? intval( $field['max_size'] ) * 1024 * 1024 : 2097152; // Default 2MB
				if ( $file['size'] > $max_size ) {
					return new WP_Error(
						'file_size_error',
						sprintf(
							/* translators: 1: field label, 2: max file size */
							__( '%1$s file size must be %2$sMB or less', 'form-plant' ),
							$field['label'],
							$max_size / 1024 / 1024
						)
					);
				}

				// File extension check
				if ( ! empty( $field['allowed_types'] ) ) {
					$file_info = pathinfo( $file['name'] );
					$file_ext  = isset( $file_info['extension'] ) ? strtolower( $file_info['extension'] ) : '';
					$allowed_types = array_map( 'strtolower', $field['allowed_types'] );

					if ( ! in_array( $file_ext, $allowed_types, true ) ) {
						return new WP_Error(
							'file_type_error',
							sprintf(
								/* translators: 1: field label, 2: allowed file types */
								__( '%1$s only accepts %2$s files', 'form-plant' ),
								$field['label'],
								implode( ', ', $allowed_types )
							)
						);
					}
				}
			}
		}

		return true;
	}
}
