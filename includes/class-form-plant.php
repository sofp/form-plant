<?php
/**
 * Main plugin class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Form_Plant class
 */
class FPLANT_Form_Plant {

	/**
	 * Singleton instance
	 *
	 * @var FPLANT_Form_Plant
	 */
	private static $instance = null;

	/**
	 * Form manager
	 *
	 * @var FPLANT_Form_Manager
	 */
	public $form_manager;

	/**
	 * Field manager
	 *
	 * @var FPLANT_Field_Manager
	 */
	public $field_manager;

	/**
	 * Submission manager
	 *
	 * @var FPLANT_Submission_Manager
	 */
	public $submission_manager;

	/**
	 * Admin
	 *
	 * @var FPLANT_Admin
	 */
	public $admin;

	/**
	 * Shortcode
	 *
	 * @var FPLANT_Shortcode
	 */
	public $shortcode;

	/**
	 * Validator
	 *
	 * @var FPLANT_Validator
	 */
	public $validator;

	/**
	 * Email handler
	 *
	 * @var FPLANT_Email_Handler
	 */
	public $email_handler;

	/**
	 * Embed
	 *
	 * @var FPLANT_Embed
	 */
	public $embed;

	/**
	 * Get singleton instance
	 *
	 * @return Form_Plant
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Load dependencies
	 */
	private function load_dependencies() {
		// Core classes
		require_once FPLANT_PLUGIN_DIR . 'includes/class-form-manager.php';
		require_once FPLANT_PLUGIN_DIR . 'includes/class-field-manager.php';
		require_once FPLANT_PLUGIN_DIR . 'includes/class-template-loader.php';
		require_once FPLANT_PLUGIN_DIR . 'includes/class-submission-manager.php';
		require_once FPLANT_PLUGIN_DIR . 'includes/class-validator.php';
		require_once FPLANT_PLUGIN_DIR . 'includes/class-email-handler.php';
		require_once FPLANT_PLUGIN_DIR . 'includes/class-shortcode.php';
		require_once FPLANT_PLUGIN_DIR . 'includes/class-database.php';

		// Admin
		if ( is_admin() ) {
			require_once FPLANT_PLUGIN_DIR . 'includes/class-admin.php';
		}

		// REST API
		require_once FPLANT_PLUGIN_DIR . 'includes/class-rest-api.php';

		// Embed
		require_once FPLANT_PLUGIN_DIR . 'includes/class-embed.php';
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Register custom post types
		add_action( 'init', array( $this, 'register_post_types' ) );

		// Check database updates
		add_action( 'plugins_loaded', array( $this, 'check_database_updates' ) );

		// Register scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// MailHog SMTP settings (for development)
		add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );

		// Ajax actions for custom CSS upload
		add_action( 'wp_ajax_fplant_upload_css', array( $this, 'handle_css_upload' ) );
		add_action( 'wp_ajax_fplant_delete_css', array( $this, 'handle_css_delete' ) );
	}

	/**
	 * Initialize components
	 */
	private function init_components() {
		$this->form_manager       = new FPLANT_Form_Manager();
		$this->field_manager      = new FPLANT_Field_Manager();
		$this->submission_manager = new FPLANT_Submission_Manager();
		$this->validator          = new FPLANT_Validator();
		$this->email_handler      = new FPLANT_Email_Handler();
		$this->shortcode          = new FPLANT_Shortcode();

		if ( is_admin() ) {
			$this->admin = new FPLANT_Admin();
		}

		// Initialize REST API
		new FPLANT_REST_API();

		// Initialize embed
		$this->embed = new FPLANT_Embed();
	}

	/**
	 * Check database updates
	 */
	public function check_database_updates() {
		$db_version = get_option( 'fplant_db_version', '0' );

		// Custom tables introduced in version 1.0.0
		if ( version_compare( $db_version, '1.0.0', '<' ) ) {
			FPLANT_Database::create_tables();
			update_option( 'fplant_db_version', '1.0.0' );
		}
	}

	/**
	 * Register custom post types
	 */
	public function register_post_types() {
		// Form post type
		register_post_type(
			'fplant_form',
			array(
				'labels'              => array(
					'name'               => __( 'Forms', 'form-plant' ),
					'singular_name'      => __( 'Form', 'form-plant' ),
					'add_new'            => __( 'Add New', 'form-plant' ),
					'add_new_item'       => __( 'Add New Form', 'form-plant' ),
					'edit_item'          => __( 'Edit Form', 'form-plant' ),
					'new_item'           => __( 'New Form', 'form-plant' ),
					'view_item'          => __( 'View Form', 'form-plant' ),
					'search_items'       => __( 'Search Forms', 'form-plant' ),
					'not_found'          => __( 'No forms found', 'form-plant' ),
					'not_found_in_trash' => __( 'No forms found in Trash', 'form-plant' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'hierarchical'        => false,
				'supports'            => array( 'title', 'author' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'show_in_rest'        => true,
			)
		);

	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_scripts() {
		// Only load on form pages (optimization)
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		// Check if shortcode is present
		if ( ! has_shortcode( $post->post_content, 'fplant' ) ) {
			return;
		}

		// Get form IDs
		preg_match_all( '/\[fplant\s+id="?(\d+)"?\]/', $post->post_content, $matches );
		$form_ids = array_unique( $matches[1] );

		// Whether to load default CSS (if multiple forms, load if any needs default)
		$load_default_css = true;

		// Collect inline CSS to add later
		$inline_css_queue = array();

		foreach ( $form_ids as $form_id ) {
			$form = FPLANT_Database::get_form( $form_id );
			if ( ! $form ) {
				continue;
			}

			$css_mode = isset( $form['settings']['custom_css_mode'] )
				? $form['settings']['custom_css_mode']
				: 'none';

			// In replace mode, don't load default CSS (but consider other forms)
			if ( 'replace' === $css_mode ) {
				$load_default_css = false;
			}

			// Load custom CSS
			if ( 'none' !== $css_mode ) {
				$custom_css_url = isset( $form['settings']['custom_css_file_url'] )
					? $form['settings']['custom_css_file_url']
					: '';

				$custom_css_inline = isset( $form['settings']['custom_css_inline'] )
					? $form['settings']['custom_css_inline']
					: '';

				$inline_handle = 'fplant-form'; // Default

				// Load custom CSS file
				if ( ! empty( $custom_css_url ) ) {
					wp_enqueue_style(
						'fplant-form-custom-' . $form_id,
						$custom_css_url,
						array(),
						FPLANT_VERSION
					);
					$inline_handle = 'fplant-form-custom-' . $form_id;

					// If custom CSS file exists, inline CSS can be added immediately
					if ( ! empty( $custom_css_inline ) ) {
						$sanitized_css = $this->sanitize_css( $custom_css_inline );
						wp_add_inline_style( $inline_handle, $sanitized_css );
					}
				} elseif ( 'replace' === $css_mode && ! empty( $custom_css_inline ) ) {
					// In replace mode with inline CSS only, register dummy style
					// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Intentionally no version for inline-only style
					wp_register_style( 'fplant-form-inline-' . $form_id, false );
					wp_enqueue_style( 'fplant-form-inline-' . $form_id );
					$inline_handle = 'fplant-form-inline-' . $form_id;
					$sanitized_css = $this->sanitize_css( $custom_css_inline );
					wp_add_inline_style( $inline_handle, $sanitized_css );
				} elseif ( ! empty( $custom_css_inline ) ) {
					// In append mode with inline CSS only, queue for later addition
					$inline_css_queue[] = $this->sanitize_css( $custom_css_inline );
				}
			}
		}

		// Load default CSS (when not in replace mode, or when no forms)
		if ( $load_default_css || empty( $form_ids ) ) {
			wp_enqueue_style(
				'fplant-form',
				FPLANT_PLUGIN_URL . 'assets/css/form.css',
				array(),
				FPLANT_VERSION
			);

			// Add queued inline CSS
			foreach ( $inline_css_queue as $inline_css ) {
				wp_add_inline_style( 'fplant-form', $inline_css );
			}
		}

		// Conditionally load reCAPTCHA v3 script
		$recaptcha_site_key = get_option( 'fplant_recaptcha_site_key' );
		$needs_recaptcha    = false;

		if ( ! empty( $recaptcha_site_key ) ) {
			foreach ( $form_ids as $fid ) {
				$f = FPLANT_Database::get_form( $fid );
				if ( $f && ! empty( $f['settings']['recaptcha_enabled'] ) ) {
					$needs_recaptcha = true;
					break;
				}
			}
		}

		if ( $needs_recaptcha ) {
			// External Google reCAPTCHA script - version managed by Google, null is intentional
			wp_enqueue_script( 'google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $recaptcha_site_key ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}

		// Scripts
		wp_enqueue_script(
			'fplant-form',
			FPLANT_PLUGIN_URL . 'assets/js/form.js',
			array(),
			FPLANT_VERSION,
			true
		);

		// Localize
		wp_localize_script(
			'fplant-form',
			'wpfplantData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fplant_form_nonce' ),
				'i18n'    => array(
					'validationError'     => __( 'There are errors in your input', 'form-plant' ),
					'requiredCheckbox'    => __( 'This field is required. Please select at least one option.', 'form-plant' ),
					'requiredRadio'       => __( 'This field is required. Please make a selection.', 'form-plant' ),
					'requiredSelect'      => __( 'This field is required. Please make a selection.', 'form-plant' ),
					'requiredFile'        => __( 'This field is required. Please select a file.', 'form-plant' ),
					'requiredText'        => __( 'This field is required. Please enter a value.', 'form-plant' ),
					/* translators: %s: maximum file size in MB */
					'fileTooLarge'        => __( 'File size is too large. Please select a file under %sMB.', 'form-plant' ),
					'imageRequired'       => __( 'Please select an image file.', 'form-plant' ),
					'serverError'         => __( 'A server error occurred. Please try again.', 'form-plant' ),
					'errorOccurred'       => __( 'An error occurred. Please try again.', 'form-plant' ),
					'recaptchaError'      => __( 'reCAPTCHA verification failed. Please reload the page and try again.', 'form-plant' ),
					'confirmationTitle'   => __( 'Confirm Your Input', 'form-plant' ),
					'confirmationMessage' => __( 'If the information below is correct, please click the "Submit" button.', 'form-plant' ),
					'back'                => __( 'Back', 'form-plant' ),
					'submitForm'          => __( 'Submit', 'form-plant' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function admin_enqueue_scripts( $hook ) {
		// Only load on Form Plant pages
		if ( strpos( $hook, 'fplant' ) === false && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		// Admin styles
		wp_enqueue_style(
			'fplant-admin',
			FPLANT_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			FPLANT_VERSION
		);

		// Admin scripts
		wp_enqueue_script(
			'fplant-admin',
			FPLANT_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			FPLANT_VERSION,
			true
		);

		// Get form data for form edit page
		$form_data = array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin page URL params, not form submission
		if ( isset( $_GET['page'] ) && 'fplant-form-new' === $_GET['page'] && isset( $_GET['id'] ) ) {
			$post_id = absint( $_GET['id'] );
			$post    = get_post( $post_id );
			if ( $post && 'fplant_form' === $post->post_type ) {
				$form_data = FPLANT_Database::get_form( $post_id );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Localize
		wp_localize_script(
			'fplant-admin',
			'wpfplantAdminData',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'fplant_admin_nonce' ),
				'formData' => $form_data,
				'cssNonce' => wp_create_nonce( 'fplant_css_upload' ),
				'editUrl'  => admin_url( 'admin.php?page=fplant-form-new' ),
				'listUrl'  => admin_url( 'admin.php?page=fplant-forms' ),
				'i18n'     => array(
					'editField'             => __( 'Edit Field', 'form-plant' ),
					'addField'              => __( 'Add Field', 'form-plant' ),
					'value'                 => __( 'Value', 'form-plant' ),
					'label'                 => __( 'Label', 'form-plant' ),
					'delete'                => __( 'Delete', 'form-plant' ),
					'edit'                  => __( 'Edit', 'form-plant' ),
					'optionRequired'        => __( 'At least one option is required', 'form-plant' ),
					'fieldNameRequired'     => __( 'Please enter a field name', 'form-plant' ),
					'fieldNameAlphanumeric' => __( 'Field name can only contain alphanumeric characters and underscores', 'form-plant' ),
					'fieldLabelRequired'    => __( 'Please enter a field label', 'form-plant' ),
					'fieldNameExists'       => __( 'This field name is already in use', 'form-plant' ),
					'addOneOption'          => __( 'Please add at least one option', 'form-plant' ),
					'confirmDeleteField'    => __( 'Are you sure you want to delete this field?', 'form-plant' ),
					'confirmDeleteForm'     => __( 'Are you sure you want to delete this form?', 'form-plant' ),
					'confirmTrashForm'      => __( 'Move this form to trash?', 'form-plant' ),
					'confirmDeleteCss'      => __( 'Delete this CSS file?', 'form-plant' ),
					'copied'                => __( 'Copied!', 'form-plant' ),
					'uploading'             => __( 'Uploading...', 'form-plant' ),
					'uploadComplete'        => __( 'Upload complete:', 'form-plant' ),
					'errorOccurred'         => __( 'An error occurred', 'form-plant' ),
					'networkError'          => __( 'A network error occurred', 'form-plant' ),
					'uploadFailed'          => __( 'Upload failed', 'form-plant' ),
					'deleteFailed'          => __( 'Delete failed', 'form-plant' ),
					'submit'                => __( 'Submit', 'form-plant' ),
					'back'                  => __( 'Back', 'form-plant' ),
					'submitForm'            => __( 'Submit Form', 'form-plant' ),
					'dismissNotice'         => __( 'Dismiss this notice', 'form-plant' ),
					'noFieldsYet'           => __( 'No fields yet. Click "Add Field" button to add one.', 'form-plant' ),
					'fieldNameLabel'        => __( 'Field name:', 'form-plant' ),
					'cssFileRequired'       => __( 'Please select a CSS file (.css)', 'form-plant' ),
					'currentFile'           => __( 'Current file:', 'form-plant' ),
					'errorPrefix'           => __( 'Error:', 'form-plant' ),
					'missingRequiredFields'        => __( 'The following required items are missing from the HTML template:', 'form-plant' ),
					'submitButton'                 => __( 'Submit button', 'form-plant' ),
					'templateEmpty'                => __( 'HTML template is empty. Please add the required tags or uncheck "Use HTML template".', 'form-plant' ),
					'confirmationTemplateEmpty'    => __( 'Confirmation HTML template is empty. Please add the required tags or uncheck "Use confirmation screen HTML template".', 'form-plant' ),
					'confirmationSubmitRequired'   => __( 'Submit button [fplant_confirm_submit] is required in the confirmation template.', 'form-plant' ),
				),
			)
		);

		// Submission list page
		if ( 'fplant_form_page_fplant-submissions' === $hook ) {
			wp_enqueue_script(
				'fplant-submission-list',
				FPLANT_PLUGIN_URL . 'admin/js/submission-list.js',
				array( 'jquery' ),
				FPLANT_VERSION,
				true
			);

			wp_localize_script(
				'fplant-submission-list',
				'fplantSubmissionList',
				array(
					'adminUrl' => admin_url( 'admin.php?page=fplant-submissions' ),
					'nonce'    => wp_create_nonce( 'fplant_admin_nonce' ),
					'i18n'     => array(
						'deleteConfirm'  => __( 'Are you sure you want to delete this submission?', 'form-plant' ),
						'selectItems'    => __( 'Please select items to delete', 'form-plant' ),
						'deleteSelected' => __( 'Are you sure you want to delete the selected', 'form-plant' ),
						'submissions'    => __( 'submissions?', 'form-plant' ),
						'deleteFailed'   => __( 'Delete failed', 'form-plant' ),
						'errorOccurred'  => __( 'An error occurred', 'form-plant' ),
					),
				)
			);
		}
	}

	/**
	 * SMTP settings (for MailHog)
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance
	 */
	public function configure_smtp( $phpmailer ) {
		// Only configure if MailHog is available
		if ( defined( 'SMTP_HOST' ) && 'mailhog' === SMTP_HOST ) {
			$phpmailer->isSMTP();
			$phpmailer->Host       = SMTP_HOST;
			$phpmailer->Port       = defined( 'SMTP_PORT' ) ? SMTP_PORT : 1025;
			$phpmailer->SMTPAuth   = false;
			$phpmailer->SMTPSecure = '';

			// From and FromName are set via wp_mail() headers, so not set here
			// $phpmailer->From and $phpmailer->FromName are handled automatically within wp_mail()

			// Debug log
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - SMTP settings: MailHog (' . $phpmailer->Host . ':' . $phpmailer->Port . ')' );
			}
		}
	}

	/**
	 * Sanitize CSS
	 *
	 * @param string $css CSS string
	 * @return string Sanitized CSS
	 */
	public function sanitize_css( $css ) {
		// Remove dangerous patterns
		$dangerous_patterns = array(
			'/expression\s*\(/i',
			'/javascript\s*:/i',
			'/behavior\s*:/i',
			'/@import/i',
			'/url\s*\(\s*["\']?\s*javascript/i',
			'/binding\s*:/i',
			'/-moz-binding/i',
		);

		foreach ( $dangerous_patterns as $pattern ) {
			$css = preg_replace( $pattern, '', $css );
		}

		return wp_strip_all_tags( $css );
	}

	/**
	 * Get custom CSS upload directory
	 *
	 * @return array Directory info (path, url)
	 */
	public function get_css_upload_dir() {
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'] . '/fplant_uploads/assets';
		$base_url   = $upload_dir['baseurl'] . '/fplant_uploads/assets';

		return array(
			'path' => $base_dir,
			'url'  => $base_url,
		);
	}

	/**
	 * Get custom CSS upload directory for upload_dir filter
	 *
	 * @param array $upload WordPress upload directory array
	 * @return array Modified upload directory array
	 */
	public function get_css_upload_dir_for_filter( $upload ) {
		$dir_info = $this->get_css_upload_dir();

		$upload['path']   = $dir_info['path'];
		$upload['url']    = $dir_info['url'];
		$upload['subdir'] = '/fplant_uploads/assets';

		return $upload;
	}

	/**
	 * Create custom CSS upload directory
	 *
	 * @return bool True on success
	 */
	private function create_css_upload_dir() {
		$dir_info = $this->get_css_upload_dir();
		$dir_path = $dir_info['path'];

		// Create directory if it doesn't exist
		if ( ! file_exists( $dir_path ) ) {
			if ( ! wp_mkdir_p( $dir_path ) ) {
				return false;
			}

			// Disable PHP execution via .htaccess
			$htaccess_content = "# Form Plant - Disable PHP execution\n";
			$htaccess_content .= "<Files *.php>\n";
			$htaccess_content .= "deny from all\n";
			$htaccess_content .= "</Files>\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir_path . '/.htaccess', $htaccess_content );

			// Place index.php (prevent directory listing)
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir_path . '/index.php', '<?php // Silence is golden.' );
		}

		return true;
	}

	/**
	 * Handle CSS file upload (Ajax)
	 */
	public function handle_css_upload() {
		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		// Nonce verification
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fplant_css_upload' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );
		}

		// File check
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES validated for file operations
		if ( ! isset( $_FILES['css_file'] ) || empty( $_FILES['css_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES validated for file operations
		$file = $_FILES['css_file'];

		// Extension check
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'css' !== $ext ) {
			wp_send_json_error( array( 'message' => 'Only .css files are allowed.' ) );
		}

		// MIME type check
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		// CSS files are detected as text/plain or text/css
		if ( ! in_array( $mime, array( 'text/plain', 'text/css', 'text/x-css' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid file type.' ) );
		}

		// Create directory
		if ( ! $this->create_css_upload_dir() ) {
			wp_send_json_error( array( 'message' => 'Failed to create upload directory.' ) );
		}

		// Generate filename
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified at line 592
		$form_id   = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$timestamp = time();
		$filename  = sprintf( 'form_%d_%d.css', $form_id, $timestamp );

		$dir_info  = $this->get_css_upload_dir();
		$file_path = $dir_info['path'] . '/' . $filename;
		$file_url  = $dir_info['url'] . '/' . $filename;

		// Use WordPress file upload handler
		$upload_overrides = array(
			'test_form'                => false,
			'mimes'                    => array( 'css' => 'text/css' ),
			'unique_filename_callback' => function ( $dir, $name, $ext ) use ( $filename ) {
				return $filename;
			},
		);

		// Set custom upload directory
		add_filter( 'upload_dir', array( $this, 'get_css_upload_dir_for_filter' ) );
		$upload_result = wp_handle_upload( $file, $upload_overrides );
		remove_filter( 'upload_dir', array( $this, 'get_css_upload_dir_for_filter' ) );

		if ( isset( $upload_result['error'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to upload custom CSS file', 'form-plant' ) . ': ' . $upload_result['error'],
				)
			);
		}

		wp_send_json_success(
			array(
				'url'      => $upload_result['url'],
				'filename' => basename( $upload_result['file'] ),
			)
		);
	}

	/**
	 * Handle CSS file delete (Ajax)
	 */
	public function handle_css_delete() {
		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		// Nonce verification
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fplant_css_upload' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );
		}

		// Get file URL
		$file_url = isset( $_POST['file_url'] ) ? esc_url_raw( wp_unslash( $_POST['file_url'] ) ) : '';
		if ( empty( $file_url ) ) {
			wp_send_json_error( array( 'message' => 'No file specified.' ) );
		}

		// Get file path from URL
		$dir_info = $this->get_css_upload_dir();
		$filename = basename( $file_url );

		// Security: Check for path traversal in filename
		if ( strpos( $filename, '..' ) !== false || strpos( $filename, '/' ) !== false ) {
			wp_send_json_error( array( 'message' => 'Invalid filename.' ) );
		}

		// Check if CSS file
		if ( strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) !== 'css' ) {
			wp_send_json_error( array( 'message' => 'Invalid file type.' ) );
		}

		$file_path = $dir_info['path'] . '/' . $filename;

		// Check if file exists
		if ( ! file_exists( $file_path ) ) {
			// Return success even if file doesn't exist (already deleted)
			wp_send_json_success( array( 'message' => 'File deleted.' ) );
		}

		// Security: Normalize with realpath() and verify within allowed directory
		$real_file_path = realpath( $file_path );
		$real_dir_path  = realpath( $dir_info['path'] );

		if ( false === $real_file_path ||
			false === $real_dir_path ||
			strpos( $real_file_path, $real_dir_path . DIRECTORY_SEPARATOR ) !== 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid file path.' ) );
		}

		// Delete file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		if ( ! unlink( $real_file_path ) ) {
			wp_send_json_error( array( 'message' => 'Failed to delete file.' ) );
		}

		wp_send_json_success( array( 'message' => 'File deleted.' ) );
	}

	/**
	 * Plugin activation handler
	 */
	public static function activate() {
		// Register custom post types
		$plugin = new self();
		$plugin->register_post_types();

		// Create database tables
		FPLANT_Database::create_tables();

		// Flush rewrite rules for embed endpoint
		FPLANT_Embed::flush_rewrite_rules();

		// Save version info
		update_option( 'fplant_version', FPLANT_VERSION );
		update_option( 'fplant_db_version', '1.0.0' );
		update_option( 'fplant_activated_time', time() );
	}

	/**
	 * Plugin deactivation handler
	 */
	public static function deactivate() {
		// Flush permalink settings
		flush_rewrite_rules();
	}
}
