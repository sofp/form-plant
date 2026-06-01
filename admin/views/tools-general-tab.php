<?php
/**
 * Tools page — General tab (Import / Export).
 *
 * Rendered as a partial from admin/views/tools.php.
 *
 * @package Form_Plant
 *
 * @var array $fplant_forms List of forms passed from FPLANT_Admin::render_tools_page().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="fplant-card">
	<div class="fplant-card-header">
		<?php esc_html_e( 'Export', 'form-plant' ); ?>
	</div>

	<div class="fplant-card-body" style="padding: 16px 20px;">
		<p><?php esc_html_e( 'Export form settings as a JSON file.', 'form-plant' ); ?></p>

		<div id="fplant-export-options" style="margin: 16px 0;">
			<label style="display: block; margin-bottom: 8px;">
				<input type="radio" name="fplant_export_scope" value="all" checked>
				<?php esc_html_e( 'All forms', 'form-plant' ); ?>
			</label>
			<label style="display: block; margin-bottom: 8px;">
				<input type="radio" name="fplant_export_scope" value="selected">
				<?php esc_html_e( 'Selected forms', 'form-plant' ); ?>
			</label>

			<div id="fplant-export-form-list" style="margin-left: 24px; display: none;">
				<?php if ( ! empty( $fplant_forms ) ) : ?>
					<?php foreach ( $fplant_forms as $fplant_form ) : ?>
						<label style="display: block; margin-bottom: 4px;">
							<input type="checkbox" name="fplant_export_form_ids[]" value="<?php echo esc_attr( $fplant_form['id'] ); ?>">
							<?php echo esc_html( $fplant_form['title'] ); ?>
							<span style="color: #999;">(ID: <?php echo esc_html( $fplant_form['id'] ); ?>)</span>
						</label>
					<?php endforeach; ?>
				<?php else : ?>
					<p style="color: #999;"><?php esc_html_e( 'No forms found.', 'form-plant' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div id="fplant-export-message" class="notice" style="display: none;"></div>

		<button type="button" id="fplant-export-btn" class="button button-primary" <?php echo empty( $fplant_forms ) ? 'disabled' : ''; ?>>
			<?php esc_html_e( 'Export', 'form-plant' ); ?>
		</button>
	</div>
</div>

<div class="fplant-card">
	<div class="fplant-card-header">
		<?php esc_html_e( 'Import', 'form-plant' ); ?>
	</div>

	<div class="fplant-card-body" style="padding: 16px 20px;">
		<p><?php esc_html_e( 'Import form settings from a JSON file.', 'form-plant' ); ?></p>
		<p class="description"><?php esc_html_e( 'Imported forms are always created as new forms.', 'form-plant' ); ?></p>

		<div style="margin: 16px 0;">
			<input type="file" id="fplant-import-file" accept=".json">
		</div>

		<div id="fplant-import-message" class="notice" style="display: none;"></div>

		<button type="button" id="fplant-import-btn" class="button button-primary">
			<?php esc_html_e( 'Import', 'form-plant' ); ?>
		</button>
	</div>
</div>
