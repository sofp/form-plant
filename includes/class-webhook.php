<?php
/**
 * Webhook dispatcher class
 *
 * Implements the generic webhook feature: on completed submissions the form
 * data is POSTed as signed JSON to the destination URLs configured per form
 * (settings.webhooks). Fired after emails in
 * FPLANT_Submission_Manager::process_submission(); preview runs return before
 * that point, so previews never trigger webhooks.
 *
 * @package Form_Plant
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Webhook class
 */
class FPLANT_Webhook {

	/**
	 * Maximum number of webhooks per form.
	 */
	const MAX_WEBHOOKS = 3;

	/**
	 * Delay before the single automatic retry (seconds).
	 */
	const RETRY_DELAY = 60;

	/**
	 * Cron hook name used for the retry.
	 */
	const RETRY_HOOK = 'fplant_webhook_retry';

	/**
	 * HTTP timeout (seconds).
	 */
	const TIMEOUT = 5;

	/**
	 * Register hooks. Must run on every load (front and admin) so the
	 * scheduled retry event can always find its callback.
	 */
	public static function init() {
		add_action( self::RETRY_HOOK, array( __CLASS__, 'handle_retry' ), 10, 2 );
	}

	/**
	 * Sanitize the webhooks form setting (settings.webhooks).
	 *
	 * Called with the raw (unsanitized) rows from the save request; the
	 * generic recursive settings sanitizer is not sufficient for URLs and
	 * secrets, so this runs instead for this key.
	 *
	 * @param mixed $raw Raw webhook rows.
	 * @return array
	 */
	public static function sanitize_settings( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( array_values( $raw ) as $row ) {
			if ( count( $sanitized ) >= self::MAX_WEBHOOKS ) {
				break;
			}

			if ( ! is_array( $row ) ) {
				continue;
			}

			$url = isset( $row['url'] ) ? esc_url_raw( trim( (string) $row['url'] ) ) : '';

			// Rows without a URL are not persisted.
			if ( '' === $url ) {
				continue;
			}

			$secret = isset( $row['secret'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $row['secret'] ) : '';
			if ( '' === $secret ) {
				$secret = self::generate_secret();
			}

			$sanitized[] = array(
				'enabled' => ! empty( $row['enabled'] ),
				'url'     => $url,
				'secret'  => substr( $secret, 0, 64 ),
			);
		}

		return $sanitized;
	}

	/**
	 * Generate a new signing secret (32 hex chars).
	 *
	 * @return string
	 */
	public static function generate_secret() {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Whether a URL is an acceptable webhook destination.
	 *
	 * HTTPS only. Plain HTTP can be enabled for local development via the
	 * fplant_webhook_allow_http filter. Destination hosts are additionally
	 * restricted by wp_safe_remote_post() at send time (SSRF protection).
	 *
	 * @param string $url Destination URL.
	 * @return bool
	 */
	public static function is_url_allowed( $url ) {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		if ( 'https' === $scheme ) {
			return true;
		}

		if ( 'http' === $scheme ) {
			/**
			 * Filters whether plain-HTTP webhook destinations are allowed.
			 *
			 * Intended for local development only (e.g. a receiver container).
			 *
			 * @since 1.4.0
			 * @param bool   $allow Whether to allow http:// destinations. Default false.
			 * @param string $url   Destination URL.
			 */
			return (bool) apply_filters( 'fplant_webhook_allow_http', false, $url );
		}

		return false;
	}

	/**
	 * Dispatch all enabled webhooks for a completed submission.
	 *
	 * @param array $form           Form data (FPLANT_Database::get_form() shape).
	 * @param int   $form_id        Form ID.
	 * @param array $sanitized_data Sanitized submission values.
	 * @param int   $submission_id  Submission ID (0 when saving is disabled).
	 */
	public static function dispatch( $form, $form_id, $sanitized_data, $submission_id ) {
		$webhooks = self::get_active_webhooks( $form );

		if ( empty( $webhooks ) ) {
			return;
		}

		$payload = self::build_payload( $form, $form_id, $sanitized_data, $submission_id );
		$results = array();

		foreach ( $webhooks as $webhook ) {
			/**
			 * Filters whether a webhook should be sent for this submission.
			 *
			 * Extension point for conditional delivery (used by Pro's
			 * conditional logic).
			 *
			 * @since 1.4.0
			 * @param bool  $should_send    Whether to send. Default true.
			 * @param array $webhook        Webhook config (url, secret, enabled).
			 * @param array $payload        Payload to be sent.
			 * @param int   $form_id        Form ID.
			 * @param array $sanitized_data Sanitized submission values.
			 */
			if ( ! apply_filters( 'fplant_webhook_should_send', true, $webhook, $payload, $form_id, $sanitized_data ) ) {
				continue;
			}

			$result = self::deliver( $webhook, $payload, false );

			// Schedule the single retry. Requires a stored submission because
			// the retry rebuilds the payload from the saved data.
			if ( 'failed' === $result['status'] && $submission_id > 0 ) {
				wp_schedule_single_event(
					time() + self::RETRY_DELAY,
					self::RETRY_HOOK,
					array( (int) $submission_id, $webhook['url'] )
				);
			}

			$results[] = $result;
		}

		if ( $submission_id > 0 && ! empty( $results ) ) {
			FPLANT_Database::set_submission_extra( $submission_id, 'webhook_deliveries', $results );
		}
	}

	/**
	 * Cron callback: retry a failed delivery once.
	 *
	 * The payload is rebuilt from the stored submission (password masking and
	 * save filters already applied), so it can differ slightly from the
	 * original request body. The signature is recomputed accordingly.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $url           Destination URL that failed.
	 */
	public static function handle_retry( $submission_id, $url ) {
		$submission = FPLANT_Database::get_submission( $submission_id );

		if ( ! $submission ) {
			return;
		}

		$form = FPLANT_Database::get_form( $submission['form_id'] );

		if ( ! $form ) {
			return;
		}

		// Find the (still) matching, enabled webhook. Settings may have
		// changed since the original attempt; in that case the retry is dropped.
		$webhook = null;
		foreach ( self::get_active_webhooks( $form ) as $candidate ) {
			if ( $candidate['url'] === $url ) {
				$webhook = $candidate;
				break;
			}
		}

		if ( null === $webhook ) {
			return;
		}

		$payload = self::build_payload( $form, (int) $submission['form_id'], $submission['data'], $submission_id );
		$result  = self::deliver( $webhook, $payload, true );

		// Update the stored result for this URL.
		$deliveries = $submission['webhook_deliveries'];
		foreach ( $deliveries as $i => $delivery ) {
			if ( isset( $delivery['url'] ) && $delivery['url'] === $url ) {
				$deliveries[ $i ] = $result;
				break;
			}
		}

		FPLANT_Database::set_submission_extra( $submission_id, 'webhook_deliveries', $deliveries );
	}

	/**
	 * Send a manual test delivery (used by the settings screen).
	 *
	 * @param string $url     Destination URL.
	 * @param string $secret  Signing secret.
	 * @param int    $form_id Form ID (for the sample payload).
	 * @return array Delivery result.
	 */
	public static function send_test( $url, $secret, $form_id ) {
		$payload = array(
			'event'         => 'submission.test',
			'form_id'       => (int) $form_id,
			'form_title'    => get_the_title( $form_id ),
			'submission_id' => 0,
			'submitted_at'  => wp_date( 'c' ),
			'site_url'      => home_url(),
			'fields'        => array(
				array(
					'key'   => 'sample_field',
					'label' => __( 'Sample field', 'form-plant' ),
					'type'  => 'text',
					'value' => __( 'This is a test delivery from Form Plant.', 'form-plant' ),
				),
			),
		);

		return self::deliver(
			array(
				'url'    => $url,
				'secret' => $secret,
			),
			$payload,
			false,
			'submission.test'
		);
	}

	/**
	 * Build the delivery payload for a submission.
	 *
	 * @param array $form           Form data.
	 * @param int   $form_id        Form ID.
	 * @param array $sanitized_data Sanitized submission values.
	 * @param int   $submission_id  Submission ID (0 when saving is disabled).
	 * @return array
	 */
	public static function build_payload( $form, $form_id, $sanitized_data, $submission_id ) {
		$fields = array();

		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			$name = $field['name'] ?? '';
			$type = $field['type'] ?? '';

			// Non-input widgets never carry a value.
			if ( '' === $name || in_array( $type, array( 'submit', 'html' ), true ) ) {
				continue;
			}

			if ( ! array_key_exists( $name, $sanitized_data ) ) {
				continue;
			}

			$value = $sanitized_data[ $name ];

			// File uploads: expose the file name only. Upload URLs are not
			// included on purpose (they would be reachable by anyone who sees
			// the payload).
			if ( is_array( $value ) && isset( $value['filename'] ) ) {
				$value = $value['filename'];
			}

			$fields[] = array(
				'key'   => $name,
				'label' => $field['label'] ?? $name,
				'type'  => $type,
				'value' => $value,
			);
		}

		$payload = array(
			'event'         => 'submission.completed',
			'form_id'       => (int) $form_id,
			'form_title'    => $form['title'] ?? '',
			'submission_id' => (int) $submission_id,
			'submitted_at'  => wp_date( 'c' ),
			'site_url'      => home_url(),
			'fields'        => $fields,
		);

		/**
		 * Filters the webhook payload before it is sent.
		 *
		 * @since 1.4.0
		 * @param array $payload        Payload array (encoded to JSON afterwards).
		 * @param int   $form_id        Form ID.
		 * @param array $sanitized_data Sanitized submission values.
		 * @param int   $submission_id  Submission ID.
		 */
		return apply_filters( 'fplant_webhook_payload', $payload, $form_id, $sanitized_data, $submission_id );
	}

	/**
	 * Perform one HTTP delivery and normalize the result for storage.
	 *
	 * @param array  $webhook Webhook config (url, secret).
	 * @param array  $payload Payload array.
	 * @param bool   $retried Whether this is the automatic retry.
	 * @param string $event   Event header value.
	 * @return array {url, status, http_code, error, attempted_at, retried, delivery_id}
	 */
	private static function deliver( $webhook, $payload, $retried, $event = 'submission.completed' ) {
		$url         = $webhook['url'];
		$delivery_id = wp_generate_uuid4();
		$result      = array(
			'url'          => $url,
			'status'       => 'failed',
			'http_code'    => 0,
			'error'        => '',
			'attempted_at' => wp_date( 'c' ),
			'retried'      => (bool) $retried,
			'delivery_id'  => $delivery_id,
		);

		if ( ! self::is_url_allowed( $url ) ) {
			$result['error'] = 'url_not_allowed';
			return $result;
		}

		$body = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE );

		$args = array(
			'timeout'     => self::TIMEOUT,
			// Do not follow redirects: WordPress re-sends the POST (with body)
			// to the Location target on 301/302, which breaks GET-only result
			// pages — Google Apps Script web apps always respond with a 302 to
			// such a page after executing doPost. Delivery only needs the
			// endpoint to receive the payload, not the redirected content.
			'redirection' => 0,
			'body'        => $body,
			'user-agent'  => 'FormPlant-Webhook/' . FPLANT_VERSION,
			'headers'     => array(
				'Content-Type'        => 'application/json; charset=utf-8',
				'X-FPlant-Event'      => $event,
				'X-FPlant-Delivery'   => $delivery_id,
				'X-FPlant-Signature'  => 'sha256=' . self::sign( $webhook['secret'], $body ),
			),
		);

		/**
		 * Filters the wp_safe_remote_post() arguments for a webhook delivery.
		 *
		 * @since 1.4.0
		 * @param array  $args Request arguments.
		 * @param string $url  Destination URL.
		 */
		$args = apply_filters( 'fplant_webhook_request_args', $args, $url );

		// wp_safe_remote_post rejects loopback/private destinations (SSRF protection).
		$response = wp_safe_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			return $result;
		}

		$code                = (int) wp_remote_retrieve_response_code( $response );
		$result['http_code'] = $code;
		// 3xx counts as delivered: the endpoint received the payload and
		// answered with a redirect (Google Apps Script always 302s after
		// executing doPost). Redirects are not followed (see $args above).
		$result['status']    = ( $code >= 200 && $code < 400 ) ? 'ok' : 'failed';

		return $result;
	}

	/**
	 * Compute the request signature.
	 *
	 * @param string $secret Signing secret.
	 * @param string $body   Raw request body.
	 * @return string Hex HMAC-SHA256.
	 */
	public static function sign( $secret, $body ) {
		return hash_hmac( 'sha256', $body, $secret );
	}

	/**
	 * Enabled webhooks with an acceptable URL for a form.
	 *
	 * @param array $form Form data.
	 * @return array
	 */
	private static function get_active_webhooks( $form ) {
		$rows   = $form['settings']['webhooks'] ?? array();
		$active = array();

		if ( ! is_array( $rows ) ) {
			return $active;
		}

		foreach ( array_slice( $rows, 0, self::MAX_WEBHOOKS ) as $row ) {
			if ( empty( $row['enabled'] ) || empty( $row['url'] ) || empty( $row['secret'] ) ) {
				continue;
			}

			$active[] = array(
				'enabled' => true,
				'url'     => (string) $row['url'],
				'secret'  => (string) $row['secret'],
			);
		}

		return $active;
	}
}
