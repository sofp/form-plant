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
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'form',
				'plural'   => 'forms',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Column definitions
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'title'       => __( 'Form Name', 'form-plant' ),
			'shortcode'   => __( 'Shortcode', 'form-plant' ),
			'submissions' => __( 'Submissions', 'form-plant' ),
			'author'      => __( 'Author', 'form-plant' ),
			'date'        => __( 'Date', 'form-plant' ),
		);
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
		$per_page     = 20;
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

		foreach ( $query->posts as $post ) {
			$this->items[] = array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'status'     => $post->post_status,
				'author'     => $post->post_author,
				'date'       => $post->post_date,
			);
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

		// Status display
		$status_label = '';
		if ( 'publish' !== $item['status'] ) {
			$status_labels = array(
				'private' => __( 'Private', 'form-plant' ),
				'draft'   => __( 'Draft', 'form-plant' ),
				'pending' => __( 'Pending Review', 'form-plant' ),
				'trash'   => __( 'Trash', 'form-plant' ),
			);
			if ( isset( $status_labels[ $item['status'] ] ) ) {
				$status_label = ' &mdash; ' . $status_labels[ $item['status'] ];
			}
		}

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
