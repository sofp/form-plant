<?php
/**
 * Validation class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Validator class
 */
class FPLANT_Validator {

	/**
	 * Validate form data
	 *
	 * @param array $fields  Field configuration
	 * @param array $data    Submission data
	 * @param int   $form_id Form ID
	 * @return array
	 */
	public function validate( $fields, $data, $form_id = 0 ) {
		$errors = array();

		// Hook: Data processing before validation
		$data = apply_filters( 'fplant_before_validation', $data, $fields, $form_id );

		foreach ( $fields as $field ) {
			// Skip HTML fields
			if ( 'html' === $field['type'] ) {
				continue;
			}

			$field_name = $field['name'];
			$value      = isset( $data[ $field_name ] ) ? $data[ $field_name ] : '';

			// Acceptance fields are always required regardless of the stored
			// required flag: consent must be explicit on every submission.
			if ( 'acceptance' === $field['type'] ) {
				if ( empty( $value ) || '0' === $value ) {
					$message = ! empty( $field['validation_message'] )
						? $field['validation_message']
						: __( 'You must agree before submitting.', 'form-plant' );

					/**
					 * Filters the validation message for an unchecked acceptance field.
					 *
					 * Follows the fplant_validation_message_{type} convention.
					 *
					 * @since 1.4.0
					 * @param string $message Validation message.
					 * @param array  $field   Field configuration.
					 * @param mixed  $value   Submitted value.
					 * @param array  $context Context (type of failure).
					 */
					$errors[ $field_name ] = apply_filters( 'fplant_validation_message_acceptance', $message, $field, $value, array( 'type' => 'required' ) );
				}
				continue;
			}

			// Required check
			if ( ! empty( $field['required'] ) ) {
				$is_empty = false;

				// Check array for checkbox/radio fields
				if ( in_array( $field['type'], array( 'checkbox', 'radio' ), true ) ) {
					$is_empty = empty( $value ) || ( is_array( $value ) && count( $value ) === 0 );
				} elseif ( 'file' === $field['type'] ) {
					// Check $_FILES for file fields
					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
					$file_input = isset( $_FILES[ $field_name ] ) ? $_FILES[ $field_name ] : null;
					$is_empty   = empty( $file_input ) || empty( $file_input['name'] ) || UPLOAD_ERR_NO_FILE === intval( $file_input['error'] ?? UPLOAD_ERR_NO_FILE );
				} elseif ( 'address' === $field['type'] ) {
					// Check required sub-fields for address composite field individually
					$addr_is_ja    = ( 0 === strpos( get_locale(), 'ja' ) );
					$addr_required = $addr_is_ja
						? array( 'postal_code', 'prefecture', 'city', 'street' )
						: array( 'street', 'city', 'postal_code', 'country' );
					$addr_labels   = isset( $field['address_labels'] ) ? $field['address_labels'] : array();
					$addr_val_msgs = isset( $field['address_validation_messages'] ) ? $field['address_validation_messages'] : array();
					$addr_has_error = false;
					foreach ( $addr_required as $addr_sub ) {
						$addr_key = $field_name . '_' . $addr_sub;
						if ( empty( $data[ $addr_key ] ) ) {
							$sub_label   = ! empty( $addr_labels[ $addr_sub ] ) ? $addr_labels[ $addr_sub ] : $addr_sub;
							$sub_message = ! empty( $addr_val_msgs[ $addr_sub ] )
								? $addr_val_msgs[ $addr_sub ]
								: sprintf(
									/* translators: %s: sub-field label */
									__( '%s is required', 'form-plant' ),
									$sub_label
								);
							$errors[ $field_name . '.' . $addr_sub ] = apply_filters(
								'fplant_validation_required_message',
								$sub_message,
								$field,
								'',
								$data
							);
							$addr_has_error = true;
						}
					}
					if ( $addr_has_error ) {
						continue;
					}
					$is_empty = false;
				} elseif ( in_array( $field['type'], array( 'name_parts', 'name_kana' ), true ) ) {
					// Check required sub-fields for name composite field individually
					$name_format = isset( $field['name_format'] ) ? $field['name_format'] : '2';
					if ( '1' === $name_format ) {
						$is_empty = empty( $value ) && '0' !== $value;
					} else {
						$default_labels = 'name_kana' === $field['type']
							? array(
								'family' => __( 'Last Name (Kana)', 'form-plant' ),
								'given'  => __( 'First Name (Kana)', 'form-plant' ),
								'middle' => __( 'Middle Name (Kana)', 'form-plant' ),
							)
							: array(
								'family' => __( 'Last Name', 'form-plant' ),
								'given'  => __( 'First Name', 'form-plant' ),
								'middle' => __( 'Middle Name', 'form-plant' ),
							);
						$name_labels    = isset( $field['name_labels'] ) ? wp_parse_args( $field['name_labels'], $default_labels ) : $default_labels;
						$name_val_msgs  = isset( $field['name_validation_messages'] ) ? $field['name_validation_messages'] : array();
						$required_parts = array( 'family', 'given' );
						$name_has_error = false;
						foreach ( $required_parts as $part_key ) {
							$part_data_key = $field_name . '_' . $part_key;
							if ( empty( $data[ $part_data_key ] ) ) {
								$sub_label   = ! empty( $name_labels[ $part_key ] ) ? $name_labels[ $part_key ] : $part_key;
								$sub_message = ! empty( $name_val_msgs[ $part_key ] )
									? $name_val_msgs[ $part_key ]
									: sprintf(
										/* translators: %s: sub-field label */
										__( '%s is required', 'form-plant' ),
										$sub_label
									);
								$errors[ $field_name . '.' . $part_key ] = apply_filters(
									'fplant_validation_required_message',
									$sub_message,
									$field,
									'',
									$data
								);
								$name_has_error = true;
							}
						}
						if ( $name_has_error ) {
							continue;
						}
						$is_empty = false;
					}
				} else {
					$is_empty = empty( $value ) && '0' !== $value;
				}

				if ( $is_empty ) {
					$message = sprintf(
						/* translators: %s: field label */
						__( '%s is required', 'form-plant' ),
						$field['label']
					);
					$errors[ $field_name ] = apply_filters(
						'fplant_validation_required_message',
						$message,
						$field,
						$value,
						$data
					);
					continue;
				}
			}

			// Skip if value is empty (except file fields which use $_FILES)
			if ( 'file' !== $field['type'] && empty( $value ) && '0' !== $value ) {
				continue;
			}

			// Hook: Override validation for specific fields
			$field_error = apply_filters( "fplant_validate_field_{$field_name}", null, $field, $value, $data, $form_id );

			// If custom validation was executed
			if ( null !== $field_error ) {
				if ( false !== $field_error && '' !== $field_error ) {
					$errors[ $field_name ] = $field_error;
				}
				continue; // Skip standard validation
			}

			// Validation by field type
			$field_error = $this->validate_field_type( $field, $value );
			if ( $field_error ) {
				if ( is_array( $field_error ) && isset( $field_error['sub_key'] ) ) {
					$errors[ $field_name . '.' . $field_error['sub_key'] ] = $field_error['message'];
				} else {
					$errors[ $field_name ] = $field_error;
				}
				continue;
			}

			// Custom validation
			if ( ! empty( $field['validation'] ) ) {
				$validation_error = $this->validate_custom_rules( $field, $value );
				if ( $validation_error ) {
					$errors[ $field_name ] = $validation_error;
				}
			}
		}

		// Apply filters
		$errors = apply_filters( 'fplant_validation_errors', $errors, $fields, $data );

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validate by field type
	 *
	 * @param array  $field Field configuration
	 * @param string $value Value
	 * @return string|false
	 */
	private function validate_field_type( $field, $value ) {
		switch ( $field['type'] ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					$message = sprintf(
						/* translators: %s: field label */
						__( '%s format is invalid', 'form-plant' ),
						$field['label']
					);
					return apply_filters( 'fplant_validation_message_email', $message, $field, $value, array( 'type' => 'format' ) );
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$message = sprintf(
						/* translators: %s: field label */
						__( '%s format is invalid', 'form-plant' ),
						$field['label']
					);
					return apply_filters( 'fplant_validation_message_url', $message, $field, $value, array( 'type' => 'format' ) );
				}
				break;

			case 'tel':
				// Check Japanese phone number format (with or without hyphens)
				$tel = preg_replace( '/[^0-9]/', '', $value );
				if ( ! preg_match( '/^0\d{9,10}$/', $tel ) ) {
					$message = sprintf(
						/* translators: %s: field label */
						__( '%s format is invalid', 'form-plant' ),
						$field['label']
					);
					return apply_filters( 'fplant_validation_message_tel', $message, $field, $value, array( 'type' => 'format' ) );
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					$message = sprintf(
						/* translators: %s: field label */
						__( '%s must be a number', 'form-plant' ),
						$field['label']
					);
					return apply_filters( 'fplant_validation_message_number', $message, $field, $value, array( 'type' => 'format' ) );
				}

				// Minimum value check
				if ( isset( $field['min'] ) && '' !== $field['min'] && $value < $field['min'] ) {
					$message = sprintf(
						/* translators: 1: field label, 2: minimum value */
						__( '%1$s must be at least %2$s', 'form-plant' ),
						$field['label'],
						$field['min']
					);
					return apply_filters( 'fplant_validation_message_number', $message, $field, $value, array( 'type' => 'min', 'min' => $field['min'] ) );
				}

				// Maximum value check
				if ( isset( $field['max'] ) && '' !== $field['max'] && $value > $field['max'] ) {
					$message = sprintf(
						/* translators: 1: field label, 2: maximum value */
						__( '%1$s must be at most %2$s', 'form-plant' ),
						$field['label'],
						$field['max']
					);
					return apply_filters( 'fplant_validation_message_number', $message, $field, $value, array( 'type' => 'max', 'max' => $field['max'] ) );
				}
				break;

			case 'name_kana':
				$kana_validation = isset( $field['kana_validation'] ) ? $field['kana_validation'] : 'katakana';
				if ( 'none' !== $kana_validation && ! empty( $value ) ) {
					if ( 'katakana' === $kana_validation ) {
						$kana_pattern = '/^[\p{Katakana}\x{30FC}\s]+$/u';
						$kana_default = sprintf(
							/* translators: %s: field label */
							__( '%s must be in katakana', 'form-plant' ),
							$field['label']
						);
					} else {
						$kana_pattern = '/^[\p{Hiragana}\x{30FC}\s]+$/u';
						$kana_default = sprintf(
							/* translators: %s: field label */
							__( '%s must be in hiragana', 'form-plant' ),
							$field['label']
						);
					}
					if ( ! preg_match( $kana_pattern, $value ) ) {
						$kana_message = ! empty( $field['kana_error_message'] )
							? $field['kana_error_message']
							: $kana_default;
						return apply_filters( 'fplant_validation_message_name_kana', $kana_message, $field, $value );
					}
				}
				break;

			case 'password':
				// Minimum length check
				if ( ! empty( $field['password_min_length'] ) && mb_strlen( $value ) < intval( $field['password_min_length'] ) ) {
					$message = sprintf(
						/* translators: 1: field label, 2: minimum length */
						__( '%1$s must be at least %2$s characters', 'form-plant' ),
						$field['label'],
						$field['password_min_length']
					);
					return apply_filters( 'fplant_validation_message_password', $message, $field, $value, array( 'type' => 'min_length' ) );
				}

				// Password strength check
				if ( ! empty( $field['password_strength_meter'] ) && ! empty( $field['password_strength_level'] ) && 'none' !== $field['password_strength_level'] ) {
					$score          = $this->estimate_password_strength( $value );
					$required_score = $this->get_required_strength_score( $field['password_strength_level'] );
					if ( $score < $required_score ) {
						$level_label = $this->get_strength_level_label( $field['password_strength_level'] );
						$message     = sprintf(
							/* translators: 1: field label, 2: required strength level */
							__( '%1$s does not meet the required strength level (%2$s)', 'form-plant' ),
							$field['label'],
							$level_label
						);
						return apply_filters( 'fplant_validation_message_password', $message, $field, $value, array( 'type' => 'strength' ) );
					}
				}
				break;

			case 'postal_code':
				$is_ja = ( 0 === strpos( get_locale(), 'ja' ) );
				if ( $is_ja ) {
					$postal_clean = preg_replace( '/[^0-9]/', '', $value );
					if ( 7 !== strlen( $postal_clean ) ) {
						$message = sprintf(
							/* translators: %s: field label */
							__( '%s format is invalid', 'form-plant' ),
							$field['label']
						);
						return apply_filters( 'fplant_validation_message_postal_code', $message, $field, $value, array( 'type' => 'format' ) );
					}
				}
				break;

			case 'address':
				// Validate postal code sub-field for Japanese locale
				if ( 0 === strpos( get_locale(), 'ja' ) && is_array( $value ) && ! empty( $value['postal_code'] ) ) {
					$addr_postal_clean = preg_replace( '/[^0-9]/', '', $value['postal_code'] );
					if ( 7 !== strlen( $addr_postal_clean ) ) {
						$addr_labels   = isset( $field['address_labels'] ) ? $field['address_labels'] : array();
						$addr_val_msgs = isset( $field['address_validation_messages'] ) ? $field['address_validation_messages'] : array();
						$sub_label     = ! empty( $addr_labels['postal_code'] ) ? $addr_labels['postal_code'] : __( 'Postal Code', 'form-plant' );
						$message       = ! empty( $addr_val_msgs['postal_code'] )
							? $addr_val_msgs['postal_code']
							: sprintf(
								/* translators: %s: sub-field label */
								__( '%s format is invalid', 'form-plant' ),
								$sub_label
							);
						return array(
							'sub_key' => 'postal_code',
							'message' => apply_filters( 'fplant_validation_message_postal_code', $message, $field, $value, array( 'type' => 'format' ) ),
						);
					}
				}
				break;

			case 'file':
				// File upload validation is handled separately
				$field_name = (string) $field['name'];
				// Validate field name format (alphanumeric and underscores only)
				if ( preg_match( '/^[A-Za-z0-9_]+$/', $field_name ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in calling handler, $_FILES sanitized in validate_file().
					$file_error = $this->validate_file( $field, $_FILES[ $field_name ] ?? null );
					if ( $file_error ) {
						return $file_error;
					}
				}
				break;
		}

		// Hook: Additional validation by field type
		$additional_error = apply_filters(
			"fplant_validate_field_type_{$field['type']}",
			false,
			$field,
			$value
		);

		if ( false !== $additional_error && '' !== $additional_error ) {
			return $additional_error;
		}

		return false;
	}

	/**
	 * Custom validation rules
	 *
	 * @param array  $field Field configuration
	 * @param string $value Value
	 * @return string|false
	 */
	private function validate_custom_rules( $field, $value ) {
		$validation = $field['validation'];

		// Minimum character length
		if ( isset( $validation['min_length'] ) && mb_strlen( $value ) < $validation['min_length'] ) {
			$message = sprintf(
				/* translators: 1: field label, 2: minimum length */
				__( '%1$s must be at least %2$s characters', 'form-plant' ),
				$field['label'],
				$validation['min_length']
			);
			return apply_filters( 'fplant_validation_message_min_length', $message, $field, $value, $validation );
		}

		// Maximum character length
		if ( isset( $validation['max_length'] ) && mb_strlen( $value ) > $validation['max_length'] ) {
			$message = ! empty( $validation['max_length_message'] )
				? $validation['max_length_message']
				: sprintf(
					/* translators: 1: field label, 2: maximum length */
					__( '%1$s must be at most %2$s characters', 'form-plant' ),
					$field['label'],
					$validation['max_length']
				);
			return apply_filters( 'fplant_validation_message_max_length', $message, $field, $value, $validation );
		}

		// Regex pattern
		if ( ! empty( $validation['pattern'] ) ) {
			if ( ! preg_match( $validation['pattern'], $value ) ) {
				$error_message = ! empty( $validation['pattern_message'] )
					? $validation['pattern_message']
					: sprintf(
						/* translators: %s: field label */
						__( '%s format is invalid', 'form-plant' ),
						$field['label']
					);
				return apply_filters( 'fplant_validation_message_pattern', $error_message, $field, $value, $validation );
			}
		}

		// Hook: Extend custom validation rules
		return apply_filters(
			'fplant_validate_custom_rules',
			false,
			$field,
			$value,
			$validation
		);
	}

	/**
	 * Validate file upload
	 *
	 * @param array $field Field configuration
	 * @param array $file  File info
	 * @return string|false
	 */
	private function validate_file( $field, $file ) {
		if ( ! $file || empty( $file['name'] ) ) {
			return false;
		}

		// Sanitize raw $_FILES data.
		$file = array(
			'name'     => sanitize_file_name( $file['name'] ),
			'type'     => isset( $file['type'] ) ? sanitize_mime_type( $file['type'] ) : '',
			'tmp_name' => isset( $file['tmp_name'] ) ? $file['tmp_name'] : '',
			'error'    => isset( $file['error'] ) ? intval( $file['error'] ) : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $file['size'] ) ? intval( $file['size'] ) : 0,
		);

		// Error check
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			$message = sprintf(
				/* translators: %s: field label */
				__( '%s upload failed', 'form-plant' ),
				$field['label']
			);
			return apply_filters( 'fplant_validation_message_file', $message, $field, $file, array( 'type' => 'upload_error' ) );
		}

		// File size check
		$max_size = ! empty( $field['max_size'] ) ? $field['max_size'] : 5; // MB
		$max_bytes = $max_size * 1024 * 1024;

		if ( $file['size'] > $max_bytes ) {
			$message = sprintf(
				/* translators: 1: field label, 2: max file size */
				__( '%1$s file size must be %2$sMB or less', 'form-plant' ),
				$field['label'],
				$max_size
			);
			return apply_filters( 'fplant_validation_message_file', $message, $field, $file, array( 'type' => 'size', 'max_size' => $max_size ) );
		}

		// Extension check
		$allowed_types = ! empty( $field['allowed_types'] ) ? $field['allowed_types'] : array( 'jpg', 'jpeg', 'png', 'gif', 'pdf' );
		$file_extension = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_extension, $allowed_types, true ) ) {
			$message = sprintf(
				/* translators: 1: field label, 2: allowed file types */
				__( '%1$s only accepts %2$s files', 'form-plant' ),
				$field['label'],
				implode( ', ', $allowed_types )
			);
			return apply_filters( 'fplant_validation_message_file', $message, $field, $file, array( 'type' => 'extension', 'allowed_types' => $allowed_types ) );
		}

		return false;
	}

	/**
	 * Check if email uses a disposable domain
	 *
	 * @param string $email Email address.
	 * @return bool True if disposable.
	 */
	public function is_disposable_email( $email ) {
		$domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );
		if ( empty( $domain ) ) {
			return false;
		}

		// MX record check.
		if ( ! checkdnsrr( $domain, 'MX' ) && ! checkdnsrr( $domain, 'A' ) ) {
			return true; // No DNS record = invalid domain.
		}

		// Check against disposable domain list.
		$list_file = FPLANT_PLUGIN_DIR . 'data/disposable-email-domains.txt';
		if ( ! file_exists( $list_file ) ) {
			return false;
		}

		// Cache (Transient, 1 hour).
		$cache_key = 'fplant_disposable_domains';
		$domains   = get_transient( $cache_key );
		if ( false === $domains ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents( $list_file );
			$domains = array_filter( array_map( 'trim', explode( "\n", strtolower( $content ) ) ) );
			$domains = array_flip( $domains ); // Associative array for fast lookup.
			set_transient( $cache_key, $domains, HOUR_IN_SECONDS );
		}

		return isset( $domains[ $domain ] );
	}

	/**
	 * Check email against blocked domains
	 *
	 * @param string $email Email address.
	 * @return bool True if blocked.
	 */
	public function is_blocked_email_domain( $email ) {
		$blocked = get_option( 'fplant_blocked_email_domains', '' );
		if ( empty( $blocked ) ) {
			return false;
		}

		$domain          = strtolower( substr( strrchr( $email, '@' ), 1 ) );
		$blocked_domains = array_filter( array_map( 'trim', explode( "\n", strtolower( $blocked ) ) ) );

		return in_array( $domain, $blocked_domains, true );
	}

	/**
	 * Check submission data for blocked keywords
	 *
	 * @param array $data Submission data.
	 * @return bool True if blocked keyword found.
	 */
	public function contains_blocked_keywords( $data ) {
		$blocked = get_option( 'fplant_blocked_keywords', '' );
		if ( empty( $blocked ) ) {
			return false;
		}

		$keywords = array_filter( array_map( 'trim', explode( "\n", strtolower( $blocked ) ) ) );
		$text     = strtolower( implode( ' ', array_values( array_filter( $data, 'is_string' ) ) ) );

		foreach ( $keywords as $keyword ) {
			if ( ! empty( $keyword ) && false !== strpos( $text, $keyword ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Spam protection check
	 *
	 * @param array $form_data     Form data
	 * @param array $spam_settings Spam protection settings
	 * @return bool
	 */
	public function check_spam( $form_data, $spam_settings ) {
		// Honeypot check
		if ( ! empty( $spam_settings['honeypot'] ) ) {
			$honeypot_field_name = $spam_settings['honeypot_field_name'] ?? 'fplant_website_url';
			if ( ! empty( $form_data[ $honeypot_field_name ] ) ) {
				return true; // Spam
			}
		}

		// IP address rate limiting
		if ( ! empty( $spam_settings['rate_limit'] ) ) {
			$is_limited = $this->check_rate_limit(
				$spam_settings['rate_limit_minutes'] ?? 5,
				$spam_settings['rate_limit_count'] ?? 3
			);

			if ( $is_limited ) {
				return true; // Spam
			}
		}

		// Time-based check
		if ( ! empty( $spam_settings['time_check'] ) ) {
			$min_seconds = $spam_settings['time_check_seconds'] ?? 3;
			$form_ts     = isset( $form_data['fplant_form_ts'] ) ? intval( $form_data['fplant_form_ts'] ) : 0;
			if ( $form_ts > 0 ) {
				$elapsed = time() - $form_ts;
				if ( $elapsed < $min_seconds ) {
					return true; // Spam - too fast
				}
			} else {
				// No timestamp = JS not executed = likely a bot
				return true;
			}
		}

		return false;
	}

	/**
	 * Rate limit check
	 *
	 * @param int $minutes   Minutes
	 * @param int $max_count Maximum submission count
	 * @return bool
	 */
	private function check_rate_limit( $minutes, $max_count ) {
		$ip = $this->get_client_ip();
		if ( empty( $ip ) ) {
			return false; // Cannot identify client, skip rate limit (fail-open).
		}
		$transient_key = 'fplant_rate_limit_' . md5( $ip );

		$submissions = get_transient( $transient_key );

		if ( false === $submissions ) {
			$submissions = array();
		}

		// Remove old submission records
		$current_time = time();
		$time_limit = $current_time - ( $minutes * 60 );

		$submissions = array_filter(
			$submissions,
			function( $timestamp ) use ( $time_limit ) {
				return $timestamp > $time_limit;
			}
		);

		// Limit check
		if ( count( $submissions ) >= $max_count ) {
			return true; // Limit exceeded
		}

		// Record new submission
		$submissions[] = $current_time;
		set_transient( $transient_key, $submissions, $minutes * 60 );

		return false;
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	private function get_client_ip() {
		// Only use REMOTE_ADDR to prevent IP spoofing via forgeable headers
		// (HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR, etc.).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Validated by filter_var, sanitized after validation
		if ( isset( $_SERVER['REMOTE_ADDR'] ) && filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return '';
	}

	/**
	 * Estimate password strength (server-side approximation)
	 *
	 * @param string $password Password to evaluate.
	 * @return int Score 0-4.
	 */
	private function estimate_password_strength( $password ) {
		$length = mb_strlen( $password );
		$score  = 0;

		if ( $length >= 8 ) {
			++$score;
		}
		if ( $length >= 12 ) {
			++$score;
		}
		if ( preg_match( '/[a-z]/', $password ) && preg_match( '/[A-Z]/', $password ) ) {
			++$score;
		}
		if ( preg_match( '/[0-9]/', $password ) ) {
			++$score;
		}
		if ( preg_match( '/[^a-zA-Z0-9]/', $password ) ) {
			++$score;
		}

		return min( $score, 4 );
	}

	/**
	 * Get required score from strength level setting
	 *
	 * @param string $level Strength level (weak, fair, strong).
	 * @return int Required score.
	 */
	private function get_required_strength_score( $level ) {
		switch ( $level ) {
			case 'weak':
				return 1;
			case 'fair':
				return 2;
			case 'strong':
				return 3;
			default:
				return 0;
		}
	}

	/**
	 * Get localized label for strength level
	 *
	 * @param string $level Strength level.
	 * @return string Localized label.
	 */
	private function get_strength_level_label( $level ) {
		switch ( $level ) {
			case 'weak':
				return __( 'Weak', 'form-plant' );
			case 'fair':
				return __( 'Fair', 'form-plant' );
			case 'strong':
				return __( 'Strong', 'form-plant' );
			default:
				return __( 'None', 'form-plant' );
		}
	}
}
