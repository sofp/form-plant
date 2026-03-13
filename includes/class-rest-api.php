<?php
/**
 * REST API class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_REST_API class
 */
class FPLANT_REST_API {

	/**
	 * Namespace
	 *
	 * @var string
	 */
	private $namespace = 'form-plant/v1';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'handle_cors' ), 10, 4 );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Embed: get form HTML
		register_rest_route(
			$this->namespace,
			'/embed/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_embed_form' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Embed: submit form (CORS enabled)
		register_rest_route(
			$this->namespace,
			'/embed/submit',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'submit_embed_form' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'OPTIONS',
					'callback'            => array( $this, 'handle_options_request' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Embed: validate form and get confirmation HTML (CORS enabled)
		register_rest_route(
			$this->namespace,
			'/embed/validate',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'validate_embed_form' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'OPTIONS',
					'callback'            => array( $this, 'handle_options_request' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle OPTIONS preflight request
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_options_request( $request ) {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

		$response = new WP_REST_Response( null, 200 );

		if ( ! empty( $origin ) ) {
			$response->header( 'Access-Control-Allow-Origin', $origin );
			$response->header( 'Access-Control-Allow-Methods', 'GET, POST, OPTIONS' );
			$response->header( 'Access-Control-Allow-Headers', 'Content-Type' );
			$response->header( 'Access-Control-Allow-Credentials', 'true' );
			$response->header( 'Access-Control-Max-Age', '86400' );
		}

		return $response;
	}

	/**
	 * Handle CORS
	 *
	 * @param bool             $served  Whether the request has been served.
	 * @param WP_REST_Response $result  Response.
	 * @param WP_REST_Request  $request Request.
	 * @param WP_REST_Server   $server  Server.
	 * @return bool
	 */
	public function handle_cors( $served, $result, $request, $server ) {
		// Only handle CORS for embed endpoints
		$route = $request->get_route();
		if ( strpos( $route, '/form-plant/v1/embed' ) === false ) {
			return $served;
		}

		// Get form ID
		$form_id = $this->get_form_id_from_request( $request );
		if ( ! $form_id ) {
			return $served;
		}

		$form = FPLANT_Database::get_form( $form_id );
		if ( ! $form ) {
			return $served;
		}

		// Check if JS embedding is allowed
		if ( empty( $form['settings']['embed_js_enabled'] ) ) {
			return $served;
		}

		// Check allowed URL list
		$allowed_urls = $form['settings']['embed_js_allowed_urls'] ?? array();
		$origin       = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

		if ( ! empty( $allowed_urls ) && ! empty( $origin ) ) {
			$allowed_origin = $this->get_allowed_origin( $origin, $allowed_urls );
			if ( $allowed_origin ) {
				header( 'Access-Control-Allow-Origin: ' . $allowed_origin );
				header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
				header( 'Access-Control-Allow-Headers: Content-Type' );
				header( 'Access-Control-Allow-Credentials: true' );
				header( 'Vary: Origin' );
			}
		}

		return $served;
	}

	/**
	 * Get form ID from request
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int|null
	 */
	private function get_form_id_from_request( $request ) {
		// Get from URL parameter
		$form_id = $request->get_param( 'id' );
		if ( $form_id ) {
			return absint( $form_id );
		}

		// Get from POST data
		$form_id = $request->get_param( 'form_id' );
		if ( $form_id ) {
			return absint( $form_id );
		}

		return null;
	}

	/**
	 * Get allowed origin
	 *
	 * @param string $origin       Request origin.
	 * @param array  $allowed_urls Allowed URL list.
	 * @return string|null
	 */
	private function get_allowed_origin( $origin, $allowed_urls ) {
		foreach ( $allowed_urls as $allowed_url ) {
			$allowed_url = trim( $allowed_url );
			if ( empty( $allowed_url ) ) {
				continue;
			}

			$allowed_origin = $this->get_origin_from_url( $allowed_url );
			if ( $origin === $allowed_origin ) {
				return $origin;
			}
		}

		return null;
	}

	/**
	 * Get origin from URL
	 *
	 * @param string $url URL.
	 * @return string Origin (scheme://host[:port]).
	 */
	private function get_origin_from_url( $url ) {
		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return '';
		}

		$origin = $parsed['scheme'] . '://' . $parsed['host'];
		if ( ! empty( $parsed['port'] ) ) {
			// Add port if not default
			if ( ( 'http' === $parsed['scheme'] && 80 !== $parsed['port'] ) ||
				( 'https' === $parsed['scheme'] && 443 !== $parsed['port'] ) ) {
				$origin .= ':' . $parsed['port'];
			}
		}

		return $origin;
	}

	/**
	 * Get embed form HTML
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_embed_form( $request ) {
		$form_id = $request->get_param( 'id' );
		$form    = FPLANT_Database::get_form( $form_id );

		if ( ! $form ) {
			return new WP_Error(
				'form_not_found',
				__( 'Form not found', 'form-plant' ),
				array( 'status' => 404 )
			);
		}

		// Check if JS embedding is allowed
		if ( empty( $form['settings']['embed_js_enabled'] ) ) {
			return new WP_Error(
				'embed_not_allowed',
				__( 'JS embedding is not allowed for this form', 'form-plant' ),
				array( 'status' => 403 )
			);
		}

		// Check allowed URL list
		$allowed_urls = $form['settings']['embed_js_allowed_urls'] ?? array();
		$origin       = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

		// If origin is empty, try to get it from referer (same-origin requests don't send Origin header)
		if ( empty( $origin ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer_origin = $this->get_origin_from_url( sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
			$site_origin    = $this->get_origin_from_url( home_url() );
			// Allow same-origin requests (from the WordPress site itself)
			if ( $referer_origin === $site_origin ) {
				$origin = $referer_origin;
			}
		}

		if ( ! empty( $allowed_urls ) ) {
			$allowed_origin = $this->get_allowed_origin( $origin, $allowed_urls );
			if ( ! $allowed_origin ) {
				return new WP_Error(
					'origin_not_allowed',
					__( 'Embedding from this domain is not allowed', 'form-plant' ),
					array( 'status' => 403 )
				);
			}
		}

		// Generate form HTML
		$html = $this->generate_embed_form_html( $form );

		return rest_ensure_response(
			array(
				'success'   => true,
				'data'      => array(
					'id'       => $form['id'],
					'title'    => $form['title'],
					'html'     => $html,
					'fields'   => $form['fields'],
					'settings' => $form['settings'],
				),
				'recaptcha' => array(
					'enabled' => ! empty( $form['settings']['recaptcha_enabled'] ),
					'version' => $form['settings']['recaptcha_version'] ?? 'v3',
					'siteKey' => get_option( 'fplant_recaptcha_site_key', '' ),
				),
			)
		);
	}

	/**
	 * Generate embed form HTML
	 *
	 * Uses template loader for field rendering (same as iframe embed).
	 * This allows theme template overrides to work with JavaScript embedding.
	 *
	 * @param array $form Form data.
	 * @return string
	 */
	private function generate_embed_form_html( $form ) {
		$form_id  = $form['id'];
		$fields   = $form['fields'] ?? array();
		$settings = $form['settings'] ?? array();

		// Set global form context for shortcodes (required for [fplant_field] etc.)
		global $fplant_current_form;
		$fplant_current_form = $form;

		// Use Field Manager for template-based rendering (same as embed.php)
		$field_manager = new FPLANT_Field_Manager();

		// Form settings
		$use_confirmation = ! empty( $settings['use_confirmation'] );

		ob_start();
		?>
		<div class="fplant-form-wrapper" data-form-id="<?php echo esc_attr( $form_id ); ?>">
			<!-- Messages outside form so they remain visible when form is hidden -->
			<div class="fplant-messages">
				<div class="fplant-errors" data-show-field-errors="false" style="display: none;"></div>
				<div class="fplant-success" style="display: none;"></div>
			</div>

			<form class="fplant-form" method="post" enctype="multipart/form-data" data-form-id="<?php echo esc_attr( $form_id ); ?>" data-use-confirmation="<?php echo esc_attr( $use_confirmation ? '1' : '0' ); ?>">
				<input type="hidden" name="fplant_form_id" value="<?php echo esc_attr( $form_id ); ?>">
				<input type="hidden" name="fplant_embed_mode" value="1">

				<div class="fplant-field-wrap fplant-field-url" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden;">
					<label for="fplant_field_url_<?php echo esc_attr( $form_id ); ?>">Website URL</label>
					<input type="text" name="fplant_website_url" id="fplant_field_url_<?php echo esc_attr( $form_id ); ?>" value="" tabindex="-1" autocomplete="off">
				</div>

				<?php if ( ! empty( $form['html_template'] ) && ! empty( $settings['use_html_template'] ) ) : ?>
					<?php
					// Process shortcodes in the input screen HTML template (same as embed.php)
					echo wp_kses( do_shortcode( $form['html_template'] ), fplant_get_allowed_form_html() );
					?>
				<?php else : ?>
					<?php
					// Render fields using template loader (same as form-wrapper.php)
					foreach ( $fields as $field ) :
						// Skip html and hidden fields from field group wrapper
						if ( 'html' === $field['type'] || 'hidden' === $field['type'] ) {
							continue;
						}

						// Get initial value for the field
						$field_value = $field_manager->get_field_initial_value( $field, $form_id, $settings );
						?>
						<div class="fplant-field-group" data-field-name="<?php echo esc_attr( $field['name'] ); ?>">
							<?php if ( ! empty( $field['label'] ) ) : ?>
								<label for="fplant-field-<?php echo esc_attr( $field['name'] ); ?>">
									<?php echo esc_html( $field['label'] ); ?>
									<?php if ( ! empty( $field['required'] ) ) : ?>
										<span class="required">*</span>
									<?php endif; ?>
								</label>
							<?php endif; ?>

							<?php
							// Render field using template (supports theme overrides)
							echo wp_kses( $field_manager->render_field( $field, $field_value, $form_id, $settings ), fplant_get_allowed_form_html() );
							?>
							<div class="fplant-field-error" style="display: none;"></div>
						</div>
						<?php
					endforeach;
					?>

					<div class="fplant-submit-wrapper">
						<?php
						$submit_text  = $settings['input_submit_text'] ?? __( 'Submit', 'form-plant' );
						$submit_class = 'fplant-submit-button';
						if ( ! empty( $settings['input_submit_class'] ) ) {
							$submit_class .= ' ' . $settings['input_submit_class'];
						}
						$submit_id = $settings['input_submit_id'] ?? '';
						?>
						<button
							type="submit"
							class="<?php echo esc_attr( $submit_class ); ?>"
							<?php echo ! empty( $submit_id ) ? 'id="' . esc_attr( $submit_id ) . '"' : ''; ?>
						>
							<?php echo esc_html( $submit_text ); ?>
						</button>
					</div>
				<?php endif; ?>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate embed form and return confirmation HTML
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function validate_embed_form( $request ) {
		$form_id = $request->get_param( 'form_id' );
		$data    = $this->parse_form_data( $request );

		if ( ! $form_id ) {
			return new WP_Error(
				'invalid_request',
				__( 'Invalid request', 'form-plant' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'invalid_request',
				__( 'Invalid data format', 'form-plant' ),
				array( 'status' => 400 )
			);
		}

		$form = FPLANT_Database::get_form( $form_id );

		if ( ! $form ) {
			return new WP_Error(
				'form_not_found',
				__( 'Form not found', 'form-plant' ),
				array( 'status' => 404 )
			);
		}

		// Check if JS embedding is allowed.
		if ( empty( $form['settings']['embed_js_enabled'] ) ) {
			return new WP_Error(
				'embed_not_allowed',
				__( 'JS embedding is not allowed for this form', 'form-plant' ),
				array( 'status' => 403 )
			);
		}

		// Check allowed URL list.
		$allowed_urls = $form['settings']['embed_js_allowed_urls'] ?? array();
		$origin       = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

		// If origin is empty, try to get it from referer (same-origin requests don't send Origin header)
		if ( empty( $origin ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer_origin = $this->get_origin_from_url( sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
			$site_origin    = $this->get_origin_from_url( home_url() );
			// Allow same-origin requests (from the WordPress site itself)
			if ( $referer_origin === $site_origin ) {
				$origin = $referer_origin;
			}
		}

		if ( ! empty( $allowed_urls ) ) {
			$allowed_origin = $this->get_allowed_origin( $origin, $allowed_urls );
			if ( ! $allowed_origin ) {
				return new WP_Error(
					'origin_not_allowed',
					__( 'Submissions from this domain are not allowed', 'form-plant' ),
					array( 'status' => 403 )
				);
			}
		}

		// Honeypot check
		if ( ! empty( $data['fplant_website_url'] ) ) {
			return new WP_Error(
				'validation_failed',
				__( 'Invalid request', 'form-plant' ),
				array( 'status' => 400 )
			);
		}

		// Validation.
		$validator         = new FPLANT_Validator();
		$validation_result = $validator->validate( $form['fields'], $data, $form_id );

		if ( ! $validation_result['valid'] ) {
			return new WP_Error(
				'validation_failed',
				__( 'There are errors in your input', 'form-plant' ),
				array(
					'status' => 400,
					'errors' => $validation_result['errors'],
				)
			);
		}

		// Get filenames from $_FILES for confirmation display.
		$fplant_filenames = array();
		foreach ( $form['fields'] as $fplant_field ) {
			if ( 'file' === $fplant_field['type'] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via REST API authentication, file name sanitized below.
				$file_input = isset( $_FILES[ $fplant_field['name'] ] ) ? $_FILES[ $fplant_field['name'] ] : null;
				if ( ! empty( $file_input['name'] ) ) {
					$fplant_filenames[ $fplant_field['name'] ] = sanitize_file_name( $file_input['name'] );
				}
			}
		}

		// Generate confirmation HTML.
		$field_manager     = new FPLANT_Field_Manager();
		$confirmation_html = $field_manager->render_confirmation( $form, $data, $fplant_filenames );

		return rest_ensure_response(
			array(
				'success'           => true,
				'confirmation_html' => $confirmation_html,
			)
		);
	}

	/**
	 * Submit embed form
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_embed_form( $request ) {
		$form_id = $request->get_param( 'form_id' );
		$data    = $this->parse_form_data( $request );

		if ( ! $form_id ) {
			return new WP_Error(
				'invalid_request',
				__( 'Invalid request', 'form-plant' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'invalid_request',
				__( 'Invalid data format', 'form-plant' ),
				array( 'status' => 400 )
			);
		}

		$form = FPLANT_Database::get_form( $form_id );

		if ( ! $form ) {
			return new WP_Error(
				'form_not_found',
				__( 'Form not found', 'form-plant' ),
				array( 'status' => 404 )
			);
		}

		// Check if JS embedding is allowed
		if ( empty( $form['settings']['embed_js_enabled'] ) ) {
			return new WP_Error(
				'embed_not_allowed',
				__( 'JS embedding is not allowed for this form', 'form-plant' ),
				array( 'status' => 403 )
			);
		}

		// Check allowed URL list
		$allowed_urls = $form['settings']['embed_js_allowed_urls'] ?? array();
		$origin       = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

		// If origin is empty, try to get it from referer (same-origin requests don't send Origin header)
		if ( empty( $origin ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer_origin = $this->get_origin_from_url( sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
			$site_origin    = $this->get_origin_from_url( home_url() );
			// Allow same-origin requests (from the WordPress site itself)
			if ( $referer_origin === $site_origin ) {
				$origin = $referer_origin;
			}
		}

		if ( ! empty( $allowed_urls ) ) {
			$allowed_origin = $this->get_allowed_origin( $origin, $allowed_urls );
			if ( ! $allowed_origin ) {
				return new WP_Error(
					'origin_not_allowed',
					__( 'Submissions from this domain are not allowed', 'form-plant' ),
					array( 'status' => 403 )
				);
			}
		}

		// Honeypot check
		if ( ! empty( $data['fplant_website_url'] ) ) {
			return new WP_Error(
				'validation_failed',
				__( 'Invalid request', 'form-plant' ),
				array( 'status' => 400 )
			);
		}

		// Verify reCAPTCHA
		if ( ! empty( $form['settings']['recaptcha_enabled'] ) ) {
			$recaptcha_token = $request->get_param( 'recaptcha_token' );
			$recaptcha_result = $this->verify_recaptcha( $form, $recaptcha_token );
			if ( is_wp_error( $recaptcha_result ) ) {
				return $recaptcha_result;
			}
		}

		// Handle file uploads (processes only expected file fields from form definition).
		$submission_manager = new FPLANT_Submission_Manager();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API endpoint, no nonce required
		if ( ! empty( $_FILES ) ) {
			$uploaded_files = $submission_manager->handle_file_uploads( $form_id );
			if ( is_wp_error( $uploaded_files ) ) {
				return new WP_Error(
					'file_upload_failed',
					$uploaded_files->get_error_message(),
					array( 'status' => 400 )
				);
			}
			// Merge uploaded file data into data for validation and submission.
			// File data must be an array {url, file, type, filename} for sanitize_submission_data().
			if ( is_array( $uploaded_files ) ) {
				foreach ( $uploaded_files as $field_name => $file_info ) {
					$data[ $field_name ] = $file_info;
				}
			}
		}

		// Remove honeypot data before processing
		unset( $data['fplant_website_url'] );

		// Validation
		$validator         = new FPLANT_Validator();
		$validation_result = $validator->validate( $form['fields'], $data, $form_id );

		if ( ! $validation_result['valid'] ) {
			return new WP_Error(
				'validation_failed',
				__( 'There are errors in your input', 'form-plant' ),
				array(
					'status' => 400,
					'errors' => $validation_result['errors'],
				)
			);
		}

		// Process submission (includes email sending)
		$result = $submission_manager->process_submission( $form_id, $data );

		if ( ! $result || is_wp_error( $result ) || empty( $result['success'] ) ) {
			$error_message = isset( $result['message'] ) ? $result['message'] : __( 'Submission processing failed', 'form-plant' );
			return new WP_Error(
				'submission_failed',
				$error_message,
				array( 'status' => 500 )
			);
		}

		// Build response based on action type.
		$settings    = $form['settings'] ?? array();
		$action_type = $settings['action_type'] ?? 'message';
		$response    = array(
			'success'     => true,
			'message'     => $result['message'] ?? __( 'Submission completed', 'form-plant' ),
			'action_type' => $action_type,
		);

		// Add complete page HTML if action type is custom_page.
		if ( 'custom_page' === $action_type && ! empty( $settings['success_page_html'] ) ) {
			$response['complete_html'] = wp_kses_post( $settings['success_page_html'] );
		}

		// Add redirect URL if action type is redirect.
		if ( 'redirect' === $action_type && ! empty( $settings['redirect_url'] ) ) {
			$response['redirect_url'] = esc_url( $settings['redirect_url'] );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Verify reCAPTCHA
	 *
	 * @param array  $form  Form data.
	 * @param string $token reCAPTCHA token.
	 * @return true|WP_Error
	 */
	private function verify_recaptcha( $form, $token ) {
		$secret_key = get_option( 'fplant_recaptcha_secret_key', '' );

		if ( empty( $secret_key ) ) {
			// Skip if Secret Key is not set
			return true;
		}

		if ( empty( $token ) ) {
			return new WP_Error(
				'recaptcha_missing',
				__( 'reCAPTCHA verification is required', 'form-plant' ),
				array( 'status' => 400 )
			);
		}

		// Verify with Google reCAPTCHA API
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => $this->get_client_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'recaptcha_error',
				__( 'An error occurred during reCAPTCHA verification', 'form-plant' ),
				array( 'status' => 500 )
			);
		}

		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( empty( $result['success'] ) ) {
			return new WP_Error(
				'recaptcha_failed',
				__( 'reCAPTCHA verification failed', 'form-plant' ),
				array( 'status' => 400 )
			);
		}

		// Check v3 score
		if ( isset( $result['score'] ) ) {
			$threshold = floatval( get_option( 'fplant_recaptcha_v3_threshold', 0.5 ) );
			if ( $result['score'] < $threshold ) {
				return new WP_Error(
					'recaptcha_score_low',
					__( 'Submission was blocked due to suspected spam activity', 'form-plant' ),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Parse form data from request
	 *
	 * When data is sent as FormData (multipart/form-data), the 'data' parameter
	 * is a JSON string that needs decoding. When sent as JSON, it's already an array.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|null Parsed data array, or null on failure.
	 */
	private function parse_form_data( $request ) {
		$data = $request->get_param( 'data' );
		$data = FPLANT_Form_Manager::sanitize_json_input( $data );
		if ( null === $data ) {
			return null;
		}
		return $data;
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Use first IP if comma-separated
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}
}
