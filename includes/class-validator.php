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

			// Required check
			if ( ! empty( $field['required'] ) ) {
				$is_empty = false;

				// Check array for checkbox/radio fields
				if ( in_array( $field['type'], array( 'checkbox', 'radio' ), true ) ) {
					$is_empty = empty( $value ) || ( is_array( $value ) && count( $value ) === 0 );
				} elseif ( 'file' === $field['type'] ) {
					// Check $_FILES for file fields
					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified in calling AJAX handler, $_FILES checked for isset
					$is_empty = empty( $_FILES[ $field_name ] ) || empty( $_FILES[ $field_name ]['name'] ) || UPLOAD_ERR_NO_FILE === $_FILES[ $field_name ]['error'];
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
				$errors[ $field_name ] = $field_error;
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

			case 'file':
				// File upload validation is handled separately
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in calling AJAX handler, $_FILES validated by validate_file method
				$file_error = $this->validate_file( $field, $_FILES[ $field['name'] ] ?? null );
				if ( $file_error ) {
					return $file_error;
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
			$message = sprintf(
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
		$file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

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
	 * Spam protection check
	 *
	 * @param array $form_data     Form data
	 * @param array $spam_settings Spam protection settings
	 * @return bool
	 */
	public function check_spam( $form_data, $spam_settings ) {
		// Honeypot check
		if ( ! empty( $spam_settings['honeypot'] ) && ! empty( $form_data['fplant_honeypot'] ) ) {
			return true; // Spam
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
}
