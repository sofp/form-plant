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

		/**
		 * Filters whether to skip sending the admin email (MW's $Mail->to = false pattern).
		 *
		 * @since 1.2.0
		 * @param bool  $skip          Return true to skip sending. Default false.
		 * @param array $form          Form data.
		 * @param array $data          Submission data.
		 * @param int   $submission_id Submission ID.
		 */
		if ( apply_filters( 'fplant_skip_admin_email', false, $form, $data, $submission_id ) ) {
			return false;
		}

		// Filter: Data transformation before email send
		$data = apply_filters( 'fplant_before_send_email_data', $data, $form, $submission_id, 'admin' );

		// Recipient (replace tags like {admin_email} before parsing)
		$to_raw = self::replace_tags( $email_settings['to'], $data, $form, $submission_id );
		$to     = $this->parse_email_addresses( $to_raw );

		/**
		 * Filters the admin email recipient(s). Returning an empty value skips sending.
		 *
		 * MW WP Form's mwform_admin_mail ($Mail->to) equivalent.
		 *
		 * @since 1.2.0
		 * @param array $to      Recipient email addresses.
		 * @param int   $form_id Form ID.
		 * @param array $data    Submission data.
		 */
		$to = apply_filters( 'fplant_admin_email_to', $to, $form['id'], $data );
		if ( empty( $to ) ) {
			return false;
		}

		// Subject
		$subject = ! empty( $email_settings['subject'] )
			? self::replace_tags( $email_settings['subject'], $data, $form, $submission_id )
			: sprintf(
				/* translators: %s: form title */
				__( '[%s] New Inquiry', 'form-plant' ),
				$form['title']
			);

		/**
		 * Filters the admin notification email subject.
		 *
		 * @since 1.2.0
		 * @since 1.4.0 Added the $data parameter.
		 * @param string $subject Email subject.
		 * @param int    $form_id Form ID.
		 * @param array  $data    Submission data.
		 */
		$subject = apply_filters( 'fplant_admin_email_subject', $subject, $form['id'], $data );

		// Body
		$message = ! empty( $email_settings['body'] )
			? self::replace_tags( $email_settings['body'], $data, $form, $submission_id )
			: $this->generate_default_message( $data, $form, $submission_id );

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

		/**
		 * Filters the admin email headers (From / Cc / Bcc / Reply-To).
		 *
		 * MW WP Form's mwform_admin_mail ($Mail) equivalent for recipients/headers.
		 *
		 * @since 1.2.0
		 * @param array $headers Email headers.
		 * @param int   $form_id Form ID.
		 * @param array $data    Submission data.
		 */
		$headers = apply_filters( 'fplant_admin_email_headers', $headers, $form['id'], $data );

		// Prepare file attachments
		$attachments = $this->get_file_attachments( $data, $form );

		/**
		 * Filters the files attached to the admin notification email.
		 *
		 * Lets extensions attach the files their own field types collected
		 * (e.g. one per repeater row). Paths must be absolute and live inside
		 * wp-content/uploads/fplant_uploads/ — anything else is dropped by the
		 * containment check below, which runs after this filter.
		 *
		 * @since 1.5.1
		 * @param string[] $attachments   Absolute file paths.
		 * @param int      $form_id       Form ID.
		 * @param array    $data          Submission data.
		 * @param int      $submission_id Submission ID.
		 */
		$attachments = apply_filters( 'fplant_admin_email_attachments', $attachments, $form['id'], $data, $submission_id );

		// Last line of defense: never attach a file from outside the plugin's
		// own upload directory, whatever produced the path — including this
		// filter's callbacks.
		$attachments = self::filter_allowed_attachments( $attachments );

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

		/**
		 * Filters the auto-reply recipient address.
		 *
		 * MW WP Form's mwform_auto_mail ($Mail->to) equivalent.
		 *
		 * @since 1.2.0
		 * @param string $to      Recipient email address (from the form field).
		 * @param int    $form_id Form ID.
		 * @param array  $data    Submission data.
		 */
		$to = apply_filters( 'fplant_user_email_to', $to, $form['id'], $data );

		if ( ! is_email( $to ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
		error_log( 'Form Plant - User email: Invalid email address "' . $to . '"' );
			return false;
		}

		// Hook: Before email send
		do_action( 'fplant_before_user_email_send', $email_settings, $form['id'] );

		/**
		 * Filters whether to skip sending the auto-reply email.
		 *
		 * @since 1.2.0
		 * @param bool  $skip          Return true to skip sending. Default false.
		 * @param array $form          Form data.
		 * @param array $data          Submission data.
		 * @param int   $submission_id Submission ID.
		 */
		if ( apply_filters( 'fplant_skip_user_email', false, $form, $data, $submission_id ) ) {
			return false;
		}

		// Filter: Data transformation before email send
		$data = apply_filters( 'fplant_before_send_email_data', $data, $form, $submission_id, 'user' );

		// Subject
		$subject = ! empty( $email_settings['subject'] )
			? self::replace_tags( $email_settings['subject'], $data, $form, $submission_id )
			: __( 'Your inquiry has been received', 'form-plant' );

		/**
		 * Filters the auto-reply email subject.
		 *
		 * @since 1.2.0
		 * @since 1.4.0 Added the $data parameter.
		 * @param string $subject Email subject.
		 * @param int    $form_id Form ID.
		 * @param array  $data    Submission data.
		 */
		$subject = apply_filters( 'fplant_user_email_subject', $subject, $form['id'], $data );

		// Body
		$message = ! empty( $email_settings['body'] )
			? self::replace_tags( $email_settings['body'], $data, $form, $submission_id )
			: $this->generate_default_user_message( $data, $form, $submission_id );

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

		/**
		 * Filters the auto-reply email headers (From / Cc / Bcc / Reply-To).
		 *
		 * MW WP Form's mwform_auto_mail ($Mail) equivalent for headers.
		 *
		 * @since 1.2.0
		 * @param array $headers Email headers.
		 * @param int   $form_id Form ID.
		 * @param array $data    Submission data.
		 */
		$headers = apply_filters( 'fplant_user_email_headers', $headers, $form['id'], $data );

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
	 * Public since 1.5.0 so completion screens and the [fplant_complete]
	 * shortcode expand the same tags as emails.
	 *
	 * @since 1.5.0 Made public static; added the $args parameter.
	 * @param string $text          Text
	 * @param array  $data          Submission data
	 * @param array  $form          Form data
	 * @param int    $submission_id Submission ID
	 * @param array  $args          {
	 *     Optional. Rendering options.
	 *
	 *     @type string $escape        'none' (default, plain text / email) or 'html' (each inserted
	 *                                 value is esc_html()'d; {all_fields} also gets nl2br()).
	 *     @type bool   $mask_password Mask password field values regardless of the field setting.
	 *     @type string $context       Context passed to the fplant_display_fields filter for
	 *                                 {all_fields}: 'email' (default) or 'completion'.
	 * }
	 * @return string
	 */
	public static function replace_tags( $text, $data, $form, $submission_id = 0, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'escape'        => 'none',
				'mask_password' => false,
				'context'       => 'email',
			)
		);
		$escape        = ( 'html' === $args['escape'] );
		$mask_password = ! empty( $args['mask_password'] );
		$context       = is_string( $args['context'] ) && '' !== $args['context'] ? $args['context'] : 'email';
		$esc           = static function ( $value ) use ( $escape ) {
			return $escape ? esc_html( (string) $value ) : (string) $value;
		};

		// Process {all_fields} tag
		if ( strpos( $text, '{all_fields}' ) !== false ) {
			$all_fields_text = self::generate_all_fields_text( $data, $form, $mask_password, $context, $submission_id );
			$text            = str_replace( '{all_fields}', $escape ? nl2br( esc_html( $all_fields_text ) ) : $all_fields_text, $text );
		}

		// Process {field:fieldname} tag
		preg_match_all( '/\{field:([^\}]+)\}/', $text, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $field_name ) {
				$value = isset( $data[ $field_name ] ) ? $data[ $field_name ] : '';

				// Mask password / translate acceptance field values in email
				if ( ! empty( $form['fields'] ) && is_string( $value ) && ! empty( $value ) ) {
					foreach ( $form['fields'] as $f ) {
						if ( $f['name'] !== $field_name ) {
							continue;
						}
						if ( 'password' === $f['type'] && ( $mask_password || ! empty( $f['password_mask_email'] ) ) ) {
							$value = str_repeat( '*', max( mb_strlen( $value ), 8 ) );
						} elseif ( 'acceptance' === $f['type'] ) {
							$value = FPLANT_Field_Manager::acceptance_display_value();
						}
						break;
					}
				}

				// Use filename only for file fields
				if ( is_array( $value ) && isset( $value['filename'] ) ) {
					$value = $value['filename'];
				} elseif ( is_array( $value ) ) {
					// Flat and structured arrays share the plain-text boundary
					// (the field definition carries the delimiter / sub_fields).
					$field_def = array();
					if ( ! empty( $form['fields'] ) ) {
						foreach ( $form['fields'] as $f ) {
							if ( $f['name'] === $field_name ) {
								$field_def = $f;
								break;
							}
						}
					}
					$value = FPLANT_Field_Manager::format_submission_value( $value, $field_def, 'email_tag', (int) $form['id'], (int) $submission_id );
				}
				$text = str_replace( '{field:' . $field_name . '}', $esc( $value ), $text );
			}
		}

		// Replace field values (for backward compatibility)
		foreach ( $data as $key => $value ) {
			// Use filename only for file fields
			if ( is_array( $value ) && isset( $value['filename'] ) ) {
				$value = $value['filename'];
			} elseif ( is_array( $value ) ) {
				// Flat and structured arrays share the plain-text boundary.
				$field_def = array();
				if ( ! empty( $form['fields'] ) ) {
					foreach ( $form['fields'] as $f ) {
						if ( $f['name'] === $key ) {
							$field_def = $f;
							break;
						}
					}
				}
				$value = FPLANT_Field_Manager::format_submission_value( $value, $field_def, 'email_tag', (int) $form['id'], (int) $submission_id );
			} elseif ( ! empty( $value ) && ! empty( $form['fields'] ) ) {
				// Acceptance stores '1'; output the shared wording instead.
				// Passwords are masked per the field setting (or always on
				// completion screens).
				foreach ( $form['fields'] as $f ) {
					if ( $f['name'] !== $key ) {
						continue;
					}
					if ( 'acceptance' === $f['type'] ) {
						$value = FPLANT_Field_Manager::acceptance_display_value();
					} elseif ( 'password' === $f['type'] && is_string( $value ) && ( $mask_password || ! empty( $f['password_mask_email'] ) ) ) {
						$value = str_repeat( '*', max( mb_strlen( $value ), 8 ) );
					}
					break;
				}
			}
			$text = str_replace( '{' . $key . '}', $esc( $value ), $text );
		}

		// System tags
		$text = str_replace( '{form_title}', $esc( isset( $form['title'] ) ? $form['title'] : '' ), $text );
		$text = str_replace( '{submission_id}', $esc( $submission_id ), $text );
		$text = str_replace( '{submission_date}', current_time( 'Y-m-d H:i:s' ), $text );
		$text = str_replace( '{ip_address}', self::get_client_ip(), $text );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- User agent for logging, sanitized with esc_html in email content
		$text = str_replace( '{user_agent}', $esc( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ), $text );
		$text = str_replace( '{site_name}', $esc( get_bloginfo( 'name' ) ), $text );
		$text = str_replace( '{site_url}', $esc( home_url() ), $text );
		$text = str_replace( '{admin_email}', $esc( get_option( 'admin_email' ) ), $text );

		return $text;
	}

	/**
	 * Generate all fields text
	 *
	 * @param array  $data          Submission data
	 * @param array  $form          Form data
	 * @param bool   $mask_password Whether to mask password values
	 * @param string $context       Output context ( 'email' / 'completion' )
	 * @param int    $submission_id Submission ID ( 0 when unknown )
	 * @return string
	 */
	private static function generate_all_fields_text( $data, $form, $mask_password = false, $context = 'email', $submission_id = 0 ) {
		$lines = array();

		foreach ( self::get_display_fields( $data, $form, $context ) as $field ) {
			if ( 'hidden' === $field['type'] || FPLANT_Field_Manager::is_layout_type( $field['type'] ) ) {
				continue;
			}

			// Skip acceptance fields unless configured to appear in emails
			// (default OFF).
			if ( 'acceptance' === $field['type'] && empty( $field['acceptance_show_email'] ) ) {
				continue;
			}

			$value = isset( $data[ $field['name'] ] ) ? $data[ $field['name'] ] : '';

			// Mask password field value in email (always on completion screens)
			if ( 'password' === $field['type'] && ( $mask_password || ! empty( $field['password_mask_email'] ) ) && ! empty( $value ) && is_string( $value ) ) {
				$value = str_repeat( '*', max( mb_strlen( $value ), 8 ) );
			}

			// Acceptance stores '1'; output the shared wording instead.
			if ( 'acceptance' === $field['type'] && ! empty( $value ) ) {
				$value = FPLANT_Field_Manager::acceptance_display_value();
			}

			// Display filename only for file fields
			if ( 'file' === $field['type'] && is_array( $value ) && isset( $value['filename'] ) ) {
				$value = $value['filename'];
			} elseif ( is_array( $value ) ) {
				// Non-file arrays (checkbox, etc.)
				if ( isset( $value['url'] ) ) {
					// If file info array
					$value = isset( $value['filename'] ) ? $value['filename'] : '';
				} else {
					// Flat and structured arrays share the plain-text boundary.
					$value = FPLANT_Field_Manager::format_submission_value( $value, $field, 'email_all_fields', (int) $form['id'], (int) $submission_id );
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
	 * @param array $data          Submission data
	 * @param array $form          Form data
	 * @param int   $submission_id Submission ID ( 0 when unknown )
	 * @return string
	 */
	private function generate_default_message( $data, $form, $submission_id = 0 ) {
		$message = __( 'The following submission was received:', 'form-plant' ) . "\n\n";

		foreach ( self::get_display_fields( $data, $form ) as $field ) {
			if ( 'hidden' === $field['type'] || FPLANT_Field_Manager::is_layout_type( $field['type'] ) ) {
				continue;
			}

			// Skip acceptance fields unless configured to appear in emails
			// (default OFF).
			if ( 'acceptance' === $field['type'] && empty( $field['acceptance_show_email'] ) ) {
				continue;
			}

			$value = isset( $data[ $field['name'] ] ) ? $data[ $field['name'] ] : '';

			// Mask password field value in email
			if ( 'password' === $field['type'] && ! empty( $field['password_mask_email'] ) && ! empty( $value ) && is_string( $value ) ) {
				$value = str_repeat( '*', max( mb_strlen( $value ), 8 ) );
			}

			// Acceptance stores '1'; output the shared wording instead.
			if ( 'acceptance' === $field['type'] && ! empty( $value ) ) {
				$value = FPLANT_Field_Manager::acceptance_display_value();
			}

			if ( is_array( $value ) ) {
				// Flat and structured arrays share the plain-text boundary
				// (file-info arrays render their filename there too).
				$value = FPLANT_Field_Manager::format_submission_value( $value, $field, 'email_all_fields', (int) $form['id'], (int) $submission_id );
			}

			$label = $field['label'] ?? $field['name'];
			$message .= $label . ': ' . $value . "\n";
		}

		$message .= "\n---\n";
		/* translators: %s: submission date and time */
		$message .= sprintf( __( 'Submitted at: %s', 'form-plant' ), current_time( 'Y-m-d H:i:s' ) ) . "\n";
		/* translators: %s: IP address */
		$message .= sprintf( __( 'IP Address: %s', 'form-plant' ), self::get_client_ip() );

		return $message;
	}

	/**
	 * Generate default message (for user)
	 *
	 * @param array $data          Submission data
	 * @param array $form          Form data
	 * @param int   $submission_id Submission ID ( 0 when unknown )
	 * @return string
	 */
	private function generate_default_user_message( $data, $form, $submission_id = 0 ) {
		$message = __( 'Thank you for your inquiry.', 'form-plant' ) . "\n";
		$message .= __( 'We have received the following:', 'form-plant' ) . "\n\n";

		foreach ( self::get_display_fields( $data, $form ) as $field ) {
			if ( 'hidden' === $field['type'] || FPLANT_Field_Manager::is_layout_type( $field['type'] ) ) {
				continue;
			}

			// Skip acceptance fields unless configured to appear in emails
			// (default OFF).
			if ( 'acceptance' === $field['type'] && empty( $field['acceptance_show_email'] ) ) {
				continue;
			}

			$value = isset( $data[ $field['name'] ] ) ? $data[ $field['name'] ] : '';

			// Mask password field value in email
			if ( 'password' === $field['type'] && ! empty( $field['password_mask_email'] ) && ! empty( $value ) && is_string( $value ) ) {
				$value = str_repeat( '*', max( mb_strlen( $value ), 8 ) );
			}

			// Acceptance stores '1'; output the shared wording instead.
			if ( 'acceptance' === $field['type'] && ! empty( $value ) ) {
				$value = FPLANT_Field_Manager::acceptance_display_value();
			}

			if ( is_array( $value ) ) {
				// Flat and structured arrays share the plain-text boundary
				// (file-info arrays render their filename there too).
				$value = FPLANT_Field_Manager::format_submission_value( $value, $field, 'email_all_fields', (int) $form['id'], (int) $submission_id );
			}

			$label = $field['label'] ?? $field['name'];
			$message .= $label . ': ' . $value . "\n";
		}

		return $message;
	}

	/**
	 * Fields to list in email bodies ({all_fields} and the default messages).
	 *
	 * @since 1.5.0
	 * @param array  $data    Submission data.
	 * @param array  $form    Form data.
	 * @param string $context Filter context ('email' or 'completion').
	 * @return array Field definitions.
	 */
	private static function get_display_fields( $data, $form, $context = 'email' ) {
		/** This filter is documented in includes/class-submission-manager.php */
		$fields = apply_filters( 'fplant_display_fields', $form['fields'], $data, $form, $context );

		return is_array( $fields ) ? $fields : $form['fields'];
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
	private static function get_client_ip() {
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
	 * Keep only attachment paths inside the plugin's upload directory.
	 *
	 * Applies the same realpath() containment check as the submission-detail
	 * download handler, so neither a forged submission value nor a third-party
	 * filter can attach an arbitrary server-side file to an outgoing email.
	 *
	 * @since 1.5.1
	 * @param array $attachments Absolute file paths.
	 * @return array Paths that resolve inside uploads/fplant_uploads/.
	 */
	private static function filter_allowed_attachments( $attachments ) {
		if ( empty( $attachments ) || ! is_array( $attachments ) ) {
			return array();
		}

		$upload_dir   = wp_upload_dir();
		$real_allowed = realpath( $upload_dir['basedir'] . '/fplant_uploads' );
		if ( false === $real_allowed ) {
			return array();
		}
		$real_allowed = rtrim( $real_allowed, '/\\' ) . DIRECTORY_SEPARATOR;

		$allowed_attachments = array();

		foreach ( $attachments as $attachment ) {
			if ( ! is_string( $attachment ) || '' === $attachment ) {
				continue;
			}

			$real_path = realpath( $attachment );
			if ( false === $real_path || 0 !== strpos( $real_path, $real_allowed ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
				error_log( 'Form Plant - Attachment rejected (outside upload directory): ' . $attachment );
				continue;
			}

			$allowed_attachments[] = $attachment;
		}

		return $allowed_attachments;
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
