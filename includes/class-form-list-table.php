<?php
/**
 * Form list table class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Form list table
 */
class FPLANT_Form_List_Table extends WP_List_Table {

	/**
	 * Column display options from Screen Options
	 *
	 * @var array
	 */
	private $column_options = array();

	/**
	 * Items per page
	 *
	 * @var int
	 */
	private $per_page = 20;

	/**
	 * Cached form metadata
	 *
	 * @var array
	 */
	private $form_meta_cache = array();

	/**
	 * Field type => icon class map, built lazily from FPLANT_Field_Manager.
	 *
	 * @var array|null
	 */
	private $field_type_icons = null;

	/**
	 * Constructor
	 *
	 * @param array $column_options Column display options.
	 * @param int   $per_page      Items per page.
	 */
	public function __construct( $column_options = array(), $per_page = 20 ) {
		parent::__construct(
			array(
				'singular' => 'form',
				'plural'   => 'forms',
				'ajax'     => false,
			)
		);
		$this->column_options = $column_options;
		$this->per_page       = $per_page;
	}

	/**
	 * Column definitions
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'    => '<input type="checkbox" />',
			'title' => __( 'Form Name', 'form-plant' ),
		);

		$optional_columns = array(
			'shortcode'    => __( 'Shortcode', 'form-plant' ),
			'field_count'  => __( 'Field Count', 'form-plant' ),
			'field_names'  => __( 'Field Names', 'form-plant' ),
			'layout'       => __( 'Layout', 'form-plant' ),
			'email_config' => __( 'Email Settings', 'form-plant' ),
			'after_submit' => __( 'After Submit Action', 'form-plant' ),
			'save_data'    => __( 'Submission Data Settings', 'form-plant' ),
			'submissions'  => __( 'Submissions', 'form-plant' ),
			'embed'        => __( 'External Embed', 'form-plant' ),
			'spam'         => __( 'Spam Protection', 'form-plant' ),
			'author'       => __( 'Author', 'form-plant' ),
		);

		foreach ( $optional_columns as $key => $label ) {
			if ( ! empty( $this->column_options[ $key ] ) ) {
				$columns[ $key ] = $label;
			}
		}

		$columns['date'] = __( 'Date', 'form-plant' );

		return $columns;
	}

	/**
	 * Sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
			'date'  => array( 'date', true ),
		);
	}

	/**
	 * Status views
	 *
	 * @return array
	 */
	protected function get_views() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state
		$current_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : 'all';
		$views          = array();
		$base_url       = admin_url( 'admin.php?page=fplant-forms' );

		// Get count for each status
		$counts = $this->get_status_counts();

		$statuses = array(
			'all'     => __( 'All', 'form-plant' ),
			'publish' => __( 'Published', 'form-plant' ),
			'private' => __( 'Private', 'form-plant' ),
			'draft'   => __( 'Draft', 'form-plant' ),
			'pending' => __( 'Pending Review', 'form-plant' ),
			'trash'   => __( 'Trash', 'form-plant' ),
		);

		foreach ( $statuses as $status => $label ) {
			$count = isset( $counts[ $status ] ) ? $counts[ $status ] : 0;
			if ( 'all' === $status ) {
				$count = array_sum( array_diff_key( $counts, array( 'trash' => 0 ) ) );
			}

			if ( 0 === $count && 'all' !== $status && 'publish' !== $status ) {
				continue;
			}

			$class = ( $current_status === $status ) ? 'current' : '';
			$url   = ( 'all' === $status ) ? $base_url : add_query_arg( 'post_status', $status, $base_url );

			$views[ $status ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label ),
				number_format_i18n( $count )
			);
		}

		return $views;
	}

	/**
	 * Get status counts
	 *
	 * @return array
	 */
	private function get_status_counts() {
		$counts   = array();
		$statuses = array( 'publish', 'private', 'draft', 'pending', 'trash' );

		foreach ( $statuses as $status ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'fplant_form',
					'post_status'    => $status,
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			$counts[ $status ] = $query->found_posts;
		}

		return $counts;
	}

	/**
	 * Bulk actions definition
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state
		$current_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : 'all';

		if ( 'trash' === $current_status ) {
			return array(
				'restore' => __( 'Restore', 'form-plant' ),
				'delete'  => __( 'Delete Permanently', 'form-plant' ),
			);
		}

		return array(
			'trash' => __( 'Move to Trash', 'form-plant' ),
		);
	}

	/**
	 * Prepare data
	 */
	public function prepare_items() {
		$per_page     = $this->per_page;
		$current_page = $this->get_pagenum();

		// Column settings
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort parameter
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort parameter
		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state
		$post_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : 'all';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search parameter
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Query arguments
		$args = array(
			'post_type'      => 'fplant_form',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		// Status filter
		if ( 'all' === $post_status ) {
			$args['post_status'] = array( 'publish', 'private', 'draft', 'pending' );
		} else {
			$args['post_status'] = $post_status;
		}

		// Search
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query       = new WP_Query( $args );
		$this->items = array();
		$form_ids    = array();

		foreach ( $query->posts as $post ) {
			$this->items[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'author' => $post->post_author,
				'date'   => $post->post_date,
			);
			$form_ids[] = $post->ID;
		}

		// Preload form metadata if any metadata-dependent columns are visible
		if ( $this->needs_form_metadata() && ! empty( $form_ids ) ) {
			$this->preload_form_metadata( $form_ids );
		}

		// Pagination settings
		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => ceil( $query->found_posts / $per_page ),
			)
		);
	}

	/**
	 * Check if any metadata-dependent columns are visible
	 *
	 * @return bool
	 */
	private function needs_form_metadata() {
		$meta_columns = array( 'field_count', 'field_names', 'layout', 'email_config', 'after_submit', 'save_data', 'embed', 'spam' );
		foreach ( $meta_columns as $col ) {
			if ( ! empty( $this->column_options[ $col ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Preload form metadata for all displayed forms
	 *
	 * @param array $form_ids Form IDs to preload.
	 */
	private function preload_form_metadata( $form_ids ) {
		// Warm WordPress post meta cache in a single query
		update_postmeta_cache( $form_ids );

		foreach ( $form_ids as $form_id ) {
			$this->form_meta_cache[ $form_id ] = FPLANT_Database::get_form( $form_id );
		}
	}

	/**
	 * Get cached form data
	 *
	 * @param int $form_id Form ID.
	 * @return array|null
	 */
	private function get_cached_form( $form_id ) {
		if ( isset( $this->form_meta_cache[ $form_id ] ) ) {
			return $this->form_meta_cache[ $form_id ];
		}
		$form                              = FPLANT_Database::get_form( $form_id );
		$this->form_meta_cache[ $form_id ] = $form;
		return $form;
	}

	/**
	 * Checkbox column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="form_ids[]" value="%d" />',
			$item['id']
		);
	}

	/**
	 * Title column (with row actions)
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_title( $item ) {
		$edit_url = admin_url( 'admin.php?page=fplant-form-new&id=' . $item['id'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state
		$current_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : 'all';

		// Title link
		$title = sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>',
			esc_url( $edit_url ),
			esc_html( $item['title'] ? $item['title'] : __( '(No Title)', 'form-plant' ) )
		);

		// Status badge (shown for every status, including published).
		$status_labels = array(
			'publish' => __( 'Published', 'form-plant' ),
			'private' => __( 'Private', 'form-plant' ),
			'draft'   => __( 'Draft', 'form-plant' ),
			'pending' => __( 'Pending Review', 'form-plant' ),
			'trash'   => __( 'Trash', 'form-plant' ),
		);
		$status_text  = isset( $status_labels[ $item['status'] ] ) ? $status_labels[ $item['status'] ] : $item['status'];
		$status_label = sprintf(
			' <span class="fplant-status-badge fplant-status-%s">%s</span>',
			esc_attr( $item['status'] ),
			esc_html( $status_text )
		);

		// Row actions
		$actions = array();

		if ( 'trash' === $current_status ) {
			// Trash actions
			$actions['restore'] = sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=fplant-forms&action=restore&form_id=' . $item['id'] . '&post_status=trash' ),
					'restore_form_' . $item['id']
				),
				__( 'Restore', 'form-plant' )
			);
			$actions['delete'] = sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=fplant-forms&action=delete&form_id=' . $item['id'] . '&post_status=trash' ),
					'delete_form_' . $item['id']
				),
				esc_js( __( 'Are you sure you want to delete this permanently? This action cannot be undone.', 'form-plant' ) ),
				__( 'Delete Permanently', 'form-plant' )
			);
		} else {
			// Normal actions
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				__( 'Edit', 'form-plant' )
			);
			$actions['inline hide-if-no-js'] = sprintf(
				'<button type="button" class="button-link editinline" data-form-id="%d" data-form-title="%s" data-form-status="%s">%s</button>',
				$item['id'],
				esc_attr( $item['title'] ),
				esc_attr( $item['status'] ),
				__( 'Quick Edit', 'form-plant' )
			);
			$actions['duplicate'] = sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=fplant-forms&action=duplicate&form_id=' . $item['id'] ),
					'duplicate_form_' . $item['id']
				),
				__( 'Duplicate', 'form-plant' )
			);
			$actions['trash'] = sprintf(
				'<a href="%s" class="submitdelete">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=fplant-forms&action=trash&form_id=' . $item['id'] ),
					'trash_form_' . $item['id']
				),
				__( 'Move to Trash', 'form-plant' )
			);
		}

		return $title . $status_label . $this->row_actions( $actions );
	}

	/**
	 * Shortcode column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_shortcode( $item ) {
		$shortcode = '[fplant id="' . $item['id'] . '"]';
		return sprintf(
			'<code>%s</code> <button type="button" class="button button-small fplant-copy-button" data-copy="%s">%s</button>',
			esc_html( $shortcode ),
			esc_attr( $shortcode ),
			__( 'Copy', 'form-plant' )
		);
	}

	/**
	 * Field count column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_field_count( $item ) {
		$form = $this->get_cached_form( $item['id'] );
		if ( ! $form || empty( $form['fields'] ) ) {
			return '0';
		}
		return number_format_i18n( count( $form['fields'] ) );
	}

	/**
	 * Field names column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_field_names( $item ) {
		$form = $this->get_cached_form( $item['id'] );
		if ( ! $form || empty( $form['fields'] ) ) {
			return '&mdash;';
		}

		$fields  = $form['fields'];
		$total   = count( $fields );
		$max     = 4;
		$display = array_slice( $fields, 0, $max );
		$output  = array();

		foreach ( $display as $field ) {
			$icon  = $this->get_field_type_icon( isset( $field['type'] ) ? $field['type'] : '' );
			$label = esc_html( ! empty( $field['label'] ) ? $field['label'] : $field['name'] );
			if ( ! empty( $field['required'] ) ) {
				$label .= '<span class="fplant-required">*</span>';
			}
			$output[] = '<span class="fplant-field-name-item">'
				. '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>'
				. '<span class="fplant-field-name-label">' . $label . '</span>'
				. '</span>';
		}

		$html = implode( '', $output );
		if ( $total > $max ) {
			$remaining = $total - $max;
			$html     .= sprintf(
				'<span class="description fplant-field-name-more">%s</span>',
				sprintf(
					/* translators: %d: number of remaining fields */
					esc_html__( 'and %d more...', 'form-plant' ),
					$remaining
				)
			);
		}
		return $html;
	}

	/**
	 * Resolve a field type's icon class (dashicons or custom), built once from
	 * the shared FPLANT_Field_Manager schema so the list matches the editor.
	 *
	 * @param string $type Field type key.
	 * @return string Icon class, e.g. 'dashicons-edit'. Falls back to a generic icon.
	 */
	private function get_field_type_icon( $type ) {
		if ( null === $this->field_type_icons ) {
			$this->field_type_icons = array();
			if ( class_exists( 'FPLANT_Field_Manager' ) ) {
				foreach ( ( new FPLANT_Field_Manager() )->get_field_types() as $key => $cfg ) {
					$this->field_type_icons[ $key ] = ! empty( $cfg['icon'] ) ? $cfg['icon'] : 'dashicons-forms';
				}
			}
		}
		return isset( $this->field_type_icons[ $type ] ) ? $this->field_type_icons[ $type ] : 'dashicons-forms';
	}

	/**
	 * Layout column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_layout( $item ) {
		$form = $this->get_cached_form( $item['id'] );
		if ( ! $form ) {
			return '&mdash;';
		}

		$settings = isset( $form['settings'] ) ? $form['settings'] : array();
		$parts    = array();

		if ( ! empty( $settings['use_html_template'] ) ) {
			$parts[] = esc_html__( 'HTML Template', 'form-plant' );
		}
		if ( ! empty( $settings['use_confirmation'] ) ) {
			$parts[] = esc_html__( 'Confirmation', 'form-plant' );
		}
		if ( ! empty( $settings['use_confirmation_template'] ) ) {
			$parts[] = esc_html__( 'Confirmation HTML', 'form-plant' );
		}

		return ! empty( $parts ) ? implode( '<br>', $parts ) : '&mdash;';
	}

	/**
	 * Email settings column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_email_config( $item ) {
		$form = $this->get_cached_form( $item['id'] );
		if ( ! $form ) {
			return '&mdash;';
		}

		$parts = array();

		if ( ! empty( $form['email_admin']['enabled'] ) ) {
			$parts[] = esc_html__( 'Admin Notification', 'form-plant' );
		}
		if ( ! empty( $form['email_user']['enabled'] ) ) {
			$parts[] = esc_html__( 'Auto Reply', 'form-plant' );
		}

		return ! empty( $parts ) ? implode( '<br>', $parts ) : '&mdash;';
	}

	/**
	 * After submit action column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_after_submit( $item ) {
		$form = $this->get_cached_form( $item['id'] );
		if ( ! $form ) {
			return '&mdash;';
		}

		$settings    = isset( $form['settings'] ) ? $form['settings'] : array();
		$action_type = isset( $settings['action_type'] ) ? $settings['action_type'] : 'message';
		$labels      = array(
			'message'     => __( 'Message', 'form-plant' ),
			'redirect'    => __( 'Redirect', 'form-plant' ),
			'custom_page' => __( 'Custom Page', 'form-plant' ),
		);

		return esc_html( isset( $labels[ $action_type ] ) ? $labels[ $action_type ] : $action_type );
	}

	/**
	 * Submission data column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_save_data( $item ) {
		$form = $this->get_cached_form( $item['id'] );
		if ( ! $form ) {
			return '&mdash;';
		}

		$settings  = isset( $form['settings'] ) ? $form['settings'] : array();
		$save_type = isset( $settings['save_submission'] ) ? $settings['save_submission'] : 'full';
		$labels    = array(
			'none'          => __( 'None', 'form-plant' ),
			'metadata_only' => __( 'Metadata Only', 'form-plant' ),
			'full'          => __( 'Full', 'form-plant' ),
		);

		return esc_html( isset( $labels[ $save_type ] ) ? $labels[ $save_type ] : $save_type );
	}

	/**
	 * External embed column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_embed( $item ) {
		$form = $this->get_cached_form( $item['id'] );
		if ( ! $form ) {
			return '&mdash;';
		}

		$settings = isset( $form['settings'] ) ? $form['settings'] : array();
		$parts    = array();

		if ( ! empty( $settings['embed_iframe_enabled'] ) ) {
			$parts[] = 'iframe';
		}
		if ( ! empty( $settings['embed_js_enabled'] ) ) {
			$parts[] = 'JavaScript';
		}

		return ! empty( $parts ) ? implode( '<br>', array_map( 'esc_html', $parts ) ) : '&mdash;';
	}

	/**
	 * Spam protection column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_spam( $item ) {
		$form = $this->get_cached_form( $item['id'] );
		if ( ! $form ) {
			return '&mdash;';
		}

		$settings = isset( $form['settings'] ) ? $form['settings'] : array();
		$parts    = array();

		if ( ! empty( $settings['spam_honeypot_enabled'] ) ) {
			$parts[] = __( 'Honeypot', 'form-plant' );
		}

		$captcha_type = isset( $settings['captcha_type'] ) ? $settings['captcha_type'] : 'none';
		$captcha_labels = array(
			'recaptcha'    => 'reCAPTCHA v3',
			'recaptcha_v2' => 'reCAPTCHA v2',
			'turnstile'    => 'Turnstile',
		);
		if ( isset( $captcha_labels[ $captcha_type ] ) ) {
			$parts[] = $captcha_labels[ $captcha_type ];
		}

		if ( ! empty( $settings['spam_rate_limit_enabled'] ) ) {
			$parts[] = __( 'Rate Limit', 'form-plant' );
		}

		if ( ! empty( $settings['spam_time_check_enabled'] ) ) {
			$parts[] = __( 'Time Check', 'form-plant' );
		}

		if ( ! empty( $settings['spam_disposable_email_block'] ) ) {
			$parts[] = __( 'Email Address Verification', 'form-plant' );
		}

		return ! empty( $parts ) ? implode( '<br>', array_map( 'esc_html', $parts ) ) : '&mdash;';
	}

	/**
	 * Render ON/OFF badge
	 *
	 * @param string $label Label text.
	 * @param bool   $is_on Whether the feature is on.
	 * @return string
	 */
	private function render_on_off_badge( $label, $is_on ) {
		if ( $is_on ) {
			return sprintf(
				'<span class="fplant-badge fplant-badge-on">%s</span>',
				esc_html( $label )
			);
		}
		return sprintf(
			'<span class="fplant-badge fplant-badge-off">%s</span>',
			esc_html( $label )
		);
	}

	/**
	 * Submissions column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_submissions( $item ) {
		$count = FPLANT_Database::get_submissions_count( $item['id'] );
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=fplant-submissions&form_id=' . $item['id'] ) ),
			number_format_i18n( $count )
		);
	}

	/**
	 * Author column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_author( $item ) {
		$user = get_user_by( 'id', $item['author'] );
		if ( $user ) {
			return esc_html( $user->display_name );
		}
		return '&mdash;';
	}

	/**
	 * Date column
	 *
	 * @param array $item Item
	 * @return string
	 */
	public function column_date( $item ) {
		$date = mysql2date( get_option( 'date_format' ), $item['date'] );
		$time = mysql2date( get_option( 'time_format' ), $item['date'] );
		return sprintf(
			'%s<br><abbr title="%s %s">%s</abbr>',
			esc_html( $date ),
			esc_attr( $date ),
			esc_attr( $time ),
			esc_html( $time )
		);
	}

	/**
	 * Default column
	 *
	 * @param array  $item        Item
	 * @param string $column_name Column name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Message when no items
	 */
	public function no_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state
		$current_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : 'all';
		if ( 'trash' === $current_status ) {
			esc_html_e( 'No forms found in Trash.', 'form-plant' );
		} else {
			esc_html_e( 'No forms found.', 'form-plant' );
		}
	}

	/**
	 * Output inline edit form
	 */
	public function inline_edit() {
		?>
		<form method="post" class="fplant-quick-edit-form" style="display:none;">
			<?php wp_nonce_field( 'fplant_quick_edit', 'fplant_quick_edit_nonce' ); ?>
			<input type="hidden" name="form_id" value="">
			<table style="display: none;">
				<tbody>
					<tr id="fplant-inline-edit" class="inline-edit-row inline-edit-row-post quick-edit-row">
						<td colspan="<?php echo esc_attr( count( $this->get_columns() ) ); ?>" class="colspanchange">
							<div class="inline-edit-wrapper">
								<fieldset class="inline-edit-col-left">
									<legend class="inline-edit-legend"><?php esc_html_e( 'Quick Edit', 'form-plant' ); ?></legend>
									<div class="inline-edit-col">
										<label>
											<span class="title"><?php esc_html_e( 'Title', 'form-plant' ); ?></span>
											<span class="input-text-wrap"><input type="text" name="post_title" class="ptitle" value=""></span>
										</label>
									</div>
								</fieldset>
								<fieldset class="inline-edit-col-right">
									<div class="inline-edit-col">
										<label>
											<span class="title"><?php esc_html_e( 'Status', 'form-plant' ); ?></span>
											<select name="post_status">
												<option value="publish"><?php esc_html_e( 'Published', 'form-plant' ); ?></option>
												<option value="private"><?php esc_html_e( 'Private', 'form-plant' ); ?></option>
												<option value="draft"><?php esc_html_e( 'Draft', 'form-plant' ); ?></option>
												<option value="pending"><?php esc_html_e( 'Pending Review', 'form-plant' ); ?></option>
											</select>
										</label>
									</div>
								</fieldset>
								<div class="submit inline-edit-save">
									<button type="button" class="button cancel alignleft"><?php esc_html_e( 'Cancel', 'form-plant' ); ?></button>
									<button type="button" class="button button-primary save alignright"><?php esc_html_e( 'Update', 'form-plant' ); ?></button>
									<span class="spinner"></span>
									<br class="clear">
								</div>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</form>
		<?php
	}
}
