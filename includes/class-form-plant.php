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
			require_once FPLANT_PLUGIN_DIR . 'includes/migration/class-migrator-base.php';
			require_once FPLANT_PLUGIN_DIR . 'includes/migration/class-name-translator.php';
			require_once FPLANT_PLUGIN_DIR . 'includes/migration/class-mwwpform-parser.php';
			require_once FPLANT_PLUGIN_DIR . 'includes/migration/class-mwwpform-field-mapper.php';
			require_once FPLANT_PLUGIN_DIR . 'includes/migration/class-mwwpform-email-mapper.php';
			require_once FPLANT_PLUGIN_DIR . 'includes/migration/class-mwwpform-validation-merger.php';
			require_once FPLANT_PLUGIN_DIR . 'includes/migration/class-mwwpform-template-builder.php';
			require_once FPLANT_PLUGIN_DIR . 'includes/migration/class-mwwpform-migrator.php';
			require_once FPLANT_PLUGIN_DIR . 'admin/class-migration-admin.php';
		}

		// REST API
		require_once FPLANT_PLUGIN_DIR . 'includes/class-rest-api.php';

		// Embed
		require_once FPLANT_PLUGIN_DIR . 'includes/class-embed.php';

		// Block editor integration
		require_once FPLANT_PLUGIN_DIR . 'includes/class-block.php';
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Load translations
		add_action( 'init', array( $this, 'load_textdomain' ) );

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
			new FPLANT_Migration_Admin();
		}

		// Initialize REST API
		new FPLANT_REST_API();

		// Initialize embed
		$this->embed = new FPLANT_Embed();

		// Initialize block editor integration
		new FPLANT_Block();
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

		// Whether to load default CSS (form.css) — skip when all forms use 'none'
		$load_default_css = false;

		// Collect inline CSS to add later
		$inline_css_queue = array();

		// Collect per-form JS config and check CAPTCHA need
		$recaptcha_site_key    = get_option( 'fplant_recaptcha_site_key' );
		$recaptcha_v2_site_key = get_option( 'fplant_recaptcha_v2_site_key' );
		$turnstile_site_key    = get_option( 'fplant_turnstile_site_key' );
		$needs_recaptcha       = false;
		$needs_recaptcha_v2    = false;
		$needs_turnstile       = false;
		$needs_zxcvbn          = false;
		$form_inline_js        = '';

		foreach ( $form_ids as $form_id ) {
			$form = FPLANT_Database::get_form( $form_id );
			if ( ! $form ) {
				continue;
			}

			// --- Design CSS ---

			$fplant_design_type = $form['settings']['design_type'] ?? 'simple1';
			// Backward compatibility: 'default' maps to 'simple1'
			if ( 'default' === $fplant_design_type ) {
				$fplant_design_type = 'simple1';
			}
			// simple1 uses form.css; simple2/normal use self-contained design CSS
			if ( 'simple1' === $fplant_design_type ) {
				$load_default_css = true;
			} elseif ( in_array( $fplant_design_type, array( 'simple2', 'normal' ), true ) ) {
				wp_enqueue_style(
					'fplant-design-' . $fplant_design_type,
					FPLANT_PLUGIN_URL . 'assets/css/design-' . $fplant_design_type . '.css',
					array(),
					FPLANT_VERSION
				);
			}

			// --- Custom CSS files ---

			$custom_css_file_urls = array();
			if ( ! empty( $form['settings']['custom_css_file_urls'] ) && is_array( $form['settings']['custom_css_file_urls'] ) ) {
				$custom_css_file_urls = $form['settings']['custom_css_file_urls'];
			} elseif ( ! empty( $form['settings']['custom_css_file_url'] ) ) {
				// Backward compatibility: single URL to array
				$custom_css_file_urls = array( $form['settings']['custom_css_file_url'] );
			}

			$css_file_index  = 0;
			$inline_handle   = 'fplant-form';
			foreach ( $custom_css_file_urls as $css_url ) {
				if ( ! empty( $css_url ) ) {
					$handle = 'fplant-form-custom-' . $form_id . '-' . $css_file_index;
					wp_enqueue_style( $handle, $css_url, array(), FPLANT_VERSION );
					$inline_handle = $handle;
					$css_file_index++;
				}
			}

			// --- Inline CSS ---

			$custom_css_inline = isset( $form['settings']['custom_css_inline'] )
				? $form['settings']['custom_css_inline']
				: '';

			if ( ! empty( $custom_css_inline ) ) {
				$sanitized_css = $this->sanitize_css( $custom_css_inline );
				if ( $css_file_index > 0 ) {
					wp_add_inline_style( $inline_handle, $sanitized_css );
				} else {
					$inline_css_queue[] = $sanitized_css;
				}
			}

			// --- CAPTCHA check ---

			$fplant_captcha_type = $form['settings']['captcha_type'] ?? 'none';
			// Backward compatibility
			if ( 'none' === $fplant_captcha_type && ! empty( $form['settings']['recaptcha_enabled'] ) ) {
				$fplant_captcha_type = 'recaptcha';
			}

			if ( 'recaptcha_v2' === $fplant_captcha_type && ! empty( $recaptcha_v2_site_key ) ) {
				$needs_recaptcha_v2 = true;
			} elseif ( 'recaptcha' === $fplant_captcha_type && ! empty( $recaptcha_site_key ) ) {
				$needs_recaptcha = true;
			} elseif ( 'turnstile' === $fplant_captcha_type && ! empty( $turnstile_site_key ) ) {
				$needs_turnstile = true;
			}

			// --- Password strength meter check ---

			if ( ! $needs_zxcvbn && ! empty( $form['fields'] ) ) {
				foreach ( $form['fields'] as $pw_field ) {
					if ( 'password' === $pw_field['type'] && ! empty( $pw_field['password_strength_meter'] ) ) {
						$needs_zxcvbn = true;
						break;
					}
				}
			}

			// --- Per-form JS config ---

			$form_inline_js .= $this->generate_form_inline_js( $form );
		}

		// Load default CSS (form.css) when at least one form uses a design type
		if ( $load_default_css ) {
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
		} elseif ( ! empty( $inline_css_queue ) ) {
			// No default CSS but inline CSS exists — use dummy handle
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Intentionally no version for inline-only style
			wp_register_style( 'fplant-form-inline', false );
			wp_enqueue_style( 'fplant-form-inline' );
			foreach ( $inline_css_queue as $inline_css ) {
				wp_add_inline_style( 'fplant-form-inline', $inline_css );
			}
		}

		// v2 and v3 api.js cannot be loaded simultaneously; v2 takes priority
		if ( $needs_recaptcha_v2 ) {
			$needs_recaptcha = false;
		}

		if ( $needs_recaptcha_v2 ) {
			// External Google reCAPTCHA v2 script - version managed by Google, null is intentional
			wp_enqueue_script( 'google-recaptcha-v2', 'https://www.google.com/recaptcha/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- External CAPTCHA service, cannot be bundled locally
		}

		if ( $needs_recaptcha ) {
			// External Google reCAPTCHA v3 script - version managed by Google, null is intentional
			wp_enqueue_script( 'google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $recaptcha_site_key ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- External CAPTCHA service, cannot be bundled locally
		}

		if ( $needs_turnstile ) {
			// External Cloudflare Turnstile script - version managed by Cloudflare, null is intentional
			wp_enqueue_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- External CAPTCHA service, cannot be bundled locally
		}

		if ( $needs_zxcvbn ) {
			wp_enqueue_script( 'zxcvbn-async' );
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
			'fplantData',
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
					/* translators: %s: sub-field label (e.g., Last Name, First Name) */
					'requiredSubField'    => __( '%s is required', 'form-plant' ),
					/* translators: %s: maximum file size in MB */
					'fileTooLarge'        => __( 'File size is too large. Please select a file under %sMB.', 'form-plant' ),
					'imageRequired'       => __( 'Please select an image file.', 'form-plant' ),
					'kanaKatakanaOnly'    => __( 'Please enter in katakana.', 'form-plant' ),
					'kanaHiraganaOnly'    => __( 'Please enter in hiragana.', 'form-plant' ),
					'serverError'         => __( 'A server error occurred. Please try again.', 'form-plant' ),
					'errorOccurred'       => __( 'An error occurred. Please try again.', 'form-plant' ),
					'recaptchaError'      => __( 'reCAPTCHA verification failed. Please reload the page and try again.', 'form-plant' ),
					'captchaError'        => __( 'CAPTCHA verification failed. Please reload the page and try again.', 'form-plant' ),
					'confirmationTitle'   => __( 'Confirm Your Input', 'form-plant' ),
					'confirmationMessage' => __( 'If the information below is correct, please click the "Submit" button.', 'form-plant' ),
					'back'                => __( 'Back', 'form-plant' ),
					'submitForm'          => __( 'Submit', 'form-plant' ),
					/* translators: %s: minimum character count */
					'passwordMinLength'   => __( 'Password must be at least %s characters', 'form-plant' ),
					'passwordTooWeak'     => __( 'Password is not strong enough', 'form-plant' ),
					'strengthVeryWeak'    => __( 'Very Weak', 'form-plant' ),
					'strengthWeak'        => __( 'Weak', 'form-plant' ),
					'strengthFair'        => __( 'Fair', 'form-plant' ),
					'strengthStrong'      => __( 'Strong', 'form-plant' ),
					'strengthVeryStrong'  => __( 'Very Strong', 'form-plant' ),
					'showPassword'        => __( 'Show password', 'form-plant' ),
					'hidePassword'        => __( 'Hide password', 'form-plant' ),
					'searchingAddress'    => __( 'Searching...', 'form-plant' ),
					'addressNotFound'     => __( 'Address not found for this postal code', 'form-plant' ),
					'searchError'         => __( 'Address search failed. Please try again.', 'form-plant' ),
				),
			)
		);

		// Per-form inline JS (fields config, confirmation, reCAPTCHA config)
		if ( ! empty( $form_inline_js ) ) {
			wp_add_inline_script( 'fplant-form', $form_inline_js, 'before' );
		}
	}

	/**
	 * Generate per-form inline JavaScript configuration.
	 *
	 * @param array $form Form data.
	 * @return string Inline JavaScript code.
	 */
	private function generate_form_inline_js( $form ) {
		$fplant_form_id = absint( $form['id'] );

		$inline_js = '';

		// fplantFieldsConfig
		$inline_js .= 'if (typeof window.fplantFieldsConfig === "undefined") { window.fplantFieldsConfig = {}; }' . "\n";
		$inline_js .= 'window.fplantFieldsConfig[' . $fplant_form_id . '] = ' . wp_json_encode( $form['fields'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . ";\n";

		// fplantConfirmationTemplate
		$inline_js .= 'if (typeof window.fplantConfirmationTemplate === "undefined") { window.fplantConfirmationTemplate = {}; }' . "\n";
		$inline_js .= 'window.fplantConfirmationTemplate[' . $fplant_form_id . '] = ' . wp_json_encode( $form['settings']['confirmation_template'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . ";\n";

		// fplantConfirmationButtons
		$inline_js .= 'if (typeof window.fplantConfirmationButtons === "undefined") { window.fplantConfirmationButtons = {}; }' . "\n";
		$inline_js .= 'window.fplantConfirmationButtons[' . $fplant_form_id . '] = ' . wp_json_encode(
			array(
				'back'         => $form['settings']['confirmation_back_text'] ?? __( 'Back', 'form-plant' ),
				'back_class'   => $form['settings']['confirmation_back_class'] ?? '',
				'back_id'      => $form['settings']['confirmation_back_id'] ?? '',
				'submit'       => $form['settings']['confirmation_submit_text'] ?? __( 'Submit Form', 'form-plant' ),
				'submit_class' => $form['settings']['confirmation_submit_class'] ?? '',
				'submit_id'    => $form['settings']['confirmation_submit_id'] ?? '',
			),
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
		) . ";\n";

		// fplantCaptchaConfig
		$fplant_captcha_type = $form['settings']['captcha_type'] ?? 'none';
		// Backward compatibility
		if ( 'none' === $fplant_captcha_type && ! empty( $form['settings']['recaptcha_enabled'] ) ) {
			$fplant_captcha_type = 'recaptcha';
		}

		$fplant_recaptcha_site_key    = get_option( 'fplant_recaptcha_site_key', '' );
		$fplant_recaptcha_v2_site_key = get_option( 'fplant_recaptcha_v2_site_key', '' );
		$fplant_turnstile_site_key    = get_option( 'fplant_turnstile_site_key', '' );

		// If the site key is empty, fall back to type "none".
		if ( 'recaptcha_v2' === $fplant_captcha_type && empty( $fplant_recaptcha_v2_site_key ) ) {
			$fplant_captcha_type = 'none';
		}
		if ( 'recaptcha' === $fplant_captcha_type && empty( $fplant_recaptcha_site_key ) ) {
			$fplant_captcha_type = 'none';
		}
		if ( 'turnstile' === $fplant_captcha_type && empty( $fplant_turnstile_site_key ) ) {
			$fplant_captcha_type = 'none';
		}

		$inline_js .= 'if (typeof window.fplantCaptchaConfig === "undefined") { window.fplantCaptchaConfig = {}; }' . "\n";
		$inline_js .= 'window.fplantCaptchaConfig[' . $fplant_form_id . '] = ' . wp_json_encode(
			array(
				'type'                => $fplant_captcha_type,
				'recaptchaSiteKey'    => $fplant_recaptcha_site_key,
				'recaptchaV2SiteKey'  => $fplant_recaptcha_v2_site_key,
				'turnstileSiteKey'    => $fplant_turnstile_site_key,
			),
			JSON_HEX_TAG
		) . ";\n";

		return $inline_js;
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function admin_enqueue_scripts( $hook ) {
		// Only load on Form Plant pages
		if ( strpos( $hook, 'fplant' ) === false && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		// Migration tab assets (Tools page → MW WP Form Migration tab).
		if (
			class_exists( 'FPLANT_Migration_Admin' )
			&& FPLANT_Migration_Admin::is_current_screen()
			&& FPLANT_Migration_Admin::is_mwwpform_active()
		) {
			wp_enqueue_style(
				'fplant-migration',
				FPLANT_PLUGIN_URL . 'assets/admin/css/migration.css',
				array(),
				FPLANT_VERSION
			);
			wp_enqueue_script(
				'fplant-migration',
				FPLANT_PLUGIN_URL . 'assets/admin/js/migration.js',
				array( 'jquery' ),
				FPLANT_VERSION,
				true
			);
			wp_localize_script(
				'fplant-migration',
				'fplantMigrationData',
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'fplant_admin_nonce' ),
					'editFormUrlBase' => admin_url( 'admin.php?page=fplant-form-new&id=' ),
					'i18n'            => array(
						'loading'        => __( 'Loading list…', 'form-plant' ),
						'noForms'        => __( 'No MW WP Form forms were found.', 'form-plant' ),
						'loadError'      => __( 'Failed to load the list.', 'form-plant' ),
						'networkError'   => __( 'A network error occurred.', 'form-plant' ),
						'noResults'      => __( 'No results.', 'form-plant' ),
						'noWarnings'     => __( 'No warnings.', 'form-plant' ),
						'summaryLabel'   => __( 'Result summary', 'form-plant' ),
						'formsLabel'     => __( 'forms', 'form-plant' ),
						'statusMigrated' => __( 'Migrated', 'form-plant' ),
						'statusPending'  => __( 'Not migrated', 'form-plant' ),
						'statusRunning'  => __( 'Migrating…', 'form-plant' ),
						'statusSuccess'  => __( 'Success', 'form-plant' ),
						'statusPartial'  => __( 'With warnings', 'form-plant' ),
						'statusFailed'   => __( 'Failed', 'form-plant' ),
						'openNewForm'    => __( 'Open the generated Form Plant form', 'form-plant' ),
						'confirmRun'     => __( 'Migrate the selected forms to Form Plant. Are you sure? (Existing migrated forms are kept as-is, and a new Form Plant form is added.)', 'form-plant' ),
						'viewLog'        => __( 'View log', 'form-plant' ),
						'logTitle'       => __( 'Migration Log', 'form-plant' ),
						'logClose'       => __( 'Close', 'form-plant' ),
						'logLoading'     => __( 'Loading the log…', 'form-plant' ),
						'logLoadError'   => __( 'Failed to load the log.', 'form-plant' ),
						'logNoEntries'   => __( 'No migration log has been saved for this form.', 'form-plant' ),
						'logMigratedAt'  => __( 'Migrated at', 'form-plant' ),
						'logStatus'      => __( 'Status', 'form-plant' ),
						'logSourceForm'  => __( 'Source form', 'form-plant' ),
						'logWarnings'    => __( 'Warnings', 'form-plant' ),
					),
				)
			);
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
		if ( isset( $_GET['page'] ) && 'fplant-form-new' === $_GET['page'] ) {
			if ( isset( $_GET['id'] ) ) {
				// Existing form edit
				$post_id = absint( $_GET['id'] );
				$post    = get_post( $post_id );
				if ( $post && 'fplant_form' === $post->post_type ) {
					$form_data = FPLANT_Database::get_form( $post_id );
				}
			} else {
				// New form — set default fields and basic settings
				$form_data = array(
					'fields'   => self::get_default_fields(),
					'settings' => array(
						'save_submission'  => 'full',
						'use_confirmation' => true,
					),
				);
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Localize
		wp_localize_script(
			'fplant-admin',
			'fplantAdminData',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'fplant_admin_nonce' ),
				'formData'           => $form_data,
				'cssNonce'           => wp_create_nonce( 'fplant_css_upload' ),
				'pluginUrl'          => FPLANT_PLUGIN_URL,
				'defaultPrefectures' => array_map(
					function ( $pref ) {
						return array(
							'value' => $pref,
							'label' => $pref,
						);
					},
					FPLANT_Field_Manager::get_prefectures()
				),
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
					'cssFileLimit'          => __( 'Maximum 10 CSS files can be uploaded.', 'form-plant' ),
					'currentFile'           => __( 'Current file:', 'form-plant' ),
					'errorPrefix'           => __( 'Error:', 'form-plant' ),
					'missingRequiredFields'        => __( 'The following required items are missing from the HTML template:', 'form-plant' ),
					'submitButton'                 => __( 'Submit button', 'form-plant' ),
					'templateEmpty'                => __( 'HTML template is empty. Please add the required tags or uncheck "Use HTML template".', 'form-plant' ),
					'confirmationTemplateEmpty'    => __( 'Confirmation HTML template is empty. Please add the required tags or uncheck "Use confirmation screen HTML template".', 'form-plant' ),
					'confirmationSubmitRequired'   => __( 'Submit button [fplant_confirm_submit] is required in the confirmation template.', 'form-plant' ),
					'confirmCloseModal'            => __( 'Changes have not been saved. Are you sure you want to close?', 'form-plant' ),
				),
			)
		);

		// Tools page
		if ( 'form-plant_page_fplant-tools' === $hook ) {
			wp_enqueue_script(
				'fplant-admin-tools',
				FPLANT_PLUGIN_URL . 'admin/js/admin-tools.js',
				array( 'jquery' ),
				FPLANT_VERSION,
				true
			);

			wp_localize_script(
				'fplant-admin-tools',
				'fplantTools',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'fplant_admin_nonce' ),
					'i18n'    => array(
						'exportSuccess' => __( 'Export completed.', 'form-plant' ),
						'selectFile'    => __( 'Please select a file.', 'form-plant' ),
						'invalidFile'   => __( 'Please select a JSON file.', 'form-plant' ),
						'selectForm'    => __( 'Please select at least one form.', 'form-plant' ),
						'confirmImport' => __( 'Import forms from this file?', 'form-plant' ),
						'noForms'       => __( 'No forms found to export.', 'form-plant' ),
						'errorOccurred' => __( 'An error occurred', 'form-plant' ),
					),
				)
			);
		}

		// Submission list page
		if ( 'form-plant_page_fplant-submissions' === $hook ) {
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
						'loading'        => __( 'Loading...', 'form-plant' ),
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
		// Use $upload's basedir/baseurl directly to avoid recursive wp_upload_dir() call
		$upload['path']   = $upload['basedir'] . '/fplant_uploads/assets';
		$upload['url']    = $upload['baseurl'] . '/fplant_uploads/assets';
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
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'form-plant' ) ) );
		}

		// Nonce verification
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fplant_css_upload' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'form-plant' ) ) );
		}

		// File check
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES validated for file operations
		if ( ! isset( $_FILES['css_file'] ) || empty( $_FILES['css_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'form-plant' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES validated for file operations
		$file = $_FILES['css_file'];

		// Extension check
		$ext = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
		if ( 'css' !== $ext ) {
			wp_send_json_error( array( 'message' => __( 'Only .css files are allowed.', 'form-plant' ) ) );
		}

		// MIME type check
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		// CSS files are detected as text/plain or text/css
		if ( ! in_array( $mime, array( 'text/plain', 'text/css', 'text/x-css' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type.', 'form-plant' ) ) );
		}

		// Create directory
		if ( ! $this->create_css_upload_dir() ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create upload directory.', 'form-plant' ) ) );
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
			'unique_filename_callback' => function ( $dir, $name, $ext ) use ( $filename ) {
				return $filename;
			},
		);

		// Temporarily allow .css uploads.
		// wp_check_filetype_and_ext() rejects .css because finfo detects text/plain
		// instead of text/css. We add both upload_mimes (allowed list) and
		// wp_check_filetype_and_ext (real MIME override) filters.
		// Extension and MIME type have already been validated above.
		$allow_css_mime = function ( $mimes ) {
			$mimes['css'] = 'text/css';
			return $mimes;
		};
		$fix_css_filetype = function ( $data, $file_path, $filename, $mimes ) {
			if ( '.css' === strtolower( substr( $filename, -4 ) ) ) {
				$data['ext']  = 'css';
				$data['type'] = 'text/css';
			}
			return $data;
		};
		add_filter( 'upload_mimes', $allow_css_mime );
		add_filter( 'wp_check_filetype_and_ext', $fix_css_filetype, 10, 4 );

		// Set custom upload directory
		add_filter( 'upload_dir', array( $this, 'get_css_upload_dir_for_filter' ) );
		$upload_result = wp_handle_upload( $file, $upload_overrides );
		remove_filter( 'upload_dir', array( $this, 'get_css_upload_dir_for_filter' ) );
		remove_filter( 'wp_check_filetype_and_ext', $fix_css_filetype, 10 );
		remove_filter( 'upload_mimes', $allow_css_mime );

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
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'form-plant' ) ) );
		}

		// Nonce verification
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fplant_css_upload' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'form-plant' ) ) );
		}

		// Get file URL
		$file_url = isset( $_POST['file_url'] ) ? esc_url_raw( wp_unslash( $_POST['file_url'] ) ) : '';
		if ( empty( $file_url ) ) {
			wp_send_json_error( array( 'message' => __( 'No file specified.', 'form-plant' ) ) );
		}

		// Get file path from URL
		$dir_info = $this->get_css_upload_dir();
		$filename = basename( $file_url );

		// Security: Check for path traversal in filename
		if ( strpos( $filename, '..' ) !== false || strpos( $filename, '/' ) !== false ) {
			wp_send_json_error( array( 'message' => __( 'Invalid filename.', 'form-plant' ) ) );
		}

		// Check if CSS file
		if ( strtolower( pathinfo( sanitize_file_name( $filename ), PATHINFO_EXTENSION ) ) !== 'css' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type.', 'form-plant' ) ) );
		}

		$file_path = $dir_info['path'] . '/' . $filename;

		// Check if file exists
		if ( ! file_exists( $file_path ) ) {
			// Return success even if file doesn't exist (already deleted)
			wp_send_json_success( array( 'message' => __( 'File deleted.', 'form-plant' ) ) );
		}

		// Security: Normalize with realpath() and verify within allowed directory
		$real_file_path = realpath( $file_path );
		$real_dir_path  = realpath( $dir_info['path'] );

		if ( false === $real_file_path ||
			false === $real_dir_path ||
			strpos( $real_file_path, $real_dir_path . DIRECTORY_SEPARATOR ) !== 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file path.', 'form-plant' ) ) );
		}

		// Delete file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		if ( ! unlink( $real_file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete file.', 'form-plant' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'File deleted.', 'form-plant' ) ) );
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

		// Create default form (only on first activation)
		self::maybe_create_default_form();
	}

	/**
	 * Get default form fields.
	 *
	 * Used for both activation default form and new form creation.
	 *
	 * @return array Default fields array.
	 */
	public static function get_default_fields() {
		return array(
			array(
				'type'        => 'name_parts',
				'name'        => 'your_name',
				'label'       => __( 'Name', 'form-plant' ),
				'required'    => true,
				'name_format' => '2',
			),
			array(
				'type'     => 'email',
				'name'     => 'email',
				'label'    => __( 'Email', 'form-plant' ),
				'required' => true,
			),
			array(
				'type'     => 'textarea',
				'name'     => 'message',
				'label'    => __( 'Message', 'form-plant' ),
				'required' => false,
			),
		);
	}

	/**
	 * Create default contact form if no forms exist.
	 */
	private static function maybe_create_default_form() {
		// Skip if already created
		if ( get_option( 'fplant_default_form_created' ) ) {
			return;
		}

		// Skip if forms already exist
		$existing = get_posts(
			array(
				'post_type'      => 'fplant_form',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			update_option( 'fplant_default_form_created', true );
			return;
		}

		// Create default form
		$form_id = wp_insert_post(
			array(
				'post_type'   => 'fplant_form',
				'post_title'  => __( 'Contact Form', 'form-plant' ),
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $form_id ) ) {
			return;
		}

		// Fields
		$default_fields = self::get_default_fields();

		// Settings
		$default_settings = array(
			'save_submission'  => 'full',
			'action_type'      => 'message',
			'success_message'  => __( 'Thank you for your message. We will get back to you shortly.', 'form-plant' ),
			'use_confirmation' => true,
		);

		// Admin email notification
		$default_email_admin = array(
			'enabled' => true,
			'to'      => '{admin_email}',
			'subject' => __( '[{site_name}] New contact form submission', 'form-plant' ),
			'body'    => __( "You have received a new message.\n\nName: {your_name}\nEmail: {email}\nMessage:\n{message}", 'form-plant' ),
		);

		// Auto-reply (disabled by default)
		$default_email_user = array(
			'enabled'     => false,
			'email_field' => 'email',
			'subject'     => __( 'Thank you for your inquiry', 'form-plant' ),
			'body'        => __( "Thank you for contacting us.\n\nWe have received the following message:\n\nName: {your_name}\nMessage:\n{message}\n\nWe will get back to you shortly.", 'form-plant' ),
		);

		// Save metadata
		FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_FIELDS, $default_fields );
		FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_SETTINGS, $default_settings );
		FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_EMAIL_ADMIN, $default_email_admin );
		FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_EMAIL_USER, $default_email_user );

		// Mark as created
		update_option( 'fplant_default_form_created', true );
	}

	/**
	 * Plugin deactivation handler
	 */
	public static function deactivate() {
		// Flush permalink settings
		flush_rewrite_rules();
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * While WordPress.org automatically loads translations from
	 * translate.wordpress.org since WP 4.6, bundled translations
	 * serve as a fallback until community translations are available.
	 *
	 * TODO: Remove this method and its init hook. GlotPress translations
	 * are now available (94% approved). Also remove the .po/.mo files
	 * from the SVN distribution (already excluded by sync-wporg-trunk.sh).
	 * See: https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
	 */
	public function load_textdomain() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Temporary: bundled translations fallback until translate.wordpress.org translations are available.
		load_plugin_textdomain(
			'form-plant',
			false,
			dirname( FPLANT_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
