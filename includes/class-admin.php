<?php
/**
 * Admin class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Admin class
 */
class FPLANT_Admin {

	/**
	 * Submissions page hook suffix
	 *
	 * @var string
	 */
	private $submissions_page_hook;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Register settings
		add_action( 'admin_init', array( $this, 'register_recaptcha_settings' ) );

		// Form list actions handling
		add_action( 'admin_init', array( $this, 'handle_form_list_actions' ) );

		// AJAX actions
		add_action( 'wp_ajax_fplant_save_form', array( $this, 'ajax_save_form' ) );
		add_action( 'wp_ajax_fplant_delete_form', array( $this, 'ajax_delete_form' ) );
		add_action( 'wp_ajax_fplant_duplicate_form', array( $this, 'ajax_duplicate_form' ) );
		add_action( 'wp_ajax_fplant_export_submissions', array( $this, 'ajax_export_submissions' ) );
		add_action( 'wp_ajax_fplant_get_submission_detail', array( $this, 'ajax_get_submission_detail' ) );
		add_action( 'wp_ajax_fplant_delete_submissions', array( $this, 'ajax_delete_submissions' ) );
		add_action( 'wp_ajax_fplant_quick_edit_form', array( $this, 'ajax_quick_edit_form' ) );
		add_action( 'wp_ajax_fplant_trash_form', array( $this, 'ajax_trash_form' ) );
		add_action( 'wp_ajax_fplant_download_file', array( $this, 'ajax_download_file' ) );

		// Screen Options save filter (also saves column settings)
		add_filter( 'set-screen-option', array( $this, 'set_submissions_screen_options' ), 10, 3 );
		// WordPress 5.4.2+ compatibility
		add_filter( 'set_screen_option_fplant_submissions_per_page', array( $this, 'set_per_page_option' ), 10, 3 );
	}

	/**
	 * Register reCAPTCHA settings
	 */
	public function register_recaptcha_settings() {
		register_setting(
			'fplant_recaptcha_settings',
			'fplant_recaptcha_site_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'fplant_recaptcha_settings',
			'fplant_recaptcha_secret_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'fplant_recaptcha_settings',
			'fplant_recaptcha_v3_threshold',
			array(
				'type'              => 'number',
				'sanitize_callback' => array( $this, 'sanitize_recaptcha_threshold' ),
				'default'           => 0.5,
			)
		);
	}

	/**
	 * Sanitize reCAPTCHA v3 score threshold
	 *
	 * @param mixed $value Input value.
	 * @return float
	 */
	public function sanitize_recaptcha_threshold( $value ) {
		$value = floatval( $value );
		return max( 0, min( 1, $value ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'Form Plant', 'form-plant' ),
			__( 'Form Plant', 'form-plant' ),
			'manage_options',
			'fplant-forms',
			array( $this, 'render_forms_page' ),
			'dashicons-feedback',
			30
		);

		// Form list
		add_submenu_page(
			'fplant-forms',
			__( 'Form List', 'form-plant' ),
			__( 'Form List', 'form-plant' ),
			'manage_options',
			'fplant-forms',
			array( $this, 'render_forms_page' )
		);

		// Add new
		add_submenu_page(
			'fplant-forms',
			__( 'Add New', 'form-plant' ),
			__( 'Add New', 'form-plant' ),
			'manage_options',
			'fplant-form-new',
			array( $this, 'render_form_edit_page' )
		);

		// Submissions
		$this->submissions_page_hook = add_submenu_page(
			'fplant-forms',
			__( 'Submissions', 'form-plant' ),
			__( 'Submissions', 'form-plant' ),
			'manage_options',
			'fplant-submissions',
			array( $this, 'render_submissions_page' )
		);

		// Register Screen Options
		add_action( 'load-' . $this->submissions_page_hook, array( $this, 'add_submissions_screen_options' ) );

		// Settings
		add_submenu_page(
			'fplant-forms',
			__( 'Settings', 'form-plant' ),
			__( 'Settings', 'form-plant' ),
			'manage_options',
			'fplant-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render forms page
	 */
	public function render_forms_page() {
		// Load WP_List_Table
		require_once FPLANT_PLUGIN_DIR . 'includes/class-form-list-table.php';

		$list_table = new FPLANT_Form_List_Table();
		$list_table->prepare_items();

		// Load view
		include FPLANT_PLUGIN_DIR . 'admin/views/form-list.php';
	}

	/**
	 * Render form edit page
	 */
	public function render_form_edit_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only form ID from URL
		$fplant_form_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$fplant_form    = null;

		if ( $fplant_form_id ) {
			$fplant_form = FPLANT_Database::get_form( $fplant_form_id );
		}

		// Field manager
		$fplant_field_manager = new FPLANT_Field_Manager();
		$fplant_field_types   = $fplant_field_manager->get_field_types();

		// Load view
		include FPLANT_PLUGIN_DIR . 'admin/views/form-edit.php';
	}

	/**
	 * Render submissions page
	 */
	public function render_submissions_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters
		$fplant_form_id   = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters
		$fplant_date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters
		$fplant_date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters
		$fplant_search    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		// Filter arguments
		$fplant_filter_args = array(
			'date_from' => $fplant_date_from,
			'date_to'   => $fplant_date_to,
			'search'    => $fplant_search,
		);

		// Pagination settings - get from Screen Options
		$fplant_user     = get_current_user_id();
		$fplant_per_page = get_user_meta( $fplant_user, 'fplant_submissions_per_page', true );

		if ( empty( $fplant_per_page ) || $fplant_per_page < 1 ) {
			$fplant_per_page = 20; // Default 20 items
		}
		$fplant_per_page = absint( $fplant_per_page );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter
		$fplant_current_page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$fplant_offset       = ( $fplant_current_page - 1 ) * $fplant_per_page;

		// Get total submissions count
		$fplant_total_items = FPLANT_Database::get_submissions_count( $fplant_form_id, $fplant_filter_args );
		$fplant_total_pages = ceil( $fplant_total_items / $fplant_per_page );

		// Get submissions
		$fplant_submissions = FPLANT_Database::get_submissions(
			$fplant_form_id,
			array_merge(
				$fplant_filter_args,
				array(
					'limit'  => $fplant_per_page,
					'offset' => $fplant_offset,
				)
			)
		);

		// Get forms list (for filter)
		$fplant_form_manager = new FPLANT_Form_Manager();
		$fplant_forms        = $fplant_form_manager->get_forms();

		// Get column display options
		$fplant_column_options = $this->get_submissions_column_options( $fplant_user );

		// Load view
		include FPLANT_PLUGIN_DIR . 'admin/views/submission-list.php';
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Load view
		include FPLANT_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Add Screen Options for submissions list
	 */
	public function add_submissions_screen_options() {
		// Items per page option
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Number of items per page', 'form-plant' ),
				'default' => 20,
				'option'  => 'fplant_submissions_per_page',
			)
		);

		// Filter for custom column options
		add_filter( 'screen_settings', array( $this, 'submissions_screen_settings' ), 10, 2 );
	}

	/**
	 * Save Screen Options values
	 *
	 * @param mixed  $status Save result
	 * @param string $option Option name
	 * @param mixed  $value  Setting value
	 * @return mixed
	 */
	public function set_submissions_screen_options( $status, $option, $value ) {
		if ( 'fplant_submissions_per_page' === $option ) {
			// Also save custom column settings (processed before set_screen_options redirect)
			$user_id = get_current_user_id();
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by WordPress core in options.php before set-screen-option filter
			$columns = array(
				'file'       => isset( $_POST['fplant_show_file_column'] ),
				'ip_address' => isset( $_POST['fplant_show_ip_column'] ),
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			update_user_meta( $user_id, 'fplant_submissions_columns', $columns );

			return absint( $value );
		}
		return $status;
	}

	/**
	 * Save per page option (WordPress 5.4.2+)
	 *
	 * @param mixed  $status Save result
	 * @param string $option Option name
	 * @param mixed  $value  Setting value
	 * @return int
	 */
	public function set_per_page_option( $status, $option, $value ) {
		return absint( $value );
	}

	/**
	 * Custom Screen Settings (column display options)
	 *
	 * @param string    $settings Existing settings HTML
	 * @param WP_Screen $screen   Current screen
	 * @return string
	 */
	public function submissions_screen_settings( $settings, $screen ) {
		if ( $screen->id !== $this->submissions_page_hook ) {
			return $settings;
		}

		$user_id = get_current_user_id();
		$columns = $this->get_submissions_column_options( $user_id );

		ob_start();
		?>
		<fieldset class="metabox-prefs">
			<legend><?php esc_html_e( 'Columns', 'form-plant' ); ?></legend>
			<label>
				<input type="checkbox" name="fplant_show_file_column" value="1"
					<?php checked( $columns['file'], true ); ?>>
				<?php esc_html_e( 'File', 'form-plant' ); ?>
			</label>
			<label>
				<input type="checkbox" name="fplant_show_ip_column" value="1"
					<?php checked( $columns['ip_address'], true ); ?>>
				<?php esc_html_e( 'IP Address', 'form-plant' ); ?>
			</label>
		</fieldset>
		<?php
		$custom_settings = ob_get_clean();

		return $settings . $custom_settings;
	}

	/**
	 * Get column display settings
	 *
	 * @param int $user_id User ID
	 * @return array
	 */
	public function get_submissions_column_options( $user_id ) {
		$defaults = array(
			'file'       => true,
			'ip_address' => true,
		);

		$saved = get_user_meta( $user_id, 'fplant_submissions_columns', true );

		if ( empty( $saved ) || ! is_array( $saved ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $saved );
	}

	/**
	 * AJAX: Save form
	 */
	public function ajax_save_form() {
		// Nonce verification
		check_ajax_referer( 'fplant_admin_nonce', 'nonce' );

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'form-plant' ) ) );
		}

		$form_id   = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data, sanitized after json_decode below
		$form_data = isset( $_POST['form_data'] ) ? wp_unslash( $_POST['form_data'] ) : array();

		// Handle if received as JSON string
		if ( is_string( $form_data ) ) {
			$form_data = json_decode( $form_data, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $form_data ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Invalid form data format', 'form-plant' ) . ': ' . json_last_error_msg(),
					)
				);
			}
		}

		// Data validation
		if ( ! is_array( $form_data ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid form data format', 'form-plant' ),
				)
			);
		}

		// Sanitization is handled per-section in FPLANT_Form_Manager::update_form()
		// with appropriate $html_allowed_keys for each data type.

		$form_manager = new FPLANT_Form_Manager();

		if ( $form_id ) {
			// Update existing form
			$result = $form_manager->update_form( $form_id, $form_data );

			if ( $result ) {
				wp_send_json_success(
					array(
						'message' => __( 'Form saved', 'form-plant' ),
						'form_id' => $form_id,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to save form', 'form-plant' ) ) );
			}
		} else {
			// Create new form
			$title   = ! empty( $form_data['title'] ) ? $form_data['title'] : __( 'New Form', 'form-plant' );
			$form_id = $form_manager->create_form( $title, $form_data );

			if ( $form_id && ! is_wp_error( $form_id ) ) {
				wp_send_json_success(
					array(
						'message' => __( 'Form created', 'form-plant' ),
						'form_id' => $form_id,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to create form', 'form-plant' ) ) );
			}
		}
	}

	/**
	 * AJAX: Delete form
	 */
	public function ajax_delete_form() {
		// Nonce verification
		check_ajax_referer( 'fplant_admin_nonce', 'nonce' );

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'form-plant' ) ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;

		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Form ID not specified', 'form-plant' ) ) );
		}

		$form_manager = new FPLANT_Form_Manager();
		$result       = $form_manager->delete_form( $form_id, true );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Form deleted', 'form-plant' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete form', 'form-plant' ) ) );
		}
	}

	/**
	 * AJAX: Move form to trash
	 */
	public function ajax_trash_form() {
		// Nonce verification
		check_ajax_referer( 'fplant_admin_nonce', 'nonce' );

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'form-plant' ) ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;

		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Form ID not specified', 'form-plant' ) ) );
		}

		$result = wp_trash_post( $form_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Form moved to trash', 'form-plant' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to move form', 'form-plant' ) ) );
		}
	}

	/**
	 * AJAX: Duplicate form
	 */
	public function ajax_duplicate_form() {
		// Nonce verification
		check_ajax_referer( 'fplant_admin_nonce', 'nonce' );

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'form-plant' ) ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;

		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Form ID not specified', 'form-plant' ) ) );
		}

		$form_manager  = new FPLANT_Form_Manager();
		$new_form_id = $form_manager->duplicate_form( $form_id );

		if ( $new_form_id ) {
			wp_send_json_success(
				array(
					'message' => __( 'Form duplicated', 'form-plant' ),
					'form_id' => $new_form_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to duplicate form', 'form-plant' ) ) );
		}
	}

	/**
	 * AJAX: Export submissions
	 */
	public function ajax_export_submissions() {
		// Nonce verification
		check_ajax_referer( 'fplant_admin_nonce', 'nonce' );

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'form-plant' ) );
		}

		$form_id   = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$search    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		$args = array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'search'    => $search,
		);

		$submission_manager = new FPLANT_Submission_Manager();
		$submission_manager->export_submissions_csv( $form_id, $args );
	}

	/**
	 * AJAX: Get submission detail
	 */
	public function ajax_get_submission_detail() {
		// Nonce verification
		check_ajax_referer( 'fplant_admin_nonce', 'nonce' );

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'form-plant' ) ) );
		}

		$submission_id = isset( $_POST['submission_id'] ) ? absint( wp_unslash( $_POST['submission_id'] ) ) : 0;

		if ( ! $submission_id ) {
			wp_send_json_error( array( 'message' => __( 'Submission ID not specified', 'form-plant' ) ) );
		}

		// Get submission
		$submission = FPLANT_Database::get_submission( $submission_id );

		if ( ! $submission ) {
			wp_send_json_error( array( 'message' => __( 'Submission not found', 'form-plant' ) ) );
		}

		// Get form data
		$form = FPLANT_Database::get_form( $submission['form_id'] );

		if ( ! $form ) {
			wp_send_json_error( array( 'message' => __( 'Form not found', 'form-plant' ) ) );
		}

		// Generate HTML
		$html = $this->render_submission_detail( $submission, $form );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Render submission detail HTML
	 *
	 * @param array $submission Submission data
	 * @param array $form       Form data
	 * @return string
	 */
	private function render_submission_detail( $submission, $form ) {
		ob_start();
		?>
		<div class="fplant-submission-detail">
			<div class="fplant-submission-meta">
				<table class="fplant-table">
					<tr>
						<th><?php esc_html_e( 'ID', 'form-plant' ); ?></th>
						<td><?php echo esc_html( $submission['id'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Form', 'form-plant' ); ?></th>
						<td><?php echo esc_html( $form['title'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Submitted At', 'form-plant' ); ?></th>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $submission['created_at'] ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'IP Address', 'form-plant' ); ?></th>
						<td><?php echo esc_html( $submission['ip_address'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'User Agent', 'form-plant' ); ?></th>
						<td style="word-break: break-all;"><?php echo esc_html( $submission['user_agent'] ); ?></td>
					</tr>
					<?php if ( $submission['referrer'] ) : ?>
						<tr>
							<th><?php esc_html_e( 'Referrer', 'form-plant' ); ?></th>
							<td style="word-break: break-all;"><?php echo esc_html( $submission['referrer'] ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<div class="fplant-submission-data" style="margin-top: 20px;">
				<h3><?php esc_html_e( 'Submission Data', 'form-plant' ); ?></h3>
				<?php if ( empty( $submission['data'] ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'Input data was not saved (metadata only setting)', 'form-plant' ); ?>
					</p>
				<?php else : ?>
					<table class="fplant-table">
						<?php
						// Create mapping of field names to labels
						$field_labels = array();
						foreach ( $form['fields'] as $field ) {
							$field_labels[ $field['name'] ] = $field['label'];
						}
						?>

						<?php foreach ( $submission['data'] as $field_name => $value ) : ?>
							<?php
							$label = isset( $field_labels[ $field_name ] ) ? $field_labels[ $field_name ] : $field_name;

							// Check if file field
							if ( is_array( $value ) && isset( $value['url'] ) && isset( $value['filename'] ) ) :
								// Generate AJAX download endpoint URL
								$download_url = add_query_arg(
									array(
										'action'        => 'fplant_download_file',
										'submission_id' => $submission['id'],
										'field'         => $field_name,
										'nonce'         => wp_create_nonce( 'fplant_download_file' ),
									),
									admin_url( 'admin-ajax.php' )
								);
								$filename  = esc_html( $value['filename'] );
								$file_type = isset( $value['type'] ) ? $value['type'] : '';
								$icon      = $this->get_file_icon( $file_type );
								?>
								<tr>
									<th style="width: 30%;"><?php echo esc_html( $label ); ?></th>
									<td>
										<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="vertical-align: middle;"></span>
										<a href="<?php echo esc_url( $download_url ); ?>" style="vertical-align: middle;">
											<?php echo esc_html( $filename ); ?>
										</a>
										<?php if ( $file_type ) : ?>
											<span class="description" style="margin-left: 10px;">
												(<?php echo esc_html( $file_type ); ?>)
											</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php else :
								// Display comma-separated if array
								if ( is_array( $value ) ) {
									$value = implode( ', ', $value );
								}
								?>
								<tr>
									<th style="width: 30%;"><?php echo esc_html( $label ); ?></th>
									<td><?php echo nl2br( esc_html( $value ) ); ?></td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get Dashicons icon class based on file type
	 *
	 * @param string $mime_type MIME type
	 * @return string Dashicons class name
	 */
	private function get_file_icon( $mime_type ) {
		if ( strpos( $mime_type, 'image/' ) === 0 ) {
			return 'dashicons-format-image';
		} elseif ( strpos( $mime_type, 'application/pdf' ) !== false ) {
			return 'dashicons-pdf';
		} elseif ( strpos( $mime_type, 'application/msword' ) !== false || strpos( $mime_type, 'wordprocessingml' ) !== false ) {
			return 'dashicons-media-document';
		} else {
			return 'dashicons-media-default';
		}
	}

	/**
	 * AJAX: Delete submissions
	 */
	public function ajax_delete_submissions() {
		// Nonce verification
		check_ajax_referer( 'fplant_admin_nonce', 'nonce' );

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'form-plant' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array items sanitized with absint() below
		$submission_ids = isset( $_POST['submission_ids'] ) ? wp_unslash( $_POST['submission_ids'] ) : array();

		if ( empty( $submission_ids ) || ! is_array( $submission_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No data specified for deletion', 'form-plant' ) ) );
		}

		// Delete processing
		$deleted_count = 0;
		foreach ( $submission_ids as $submission_id ) {
			$submission_id = absint( $submission_id );
			if ( $submission_id > 0 ) {
				if ( FPLANT_Database::delete_submission( $submission_id ) ) {
					$deleted_count++;
				}
			}
		}

		if ( $deleted_count > 0 ) {
			wp_send_json_success(
				array(
					'message'       => sprintf(
						/* translators: %d: number of deleted submissions */
						_n( '%d submission deleted', '%d submissions deleted', $deleted_count, 'form-plant' ),
						$deleted_count
					),
					'deleted_count' => $deleted_count,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete data', 'form-plant' ) ) );
		}
	}

	/**
	 * File download
	 */
	public function ajax_download_file() {
		// Nonce verification
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'fplant_download_file' ) ) {
			wp_die( esc_html__( 'Security check failed', 'form-plant' ), 403 );
		}

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'form-plant' ), 403 );
		}

		// Get submission ID and field name
		$submission_id = isset( $_GET['submission_id'] ) ? absint( wp_unslash( $_GET['submission_id'] ) ) : 0;
		$field_name    = isset( $_GET['field'] ) ? sanitize_text_field( wp_unslash( $_GET['field'] ) ) : '';

		if ( ! $submission_id || ! $field_name ) {
			wp_die( esc_html__( 'Invalid parameters', 'form-plant' ), 400 );
		}

		// Get submission
		$submission = FPLANT_Database::get_submission( $submission_id );
		if ( ! $submission ) {
			wp_die( esc_html__( 'Submission not found', 'form-plant' ), 404 );
		}

		// Get file info from field
		if ( ! isset( $submission['data'][ $field_name ] ) ) {
			wp_die( esc_html__( 'Field not found', 'form-plant' ), 404 );
		}

		$file_data = $submission['data'][ $field_name ];
		if ( ! is_array( $file_data ) || ! isset( $file_data['file'] ) || ! isset( $file_data['filename'] ) ) {
			wp_die( esc_html__( 'File not found', 'form-plant' ), 404 );
		}

		$file_path = $file_data['file'];
		$filename  = $file_data['filename'];
		$mime_type = isset( $file_data['type'] ) ? $file_data['type'] : 'application/octet-stream';

		// Security check: verify file path is within fplant_uploads
		$upload_dir    = wp_upload_dir();
		$allowed_path  = $upload_dir['basedir'] . '/fplant_uploads/';
		$real_path     = realpath( $file_path );
		$real_allowed  = realpath( $allowed_path );

		if ( false === $real_path || false === $real_allowed || strpos( $real_path, $real_allowed ) !== 0 ) {
			wp_die( esc_html__( 'Access denied', 'form-plant' ), 403 );
		}

		// Check file exists
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			wp_die( esc_html__( 'File not found', 'form-plant' ), 404 );
		}

		// Stream file
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Clear output buffer
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Direct file output for download is appropriate here
		readfile( $file_path );
		exit;
	}

	/**
	 * Handle form list row actions and bulk operations
	 */
	public function handle_form_list_actions() {
		// Only run on form list page
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page check, not data processing
		if ( ! isset( $_GET['page'] ) || 'fplant-forms' !== $_GET['page'] ) {
			return;
		}

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, nonce verified per action below
		$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, nonce verified per action below
		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;

		// Bulk operations
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately below
		if ( isset( $_POST['action'] ) && '-1' !== $_POST['action'] ) {
			// Nonce verification
			if ( ! isset( $_POST['_wpnonce_bulk'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_bulk'] ) ), 'fplant_bulk_action' ) ) {
				return;
			}
			$bulk_action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
			$form_ids    = isset( $_POST['form_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['form_ids'] ) ) : array();
			$this->process_bulk_action( $bulk_action, $form_ids );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately below
		if ( isset( $_POST['action2'] ) && '-1' !== $_POST['action2'] ) {
			// Nonce verification
			if ( ! isset( $_POST['_wpnonce_bulk'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_bulk'] ) ), 'fplant_bulk_action' ) ) {
				return;
			}
			$bulk_action = sanitize_text_field( wp_unslash( $_POST['action2'] ) );
			$form_ids    = isset( $_POST['form_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['form_ids'] ) ) : array();
			$this->process_bulk_action( $bulk_action, $form_ids );
			return;
		}

		// Individual actions
		if ( ! $action || ! $form_id ) {
			return;
		}

		$redirect_url = admin_url( 'admin.php?page=fplant-forms' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for redirect
		$post_status  = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
		if ( $post_status ) {
			$redirect_url = add_query_arg( 'post_status', $post_status, $redirect_url );
		}

		switch ( $action ) {
			case 'trash':
				// Nonce verification
				if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'trash_form_' . $form_id ) ) {
					wp_die( esc_html__( 'Invalid request', 'form-plant' ) );
				}
				wp_trash_post( $form_id );
				$redirect_url = add_query_arg( 'trashed', 1, $redirect_url );
				break;

			case 'restore':
				// Nonce verification
				if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'restore_form_' . $form_id ) ) {
					wp_die( esc_html__( 'Invalid request', 'form-plant' ) );
				}
				wp_untrash_post( $form_id );
				$redirect_url = add_query_arg( 'restored', 1, $redirect_url );
				break;

			case 'delete':
				// Nonce verification
				if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'delete_form_' . $form_id ) ) {
					wp_die( esc_html__( 'Invalid request', 'form-plant' ) );
				}
				$form_manager = new FPLANT_Form_Manager();
				$form_manager->delete_form( $form_id, true );
				$redirect_url = add_query_arg( 'deleted', 1, $redirect_url );
				break;

			case 'duplicate':
				// Nonce verification
				if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'duplicate_form_' . $form_id ) ) {
					wp_die( esc_html__( 'Invalid request', 'form-plant' ) );
				}
				$form_manager = new FPLANT_Form_Manager();
				$new_form_id  = $form_manager->duplicate_form( $form_id );
				if ( $new_form_id ) {
					$redirect_url = add_query_arg( 'duplicated', 1, $redirect_url );
				}
				break;

			default:
				return;
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process bulk action
	 *
	 * @param string $action   Action name
	 * @param array  $form_ids Array of form IDs
	 */
	private function process_bulk_action( $action, $form_ids ) {
		if ( empty( $form_ids ) ) {
			return;
		}

		$redirect_url = admin_url( 'admin.php?page=fplant-forms' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for redirect
		$post_status  = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
		if ( $post_status ) {
			$redirect_url = add_query_arg( 'post_status', $post_status, $redirect_url );
		}

		$count = 0;

		switch ( $action ) {
			case 'trash':
				foreach ( $form_ids as $form_id ) {
					if ( wp_trash_post( $form_id ) ) {
						$count++;
					}
				}
				$redirect_url = add_query_arg( 'trashed', $count, $redirect_url );
				break;

			case 'restore':
				foreach ( $form_ids as $form_id ) {
					if ( wp_untrash_post( $form_id ) ) {
						$count++;
					}
				}
				$redirect_url = add_query_arg( 'restored', $count, $redirect_url );
				break;

			case 'delete':
				$form_manager = new FPLANT_Form_Manager();
				foreach ( $form_ids as $form_id ) {
					if ( $form_manager->delete_form( $form_id, true ) ) {
						$count++;
					}
				}
				$redirect_url = add_query_arg( 'deleted', $count, $redirect_url );
				break;
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX: Update form via quick edit
	 */
	public function ajax_quick_edit_form() {
		// Nonce verification
		check_ajax_referer( 'fplant_quick_edit', 'nonce' );

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'form-plant' ) ) );
		}

		$form_id     = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$post_title  = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$post_status = isset( $_POST['post_status'] ) ? sanitize_text_field( wp_unslash( $_POST['post_status'] ) ) : 'publish';

		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Form ID not specified', 'form-plant' ) ) );
		}

		// Validate status
		$allowed_statuses = array( 'publish', 'private', 'draft', 'pending' );
		if ( ! in_array( $post_status, $allowed_statuses, true ) ) {
			$post_status = 'publish';
		}

		// Update form
		$result = wp_update_post(
			array(
				'ID'          => $form_id,
				'post_title'  => $post_title,
				'post_status' => $post_status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Form updated', 'form-plant' ) ) );
	}
}
