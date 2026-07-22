<?php
/**
 * Embed functionality class
 *
 * Handles iframe/JavaScript embedding from external sites
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embed functionality class
 */
class FPLANT_Embed extends FPLANT_Rewrite_Endpoint {

	/**
	 * Rewrite regex for the embed endpoint.
	 *
	 * @return string
	 */
	protected function rewrite_regex() {
		return '^fplant-embed/([0-9]+)/?$';
	}

	/**
	 * Query var for the embed endpoint.
	 *
	 * @return string
	 */
	protected function query_var() {
		return 'fplant_embed_form';
	}

	/**
	 * Render embed page
	 */
	public function maybe_render() {
		$form_id = get_query_var( 'fplant_embed_form' );

		if ( empty( $form_id ) ) {
			return;
		}

		$form_id = absint( $form_id );
		$form    = FPLANT_Database::get_form( $form_id );

		if ( ! $form ) {
			$this->send_error( 404, __( 'Form not found', 'form-plant' ) );
			return;
		}

		// Respect the form's publish status. Hide non-published forms from
		// visitors (only users who can edit the form may preview them).
		if ( ! FPLANT_Database::is_form_viewable( $form ) ) {
			$this->send_error( 404, __( 'Form not found', 'form-plant' ) );
			return;
		}

		// Check if iframe embedding is allowed
		if ( empty( $form['settings']['embed_iframe_enabled'] ) ) {
			$this->send_error( 403, __( 'Iframe embedding is not allowed for this form', 'form-plant' ) );
			return;
		}

		// Check allowed URL list
		$allowed_urls = $form['settings']['embed_iframe_allowed_urls'] ?? array();
		if ( ! empty( $allowed_urls ) ) {
			$referer    = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
			$is_allowed = $this->is_url_allowed( $referer, $allowed_urls );

			if ( ! $is_allowed ) {
				$this->send_error( 403, __( 'Embedding from this domain is not allowed', 'form-plant' ) );
				return;
			}

			// Set Content-Security-Policy: frame-ancestors header
			$frame_ancestors = $this->build_frame_ancestors( $allowed_urls );
			header( 'Content-Security-Policy: frame-ancestors ' . $frame_ancestors );
		}

		// Load template
		$this->load_embed_template( $form );
		exit;
	}

	/**
	 * Check if URL is in allowed list
	 *
	 * @param string $url          URL to check.
	 * @param array  $allowed_urls Allowed URL list.
	 * @return bool
	 */
	private function is_url_allowed( $url, $allowed_urls ) {
		if ( empty( $url ) || empty( $allowed_urls ) ) {
			return false;
		}

		foreach ( $allowed_urls as $allowed_url ) {
			$allowed_url = trim( $allowed_url );
			if ( empty( $allowed_url ) ) {
				continue;
			}

			// Compare origins
			$url_origin     = $this->get_origin( $url );
			$allowed_origin = $this->get_origin( $allowed_url );

			if ( $url_origin === $allowed_origin ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get origin from URL
	 *
	 * @param string $url URL.
	 * @return string Origin (scheme://host[:port]).
	 */
	private function get_origin( $url ) {
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
	 * Build frame-ancestors value
	 *
	 * @param array $allowed_urls Allowed URL list.
	 * @return string
	 */
	private function build_frame_ancestors( $allowed_urls ) {
		$origins = array( "'self'" );

		foreach ( $allowed_urls as $allowed_url ) {
			$allowed_url = trim( $allowed_url );
			if ( empty( $allowed_url ) ) {
				continue;
			}

			$origin = $this->get_origin( $allowed_url );
			if ( ! empty( $origin ) && ! in_array( $origin, $origins, true ) ) {
				$origins[] = $origin;
			}
		}

		return implode( ' ', $origins );
	}

	/**
	 * Send error response
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $message     Error message.
	 */
	private function send_error( $status_code, $message ) {
		status_header( $status_code );
		header( 'Content-Type: text/html; charset=UTF-8' );
		?>
		<!DOCTYPE html>
		<html lang="ja">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Error', 'form-plant' ); ?></title>
		</head>
		<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background-color: #f6f7f7;">
			<div style="text-align: center; padding: 40px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
				<div style="font-size: 48px; font-weight: bold; color: #d63638; margin-bottom: 10px;"><?php echo esc_html( $status_code ); ?></div>
				<div style="color: #3c434a; font-size: 16px;"><?php echo esc_html( $message ); ?></div>
			</div>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Enqueue embed scripts and styles
	 *
	 * @param array $form Form data.
	 */
	private function enqueue_embed_assets( $form ) {
		$form_id  = $form['id'];
		$settings = $form['settings'] ?? array();
		$fields   = $form['fields'] ?? array();

		// Design CSS
		$fplant_embed_design = $settings['design_type'] ?? 'simple1';
		// Backward compatibility: 'default' maps to 'simple1'
		if ( 'default' === $fplant_embed_design ) {
			$fplant_embed_design = 'simple1';
		}

		// simple1 uses form.css; simple2/normal use self-contained design CSS
		$load_default_css = false;
		if ( 'simple1' === $fplant_embed_design ) {
			$load_default_css = true;
			wp_enqueue_style( 'fplant-form', FPLANT_PLUGIN_URL . 'assets/css/form.css', array(), FPLANT_VERSION );
		} elseif ( in_array( $fplant_embed_design, array( 'simple2', 'normal' ), true ) ) {
			wp_enqueue_style(
				'fplant-design-' . $fplant_embed_design,
				FPLANT_PLUGIN_URL . 'assets/css/design-' . $fplant_embed_design . '.css',
				array(),
				FPLANT_VERSION
			);
		}

		// Custom CSS files (multiple)
		$custom_css_file_urls = array();
		if ( ! empty( $settings['custom_css_file_urls'] ) && is_array( $settings['custom_css_file_urls'] ) ) {
			$custom_css_file_urls = $settings['custom_css_file_urls'];
		} elseif ( ! empty( $settings['custom_css_file_url'] ) ) {
			// Backward compatibility: single URL to array
			$custom_css_file_urls = array( $settings['custom_css_file_url'] );
		}

		$inline_css_handle = $load_default_css ? 'fplant-form' : '';
		$css_idx           = 0;
		foreach ( $custom_css_file_urls as $css_url ) {
			if ( ! empty( $css_url ) ) {
				$handle = 'fplant-embed-custom-css-' . $css_idx;
				wp_enqueue_style( $handle, $css_url, array(), FPLANT_VERSION );
				$inline_css_handle = $handle;
				$css_idx++;
			}
		}

		// Design adjustments CSS (before custom inline CSS so the user's CSS comes later)
		if ( 'none' !== $fplant_embed_design ) {
			$fplant_design_css = FPLANT_Design_Options::build_css(
				'#fplant-form-' . absint( $form_id ),
				$settings['design_options'] ?? array()
			);
			if ( '' !== $fplant_design_css ) {
				if ( empty( $inline_css_handle ) ) {
					// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Intentionally no version for inline-only style
					wp_register_style( 'fplant-embed-inline', false );
					wp_enqueue_style( 'fplant-embed-inline' );
					$inline_css_handle = 'fplant-embed-inline';
				}
				wp_add_inline_style( $inline_css_handle, $fplant_design_css );
			}
		}

		// Custom CSS inline
		$custom_css_inline = $settings['custom_css_inline'] ?? '';
		if ( ! empty( $custom_css_inline ) ) {
			$plugin = FPLANT_Form_Plant::get_instance();
			if ( empty( $inline_css_handle ) ) {
				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Intentionally no version for inline-only style
				wp_register_style( 'fplant-embed-inline', false );
				wp_enqueue_style( 'fplant-embed-inline' );
				$inline_css_handle = 'fplant-embed-inline';
			}
			wp_add_inline_style( $inline_css_handle, $plugin->sanitize_css( $custom_css_inline ) );
		}

		// Embed base CSS (margin/padding reset, transparent background)
		$embed_css = 'html, body { margin: 0; padding: 0; background: transparent; }'
			. ' .fplant-form-wrapper { max-width: 100%; padding: 20px; box-sizing: border-box; }';
		$base_handle = $load_default_css ? 'fplant-form' : ( ! empty( $inline_css_handle ) ? $inline_css_handle : '' );
		if ( empty( $base_handle ) ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Intentionally no version for inline-only style
			wp_register_style( 'fplant-embed-base', false );
			wp_enqueue_style( 'fplant-embed-base' );
			$base_handle = 'fplant-embed-base';
		}
		wp_add_inline_style( $base_handle, $embed_css );

		// CAPTCHA
		$captcha_type       = $settings['captcha_type'] ?? 'none';
		// Backward compatibility
		if ( 'none' === $captcha_type && ! empty( $settings['recaptcha_enabled'] ) ) {
			$captcha_type = 'recaptcha';
		}

		$recaptcha_site_key = get_option( 'fplant_recaptcha_site_key', '' );
		$turnstile_site_key = get_option( 'fplant_turnstile_site_key', '' );

		// If the site key is empty, fall back to type "none".
		if ( 'recaptcha' === $captcha_type && empty( $recaptcha_site_key ) ) {
			$captcha_type = 'none';
		}
		if ( 'turnstile' === $captcha_type && empty( $turnstile_site_key ) ) {
			$captcha_type = 'none';
		}

		// phpcs:disable PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- External CAPTCHA services, cannot be bundled locally
		if ( 'recaptcha' === $captcha_type ) {
			wp_enqueue_script(
				'fplant-recaptcha',
				'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha_site_key ),
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External script, version managed by Google
				array( 'in_footer' => false )
			);
		} elseif ( 'turnstile' === $captcha_type ) {
			wp_enqueue_script(
				'cloudflare-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External script, version managed by Cloudflare
				array( 'in_footer' => true )
			);
		}
		// phpcs:enable PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent

		// form.js
		wp_enqueue_script( 'fplant-form', FPLANT_PLUGIN_URL . 'assets/js/form.js', array(), FPLANT_VERSION, true );

		// fplantData (localized script data)
		$use_confirmation = ! empty( $settings['use_confirmation'] );
		$nonce            = wp_create_nonce( 'fplant_form_nonce' );

		wp_localize_script(
			'fplant-form',
			'fplantData',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'restUrl'         => rest_url( 'form-plant/v1/' ),
				'formId'          => (int) $form_id,
				'nonce'           => $nonce,
				'useConfirmation' => $use_confirmation,
				'embedMode'       => true,
				'settings'        => $settings,
				'fields'          => $fields,
				'i18n'            => array(
					'validationError'     => __( 'There are errors in your input', 'form-plant' ),
					'requiredCheckbox'    => __( 'This field is required. Please select at least one option.', 'form-plant' ),
					'requiredAcceptance'  => __( 'You must agree before submitting.', 'form-plant' ),
					'agreed'              => __( 'Agreed', 'form-plant' ),
					'requiredRadio'       => __( 'This field is required. Please make a selection.', 'form-plant' ),
					'requiredSelect'      => __( 'This field is required. Please make a selection.', 'form-plant' ),
					'requiredFile'        => __( 'This field is required. Please select a file.', 'form-plant' ),
					'requiredText'        => __( 'This field is required. Please enter a value.', 'form-plant' ),
					/* translators: %s: sub-field label (e.g., Last Name, First Name) */
					'requiredSubField'    => __( '%s is required', 'form-plant' ),
					/* translators: %s: Maximum file size in megabytes */
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

		// fplantCaptchaConfig (inline script)
		$captcha_config_js = 'if (typeof window.fplantCaptchaConfig === "undefined") { window.fplantCaptchaConfig = {}; }' . "\n"
			. 'window.fplantCaptchaConfig[' . (int) $form_id . '] = '
			. wp_json_encode(
				array(
					'type'             => $captcha_type,
					'recaptchaSiteKey' => $recaptcha_site_key,
					'turnstileSiteKey' => $turnstile_site_key,
				),
				JSON_HEX_TAG
			) . ';';
		wp_add_inline_script( 'fplant-form', $captcha_config_js, 'before' );

		// fplantFieldsConfig (inline script)
		$fields_config_js = 'if (typeof window.fplantFieldsConfig === "undefined") { window.fplantFieldsConfig = {}; }'
			. "\nwindow.fplantFieldsConfig[" . (int) $form_id . '] = ' . wp_json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . ';';
		wp_add_inline_script( 'fplant-form', $fields_config_js, 'before' );
	}

	/**
	 * Load embed template
	 *
	 * @param array $form Form data.
	 */
	private function load_embed_template( $form ) {
		// Enqueue assets before loading template
		$this->enqueue_embed_assets( $form );

		// Set variables to pass to template
		$form_id = $form['id'];
		$fields  = $form['fields'] ?? array();

		// Load template
		include FPLANT_PLUGIN_DIR . 'templates/embed.php';
	}
}
