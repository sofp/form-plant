<?php
/**
 * Submission list page
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap fplant-admin-page">
	<div class="fplant-page-header">
		<h1><?php esc_html_e( 'Submissions', 'form-plant' ); ?></h1>
	</div>

	<div class="fplant-card">
		<div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
			<div>
				<label style="display: block; margin-bottom: 5px;"><?php esc_html_e( 'Filter by Form', 'form-plant' ); ?></label>
				<select class="fplant-form-control" id="fplant-form-filter" style="min-width: 180px;">
					<option value=""><?php esc_html_e( 'All Forms', 'form-plant' ); ?></option>
					<?php foreach ( $fplant_forms as $fplant_filter_form ) : ?>
						<option value="<?php echo esc_attr( $fplant_filter_form['id'] ); ?>" <?php selected( $fplant_form_id, $fplant_filter_form['id'] ); ?>>
							<?php echo esc_html( $fplant_filter_form['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label style="display: block; margin-bottom: 5px;"><?php esc_html_e( 'Start Date', 'form-plant' ); ?></label>
				<input type="date" class="fplant-form-control" id="fplant-date-from" value="<?php echo esc_attr( $fplant_date_from ); ?>" style="width: 140px;">
			</div>
			<div>
				<label style="display: block; margin-bottom: 5px;"><?php esc_html_e( 'End Date', 'form-plant' ); ?></label>
				<input type="date" class="fplant-form-control" id="fplant-date-to" value="<?php echo esc_attr( $fplant_date_to ); ?>" style="width: 140px;">
			</div>
			<div>
				<label style="display: block; margin-bottom: 5px;"><?php esc_html_e( 'Search', 'form-plant' ); ?></label>
				<input type="text" class="fplant-form-control" id="fplant-search" value="<?php echo esc_attr( $fplant_search ); ?>" placeholder="<?php esc_attr_e( 'Search in submission data...', 'form-plant' ); ?>" style="width: 200px;">
			</div>
			<div>
				<button type="button" class="fplant-button" id="fplant-search-button"><?php esc_html_e( 'Search', 'form-plant' ); ?></button>
			</div>
		</div>

		<div style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
			<?php
			$fplant_export_url = admin_url( 'admin-ajax.php?action=fplant_export_submissions&nonce=' . wp_create_nonce( 'fplant_admin_nonce' ) );
			if ( $fplant_form_id ) {
				$fplant_export_url = add_query_arg( 'form_id', $fplant_form_id, $fplant_export_url );
			}
			if ( $fplant_date_from ) {
				$fplant_export_url = add_query_arg( 'date_from', $fplant_date_from, $fplant_export_url );
			}
			if ( $fplant_date_to ) {
				$fplant_export_url = add_query_arg( 'date_to', $fplant_date_to, $fplant_export_url );
			}
			if ( $fplant_search ) {
				$fplant_export_url = add_query_arg( 'search', $fplant_search, $fplant_export_url );
			}
			?>
			<a href="<?php echo esc_url( $fplant_export_url ); ?>" class="fplant-button">
				<?php esc_html_e( 'Export CSV', 'form-plant' ); ?>
			</a>
			<button type="button" id="fplant-bulk-delete" class="fplant-button fplant-button-danger">
				<?php esc_html_e( 'Delete Selected', 'form-plant' ); ?>
			</button>
		</div>
	</div>

	<?php if ( empty( $fplant_submissions ) ) : ?>
		<div class="fplant-card">
			<p><?php esc_html_e( 'No submissions yet.', 'form-plant' ); ?></p>
		</div>
	<?php else : ?>
		<?php if ( $fplant_total_items > 0 ) : ?>
			<div class="fplant-pagination-info" style="margin-bottom: 10px;">
				<?php
				$fplant_start_item = $fplant_offset + 1;
				$fplant_end_item   = min( $fplant_offset + $fplant_per_page, $fplant_total_items );
				printf(
					/* translators: 1: first item number, 2: last item number, 3: total items */
					esc_html__( '%1$d-%2$d of %3$d items', 'form-plant' ),
					absint( $fplant_start_item ),
					absint( $fplant_end_item ),
					absint( $fplant_total_items )
				);
				?>
			</div>
		<?php endif; ?>
		<table class="fplant-table">
			<thead>
				<tr>
					<th style="width: 40px;">
						<input type="checkbox" id="fplant-select-all">
					</th>
					<th><?php esc_html_e( 'ID', 'form-plant' ); ?></th>
					<th><?php esc_html_e( 'Form', 'form-plant' ); ?></th>
					<?php if ( $fplant_column_options['file'] ) : ?>
						<th style="width: 50px;"><?php esc_html_e( 'File', 'form-plant' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Date', 'form-plant' ); ?></th>
					<?php if ( $fplant_column_options['ip_address'] ) : ?>
						<th><?php esc_html_e( 'IP Address', 'form-plant' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Actions', 'form-plant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $fplant_submissions as $fplant_submission ) : ?>
					<?php
					$fplant_submission_form = FPLANT_Database::get_form( $fplant_submission['form_id'] );

					// File-related check (only execute when column is displayed)
					// Determine from submission data itself (not from form definition)
					$fplant_has_file_field = false;
					$fplant_has_file       = false;
					if ( $fplant_column_options['file'] && ! empty( $fplant_submission['data'] ) && is_array( $fplant_submission['data'] ) ) {
						foreach ( $fplant_submission['data'] as $fplant_value ) {
							// Check file field structure (array with url and filename keys)
							if ( is_array( $fplant_value ) && array_key_exists( 'url', $fplant_value ) && array_key_exists( 'filename', $fplant_value ) ) {
								$fplant_has_file_field = true;
								// File is uploaded if URL is not empty
								if ( ! empty( $fplant_value['url'] ) ) {
									$fplant_has_file = true;
									break;
								}
							}
						}
					}
					?>
					<tr>
						<td>
							<input type="checkbox" class="fplant-submission-checkbox" value="<?php echo esc_attr( $fplant_submission['id'] ); ?>">
						</td>
						<td><?php echo esc_html( $fplant_submission['id'] ); ?></td>
						<td>
							<?php
							if ( $fplant_submission_form ) {
								echo esc_html( $fplant_submission_form['title'] );
							}
							?>
						</td>
						<?php if ( $fplant_column_options['file'] ) : ?>
							<td>
								<?php if ( $fplant_has_file ) : ?>
									<span class="dashicons dashicons-paperclip" title="<?php esc_attr_e( 'Contains file attachment', 'form-plant' ); ?>"></span>
								<?php elseif ( $fplant_has_file_field ) : ?>
									<span aria-hidden="true">—</span>
								<?php endif; ?>
							</td>
						<?php endif; ?>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $fplant_submission['created_at'] ) ); ?></td>
						<?php if ( $fplant_column_options['ip_address'] ) : ?>
							<td><?php echo esc_html( $fplant_submission['ip_address'] ); ?></td>
						<?php endif; ?>
						<td>
							<button type="button" class="button fplant-view-submission" data-submission-id="<?php echo esc_attr( $fplant_submission['id'] ); ?>">
								<?php esc_html_e( 'View', 'form-plant' ); ?>
							</button>
							<button type="button" class="button fplant-delete-submission" data-submission-id="<?php echo esc_attr( $fplant_submission['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'form-plant' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $fplant_total_pages > 1 ) : ?>
			<div class="fplant-pagination" style="margin-top: 20px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap;">
				<?php
				$fplant_base_url = admin_url( 'admin.php?page=fplant-submissions' );
				if ( $fplant_form_id ) {
					$fplant_base_url = add_query_arg( 'form_id', $fplant_form_id, $fplant_base_url );
				}
				if ( $fplant_date_from ) {
					$fplant_base_url = add_query_arg( 'date_from', $fplant_date_from, $fplant_base_url );
				}
				if ( $fplant_date_to ) {
					$fplant_base_url = add_query_arg( 'date_to', $fplant_date_to, $fplant_base_url );
				}
				if ( $fplant_search ) {
					$fplant_base_url = add_query_arg( 'search', $fplant_search, $fplant_base_url );
				}

				// Previous page
				if ( $fplant_current_page > 1 ) :
					$fplant_prev_url = add_query_arg( 'paged', $fplant_current_page - 1, $fplant_base_url );
					?>
					<a href="<?php echo esc_url( $fplant_prev_url ); ?>" class="button">&laquo; <?php esc_html_e( 'Previous', 'form-plant' ); ?></a>
				<?php else : ?>
					<span class="button disabled">&laquo; <?php esc_html_e( 'Previous', 'form-plant' ); ?></span>
				<?php endif; ?>

				<?php
				// Page number links
				$fplant_range = 2;
				for ( $fplant_i = 1; $fplant_i <= $fplant_total_pages; $fplant_i++ ) :
					// Limit displayed page numbers
					if ( $fplant_i === 1 || $fplant_i === $fplant_total_pages || ( $fplant_i >= $fplant_current_page - $fplant_range && $fplant_i <= $fplant_current_page + $fplant_range ) ) :
						if ( $fplant_i === $fplant_current_page ) :
							?>
							<span class="button button-primary"><?php echo esc_html( $fplant_i ); ?></span>
						<?php else :
							$fplant_page_url = add_query_arg( 'paged', $fplant_i, $fplant_base_url );
							?>
							<a href="<?php echo esc_url( $fplant_page_url ); ?>" class="button"><?php echo esc_html( $fplant_i ); ?></a>
							<?php
						endif;
					elseif ( $fplant_i === $fplant_current_page - $fplant_range - 1 || $fplant_i === $fplant_current_page + $fplant_range + 1 ) :
						?>
						<span class="button disabled">...</span>
						<?php
					endif;
				endfor;
				?>

				<?php
				// Next page
				if ( $fplant_current_page < $fplant_total_pages ) :
					$fplant_next_url = add_query_arg( 'paged', $fplant_current_page + 1, $fplant_base_url );
					?>
					<a href="<?php echo esc_url( $fplant_next_url ); ?>" class="button"><?php esc_html_e( 'Next', 'form-plant' ); ?> &raquo;</a>
				<?php else : ?>
					<span class="button disabled"><?php esc_html_e( 'Next', 'form-plant' ); ?> &raquo;</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<!-- Detail modal -->
<div id="fplant-submission-detail-modal" class="fplant-modal">
	<div class="fplant-modal-content" style="max-width: 800px;">
		<div class="fplant-modal-header">
			<h2><?php esc_html_e( 'Submission Details', 'form-plant' ); ?></h2>
			<button type="button" class="fplant-modal-close">&times;</button>
		</div>
		<div class="fplant-modal-body" id="fplant-submission-detail-content">
			<p><?php esc_html_e( 'Loading...', 'form-plant' ); ?></p>
		</div>
		<div class="fplant-modal-footer">
			<button type="button" class="button fplant-modal-close"><?php esc_html_e( 'Close', 'form-plant' ); ?></button>
		</div>
	</div>
</div>
