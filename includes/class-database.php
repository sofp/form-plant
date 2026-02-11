<?php
/**
 * Database operations class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Database class
 */
class FPLANT_Database {

	/**
	 * Form metadata keys
	 */
	const META_FIELDS          = '_fplant_fields';
	const META_HTML_TEMPLATE   = '_fplant_html_template';
	const META_SETTINGS        = '_fplant_settings';
	const META_EMAIL_ADMIN     = '_fplant_email_admin';
	const META_EMAIL_USER      = '_fplant_email_user';
	const META_SPAM_PROTECTION = '_fplant_spam_protection';
	const META_ACF_INTEGRATION = '_fplant_acf_integration';

	/**
	 * Get table name
	 *
	 * Table name is a safe value defined internally.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is safe, constructed from $wpdb->prefix
		return esc_sql( $wpdb->prefix . 'fplant_submissions' );
	}

	/**
	 * Create tables
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			submission_data longtext NOT NULL,
			sent_time datetime NOT NULL,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY sent_time (sent_time)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get form data
	 *
	 * @param int $form_id Form ID
	 * @return array|null
	 */
	public static function get_form( $form_id ) {
		$post = get_post( $form_id );

		if ( ! $post || 'fplant_form' !== $post->post_type ) {
			return null;
		}

		return array(
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'status'          => $post->post_status,
			'fields'          => self::get_form_meta( $form_id, self::META_FIELDS, array() ),
			'html_template'   => self::get_form_meta( $form_id, self::META_HTML_TEMPLATE, '' ),
			'settings'        => self::get_form_meta( $form_id, self::META_SETTINGS, array() ),
			'email_admin'     => self::get_form_meta( $form_id, self::META_EMAIL_ADMIN, array() ),
			'email_user'      => self::get_form_meta( $form_id, self::META_EMAIL_USER, array() ),
			'spam_protection' => self::get_form_meta( $form_id, self::META_SPAM_PROTECTION, array() ),
			'acf_integration' => self::get_form_meta( $form_id, self::META_ACF_INTEGRATION, array() ),
			'created_at'      => $post->post_date,
			'modified_at'     => $post->post_modified,
		);
	}

	/**
	 * Get form metadata
	 *
	 * @param int    $form_id  Form ID
	 * @param string $meta_key Meta key
	 * @param mixed  $default  Default value
	 * @return mixed
	 */
	public static function get_form_meta( $form_id, $meta_key, $default = '' ) {
		$value = get_post_meta( $form_id, $meta_key, true );

		// Return default value if empty
		if ( '' === $value || null === $value || false === $value ) {
			return $default;
		}

		// Return as-is if already an array (WordPress may unserialize automatically)
		if ( is_array( $value ) ) {
			return $value;
		}

		// Decode if JSON string
		if ( is_string( $value ) && self::is_json( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( null !== $decoded ) {
				return $decoded;
			}
		}

		return $value;
	}

	/**
	 * Update form metadata
	 *
	 * @param int    $form_id  Form ID
	 * @param string $meta_key Meta key
	 * @param mixed  $value    Value
	 * @return bool
	 */
	public static function update_form_meta( $form_id, $meta_key, $value ) {
		// Convert array to JSON string
		if ( is_array( $value ) ) {
			// Don't use JSON_UNESCAPED_SLASHES (newline \n won't be preserved)
			// Use only JSON_UNESCAPED_UNICODE instead
			$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
		}

		// WordPress's update_post_meta strips slashes, so we need to protect
		// strings containing backslashes (like \n)
		// Use wp_slash() to add slashes (pre-adding what WordPress will remove)
		$value = wp_slash( $value );

		return update_post_meta( $form_id, $meta_key, $value );
	}

	/**
	 * Save submission data
	 *
	 * @param int   $form_id Form ID
	 * @param array $data    Submission data
	 * @return int|false Submission ID or false
	 */
	public static function save_submission( $form_id, $data ) {
		global $wpdb;

		// Include additional info in submission data
		$submission_data = array(
			'form_data'  => $data,
			'ip_address' => self::get_client_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'   => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'user_id'    => get_current_user_id(),
		);

		$table_name = self::get_table_name();

		// Insert data
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for form submissions, direct insert is necessary
		$result = $wpdb->insert(
			$table_name,
			array(
				'form_id'         => $form_id,
				'submission_data' => wp_json_encode( $submission_data, JSON_UNESCAPED_UNICODE ),
				'sent_time'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get submission data
	 *
	 * @param int $submission_id Submission ID
	 * @return array|null
	 */
	public static function get_submission( $submission_id ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Note: $table_name is internal and safe (from esc_sql), dynamic table name requires interpolation
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$submission_id
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $row ) {
			return null;
		}

		$submission_data = json_decode( $row['submission_data'], true );
		if ( ! $submission_data ) {
			$submission_data = array();
		}

		return array(
			'id'         => $row['id'],
			'form_id'    => $row['form_id'],
			'data'       => isset( $submission_data['form_data'] ) ? $submission_data['form_data'] : array(),
			'ip_address' => isset( $submission_data['ip_address'] ) ? $submission_data['ip_address'] : '',
			'user_agent' => isset( $submission_data['user_agent'] ) ? $submission_data['user_agent'] : '',
			'referrer'   => isset( $submission_data['referrer'] ) ? $submission_data['referrer'] : '',
			'user_id'    => isset( $submission_data['user_id'] ) ? $submission_data['user_id'] : 0,
			'created_at' => $row['sent_time'],
		);
	}

	/**
	 * Get form submissions list
	 *
	 * @param int   $form_id Form ID
	 * @param array $args    Query arguments
	 * @return array
	 */
	public static function get_submissions( $form_id = 0, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'     => 20,
			'offset'    => 0,
			'orderby'   => 'sent_time',
			'order'     => 'DESC',
			'date_from' => '',
			'date_to'   => '',
			'search'    => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$table_name = self::get_table_name();

		// Build WHERE clause dynamically
		$where_clauses = array();
		$where_values  = array();

		if ( $form_id > 0 ) {
			$where_clauses[] = 'form_id = %d';
			$where_values[]  = $form_id;
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'sent_time >= %s';
			$where_values[]  = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'sent_time <= %s';
			$where_values[]  = $args['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = 'submission_data LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where = '';
		if ( ! empty( $where_clauses ) ) {
			$where = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		$orderby = in_array( $args['orderby'], array( 'id', 'form_id', 'sent_time' ), true ) ? $args['orderby'] : 'sent_time';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit   = absint( $args['limit'] );
		$offset  = absint( $args['offset'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Note: $table_name is internal and safe (from esc_sql), $orderby/$order are whitelisted, $where_values array contains dynamic placeholders
		if ( ! empty( $where_values ) ) {
			// Add limit and offset to the values array for prepare()
			$where_values[] = $limit;
			$where_values[] = $offset;
			$query = $wpdb->prepare(
				"SELECT * FROM {$table_name} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$where_values
			);
			$results = $wpdb->get_results( $query, ARRAY_A );
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
					$limit,
					$offset
				),
				ARRAY_A
			);
		}
		// phpcs:enable

		$submissions = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$submission_data = json_decode( $row['submission_data'], true );
				if ( ! $submission_data ) {
					$submission_data = array();
				}

				$submissions[] = array(
					'id'         => $row['id'],
					'form_id'    => $row['form_id'],
					'data'       => isset( $submission_data['form_data'] ) ? $submission_data['form_data'] : array(),
					'ip_address' => isset( $submission_data['ip_address'] ) ? $submission_data['ip_address'] : '',
					'user_agent' => isset( $submission_data['user_agent'] ) ? $submission_data['user_agent'] : '',
					'referrer'   => isset( $submission_data['referrer'] ) ? $submission_data['referrer'] : '',
					'user_id'    => isset( $submission_data['user_id'] ) ? $submission_data['user_id'] : 0,
					'created_at' => $row['sent_time'],
				);
			}
		}

		return $submissions;
	}

	/**
	 * Get submissions count
	 *
	 * @param int   $form_id Form ID (0 for all)
	 * @param array $args    Filter arguments
	 * @return int
	 */
	public static function get_submissions_count( $form_id = 0, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'date_from' => '',
			'date_to'   => '',
			'search'    => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$table_name = self::get_table_name();

		// Build WHERE clause dynamically
		$where_clauses = array();
		$where_values  = array();

		if ( $form_id > 0 ) {
			$where_clauses[] = 'form_id = %d';
			$where_values[]  = $form_id;
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'sent_time >= %s';
			$where_values[]  = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'sent_time <= %s';
			$where_values[]  = $args['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = 'submission_data LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where = '';
		if ( ! empty( $where_clauses ) ) {
			$where = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Note: $table_name is internal and safe (from esc_sql), $where contains dynamic placeholders, no user input in query
		if ( ! empty( $where_values ) ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} {$where}",
					$where_values
				)
			);
		} else {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		}
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Delete submission data
	 *
	 * @param int $submission_id Submission ID
	 * @return bool
	 */
	public static function delete_submission( $submission_id ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $submission_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Check if string is JSON
	 *
	 * @param string $string String to check
	 * @return bool
	 */
	private static function is_json( $string ) {
		if ( ! is_string( $string ) ) {
			return false;
		}

		json_decode( $string );
		return json_last_error() === JSON_ERROR_NONE;
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
}
