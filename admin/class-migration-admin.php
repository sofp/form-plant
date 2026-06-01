<?php
/**
 * Migration Admin Controller
 *
 * Migration tool from MW WP Form. Displayed as a tab inside the Tools page.
 *
 * @package Form_Plant
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPLANT_Migration_Admin
 *
 * @since 1.2.0
 */
class FPLANT_Migration_Admin {

	const TOOLS_PAGE_SLUG  = 'fplant-tools';
	const TAB_SLUG         = 'mwwpform-migration';
	const MWWPFORM_FILE    = 'mw-wp-form/mw-wp-form.php';
	const MWWPFORM_MIN_VER = '5.0';
	const NONCE_ACTION     = 'fplant_admin_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! self::is_mwwpform_active() ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'maybe_render_version_notice' ) );
		add_action( 'wp_ajax_fplant_list_mwwpform_forms', array( $this, 'ajax_list_forms' ) );
		add_action( 'wp_ajax_fplant_run_mwwpform_migration', array( $this, 'ajax_run_migration' ) );
		add_action( 'wp_ajax_fplant_get_migration_log', array( $this, 'ajax_get_migration_log' ) );
	}

	/**
	 * Detect MW WP Form.
	 *
	 * @return bool
	 */
	public static function is_mwwpform_active() {
		if ( defined( 'MWF_Config' ) || class_exists( 'MWF_Config' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::MWWPFORM_FILE );
	}

	/**
	 * Retrieve MW WP Form version string.
	 *
	 * @return string
	 */
	public static function get_mwwpform_version() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_path = WP_PLUGIN_DIR . '/' . self::MWWPFORM_FILE;
		if ( ! file_exists( $plugin_path ) ) {
			return '';
		}

		$data = get_plugin_data( $plugin_path, false, false );
		return isset( $data['Version'] ) ? $data['Version'] : '';
	}

	/**
	 * Whether the installed MW WP Form meets the minimum supported version.
	 *
	 * @return bool
	 */
	public static function is_supported_version() {
		$version = self::get_mwwpform_version();
		if ( '' === $version ) {
			return false;
		}
		return version_compare( $version, self::MWWPFORM_MIN_VER, '>=' );
	}

	/**
	 * Whether the current admin request is on the migration tab of the Tools page.
	 *
	 * @return bool
	 */
	public static function is_current_screen() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		if ( ! isset( $_GET['page'] ) || self::TOOLS_PAGE_SLUG !== $_GET['page'] ) {
			return false;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable
		return self::TAB_SLUG === $tab;
	}

	/**
	 * Render admin notice when MW WP Form version is below the supported floor.
	 */
	public function maybe_render_version_notice() {
		if ( ! self::is_current_screen() ) {
			return;
		}

		if ( self::is_supported_version() ) {
			return;
		}

		$version = self::get_mwwpform_version();
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: installed version, 2: minimum supported version. */
					__( 'MW WP Form %1$s detected. The migration feature requires v%2$s or later. Please update MW WP Form to the latest version before running a migration.', 'form-plant' ),
					'' === $version ? '(unknown)' : $version,
					self::MWWPFORM_MIN_VER
				)
			)
		);
	}

	/**
	 * Ajax: Returns the list of MW WP Form forms.
	 */
	public function ajax_list_forms() {
		$this->check_ajax_permissions();

		$migrator = new FPLANT_MWWPForm_Migrator();
		wp_send_json_success(
			array(
				'forms'        => $migrator->list_mwwpform_forms(),
				'is_supported' => self::is_supported_version(),
			)
		);
	}

	/**
	 * Ajax: Runs the migration for a single form.
	 */
	public function ajax_run_migration() {
		$this->check_ajax_permissions();

		if ( ! self::is_supported_version() ) {
			wp_send_json_error(
				array(
					'message' => __( 'The installed version of MW WP Form is not supported. Please update to the latest version.', 'form-plant' ),
				),
				400
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_permissions() above.
		$mw_form_id = isset( $_POST['mw_form_id'] ) ? absint( wp_unslash( $_POST['mw_form_id'] ) ) : 0;
		if ( 0 === $mw_form_id ) {
			wp_send_json_error(
				array( 'message' => __( 'No target form ID was specified.', 'form-plant' ) ),
				400
			);
		}

		$migrator = new FPLANT_MWWPForm_Migrator();
		$result   = $migrator->migrate( $mw_form_id );

		wp_send_json_success( $result );
	}

	/**
	 * Ajax: Returns the migration log for the specified Form Plant form.
	 */
	public function ajax_get_migration_log() {
		$this->check_ajax_permissions();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_permissions() above.
		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		if ( 0 === $form_id ) {
			wp_send_json_error(
				array( 'message' => __( 'No form ID was specified.', 'form-plant' ) ),
				400
			);
		}

		$post = get_post( $form_id );
		if ( ! $post || 'fplant_form' !== $post->post_type ) {
			wp_send_json_error(
				array( 'message' => __( 'The specified Form Plant form was not found.', 'form-plant' ) ),
				404
			);
		}

		$log = FPLANT_MWWPForm_Migrator::get_migration_log( $form_id );
		if ( empty( $log ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No migration log has been saved for this form.', 'form-plant' ) ),
				404
			);
		}

		wp_send_json_success(
			array(
				'form_id'    => $form_id,
				'form_title' => $post->post_title,
				'log'        => $log,
			)
		);
	}

	/**
	 * Common Ajax permission check.
	 */
	private function check_ajax_permissions() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied', 'form-plant' ) ),
				403
			);
		}
	}
}
