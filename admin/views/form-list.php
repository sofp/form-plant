<?php
/**
 * Form list page
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Form List', 'form-plant' ); ?></h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=fplant-form-new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New Form', 'form-plant' ); ?>
	</a>

	<hr class="wp-header-end">

	<?php
	// Notification messages - read-only display, values sanitized with absint()/sanitize_text_field()
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification display
	$fplant_trashed_count = isset( $_GET['trashed'] ) ? absint( wp_unslash( $_GET['trashed'] ) ) : 0;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification display
	$fplant_restored_count = isset( $_GET['restored'] ) ? absint( wp_unslash( $_GET['restored'] ) ) : 0;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification display
	$fplant_deleted_count = isset( $_GET['deleted'] ) ? absint( wp_unslash( $_GET['deleted'] ) ) : 0;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification display
	$fplant_duplicated = isset( $_GET['duplicated'] );

	if ( $fplant_trashed_count > 0 ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of forms */
					_n( '%d form moved to Trash.', '%d forms moved to Trash.', $fplant_trashed_count, 'form-plant' ),
					$fplant_trashed_count
				)
			)
		);
	}

	if ( $fplant_restored_count > 0 ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of forms */
					_n( '%d form restored.', '%d forms restored.', $fplant_restored_count, 'form-plant' ),
					$fplant_restored_count
				)
			)
		);
	}

	if ( $fplant_deleted_count > 0 ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of forms */
					_n( '%d form permanently deleted.', '%d forms permanently deleted.', $fplant_deleted_count, 'form-plant' ),
					$fplant_deleted_count
				)
			)
		);
	}

	if ( $fplant_duplicated ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Form duplicated.', 'form-plant' ) . '</p></div>';
	}
	?>

	<?php $list_table->views(); ?>

	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state preservation
	$fplant_current_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
	?>
	<form id="forms-filter" method="post">
		<input type="hidden" name="page" value="fplant-forms">
		<?php if ( ! empty( $fplant_current_status ) ) : ?>
			<input type="hidden" name="post_status" value="<?php echo esc_attr( $fplant_current_status ); ?>">
		<?php endif; ?>
		<?php wp_nonce_field( 'fplant_bulk_action', '_wpnonce_bulk' ); ?>

		<?php $list_table->search_box( __( 'Search Forms', 'form-plant' ), 'form' ); ?>
		<?php $list_table->display(); ?>
	</form>

	<?php $list_table->inline_edit(); ?>
</div>
