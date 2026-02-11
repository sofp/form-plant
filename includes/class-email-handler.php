<?php
/**
 * Email handler class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Email_Handler class
 */
class FPLANT_Email_Handler {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Record email log in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'wp_mail_failed', array( $this, 'log_mail_error' ) );
		}
	}

	/**
	 * Log email send error
	 *
	 * @param WP_Error $error Error object
	 */
	public function log_mail_error( $error ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - Email send error: ' . $error->get_error_message() );
	}

	/**
	 * Send admin notification email
	 *
	 * @param array $form          Form data
	 * @param array $data          Submission data
	 * @param int   $submission_id Submission ID
	 * @return bool
	 */
	public function send_admin_email( $form, $data, $submission_id = 0 ) {
		$email_settings = $form['email_admin'];

		// Skip if not enabled
		if ( empty( $email_settings['enabled'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - Admin email: Skipped (disabled)' );
			return false;
		}

		if ( empty( $email_settings['to'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - Admin email: Recipient address not set' );
			return false;
		}

		// Hook: Before email send
		do_action( 'fplant_before_admin_email_send', $email_settings, $form['id'] );

		// Filter: Data transformation before email send
		$data = apply_filters( 'fplant_before_send_email_data', $data, $form, $submission_id, 'admin' );

		// Recipient
		$to = $this->parse_email_addresses( $email_settings['to'] );

		// Subject
		$subject = ! empty( $email_settings['subject'] )
			? $this->replace_tags( $email_settings['subject'], $data, $form, $submission_id )
			: sprintf(
				/* translators: %s: form title */
				__( '[%s] New Inquiry', 'form-plant' ),
				$form['title']
			);

		// Apply filter
		$subject = apply_filters( 'fplant_admin_email_subject', $subject, $form['id'] );

		// Body
		$message = ! empty( $email_settings['body'] )
			? $this->replace_tags( $email_settings['body'], $data, $form, $submission_id )
			: $this->generate_default_message( $data, $form );

		// Apply filter
		$message = apply_filters( 'fplant_admin_email_body', $message, $form['id'], $data );

		// Headers
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		// Set sender
		if ( ! empty( $email_settings['from_email'] ) ) {
			$from_name  = ! empty( $email_settings['from_name'] ) ? $email_settings['from_name'] : get_bloginfo( 'name' );
			$from_email = $email_settings['from_email'];
			$headers[]  = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		// Set CC
		if ( ! empty( $email_settings['cc'] ) ) {
			$cc_addresses = $this->parse_email_addresses( $email_settings['cc'] );
			foreach ( $cc_addresses as $cc_email ) {
				$headers[] = 'Cc: ' . $cc_email;
			}
		}

		// Set BCC
		if ( ! empty( $email_settings['bcc'] ) ) {
			$bcc_addresses = $this->parse_email_addresses( $email_settings['bcc'] );
			foreach ( $bcc_addresses as $bcc_email ) {
				$headers[] = 'Bcc: ' . $bcc_email;
			}
		}

		// Set Reply-To
		if ( ! empty( $email_settings['reply_to'] ) ) {
			$reply_to = sanitize_email( $email_settings['reply_to'] );
			if ( is_email( $reply_to ) ) {
				$headers[] = 'Reply-To: ' . $reply_to;
			}
		}

		// Prepare file attachments
		$attachments = $this->get_file_attachments( $data, $form );

		// Send
		$result = wp_mail( $to, $subject, $message, $headers, $attachments );

		// Log
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - Admin email sent successfully: ' . implode( ', ', (array) $to ) . ' / Subject: ' . $subject );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - Admin email send failed: ' . implode( ', ', (array) $to ) . ' / Subject: ' . $subject );
			}
		}

		// Hook: After email send
		do_action( 'fplant_after_admin_email_send', $email_settings, $form['id'], $result );

		return $result;
	}

	/**
	 * Send auto-reply email
	 *
	 * @param array $form          Form data
	 * @param array $data          Submission data
	 * @param int   $submission_id Submission ID
	 * @return bool
	 */
	public function send_user_email( $form, $data, $submission_id = 0 ) {
		$email_settings = $form['email_user'];

		// Skip if not enabled
		if ( empty( $email_settings['enabled'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - User email: Skipped (disabled)' );
			return false;
		}

		// Recipient field
		$to_field = ! empty( $email_settings['to_field'] ) ? $email_settings['to_field'] : 'email';

		if ( empty( $data[ $to_field ] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - User email: Recipient field "' . $to_field . '" is empty' );
			return false;
		}

		$to = sanitize_email( $data[ $to_field ] );

		if ( ! is_email( $to ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - User email: Invalid email address "' . $to . '"' );
			return false;
		}

		// Hook: Before email send
		do_action( 'fplant_before_user_email_send', $email_settings, $form['id'] );

		// Filter: Data transformation before email send
		$data = apply_filters( 'fplant_before_send_email_data', $data, $form, $submission_id, 'user' );

		// Subject
		$subject = ! empty( $email_settings['subject'] )
			? $this->replace_tags( $email_settings['subject'], $data, $form, $submission_id )
			: __( 'Your inquiry has been received', 'form-plant' );

		// Apply filter
		$subject = apply_filters( 'fplant_user_email_subject', $subject, $form['id'] );

		// Body
		$message = ! empty( $email_settings['body'] )
			? $this->replace_tags( $email_settings['body'], $data, $form, $submission_id )
			: $this->generate_default_user_message( $data, $form );

		// Apply filter
		$message = apply_filters( 'fplant_user_email_body', $message, $form['id'], $data );

		// Headers
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		// Set sender
		if ( ! empty( $email_settings['from_email'] ) ) {
			$from_name  = ! empty( $email_settings['from_name'] ) ? $email_settings['from_name'] : get_bloginfo( 'name' );
			$from_email = $email_settings['from_email'];
			$headers[]  = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		// Set CC
		if ( ! empty( $email_settings['cc'] ) ) {
			$cc_addresses = $this->parse_email_addresses( $email_settings['cc'] );
			foreach ( $cc_addresses as $cc_email ) {
				$headers[] = 'Cc: ' . $cc_email;
			}
		}

		// Set BCC
		if ( ! empty( $email_settings['bcc'] ) ) {
			$bcc_addresses = $this->parse_email_addresses( $email_settings['bcc'] );
			foreach ( $bcc_addresses as $bcc_email ) {
				$headers[] = 'Bcc: ' . $bcc_email;
			}
		}

		// Set Reply-To
		if ( ! empty( $email_settings['reply_to'] ) ) {
			$reply_to = sanitize_email( $email_settings['reply_to'] );
			if ( is_email( $reply_to ) ) {
				$headers[] = 'Reply-To: ' . $reply_to;
			}
		}

		// Send
		$result = wp_mail( $to, $subject, $message, $headers );

		// Log
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - User email sent successfully: ' . $to . ' / Subject: ' . $subject );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - User email send failed: ' . $to . ' / Subject: ' . $subject );
			}
		}

		// Hook: After email send
		do_action( 'fplant_after_user_email_send', $email_settings, $form['id'], $result );

		return $result;
	}

	/**
	 * Replace tags
	 *
	 * @param string $text          Text
	 * @param array  $data          Submission data
	 * @param array  $form          Form data
	 * @param int    $submission_id Submission ID
	 * @return string
	 */
	private function replace_tags( $text, $data, $form, $submission_id = 0 ) {
		// Process {all_fields} tag
		if ( strpos( $text, '{all_fields}' ) !== false ) {
			$all_fields_text = $this->generate_all_fields_text( $data, $form );
			$text            = str_replace( '{all_fields}', $all_fields_text, $text );
		}

		// Process {field:fieldname} tag
		preg_match_all( '/\{field:([^\}]+)\}/', $text, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $field_name ) {
				$value = isset( $data[ $field_name ] ) ? $data[ $field_name ] : '';
				// Use filename only for file fields
				if ( is_array( $value ) && isset( $value['filename'] ) ) {
					$value = $value['filename'];
				} elseif ( is_array( $value ) ) {
					// Search for field delimiter setting
					$delimiter = ', ';
					if ( ! empty( $form['fields'] ) ) {
						foreach ( $form['fields'] as $f ) {
							if ( $f['name'] === $field_name && isset( $f['delimiter'] ) ) {
								$delimiter = $f['delimiter'];
								break;
							}
						}
					}
					$value = implode( $delimiter, $value );
				}
				$text = str_replace( '{field:' . $field_name . '}', esc_html( $value ), $text );
			}
		}

		// Replace field values (for backward compatibility)
		foreach ( $data as $key => $value ) {
			// Use filename only for file fields
			if ( is_array( $value ) && isset( $value['filename'] ) ) {
				$value = $value['filename'];
			} elseif ( is_array( $value ) ) {
				// Search for field delimiter setting
				$delimiter = ', ';
				if ( ! empty( $form['fields'] ) ) {
					foreach ( $form['fields'] as $f ) {
						if ( $f['name'] === $key && isset( $f['delimiter'] ) ) {
							$delimiter = $f['delimiter'];
							break;
						}
					}
				}
				$value = implode( $delimiter, $value );
			}
			$text = str_replace( '{' . $key . '}', esc_html( $value ), $text );
		}

		// System tags
		$text = str_replace( '{form_title}', esc_html( $form['title'] ), $text );
		$text = str_replace( '{submission_id}', $submission_id, $text );
		$text = str_replace( '{submission_date}', current_time( 'Y-m-d H:i:s' ), $text );
		$text = str_replace( '{ip_address}', $this->get_client_ip(), $text );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- User agent for logging, sanitized with esc_html in email content
		$text = str_replace( '{user_agent}', isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '', $text );
		$text = str_replace( '{site_name}', get_bloginfo( 'name' ), $text );
		$text = str_replace( '{site_url}', home_url(), $text );

		return $text;
	}

	/**
	 * Generate all fields text
	 *
	 * @param array $data Submission data
	 * @param array $form Form data
	 * @return string
	 */
	private function generate_all_fields_text( $data, $form ) {
		$lines = array();

		foreach ( $form['fields'] as $field ) {
			if ( in_array( $field['type'], array( 'html', 'hidden' ), true ) ) {
				continue;
			}

			$value = isset( $data[ $field['name'] ] ) ? $data[ $field['name'] ] : '';

			// Display filename only for file fields
			if ( 'file' === $field['type'] && is_array( $value ) && isset( $value['filename'] ) ) {
				$value = $value['filename'];
			} elseif ( is_array( $value ) ) {
				// Non-file arrays (checkbox, etc.)
				if ( isset( $value['url'] ) ) {
					// If file info array
					$value = isset( $value['filename'] ) ? $value['filename'] : '';
				} else {
					$delimiter = isset( $field['delimiter'] ) ? $field['delimiter'] : ', ';
					$value     = implode( $delimiter, $value );
				}
			}

			$label = $field['label'] ?? $field['name'];
			$lines[] = $label . ': ' . $value;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Generate default message (for admin)
	 *
	 * @param array $data Submission data
	 * @param array $form Form data
	 * @return string
	 */
	private function generate_default_message( $data, $form ) {
		$message = __( 'The following submission was received:', 'form-plant' ) . "\n\n";

		foreach ( $form['fields'] as $field ) {
			if ( 'html' === $field['type'] || 'hidden' === $field['type'] ) {
				continue;
			}

			$value = isset( $data[ $field['name'] ] ) ? $data[ $field['name'] ] : '';

			if ( is_array( $value ) ) {
				$delimiter = isset( $field['delimiter'] ) ? $field['delimiter'] : ', ';
				$value     = implode( $delimiter, $value );
			}

			$label = $field['label'] ?? $field['name'];
			$message .= $label . ': ' . $value . "\n";
		}

		$message .= "\n---\n";
		/* translators: %s: submission date and time */
		$message .= sprintf( __( 'Submitted at: %s', 'form-plant' ), current_time( 'Y-m-d H:i:s' ) ) . "\n";
		/* translators: %s: IP address */
		$message .= sprintf( __( 'IP Address: %s', 'form-plant' ), $this->get_client_ip() );

		return $message;
	}

	/**
	 * Generate default message (for user)
	 *
	 * @param array $data Submission data
	 * @param array $form Form data
	 * @return string
	 */
	private function generate_default_user_message( $data, $form ) {
		$message = __( 'Thank you for your inquiry.', 'form-plant' ) . "\n";
		$message .= __( 'We have received the following:', 'form-plant' ) . "\n\n";

		foreach ( $form['fields'] as $field ) {
			if ( 'html' === $field['type'] || 'hidden' === $field['type'] ) {
				continue;
			}

			$value = isset( $data[ $field['name'] ] ) ? $data[ $field['name'] ] : '';

			if ( is_array( $value ) ) {
				$delimiter = isset( $field['delimiter'] ) ? $field['delimiter'] : ', ';
				$value     = implode( $delimiter, $value );
			}

			$label = $field['label'] ?? $field['name'];
			$message .= $label . ': ' . $value . "\n";
		}

		return $message;
	}

	/**
	 * Parse email addresses
	 *
	 * @param string $addresses Email addresses (comma-separated allowed)
	 * @return array
	 */
	private function parse_email_addresses( $addresses ) {
		$emails = array_map( 'trim', explode( ',', $addresses ) );
		$emails = array_filter( $emails, 'is_email' );

		return $emails;
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
	 * Get file attachments
	 *
	 * @param array $data Submission data
	 * @param array $form Form data
	 * @return array Array of file paths
	 */
	private function get_file_attachments( $data, $form ) {
		$attachments = array();

		// Loop through field settings to find file fields
		foreach ( $form['fields'] as $field ) {
			if ( 'file' !== $field['type'] ) {
				continue;
			}

			$field_name = $field['name'];
			if ( ! isset( $data[ $field_name ] ) ) {
				continue;
			}

			$file_data = $data[ $field_name ];

			// If file info is in array format and file path exists
			if ( is_array( $file_data ) && isset( $file_data['file'] ) && file_exists( $file_data['file'] ) ) {
				$attachments[] = $file_data['file'];
			}
		}

		return $attachments;
	}
}
