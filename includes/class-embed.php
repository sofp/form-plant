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
class FPLANT_Embed {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_embed_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'render_embed' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	/**
	 * Register embed endpoint
	 */
	public function register_embed_endpoint() {
		add_rewrite_rule(
			'^fplant-embed/([0-9]+)/?$',
			'index.php?fplant_embed_form=$matches[1]',
			'top'
		);
	}

	/**
	 * Add query variables
	 *
	 * @param array $vars Array of query variables.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'fplant_embed_form';
		return $vars;
	}

	/**
	 * Render embed page
	 */
	public function render_embed() {
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
			<title><?php esc_html_e( 'エラー', 'form-plant' ); ?></title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
					display: flex;
					align-items: center;
					justify-content: center;
					min-height: 100vh;
					margin: 0;
					background-color: #f6f7f7;
				}
				.error-container {
					text-align: center;
					padding: 40px;
					background: #fff;
					border-radius: 8px;
					box-shadow: 0 2px 8px rgba(0,0,0,0.1);
				}
				.error-code {
					font-size: 48px;
					font-weight: bold;
					color: #d63638;
					margin-bottom: 10px;
				}
				.error-message {
					color: #3c434a;
					font-size: 16px;
				}
			</style>
		</head>
		<body>
			<div class="error-container">
				<div class="error-code"><?php echo esc_html( $status_code ); ?></div>
				<div class="error-message"><?php echo esc_html( $message ); ?></div>
			</div>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Load embed template
	 *
	 * @param array $form Form data.
	 */
	private function load_embed_template( $form ) {
		// Set variables to pass to template
		$form_id = $form['id'];
		$fields  = $form['fields'] ?? array();

		// Load template
		include FPLANT_PLUGIN_DIR . 'templates/embed.php';
	}

	/**
	 * Flush rewrite rules (called on plugin activation)
	 */
	public static function flush_rewrite_rules() {
		$embed = new self();
		$embed->register_embed_endpoint();
		flush_rewrite_rules();
	}
}
