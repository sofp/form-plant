<?php
/**
 * Form edit page
 *
 * Note: This file uses conditional output of literal strings (e.g., 'disabled', 'readonly', ' fplant-disabled')
 * for HTML attributes. These are not escaped as they are static string constants, not dynamic user input.
 * All dynamic values are properly escaped with esc_attr(), esc_html(), etc.
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fplant_is_new = empty( $fplant_form['id'] );
$fplant_page_title = $fplant_is_new ? __( 'New Form', 'form-plant' ) : __( 'Edit Form', 'form-plant' );

// Handle save message
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification display from URL params
$fplant_message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

// Check upload directory for forms with file fields
$fplant_upload_dir_warning = false;
if ( ! $fplant_is_new && ! empty( $fplant_form['fields'] ) ) {
	foreach ( $fplant_form['fields'] as $fplant_field ) {
		if ( isset( $fplant_field['type'] ) && 'file' === $fplant_field['type'] ) {
			$fplant_upload_dir = wp_upload_dir();
			$fplant_dir        = $fplant_upload_dir['basedir'] . '/fplant_uploads';
			if ( ! file_exists( $fplant_dir ) && ! wp_mkdir_p( $fplant_dir ) ) {
				$fplant_upload_dir_warning = true;
			}
			break;
		}
	}
}
?>

<div class="wrap fplant-admin-page">
	<h1><?php echo esc_html( $fplant_page_title ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( 'updated' === $fplant_message ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Form updated.', 'form-plant' ); ?></p>
	</div>
	<?php elseif ( 'created' === $fplant_message ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Form created.', 'form-plant' ); ?></p>
	</div>
	<?php endif; ?>

	<?php if ( $fplant_upload_dir_warning ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'Warning: Could not create the upload directory. Please check the server permissions for wp-content/uploads.', 'form-plant' ); ?></p>
	</div>
	<?php endif; ?>

	<div class="fplant-form-editor">
		<div class="fplant-form-title">
			<input
				type="text"
				class="fplant-form-control fplant-form-title-input"
				placeholder="<?php esc_attr_e( 'Enter form title', 'form-plant' ); ?>"
				value="<?php echo esc_attr( $fplant_form['title'] ?? '' ); ?>"
				style="font-size: 20px; font-weight: 600;"
			>
		</div>

		<!-- Publish settings block -->
		<div class="fplant-publish-box">
			<div class="fplant-publish-box-left">
				<select class="fplant-form-status">
					<option value="publish" <?php selected( $fplant_form['status'] ?? 'publish', 'publish' ); ?>><?php esc_html_e( 'Published', 'form-plant' ); ?></option>
					<option value="private" <?php selected( $fplant_form['status'] ?? '', 'private' ); ?>><?php esc_html_e( 'Private', 'form-plant' ); ?></option>
					<option value="draft" <?php selected( $fplant_form['status'] ?? '', 'draft' ); ?>><?php esc_html_e( 'Draft', 'form-plant' ); ?></option>
					<option value="pending" <?php selected( $fplant_form['status'] ?? '', 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'form-plant' ); ?></option>
				</select>
				<?php if ( ! $fplant_is_new ) : ?>
				<button type="button" class="fplant-button-link fplant-delete-form-edit" data-form-id="<?php echo esc_attr( absint( $fplant_form['id'] ) ); ?>">
					<?php esc_html_e( 'Move to Trash', 'form-plant' ); ?>
				</button>
				<code class="fplant-shortcode-code">[fplant id="<?php echo esc_attr( absint( $fplant_form['id'] ) ); ?>"]</code>
				<button type="button" class="button button-small fplant-copy-button" data-copy='[fplant id="<?php echo esc_attr( absint( $fplant_form['id'] ) ); ?>"]'>
					<?php esc_html_e( 'Copy', 'form-plant' ); ?>
				</button>
				<?php endif; ?>
			</div>
			<div class="fplant-publish-box-right">
				<?php if ( ! $fplant_is_new ) : ?>
				<button type="button" class="fplant-button-preview fplant-preview-form" data-form-id="<?php echo esc_attr( absint( $fplant_form['id'] ) ); ?>">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Preview', 'form-plant' ); ?></span>
				</button>
				<?php endif; ?>
				<button type="button" class="fplant-button fplant-save-form" data-form-id="<?php echo esc_attr( absint( $fplant_form['id'] ?? 0 ) ); ?>">
					<?php echo $fplant_is_new ? esc_html__( 'Publish', 'form-plant' ) : esc_html__( 'Update', 'form-plant' ); ?>
				</button>
			</div>
		</div>

		<div class="fplant-tabs">
			<button type="button" class="fplant-tab active" data-tab="tab-fields">
				<?php esc_html_e( 'Field Settings', 'form-plant' ); ?>
			</button>
			<button type="button" class="fplant-tab" data-tab="tab-layout">
				<?php esc_html_e( 'Layout', 'form-plant' ); ?>
			</button>
			<button type="button" class="fplant-tab" data-tab="tab-design">
				<?php esc_html_e( 'Design', 'form-plant' ); ?>
			</button>
			<button type="button" class="fplant-tab" data-tab="tab-email">
				<?php esc_html_e( 'Email Settings', 'form-plant' ); ?>
			</button>
			<button type="button" class="fplant-tab" data-tab="tab-settings">
				<?php esc_html_e( 'Form Settings', 'form-plant' ); ?>
			</button>
			<button type="button" class="fplant-tab" data-tab="tab-spam">
				<?php esc_html_e( 'Spam Protection', 'form-plant' ); ?>
			</button>
		</div>

		<!-- Field Settings tab -->
		<div id="tab-fields" class="fplant-tab-content active">
			<div class="fplant-card">
				<div style="text-align: right; margin-bottom: 20px;">
					<button type="button" class="fplant-button fplant-add-field">
						<?php esc_html_e( '+ Add Field', 'form-plant' ); ?>
					</button>
				</div>

				<!-- Field rows (accordion) are rendered by admin.js renderFieldList() on load. -->
				<div class="fplant-field-list"></div>

				<div style="text-align: right; margin-top: 20px;">
					<?php // Hidden by admin.js for short field lists (renderFieldList). ?>
					<button type="button" class="fplant-button fplant-add-field fplant-add-field-bottom">
						<?php esc_html_e( '+ Add Field', 'form-plant' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Layout tab -->
		<div id="tab-layout" class="fplant-tab-content">
			<!-- Input screen section -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Input Screen', 'form-plant' ); ?>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Form Tag Settings', 'form-plant' ); ?></label>
					<div style="display: flex; gap: 16px; flex-wrap: wrap;">
						<div style="flex: 1; min-width: 200px;">
							<label for="fplant-form-tag-class" style="font-weight: normal; font-size: 13px;">
								<?php esc_html_e( 'CSS Class', 'form-plant' ); ?>
							</label>
							<input
								type="text"
								id="fplant-form-tag-class"
								class="fplant-form-control"
								value="<?php echo esc_attr( $fplant_form['settings']['form_tag_class'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g., my-form custom-form', 'form-plant' ); ?>"
							>
							<p class="description"><?php esc_html_e( 'Added to the default fplant-form class', 'form-plant' ); ?></p>
						</div>
						<div style="flex: 1; min-width: 200px;">
							<label for="fplant-form-tag-id" style="font-weight: normal; font-size: 13px;">
								<?php esc_html_e( 'ID', 'form-plant' ); ?>
							</label>
							<input
								type="text"
								id="fplant-form-tag-id"
								class="fplant-form-control"
								value="<?php echo esc_attr( $fplant_form['settings']['form_tag_id'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g., my-contact-form', 'form-plant' ); ?>"
							>
						</div>
					</div>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Submit Button Settings', 'form-plant' ); ?></label>
					<div style="display: flex; align-items: center; gap: 10px;">
						<span class="fplant-input-submit-preview">
							<?php echo esc_html( $fplant_form['settings']['input_submit_text'] ?? __( 'Submit', 'form-plant' ) ); ?>
						</span>
						<button type="button" class="button fplant-edit-input-submit">
							<?php esc_html_e( 'Edit', 'form-plant' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Configure submit button text, CSS class, and ID', 'form-plant' ); ?></p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Required Mark', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-setting-required-mark"
						value="<?php echo esc_attr( $fplant_form['settings']['required_mark_text'] ?? '*' ); ?>"
						placeholder="*"
						style="width: 150px;"
					>
					<p class="description"><?php esc_html_e( 'Text displayed next to the label of required fields (e.g., *, Required)', 'form-plant' ); ?></p>
				</div>
			</div>

			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Input Screen HTML Template', 'form-plant' ); ?>
				</div>

				<div class="fplant-checkbox">
					<input
						type="checkbox"
						id="use-html-template"
						class="fplant-setting-use-html-template"
						<?php checked( ! empty( $fplant_form['settings']['use_html_template'] ) ); ?>
					>
					<label for="use-html-template">
						<?php esc_html_e( 'Use HTML template', 'form-plant' ); ?>
					</label>
				</div>

				<div class="fplant-html-template-fields<?php echo empty( $fplant_form['settings']['use_html_template'] ) ? ' fplant-disabled' : ''; ?>">
					<div class="fplant-form-group">
						<label><?php esc_html_e( 'Available Shortcodes', 'form-plant' ); ?></label>
						<div class="fplant-tag-inserter">
							<select class="fplant-form-control fplant-tag-select" <?php echo empty( $fplant_form['settings']['use_html_template'] ) ? 'disabled' : ''; ?>>
								<option value=""><?php esc_html_e( '-- Select tag --', 'form-plant' ); ?></option>
								<optgroup label="<?php esc_attr_e( 'Basic Tags', 'form-plant' ); ?>">
									<option value='[fplant_submit text="Submit"]'>[fplant_submit] - <?php esc_html_e( 'Submit button', 'form-plant' ); ?></option>
									<option value="[fplant_errors]">[fplant_errors] - <?php esc_html_e( 'Error display area', 'form-plant' ); ?></option>
									<option value="[fplant_success]">[fplant_success] - <?php esc_html_e( 'Success display area', 'form-plant' ); ?></option>
								</optgroup>
								<?php if ( ! empty( $fplant_form['fields'] ) ) : ?>
								<optgroup label="<?php esc_attr_e( 'Field Tags', 'form-plant' ); ?> (* = <?php esc_attr_e( 'Required', 'form-plant' ); ?>)">
									<?php foreach ( $fplant_form['fields'] as $fplant_field ) : ?>
									<?php $fplant_required_mark = ! empty( $fplant_field['required'] ) ? '* ' : ''; ?>
									<option value='[fplant_field name="<?php echo esc_attr( $fplant_field['name'] ); ?>"]' data-required="<?php echo ! empty( $fplant_field['required'] ) ? '1' : '0'; ?>"><?php echo esc_html( $fplant_required_mark ); ?>[fplant_field name="<?php echo esc_attr( $fplant_field['name'] ); ?>"] - <?php echo esc_html( $fplant_field['label'] ?? $fplant_field['name'] ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<optgroup label="<?php esc_attr_e( 'Error Message Tags', 'form-plant' ); ?>">
									<?php foreach ( $fplant_form['fields'] as $fplant_field ) : ?>
									<option value='[fplant_field_error name="<?php echo esc_attr( $fplant_field['name'] ); ?>"]'>[fplant_field_error name="<?php echo esc_attr( $fplant_field['name'] ); ?>"] - <?php echo esc_html( $fplant_field['label'] ?? $fplant_field['name'] ); ?><?php esc_html_e( ' error', 'form-plant' ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php endif; ?>
							</select>
							<button type="button" class="button fplant-insert-tag" <?php echo empty( $fplant_form['settings']['use_html_template'] ) ? 'disabled' : ''; ?>>
								<?php esc_html_e( 'Insert', 'form-plant' ); ?>
							</button>
						</div>
						<?php if ( empty( $fplant_form['fields'] ) ) : ?>
						<p class="description"><em><?php esc_html_e( 'Field tags will be available after adding fields.', 'form-plant' ); ?></em></p>
						<?php endif; ?>
						<p class="description" style="color: #d63638;"><strong><?php esc_html_e( '* Required fields (*) and submit button must be included in the template.', 'form-plant' ); ?></strong></p>
					</div>

					<textarea
						class="fplant-form-control fplant-html-template"
						rows="15"
						placeholder="<?php esc_attr_e( 'Enter HTML template (default layout will be used if empty)', 'form-plant' ); ?>"
						style="font-family: monospace; font-size: 13px;"
						<?php echo empty( $fplant_form['settings']['use_html_template'] ) ? 'readonly' : ''; ?>
					><?php echo esc_textarea( $fplant_form['html_template'] ?? '' ); ?></textarea>
				</div>
			</div>

			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Confirmation Screen Settings', 'form-plant' ); ?>
				</div>

				<div class="fplant-checkbox">
					<input
						type="checkbox"
						id="use-confirmation"
						class="fplant-setting-use-confirmation"
						<?php checked( ! empty( $fplant_form['settings']['use_confirmation'] ) ); ?>
					>
					<label for="use-confirmation">
						<?php esc_html_e( 'Show confirmation screen before submission', 'form-plant' ); ?>
					</label>
				</div>

				<div class="fplant-confirmation-fields<?php echo empty( $fplant_form['settings']['use_confirmation'] ) ? ' fplant-disabled' : ''; ?>">
					<div class="fplant-form-group">
						<label><?php esc_html_e( 'Confirmation Screen Title', 'form-plant' ); ?></label>
						<input
							type="text"
							class="fplant-form-control fplant-setting-confirmation-title"
							value="<?php echo esc_attr( $fplant_form['settings']['confirmation_title'] ?? __( 'Confirm Your Input', 'form-plant' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Confirm Your Input', 'form-plant' ); ?>"
							<?php echo empty( $fplant_form['settings']['use_confirmation'] ) ? 'readonly' : ''; ?>
						>
					</div>

					<div class="fplant-form-group">
						<label><?php esc_html_e( 'Confirmation Screen Description', 'form-plant' ); ?></label>
						<textarea
							class="fplant-form-control fplant-setting-confirmation-message"
							rows="3"
							placeholder="<?php esc_attr_e( 'If the information below is correct, please click the "Submit" button.', 'form-plant' ); ?>"
							<?php echo empty( $fplant_form['settings']['use_confirmation'] ) ? 'readonly' : ''; ?>
						><?php echo esc_textarea( $fplant_form['settings']['confirmation_message'] ?? __( 'If the information below is correct, please click the "Submit" button.', 'form-plant' ) ); ?></textarea>
					</div>

					<div class="fplant-checkbox" style="margin-top: 20px;">
						<input
							type="checkbox"
							id="use-confirmation-template"
							class="fplant-setting-use-confirmation-template"
							<?php checked( ! empty( $fplant_form['settings']['use_confirmation_template'] ) ); ?>
							<?php echo empty( $fplant_form['settings']['use_confirmation'] ) ? 'disabled' : ''; ?>
						>
						<label for="use-confirmation-template">
							<?php esc_html_e( 'Use confirmation screen HTML template', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-confirmation-template-fields<?php echo empty( $fplant_form['settings']['use_confirmation_template'] ) ? ' fplant-disabled' : ''; ?>">
						<div class="fplant-form-group">
							<label><?php esc_html_e( 'Available Shortcodes', 'form-plant' ); ?></label>
							<div class="fplant-tag-inserter">
								<select class="fplant-form-control fplant-confirmation-tag-select" <?php echo empty( $fplant_form['settings']['use_confirmation_template'] ) ? 'disabled' : ''; ?>>
									<option value=""><?php esc_html_e( '-- Select tag --', 'form-plant' ); ?></option>
									<optgroup label="<?php esc_attr_e( 'Basic Tags', 'form-plant' ); ?>">
										<option value="[fplant_confirmation_title]">[fplant_confirmation_title] - <?php esc_html_e( 'Title', 'form-plant' ); ?></option>
										<option value="[fplant_confirmation_message]">[fplant_confirmation_message] - <?php esc_html_e( 'Message', 'form-plant' ); ?></option>
										<option value="[fplant_all_fields]">[fplant_all_fields] - <?php esc_html_e( 'All fields table', 'form-plant' ); ?></option>
										<option value='[fplant_back text="<?php esc_attr_e( 'Back', 'form-plant' ); ?>"]'>[fplant_back] - <?php esc_html_e( 'Back button', 'form-plant' ); ?></option>
										<option value='[fplant_confirm_submit text="<?php esc_attr_e( 'Submit', 'form-plant' ); ?>"]'>[fplant_confirm_submit] - <?php esc_html_e( 'Submit button', 'form-plant' ); ?></option>
									</optgroup>
									<?php if ( ! empty( $fplant_form['fields'] ) ) : ?>
									<optgroup label="<?php esc_attr_e( 'Field Value Tags', 'form-plant' ); ?>">
										<?php foreach ( $fplant_form['fields'] as $fplant_field ) : ?>
										<option value='[fplant_value name="<?php echo esc_attr( $fplant_field['name'] ); ?>"]'>[fplant_value name="<?php echo esc_attr( $fplant_field['name'] ); ?>"] - <?php echo esc_html( $fplant_field['label'] ?? $fplant_field['name'] ); ?></option>
										<?php endforeach; ?>
									</optgroup>
									<?php endif; ?>
								</select>
								<button type="button" class="button fplant-insert-confirmation-tag" <?php echo empty( $fplant_form['settings']['use_confirmation_template'] ) ? 'disabled' : ''; ?>>
									<?php esc_html_e( 'Insert', 'form-plant' ); ?>
								</button>
							</div>
							<?php if ( empty( $fplant_form['fields'] ) ) : ?>
							<p class="description"><em><?php esc_html_e( 'Field value tags will be available after adding fields.', 'form-plant' ); ?></em></p>
							<?php endif; ?>
							<p class="description" style="color: #d63638;"><strong><?php esc_html_e( '* Submit button must be included in the template.', 'form-plant' ); ?></strong></p>
						</div>

						<textarea
							class="fplant-form-control fplant-confirmation-template"
							rows="10"
							placeholder="<?php esc_attr_e( 'Custom HTML template (default template will be used if empty)', 'form-plant' ); ?>"
							style="font-family: monospace; font-size: 13px;"
							<?php echo empty( $fplant_form['settings']['use_confirmation_template'] ) ? 'readonly' : ''; ?>
						><?php echo esc_textarea( $fplant_form['settings']['confirmation_template'] ?? '' ); ?></textarea>
					</div>

					<div class="fplant-form-group">
						<label><?php esc_html_e( 'Back Button Settings', 'form-plant' ); ?></label>
						<div style="display: flex; align-items: center; gap: 10px;">
							<span class="fplant-confirmation-back-preview">
								<?php echo esc_html( $fplant_form['settings']['confirmation_back_text'] ?? __( 'Back', 'form-plant' ) ); ?>
							</span>
							<button type="button" class="button fplant-edit-confirmation-back" <?php echo empty( $fplant_form['settings']['use_confirmation'] ) ? 'disabled' : ''; ?>>
								<?php esc_html_e( 'Edit', 'form-plant' ); ?>
							</button>
						</div>
						<p class="description"><?php esc_html_e( 'Configure back button text, CSS class, and ID', 'form-plant' ); ?></p>
					</div>

					<div class="fplant-form-group">
						<label><?php esc_html_e( 'Submit Button Settings', 'form-plant' ); ?></label>
						<div style="display: flex; align-items: center; gap: 10px;">
							<span class="fplant-confirmation-submit-preview">
								<?php echo esc_html( $fplant_form['settings']['confirmation_submit_text'] ?? __( 'Submit Form', 'form-plant' ) ); ?>
							</span>
							<button type="button" class="button fplant-edit-confirmation-submit" <?php echo empty( $fplant_form['settings']['use_confirmation'] ) ? 'disabled' : ''; ?>>
								<?php esc_html_e( 'Edit', 'form-plant' ); ?>
							</button>
						</div>
						<p class="description"><?php esc_html_e( 'Configure submit button text, CSS class, and ID', 'form-plant' ); ?></p>
					</div>
				</div>
			</div>

		</div>

		<!-- Design tab -->
		<div id="tab-design" class="fplant-tab-content">
			<!-- Form Design Settings -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Form Design', 'form-plant' ); ?>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Default CSS Settings', 'form-plant' ); ?></label>
					<?php
					$fplant_design_types = array(
						'simple1' => __( 'Simple 1 (vertical)', 'form-plant' ),
						'simple2' => __( 'Simple 2 (horizontal)', 'form-plant' ),
						'normal'  => __( 'Normal (decorated)', 'form-plant' ),
						'none'    => __( 'None', 'form-plant' ),
					);
					$fplant_current_design = $fplant_form['settings']['design_type'] ?? 'simple1';
					if ( 'default' === $fplant_current_design ) {
						$fplant_current_design = 'simple1';
					}
					foreach ( $fplant_design_types as $fplant_dt_value => $fplant_dt_label ) :
						?>
						<div class="fplant-radio" style="display: flex; align-items: center; gap: 8px;">
							<input
								type="radio"
								name="design_type"
								value="<?php echo esc_attr( $fplant_dt_value ); ?>"
								id="design-type-<?php echo esc_attr( $fplant_dt_value ); ?>"
								<?php checked( $fplant_current_design === $fplant_dt_value ); ?>
							>
							<label for="design-type-<?php echo esc_attr( $fplant_dt_value ); ?>">
								<?php echo esc_html( $fplant_dt_label ); ?>
							</label>
							<?php if ( 'none' !== $fplant_dt_value ) : ?>
								<a href="#" class="fplant-download-design-css" data-design-type="<?php echo esc_attr( $fplant_dt_value ); ?>" style="font-size: 12px;">
									<?php esc_html_e( 'Download CSS', 'form-plant' ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="fplant-design-adjust"<?php if ( 'none' === $fplant_current_design ) { echo ' style="display: none;"'; } ?>>
					<hr style="border: 0; border-top: 1px solid #dcdcde; margin: 20px 0;">

					<div class="fplant-form-group">
						<label><?php esc_html_e( 'Design Adjustments', 'form-plant' ); ?></label>
						<p class="description" style="margin-bottom: 10px;">
							<?php esc_html_e( 'Adjust colors and sizes visually without writing CSS. Empty fields keep the design preset defaults.', 'form-plant' ); ?>
						</p>

						<?php
						// Current values: settings.design_options (empty string = unset).
						$fplant_design_opts = ( isset( $fplant_form['settings']['design_options'] ) && is_array( $fplant_form['settings']['design_options'] ) )
							? $fplant_form['settings']['design_options']
							: array();

						$fplant_design_val = function ( $fplant_dsection, $fplant_dkey ) use ( $fplant_design_opts ) {
							return isset( $fplant_design_opts[ $fplant_dsection ][ $fplant_dkey ] ) ? (string) $fplant_design_opts[ $fplant_dsection ][ $fplant_dkey ] : '';
						};

						$fplant_design_color = function ( $fplant_dsection, $fplant_dkey, $fplant_dlabel ) use ( $fplant_design_val ) {
							$fplant_did = 'fplant-design-' . $fplant_dsection . '-' . $fplant_dkey;
							?>
							<div class="fplant-design-control">
								<label for="<?php echo esc_attr( $fplant_did ); ?>"><?php echo esc_html( $fplant_dlabel ); ?></label>
								<input
									type="text"
									id="<?php echo esc_attr( $fplant_did ); ?>"
									class="fplant-design-input fplant-design-color"
									data-design-key="<?php echo esc_attr( $fplant_dkey ); ?>"
									value="<?php echo esc_attr( $fplant_design_val( $fplant_dsection, $fplant_dkey ) ); ?>"
								>
							</div>
							<?php
						};

						$fplant_design_number = function ( $fplant_dsection, $fplant_dkey, $fplant_dlabel, $fplant_dmin, $fplant_dmax, $fplant_dslider = false, $fplant_ddefault = null, $fplant_dstep = 1 ) use ( $fplant_design_val ) {
							$fplant_did  = 'fplant-design-' . $fplant_dsection . '-' . $fplant_dkey;
							$fplant_dval = $fplant_design_val( $fplant_dsection, $fplant_dkey );
							// While unset, the range parks at the known preset default (if any) rather than its min.
							$fplant_dpark = null !== $fplant_ddefault ? $fplant_ddefault : $fplant_dmin;
							?>
							<div class="fplant-design-control">
								<label for="<?php echo esc_attr( $fplant_did ); ?>"><?php echo esc_html( $fplant_dlabel ); ?></label>
								<div class="fplant-design-slider-group<?php echo $fplant_dslider ? ' has-range' : ''; ?>">
									<?php if ( $fplant_dslider ) : ?>
										<?php // Pointer-only companion: the number input stays the single data-bound control (empty = preset default). ?>
										<input
											type="range"
											class="fplant-design-range"
											min="<?php echo esc_attr( $fplant_dmin ); ?>"
											max="<?php echo esc_attr( $fplant_dmax ); ?>"
											step="<?php echo esc_attr( $fplant_dstep ); ?>"
											value="<?php echo esc_attr( '' !== $fplant_dval ? $fplant_dval : $fplant_dpark ); ?>"
											<?php if ( null !== $fplant_ddefault ) : ?>
												data-default="<?php echo esc_attr( $fplant_ddefault ); ?>"
											<?php endif; ?>
											aria-hidden="true"
											tabindex="-1"
										>
									<?php endif; ?>
									<?php if ( null !== $fplant_ddefault ) : ?>
										<?php // Show the preset's actual default value so an empty box is not a mystery. ?>
										<span class="fplant-design-default-hint">
											<?php
											/* translators: %s: default value in pixels, e.g. 800 */
											printf( esc_html__( 'Default: %spx', 'form-plant' ), esc_html( $fplant_ddefault ) );
											?>
										</span>
									<?php endif; ?>
									<input
										type="number"
										id="<?php echo esc_attr( $fplant_did ); ?>"
										class="fplant-design-input"
										data-design-key="<?php echo esc_attr( $fplant_dkey ); ?>"
										min="<?php echo esc_attr( $fplant_dmin ); ?>"
										max="<?php echo esc_attr( $fplant_dmax ); ?>"
										step="<?php echo esc_attr( $fplant_dstep ); ?>"
										placeholder="<?php esc_attr_e( 'Default', 'form-plant' ); ?>"
										value="<?php echo esc_attr( $fplant_dval ); ?>"
									>
								</div>
							</div>
							<?php
						};

						$fplant_design_select = function ( $fplant_dsection, $fplant_dkey, $fplant_dlabel, $fplant_dchoices ) use ( $fplant_design_val ) {
							$fplant_did  = 'fplant-design-' . $fplant_dsection . '-' . $fplant_dkey;
							$fplant_dcur = $fplant_design_val( $fplant_dsection, $fplant_dkey );
							?>
							<div class="fplant-design-control">
								<label for="<?php echo esc_attr( $fplant_did ); ?>"><?php echo esc_html( $fplant_dlabel ); ?></label>
								<select id="<?php echo esc_attr( $fplant_did ); ?>" class="fplant-design-input" data-design-key="<?php echo esc_attr( $fplant_dkey ); ?>">
									<?php foreach ( $fplant_dchoices as $fplant_dval => $fplant_dchoice ) : ?>
										<option value="<?php echo esc_attr( $fplant_dval ); ?>" <?php selected( $fplant_dcur, $fplant_dval ); ?>><?php echo esc_html( $fplant_dchoice ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<?php
						};

						$fplant_design_button_controls = function ( $fplant_dsection ) use ( $fplant_design_color, $fplant_design_number, $fplant_design_select ) {
							$fplant_design_color( $fplant_dsection, 'color', __( 'Text Color', 'form-plant' ) );
							$fplant_design_color( $fplant_dsection, 'background', __( 'Background Color', 'form-plant' ) );
							$fplant_design_color( $fplant_dsection, 'border_color', __( 'Border Color', 'form-plant' ) );
							$fplant_design_number( $fplant_dsection, 'border_width', __( 'Border Width (px)', 'form-plant' ), 0, 10, true );
							$fplant_design_color( $fplant_dsection, 'hover_color', __( 'Hover Text Color', 'form-plant' ) );
							$fplant_design_color( $fplant_dsection, 'hover_background', __( 'Hover Background Color', 'form-plant' ) );
							$fplant_design_number( $fplant_dsection, 'border_radius', __( 'Corner Radius (px)', 'form-plant' ), 0, 50, true );
							$fplant_design_number( $fplant_dsection, 'box_shadow', __( 'Shadow', 'form-plant' ), 0, 10, true );
							$fplant_design_number( $fplant_dsection, 'font_size', __( 'Font Size (px)', 'form-plant' ), 10, 32, true );
							$fplant_design_number( $fplant_dsection, 'padding_v', __( 'Vertical Padding (px)', 'form-plant' ), 0, 40, true );
							$fplant_design_number( $fplant_dsection, 'padding_h', __( 'Horizontal Padding (px)', 'form-plant' ), 0, 80, true );
							$fplant_design_select(
								$fplant_dsection,
								'width',
								__( 'Width', 'form-plant' ),
								array(
									''     => __( 'Default', 'form-plant' ),
									'auto' => __( 'Auto', 'form-plant' ),
									'full' => __( 'Full Width (100%)', 'form-plant' ),
								)
							);
						};

						$fplant_design_use_confirmation = ! empty( $fplant_form['settings']['use_confirmation'] );

						$fplant_design_accordion = function ( $fplant_dsection, $fplant_dtitle, $fplant_dcontrols, $fplant_dconfirmation_only = false ) use ( $fplant_design_use_confirmation ) {
							$fplant_dclass = 'fplant-design-accordion';
							if ( $fplant_dconfirmation_only ) {
								$fplant_dclass .= ' fplant-design-confirmation-only';
							}
							?>
							<div class="<?php echo esc_attr( $fplant_dclass ); ?>" data-design-section="<?php echo esc_attr( $fplant_dsection ); ?>"<?php if ( $fplant_dconfirmation_only && ! $fplant_design_use_confirmation ) { echo ' style="display: none;"'; } ?>>
								<button type="button" class="fplant-design-accordion-header" aria-expanded="false" aria-controls="fplant-design-body-<?php echo esc_attr( $fplant_dsection ); ?>">
									<span class="fplant-design-accordion-title"><?php echo esc_html( $fplant_dtitle ); ?></span>
									<span class="dashicons dashicons-arrow-down" aria-hidden="true"></span>
								</button>
								<div class="fplant-design-accordion-body" id="fplant-design-body-<?php echo esc_attr( $fplant_dsection ); ?>" hidden>
									<div class="fplant-design-controls">
										<?php $fplant_dcontrols(); ?>
									</div>
									<div class="fplant-design-preview">
										<div class="fplant-design-preview-host" data-design-section="<?php echo esc_attr( $fplant_dsection ); ?>"></div>
									</div>
									<div class="fplant-design-actions">
										<span class="fplant-design-save-status" role="status"></span>
										<button type="button" class="button fplant-design-reset">
											<?php esc_html_e( 'Reset', 'form-plant' ); ?>
										</button>
										<button type="button" class="button button-primary fplant-design-save">
											<?php esc_html_e( 'Save', 'form-plant' ); ?>
										</button>
									</div>
								</div>
							</div>
							<?php
						};

						$fplant_design_accordion(
							'form',
							__( 'Overall', 'form-plant' ),
							function () use ( $fplant_design_color, $fplant_design_number, $fplant_design_select ) {
								?>
								<h4 class="fplant-design-subheading"><?php esc_html_e( 'Form Frame', 'form-plant' ); ?></h4>
								<?php
								// 800 is the preset default (.fplant-form-wrapper max-width in form.css / design-*.css).
								$fplant_design_number( 'form', 'max_width', __( 'Max Width (px)', 'form-plant' ), 300, 1200, true, 800, 10 );
								$fplant_design_color( 'form', 'background', __( 'Background Color', 'form-plant' ) );
								$fplant_design_color( 'form', 'border_color', __( 'Border Color', 'form-plant' ) );
								$fplant_design_number( 'form', 'border_width', __( 'Border Width (px)', 'form-plant' ), 0, 10, true );
								$fplant_design_number( 'form', 'border_radius', __( 'Corner Radius (px)', 'form-plant' ), 0, 50, true );
								$fplant_design_number( 'form', 'box_shadow', __( 'Shadow', 'form-plant' ), 0, 10, true );
								?>
								<h4 class="fplant-design-subheading"><?php esc_html_e( 'Field Labels', 'form-plant' ); ?></h4>
								<?php
								$fplant_design_color( 'form', 'label_color', __( 'Text Color', 'form-plant' ) );
								$fplant_design_select(
									'form',
									'label_bold',
									__( 'Font Weight', 'form-plant' ),
									array(
										''       => __( 'Default', 'form-plant' ),
										'bold'   => __( 'Bold', 'form-plant' ),
										'normal' => __( 'Normal', 'form-plant' ),
									)
								);
								$fplant_design_color( 'form', 'label_background', __( 'Background Color', 'form-plant' ) );
								$fplant_design_number( 'form', 'label_font_size', __( 'Font Size (px)', 'form-plant' ), 10, 32, true );
								?>
								<h4 class="fplant-design-subheading"><?php esc_html_e( 'Field Descriptions', 'form-plant' ); ?></h4>
								<?php
								$fplant_design_color( 'form', 'desc_color', __( 'Text Color', 'form-plant' ) );
								$fplant_design_number( 'form', 'desc_font_size', __( 'Font Size (px)', 'form-plant' ), 10, 32, true );
							}
						);

						$fplant_design_accordion(
							'input',
							__( 'Input Fields', 'form-plant' ),
							function () use ( $fplant_design_color, $fplant_design_number ) {
								$fplant_design_color( 'input', 'color', __( 'Text Color', 'form-plant' ) );
								$fplant_design_color( 'input', 'background', __( 'Background Color', 'form-plant' ) );
								$fplant_design_number( 'input', 'font_size', __( 'Font Size (px)', 'form-plant' ), 10, 32, true );
								$fplant_design_color( 'input', 'border_color', __( 'Border Color', 'form-plant' ) );
								$fplant_design_color( 'input', 'focus_border_color', __( 'Focus Border Color', 'form-plant' ) );
								$fplant_design_color( 'input', 'error_border_color', __( 'Error Border Color', 'form-plant' ) );
								$fplant_design_color( 'input', 'placeholder_color', __( 'Placeholder Text Color', 'form-plant' ) );
							}
						);

						$fplant_design_accordion(
							'submit',
							__( 'Submit/Confirm Button (Input Screen)', 'form-plant' ),
							function () use ( $fplant_design_button_controls ) {
								$fplant_design_button_controls( 'submit' );
							}
						);

						$fplant_design_accordion(
							'confirm_buttons',
							__( 'Confirmation Screen Buttons', 'form-plant' ),
							function () use ( $fplant_design_button_controls ) {
								// Back and submit buttons share one frame; each set of
								// controls keeps its own data-design-section sub-scope so
								// the JS still collects/saves them as separate sections.
								echo '<div class="fplant-design-subscope" data-design-section="back">';
								echo '<h4 class="fplant-design-subheading">' . esc_html__( 'Back Button', 'form-plant' ) . '</h4>';
								$fplant_design_button_controls( 'back' );
								echo '</div>';
								echo '<div class="fplant-design-subscope" data-design-section="confirm">';
								echo '<h4 class="fplant-design-subheading">' . esc_html__( 'Submit Button', 'form-plant' ) . '</h4>';
								$fplant_design_button_controls( 'confirm' );
								echo '</div>';
							},
							true
						);

						$fplant_design_accordion(
							'error',
							__( 'Error Messages', 'form-plant' ),
							function () use ( $fplant_design_color ) {
								$fplant_design_color( 'error', 'color', __( 'Text Color', 'form-plant' ) );
								$fplant_design_color( 'error', 'background', __( 'Background Color', 'form-plant' ) );
							}
						);
						?>
					</div>
				</div>

				<hr style="border: 0; border-top: 1px solid #dcdcde; margin: 20px 0;">

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Custom CSS Settings', 'form-plant' ); ?></label>
					<p class="description" style="margin-bottom: 10px;">
						<?php esc_html_e( 'Upload your own CSS files to customize the form appearance.', 'form-plant' ); ?>
					</p>
					<div class="fplant-css-upload-wrapper" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
						<input type="file" class="fplant-css-file-input" accept=".css">
						<button type="button" class="button fplant-css-upload-button" disabled>
							<?php esc_html_e( 'Upload', 'form-plant' ); ?>
						</button>
						<span class="fplant-css-upload-status"></span>
					</div>
					<div class="fplant-css-file-list" style="margin-top: 10px;">
						<?php
						$fplant_css_urls = array();
						if ( ! empty( $fplant_form['settings']['custom_css_file_urls'] ) && is_array( $fplant_form['settings']['custom_css_file_urls'] ) ) {
							$fplant_css_urls = $fplant_form['settings']['custom_css_file_urls'];
						} elseif ( ! empty( $fplant_form['settings']['custom_css_file_url'] ) ) {
							$fplant_css_urls = array( $fplant_form['settings']['custom_css_file_url'] );
						}
						foreach ( $fplant_css_urls as $fplant_css_url ) :
							?>
							<div class="fplant-css-file-item" data-url="<?php echo esc_attr( $fplant_css_url ); ?>">
								<code><?php echo esc_html( basename( $fplant_css_url ) ); ?></code>
								<button type="button" class="button button-small fplant-remove-css-file">
									<?php esc_html_e( 'Delete', 'form-plant' ); ?>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<hr style="border: 0; border-top: 1px solid #dcdcde; margin: 20px 0;">

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Inline CSS', 'form-plant' ); ?></label>
					<p class="description" style="margin-bottom: 10px;">
						<?php esc_html_e( 'Design Adjustments output per-form CSS using an ID selector (#fplant-form-...). To override them here, prefix your selectors with the same ID or use !important.', 'form-plant' ); ?>
					</p>
					<textarea
						class="fplant-form-control fplant-custom-css-inline"
						rows="10"
						placeholder="<?php esc_attr_e( 'Enter custom CSS directly', 'form-plant' ); ?>"
						style="font-family: monospace; font-size: 13px;"
					><?php echo esc_textarea( $fplant_form['settings']['custom_css_inline'] ?? '' ); ?></textarea>
				</div>
			</div>
		</div>

		<!-- Email Settings tab -->
		<div id="tab-email" class="fplant-tab-content">
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Admin Email Settings', 'form-plant' ); ?>
				</div>

				<div class="fplant-checkbox">
					<input
						type="checkbox"
						id="email-admin-enabled"
						class="fplant-email-admin-enabled"
						<?php checked( $fplant_is_new || ! empty( $fplant_form['email_admin']['enabled'] ) ); ?>
					>
					<label for="email-admin-enabled">
						<?php esc_html_e( 'Send email notification to admin', 'form-plant' ); ?>
					</label>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Recipient Email Address', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-email-admin-to"
						value="<?php echo esc_attr( $fplant_form['email_admin']['to'] ?? get_option( 'admin_email' ) ); ?>"
						placeholder="admin@example.com"
					>
					<p class="description"><?php esc_html_e( 'Separate multiple addresses with commas. Available tag: {admin_email}', 'form-plant' ); ?></p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Sender Name', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-email-admin-from-name"
						value="<?php echo esc_attr( $fplant_form['email_admin']['from_name'] ?? get_bloginfo( 'name' ) ); ?>"
						placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
					>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Sender Email Address', 'form-plant' ); ?></label>
					<input
						type="email"
						class="fplant-form-control fplant-email-admin-from-email"
						value="<?php echo esc_attr( $fplant_form['email_admin']['from_email'] ?? get_option( 'admin_email' ) ); ?>"
						placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
					>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Subject', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-email-admin-subject"
						value="<?php echo esc_attr( $fplant_form['email_admin']['subject'] ?? __( '[{form_title}] New Inquiry', 'form-plant' ) ); ?>"
						placeholder="<?php echo esc_attr( __( '[{form_title}] New Inquiry', 'form-plant' ) ); ?>"
					>
					<p class="description">
						<?php esc_html_e( 'Available tags: {form_title}, {submission_id}, {site_name}, {admin_email}', 'form-plant' ); ?>
					</p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Email Body', 'form-plant' ); ?></label>
					<textarea
						class="fplant-form-control fplant-email-admin-body"
						rows="10"
						placeholder="<?php esc_attr_e( 'Enter email body', 'form-plant' ); ?>"
					><?php
						$fplant_default_admin_body = __( 'You have received a new inquiry.', 'form-plant' ) . "\n\n{all_fields}\n\n---\n" . __( 'Submission Date:', 'form-plant' ) . " {submission_date}\n" . __( 'Submission ID:', 'form-plant' ) . " {submission_id}";
						echo esc_textarea( $fplant_form['email_admin']['body'] ?? $fplant_default_admin_body );
					?></textarea>
					<p class="description">
						<?php esc_html_e( 'Available tags: {all_fields}, {field:field_name}, {submission_id}, {submission_date}, {ip_address}, {admin_email}', 'form-plant' ); ?>
					</p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'CC', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-email-admin-cc"
						value="<?php echo esc_attr( $fplant_form['email_admin']['cc'] ?? '' ); ?>"
						placeholder="cc@example.com"
					>
					<p class="description"><?php esc_html_e( 'Separate multiple addresses with commas', 'form-plant' ); ?></p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'BCC', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-email-admin-bcc"
						value="<?php echo esc_attr( $fplant_form['email_admin']['bcc'] ?? '' ); ?>"
						placeholder="bcc@example.com"
					>
					<p class="description"><?php esc_html_e( 'Separate multiple addresses with commas', 'form-plant' ); ?></p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Reply-To', 'form-plant' ); ?></label>
					<input
						type="email"
						class="fplant-form-control fplant-email-admin-reply-to"
						value="<?php echo esc_attr( $fplant_form['email_admin']['reply_to'] ?? '' ); ?>"
						placeholder="reply@example.com"
					>
					<p class="description"><?php esc_html_e( 'Reply destination when the recipient replies to this email', 'form-plant' ); ?></p>
				</div>
			</div>

			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Auto-reply Email Settings', 'form-plant' ); ?>
				</div>

				<div class="fplant-checkbox">
					<input
						type="checkbox"
						id="email-user-enabled"
						class="fplant-email-user-enabled"
						<?php checked( ! empty( $fplant_form['email_user']['enabled'] ) ); ?>
					>
					<label for="email-user-enabled">
						<?php esc_html_e( 'Send auto-reply email to user', 'form-plant' ); ?>
					</label>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Recipient Field', 'form-plant' ); ?></label>
					<select class="fplant-form-control fplant-email-user-to-field">
						<option value=""><?php esc_html_e( 'Please select', 'form-plant' ); ?></option>
						<?php if ( ! empty( $fplant_form['fields'] ) ) : ?>
							<?php foreach ( $fplant_form['fields'] as $fplant_field ) : ?>
								<?php if ( 'email' === $fplant_field['type'] ) : ?>
									<option value="<?php echo esc_attr( $fplant_field['name'] ); ?>" <?php selected( $fplant_form['email_user']['to_field'] ?? '', $fplant_field['name'] ); ?>>
										<?php echo esc_html( $fplant_field['label'] ?? $fplant_field['name'] ); ?>
									</option>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Select an email type field', 'form-plant' ); ?></p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Sender Name', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-email-user-from-name"
						value="<?php echo esc_attr( $fplant_form['email_user']['from_name'] ?? get_bloginfo( 'name' ) ); ?>"
						placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
					>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Sender Email Address', 'form-plant' ); ?></label>
					<input
						type="email"
						class="fplant-form-control fplant-email-user-from-email"
						value="<?php echo esc_attr( $fplant_form['email_user']['from_email'] ?? get_option( 'admin_email' ) ); ?>"
						placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
					>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Subject', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-email-user-subject"
						value="<?php echo esc_attr( $fplant_form['email_user']['subject'] ?? __( 'Thank you for your inquiry', 'form-plant' ) ); ?>"
						placeholder="<?php echo esc_attr( __( 'Thank you for your inquiry', 'form-plant' ) ); ?>"
					>
					<p class="description">
						<?php esc_html_e( 'Available tags: {form_title}, {field:field_name}, {site_name}, {admin_email}', 'form-plant' ); ?>
					</p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Email Body', 'form-plant' ); ?></label>
					<textarea
						class="fplant-form-control fplant-email-user-body"
						rows="10"
						placeholder="<?php esc_attr_e( 'Enter email body', 'form-plant' ); ?>"
					><?php
						$fplant_default_user_body = __( 'Thank you for your inquiry.', 'form-plant' ) . "\n\n" . __( 'We have received the following information.', 'form-plant' ) . "\n\n{all_fields}\n\n---\n{site_name}";
						echo esc_textarea( $fplant_form['email_user']['body'] ?? $fplant_default_user_body );
					?></textarea>
					<p class="description">
						<?php esc_html_e( 'Available tags: {all_fields}, {field:field_name}, {site_name}, {site_url}, {admin_email}', 'form-plant' ); ?>
					</p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'CC', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-email-user-cc"
						value="<?php echo esc_attr( $fplant_form['email_user']['cc'] ?? '' ); ?>"
						placeholder="cc@example.com"
					>
					<p class="description"><?php esc_html_e( 'Separate multiple addresses with commas', 'form-plant' ); ?></p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'BCC', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-email-user-bcc"
						value="<?php echo esc_attr( $fplant_form['email_user']['bcc'] ?? '' ); ?>"
						placeholder="bcc@example.com"
					>
					<p class="description"><?php esc_html_e( 'Separate multiple addresses with commas', 'form-plant' ); ?></p>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Reply-To', 'form-plant' ); ?></label>
					<input
						type="email"
						class="fplant-form-control fplant-email-user-reply-to"
						value="<?php echo esc_attr( $fplant_form['email_user']['reply_to'] ?? '' ); ?>"
						placeholder="reply@example.com"
					>
					<p class="description"><?php esc_html_e( 'Reply destination when the recipient replies to this email', 'form-plant' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Form Settings tab -->
		<div id="tab-settings" class="fplant-tab-content">
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Action After Submission', 'form-plant' ); ?>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'Action Type', 'form-plant' ); ?></label>
					<select class="fplant-form-control fplant-setting-action-type">
						<option value="message" <?php selected( $fplant_form['settings']['action_type'] ?? 'message', 'message' ); ?>>
							<?php esc_html_e( 'Message Only', 'form-plant' ); ?>
						</option>
						<option value="custom_page" <?php selected( $fplant_form['settings']['action_type'] ?? 'message', 'custom_page' ); ?>>
							<?php esc_html_e( 'Completion Page', 'form-plant' ); ?>
						</option>
						<option value="redirect" <?php selected( $fplant_form['settings']['action_type'] ?? 'message', 'redirect' ); ?>>
							<?php esc_html_e( 'Redirect URL', 'form-plant' ); ?>
						</option>
					</select>
				</div>

				<div class="fplant-form-group fplant-action-message">
					<label><?php esc_html_e( 'Success Message', 'form-plant' ); ?></label>
					<input
						type="text"
						class="fplant-form-control fplant-setting-success-message"
						value="<?php echo esc_attr( $fplant_form['settings']['success_message'] ?? __( 'Submission completed successfully', 'form-plant' ) ); ?>"
					>
				</div>

				<div class="fplant-form-group fplant-action-custom-page" style="display: none;">
					<label><?php esc_html_e( 'Completion Page HTML', 'form-plant' ); ?></label>
					<textarea
						class="fplant-form-control fplant-setting-success-page-html"
						rows="10"
					><?php echo esc_textarea( $fplant_form['settings']['success_page_html'] ?? '<h2>Submission Complete</h2>' ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Enter the HTML to display after submission. The form will be hidden and this HTML will be shown.', 'form-plant' ); ?>
					</p>
				</div>

				<div class="fplant-form-group fplant-action-redirect" style="display: none;">
					<label><?php esc_html_e( 'Redirect URL', 'form-plant' ); ?></label>
					<input
						type="url"
						class="fplant-form-control fplant-setting-redirect-url"
						value="<?php echo esc_attr( $fplant_form['settings']['redirect_url'] ?? '' ); ?>"
						placeholder="https://example.com/thanks"
					>
					<p class="description">
						<?php esc_html_e( 'Enter the URL to redirect to after submission.', 'form-plant' ); ?>
					</p>
				</div>
			</div>

			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Submission Data', 'form-plant' ); ?>
				</div>

				<?php
				// Backward compatibility for existing values
				$fplant_save_submission_value = isset( $fplant_form['settings']['save_submission'] ) ? $fplant_form['settings']['save_submission'] : 'none';
				// Convert from old format (true/false)
				if ( true === $fplant_save_submission_value || 'true' === $fplant_save_submission_value || '1' === $fplant_save_submission_value || 1 === $fplant_save_submission_value ) {
					$fplant_save_submission_value = 'full';
				} elseif ( false === $fplant_save_submission_value || 'false' === $fplant_save_submission_value || '' === $fplant_save_submission_value || '0' === $fplant_save_submission_value || 0 === $fplant_save_submission_value ) {
					$fplant_save_submission_value = 'none';
				}
				// Default for invalid values
				if ( ! in_array( $fplant_save_submission_value, array( 'none', 'metadata_only', 'full' ), true ) ) {
					$fplant_save_submission_value = 'none';
				}
				?>

				<div class="fplant-form-group">
					<div class="fplant-radio">
						<input
							type="radio"
							id="save-submission-none"
							name="save_submission"
							class="fplant-setting-save-submission"
							value="none"
							<?php checked( $fplant_save_submission_value, 'none' ); ?>
						>
						<label for="save-submission-none">
							<?php esc_html_e( 'Do not save anything', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-radio">
						<input
							type="radio"
							id="save-submission-metadata"
							name="save_submission"
							class="fplant-setting-save-submission"
							value="metadata_only"
							<?php checked( $fplant_save_submission_value, 'metadata_only' ); ?>
						>
						<label for="save-submission-metadata">
							<?php esc_html_e( 'Save only submission record (do not save input data)', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-radio">
						<input
							type="radio"
							id="save-submission-full"
							name="save_submission"
							class="fplant-setting-save-submission"
							value="full"
							<?php checked( $fplant_save_submission_value, 'full' ); ?>
						>
						<label for="save-submission-full">
							<?php esc_html_e( 'Save submission data including input data', 'form-plant' ); ?>
						</label>
					</div>
				</div>
				<p class="description">
					<?php esc_html_e( 'When "Save only submission record" is selected, only metadata such as submission date and IP address will be recorded, and input content will not be saved.', 'form-plant' ); ?>
				</p>
			</div>

			<!-- URL Parameter Settings -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'URL Parameter Settings', 'form-plant' ); ?>
				</div>

				<div class="fplant-checkbox">
					<input
						type="checkbox"
						id="allow-url-params"
						class="fplant-setting-allow-url-params"
						<?php checked( ! empty( $fplant_form['settings']['allow_url_params'] ) ); ?>
					>
					<label for="allow-url-params">
						<?php esc_html_e( 'Allow initial values from URL parameters', 'form-plant' ); ?>
					</label>
				</div>
				<p class="description">
					<?php esc_html_e( 'When enabled, field values can be set via URL parameters (e.g., ?field_name=value)', 'form-plant' ); ?>
				</p>
				<p class="description" style="color: #d63638;">
					<?php esc_html_e( '* Set {field_name} as the default value for each field.', 'form-plant' ); ?>
				</p>
			</div>

			<!-- External Usage -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'External Usage', 'form-plant' ); ?>
				</div>

				<p class="description" style="margin-bottom: 20px;">
					<?php esc_html_e( 'Settings for embedding this form on external sites.', 'form-plant' ); ?>
				</p>

				<!-- iframe Usage -->
				<div class="fplant-form-group">
					<div class="fplant-checkbox">
						<input
							type="checkbox"
							id="embed-iframe-enabled"
							class="fplant-setting-embed-iframe-enabled"
							<?php checked( ! empty( $fplant_form['settings']['embed_iframe_enabled'] ) ); ?>
						>
						<label for="embed-iframe-enabled">
							<?php esc_html_e( 'Allow iframe embedding', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-embed-iframe-settings<?php echo empty( $fplant_form['settings']['embed_iframe_enabled'] ) ? ' fplant-disabled' : ''; ?>" style="margin-top: 15px; margin-left: 25px;">
						<div class="fplant-form-group">
							<label><?php esc_html_e( 'Allowed Site URLs (multiple allowed)', 'form-plant' ); ?></label>
							<textarea
								class="fplant-form-control fplant-setting-embed-iframe-allowed-urls"
								rows="3"
								placeholder="https://example.com&#10;https://another-site.com"
								<?php echo empty( $fplant_form['settings']['embed_iframe_enabled'] ) ? 'readonly' : ''; ?>
							><?php echo esc_textarea( implode( "\n", $fplant_form['settings']['embed_iframe_allowed_urls'] ?? array() ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Enter one URL per line. Only these sites will be allowed to embed via iframe.', 'form-plant' ); ?></p>
						</div>

						<?php if ( ! empty( $fplant_form['id'] ) ) : ?>
						<div class="fplant-form-group">
							<label><?php esc_html_e( 'Embed Code', 'form-plant' ); ?></label>
							<div class="fplant-embed-code-wrapper">
								<textarea
									class="fplant-form-control fplant-embed-iframe-code"
									rows="3"
									readonly
									onclick="this.select();"
								><iframe src="<?php echo esc_url( home_url( '/fplant-embed/' . $fplant_form['id'] . '/' ) ); ?>" width="100%" height="500" frameborder="0"></iframe></textarea>
								<button type="button" class="button fplant-copy-embed-code" data-target=".fplant-embed-iframe-code">
									<?php esc_html_e( 'Copy', 'form-plant' ); ?>
								</button>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<hr style="margin: 25px 0; border: none; border-top: 1px solid #ddd;">

				<!-- JavaScript Embedding -->
				<div class="fplant-form-group">
					<div class="fplant-checkbox">
						<input
							type="checkbox"
							id="embed-js-enabled"
							class="fplant-setting-embed-js-enabled"
							<?php checked( ! empty( $fplant_form['settings']['embed_js_enabled'] ) ); ?>
						>
						<label for="embed-js-enabled">
							<?php esc_html_e( 'Allow JavaScript embedding', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-embed-js-settings<?php echo empty( $fplant_form['settings']['embed_js_enabled'] ) ? ' fplant-disabled' : ''; ?>" style="margin-top: 15px; margin-left: 25px;">
						<div class="fplant-form-group">
							<label><?php esc_html_e( 'Allowed Site URLs (multiple allowed)', 'form-plant' ); ?></label>
							<textarea
								class="fplant-form-control fplant-setting-embed-js-allowed-urls"
								rows="3"
								placeholder="https://example.com&#10;https://another-site.com"
								<?php echo empty( $fplant_form['settings']['embed_js_enabled'] ) ? 'readonly' : ''; ?>
							><?php echo esc_textarea( implode( "\n", $fplant_form['settings']['embed_js_allowed_urls'] ?? array() ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Enter one URL per line. Only these sites will be allowed for JS embedding (CORS control).', 'form-plant' ); ?></p>
						</div>

						<?php if ( ! empty( $fplant_form['id'] ) ) : ?>
						<div class="fplant-form-group">
							<label><?php esc_html_e( 'Embed Code', 'form-plant' ); ?></label>
							<div class="fplant-embed-code-wrapper">
								<textarea
									class="fplant-form-control fplant-embed-js-code"
									rows="5"
									readonly
									onclick="this.select();"
									data-form-id="<?php echo esc_attr( $fplant_form['id'] ); ?>"
									data-embed-js-url="<?php echo esc_url( FPLANT_PLUGIN_URL . 'assets/js/embed.js' ); ?>"
									data-home-url="<?php echo esc_url( home_url() ); ?>"
								></textarea>
								<button type="button" class="button fplant-copy-embed-code" data-target=".fplant-embed-js-code">
									<?php esc_html_e( 'Copy', 'form-plant' ); ?>
								</button>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Extended Settings (fplant_custom_settings_fields) -->
			<?php
			$fplant_custom_settings = FPLANT_Form_Manager::get_custom_settings_fields( absint( $fplant_form['id'] ?? 0 ) );
			if ( ! empty( $fplant_custom_settings ) ) :
				?>
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Extended Settings', 'form-plant' ); ?>
				</div>
				<?php
				foreach ( $fplant_custom_settings as $fplant_cs ) :
					$fplant_cs_value = $fplant_form['settings'][ $fplant_cs['key'] ] ?? $fplant_cs['default'];
					?>
					<div class="fplant-form-group">
						<?php if ( 'checkbox' === $fplant_cs['type'] ) : ?>
							<label>
								<input
									type="checkbox"
									data-fplant-setting="<?php echo esc_attr( $fplant_cs['key'] ); ?>"
									value="1"
									<?php checked( ! empty( $fplant_cs_value ) ); ?>
								>
								<?php echo esc_html( $fplant_cs['label'] ); ?>
							</label>
						<?php else : ?>
							<label><?php echo esc_html( $fplant_cs['label'] ); ?></label>
							<?php if ( 'textarea' === $fplant_cs['type'] ) : ?>
								<textarea
									class="fplant-form-control"
									rows="4"
									data-fplant-setting="<?php echo esc_attr( $fplant_cs['key'] ); ?>"
								><?php echo esc_textarea( (string) $fplant_cs_value ); ?></textarea>
							<?php elseif ( 'select' === $fplant_cs['type'] ) : ?>
								<select class="fplant-form-control" data-fplant-setting="<?php echo esc_attr( $fplant_cs['key'] ); ?>">
									<?php foreach ( $fplant_cs['options'] as $fplant_opt_value => $fplant_opt_label ) : ?>
										<option value="<?php echo esc_attr( $fplant_opt_value ); ?>" <?php selected( (string) $fplant_cs_value, (string) $fplant_opt_value ); ?>>
											<?php echo esc_html( $fplant_opt_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'number' === $fplant_cs['type'] ) : ?>
								<input
									type="number"
									class="fplant-form-control"
									data-fplant-setting="<?php echo esc_attr( $fplant_cs['key'] ); ?>"
									value="<?php echo esc_attr( (string) $fplant_cs_value ); ?>"
								>
							<?php else : ?>
								<input
									type="text"
									class="fplant-form-control"
									data-fplant-setting="<?php echo esc_attr( $fplant_cs['key'] ); ?>"
									value="<?php echo esc_attr( (string) $fplant_cs_value ); ?>"
								>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( ! empty( $fplant_cs['description'] ) ) : ?>
							<p class="description"><?php echo esc_html( $fplant_cs['description'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<!-- Spam Protection tab -->
		<div id="tab-spam" class="fplant-tab-content">

			<!-- CAPTCHA Settings -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'CAPTCHA Settings', 'form-plant' ); ?>
				</div>

				<?php
				$fplant_recaptcha_site_key    = get_option( 'fplant_recaptcha_site_key' );
				$fplant_recaptcha_v2_site_key = get_option( 'fplant_recaptcha_v2_site_key' );
				$fplant_turnstile_site_key    = get_option( 'fplant_turnstile_site_key' );

				// Determine captcha type with backward compatibility
				$fplant_captcha_type = $fplant_form['settings']['captcha_type'] ?? 'none';
				if ( 'none' === $fplant_captcha_type && ! empty( $fplant_form['settings']['recaptcha_enabled'] ) ) {
					$fplant_captcha_type = 'recaptcha';
				}
				?>

				<div class="fplant-form-group">
					<div class="fplant-radio">
						<input
							type="radio"
							id="captcha-type-none"
							name="captcha_type"
							class="fplant-setting-captcha-type"
							value="none"
							<?php checked( $fplant_captcha_type, 'none' ); ?>
						>
						<label for="captcha-type-none">
							<?php esc_html_e( 'Do not use CAPTCHA', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-radio">
						<input
							type="radio"
							id="captcha-type-recaptcha-v2"
							name="captcha_type"
							class="fplant-setting-captcha-type"
							value="recaptcha_v2"
							<?php checked( $fplant_captcha_type, 'recaptcha_v2' ); ?>
							<?php disabled( empty( $fplant_recaptcha_v2_site_key ) ); ?>
						>
						<label for="captcha-type-recaptcha-v2"<?php echo empty( $fplant_recaptcha_v2_site_key ) ? ' class="fplant-disabled"' : ''; ?>>
							<?php esc_html_e( 'Google reCAPTCHA v2 (Checkbox)', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-captcha-details fplant-captcha-details-recaptcha_v2" style="margin-left: 25px;<?php echo 'recaptcha_v2' !== $fplant_captcha_type ? ' display:none;' : ''; ?>">
						<p class="description">
							<?php esc_html_e( 'Users must check the "I\'m not a robot" checkbox before submitting.', 'form-plant' ); ?>
						</p>
					</div>

					<div class="fplant-radio">
						<input
							type="radio"
							id="captcha-type-recaptcha"
							name="captcha_type"
							class="fplant-setting-captcha-type"
							value="recaptcha"
							<?php checked( $fplant_captcha_type, 'recaptcha' ); ?>
							<?php disabled( empty( $fplant_recaptcha_site_key ) ); ?>
						>
						<label for="captcha-type-recaptcha"<?php echo empty( $fplant_recaptcha_site_key ) ? ' class="fplant-disabled"' : ''; ?>>
							<?php esc_html_e( 'Google reCAPTCHA v3', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-captcha-details fplant-captcha-details-recaptcha" style="margin-left: 25px;<?php echo 'recaptcha' !== $fplant_captcha_type ? ' display:none;' : ''; ?>">
						<p class="description">
							<?php esc_html_e( 'Uses reCAPTCHA v3 (invisible/score-based) for automatic background verification.', 'form-plant' ); ?>
						</p>
					</div>

					<div class="fplant-radio">
						<input
							type="radio"
							id="captcha-type-turnstile"
							name="captcha_type"
							class="fplant-setting-captcha-type"
							value="turnstile"
							<?php checked( $fplant_captcha_type, 'turnstile' ); ?>
							<?php disabled( empty( $fplant_turnstile_site_key ) ); ?>
						>
						<label for="captcha-type-turnstile"<?php echo empty( $fplant_turnstile_site_key ) ? ' class="fplant-disabled"' : ''; ?>>
							<?php esc_html_e( 'Cloudflare Turnstile', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-captcha-details fplant-captcha-details-turnstile" style="margin-left: 25px;<?php echo 'turnstile' !== $fplant_captcha_type ? ' display:none;' : ''; ?>">
						<p class="description">
							<?php esc_html_e( 'Uses Cloudflare Turnstile for automatic background verification.', 'form-plant' ); ?>
						</p>
					</div>
				</div>

				<?php if ( empty( $fplant_recaptcha_site_key ) && empty( $fplant_recaptcha_v2_site_key ) && empty( $fplant_turnstile_site_key ) ) : ?>
					<p class="description" style="color: #d63638;">
						<?php
						printf(
							/* translators: %s: settings page link */
							esc_html__( 'To use CAPTCHA, please first set up the API keys on the %s.', 'form-plant' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=fplant-settings' ) ) . '">' . esc_html__( 'Settings page', 'form-plant' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Honeypot -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Honeypot', 'form-plant' ); ?>
				</div>

				<div class="fplant-checkbox">
					<input
						type="checkbox"
						id="spam-honeypot"
						class="fplant-setting-spam-honeypot"
						<?php checked( $fplant_form['settings']['spam_honeypot_enabled'] ?? true ); ?>
					>
					<label for="spam-honeypot">
						<?php esc_html_e( 'Enable honeypot', 'form-plant' ); ?>
					</label>
				</div>
				<p class="description" style="margin-top: 5px; margin-left: 25px;">
					<?php esc_html_e( 'An invisible dummy field is used to detect bots.', 'form-plant' ); ?>
				</p>

				<div class="fplant-spam-honeypot-settings<?php echo ( isset( $fplant_form['settings']['spam_honeypot_enabled'] ) && ! $fplant_form['settings']['spam_honeypot_enabled'] ) ? ' fplant-disabled' : ''; ?>" style="margin-top: 15px; margin-left: 25px;">
					<label for="spam-honeypot-field-name"><?php esc_html_e( 'Field name', 'form-plant' ); ?></label>
					<input
						type="text"
						id="spam-honeypot-field-name"
						class="fplant-setting-spam-honeypot-field-name regular-text"
						value="<?php echo esc_attr( $fplant_form['settings']['spam_honeypot_field_name'] ?? 'fplant_website_url' ); ?>"
						<?php echo ( isset( $fplant_form['settings']['spam_honeypot_enabled'] ) && ! $fplant_form['settings']['spam_honeypot_enabled'] ) ? ' readonly' : ''; ?>
					>
					<p class="description">
						<?php esc_html_e( 'Change the field name if it conflicts with your form fields.', 'form-plant' ); ?>
					</p>
				</div>
			</div>

			<!-- Rate Limiting -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Rate Limiting', 'form-plant' ); ?>
				</div>

				<div class="fplant-checkbox">
					<input
						type="checkbox"
						id="spam-rate-limit"
						class="fplant-setting-spam-rate-limit"
						<?php checked( ! empty( $fplant_form['settings']['spam_rate_limit_enabled'] ) ); ?>
					>
					<label for="spam-rate-limit">
						<?php esc_html_e( 'Enable rate limiting', 'form-plant' ); ?>
					</label>
				</div>

				<div class="fplant-spam-rate-limit-settings<?php echo empty( $fplant_form['settings']['spam_rate_limit_enabled'] ) ? ' fplant-disabled' : ''; ?>" style="margin-top: 15px; margin-left: 25px;">
					<p class="description">
						<?php
						printf(
							/* translators: %1$s: minutes input field, %2$s: count input field */
							esc_html__( 'Block submissions exceeding %2$s times within %1$s minutes from the same IP address', 'form-plant' ),
							'<input type="number" class="fplant-setting-spam-rate-limit-minutes small-text" min="1" max="60" value="' . esc_attr( $fplant_form['settings']['spam_rate_limit_minutes'] ?? 5 ) . '"' . ( empty( $fplant_form['settings']['spam_rate_limit_enabled'] ) ? ' readonly' : '' ) . '>',
							'<input type="number" class="fplant-setting-spam-rate-limit-count small-text" min="1" max="50" value="' . esc_attr( $fplant_form['settings']['spam_rate_limit_count'] ?? 3 ) . '"' . ( empty( $fplant_form['settings']['spam_rate_limit_enabled'] ) ? ' readonly' : '' ) . '>'
						);
						?>
					</p>
				</div>
			</div>

			<!-- Submission Speed Check -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Submission Speed Check', 'form-plant' ); ?>
				</div>

				<div class="fplant-checkbox">
					<input
						type="checkbox"
						id="spam-time-check"
						class="fplant-setting-spam-time-check"
						<?php checked( ! empty( $fplant_form['settings']['spam_time_check_enabled'] ) ); ?>
					>
					<label for="spam-time-check">
						<?php esc_html_e( 'Enable submission speed check', 'form-plant' ); ?>
					</label>
				</div>

				<div class="fplant-spam-time-check-settings<?php echo empty( $fplant_form['settings']['spam_time_check_enabled'] ) ? ' fplant-disabled' : ''; ?>" style="margin-top: 15px; margin-left: 25px;">
					<p class="description">
						<?php
						printf(
							/* translators: %s: seconds input field */
							esc_html__( 'Block submissions faster than %s seconds after form display', 'form-plant' ),
							'<input type="number" class="fplant-setting-spam-time-check-seconds small-text" min="1" max="30" value="' . esc_attr( $fplant_form['settings']['spam_time_check_seconds'] ?? 3 ) . '"' . ( empty( $fplant_form['settings']['spam_time_check_enabled'] ) ? ' readonly' : '' ) . '>'
						);
						?>
					</p>
					<p class="description" style="margin-top: 5px;">
						<?php esc_html_e( 'Rejects submissions that are faster than human input speed.', 'form-plant' ); ?>
					</p>
				</div>
			</div>

			<!-- Email Address Verification -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Email Address Verification', 'form-plant' ); ?>
				</div>

				<div class="fplant-checkbox">
					<input
						type="checkbox"
						id="spam-disposable-email-block"
						class="fplant-setting-spam-disposable-email-block"
						<?php checked( ! empty( $fplant_form['settings']['spam_disposable_email_block'] ) ); ?>
					>
					<label for="spam-disposable-email-block">
						<?php esc_html_e( 'Enable disposable email blocking', 'form-plant' ); ?>
					</label>
				</div>

				<p class="description" style="margin-top: 10px; margin-left: 25px;">
					<?php esc_html_e( 'Block disposable email addresses like tempmail.com', 'form-plant' ); ?>
				</p>
			</div>

		</div>

	</div>
</div>

<!-- Field editor: a single instance moved into the open accordion row body. -->
<div id="fplant-field-editor-host" hidden>
	<div id="fplant-field-editor" class="fplant-field-editor">
		<div id="fplant-field-modal-errors" class="fplant-notice fplant-notice-error" style="display: none;"></div>
		<div class="fplant-field-tabs" role="tablist">
			<button type="button" class="fplant-field-tab active" role="tab" aria-selected="true" data-ftab="basic"><?php esc_html_e( 'Basic', 'form-plant' ); ?></button>
			<button type="button" class="fplant-field-tab" role="tab" aria-selected="false" data-ftab="validation"><?php esc_html_e( 'Validation', 'form-plant' ); ?></button>
			<button type="button" class="fplant-field-tab" role="tab" aria-selected="false" data-ftab="advanced"><?php esc_html_e( 'Advanced', 'form-plant' ); ?></button>
		</div>
		<div class="fplant-field-tab-panel active" role="tabpanel" data-ftab="basic">
			<div class="fplant-form-group">
				<label for="fplant-field-type"><?php esc_html_e( 'Field Type', 'form-plant' ); ?> <span class="required">*</span></label>
				<select id="fplant-field-type" class="fplant-form-control">
					<?php
					// Generate options from the single source of truth (filtered via fplant_field_types),
					// so Pro-registered field types appear in the picker automatically.
					$fplant_field_type_list = ( new FPLANT_Field_Manager() )->get_field_types();
					foreach ( $fplant_field_type_list as $fplant_type_key => $fplant_type_cfg ) :
						?>
						<option value="<?php echo esc_attr( $fplant_type_key ); ?>"><?php echo esc_html( $fplant_type_cfg['label'] ?? $fplant_type_key ); ?></option>
						<?php
					endforeach;
					?>
				</select>
			</div>

			<div class="fplant-form-group">
				<label for="fplant-field-name"><?php esc_html_e( 'Field Name', 'form-plant' ); ?> <span class="required">*</span></label>
				<input type="text" id="fplant-field-name" class="fplant-form-control" placeholder="field_name">
				<p class="description"><?php esc_html_e( 'Only alphanumeric characters and underscores allowed', 'form-plant' ); ?></p>
			</div>

			<div class="fplant-form-group">
				<label for="fplant-field-label"><?php esc_html_e( 'Field Label', 'form-plant' ); ?> <span class="required">*</span></label>
				<input type="text" id="fplant-field-label" class="fplant-form-control" placeholder="<?php esc_attr_e( 'Your Name', 'form-plant' ); ?>">
			</div>

			<div class="fplant-form-group">
				<label for="fplant-field-placeholder"><?php esc_html_e( 'Placeholder', 'form-plant' ); ?></label>
				<input type="text" id="fplant-field-placeholder" class="fplant-form-control">
				<?php // Multi-line variant shown for the textarea field type (toggled in admin.js). ?>
				<textarea id="fplant-field-placeholder-textarea" class="fplant-form-control" rows="2" style="display: none;"></textarea>
			</div>

			<!-- Options Settings (for select/radio/checkbox) -->
			<div id="fplant-field-options-section" class="fplant-form-group" style="display: none;">
				<label for="fplant-field-options-textarea"><?php esc_html_e( 'Options', 'form-plant' ); ?> <span class="required">*</span></label>
				<textarea id="fplant-field-options-textarea" class="fplant-form-control" rows="6" placeholder="<?php esc_attr_e( "value1:Label 1\nvalue2:Label 2\nvalue3", 'form-plant' ); ?>"></textarea>
				<p class="description"><?php esc_html_e( 'Enter one option per line. Use "value:label" format to set different value and label. If no colon, the text becomes both value and label.', 'form-plant' ); ?></p>
			</div>

			<!-- Layout Settings (for radio/checkbox) -->
			<div id="fplant-field-layout-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Layout', 'form-plant' ); ?></label>
				<div style="display: flex; gap: 20px;">
					<label style="font-weight: normal;">
						<input type="radio" name="fplant-field-layout" id="fplant-field-layout-vertical" value="vertical" checked>
						<?php esc_html_e( 'Vertical', 'form-plant' ); ?>
					</label>
					<label style="font-weight: normal;">
						<input type="radio" name="fplant-field-layout" id="fplant-field-layout-horizontal" value="horizontal">
						<?php esc_html_e( 'Horizontal', 'form-plant' ); ?>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Choose how options are displayed', 'form-plant' ); ?></p>
			</div>

			<!-- Delimiter Settings (for checkbox) -->
			<div id="fplant-field-delimiter-section" class="fplant-form-group" style="display: none;">
				<label for="fplant-field-delimiter"><?php esc_html_e( 'Delimiter', 'form-plant' ); ?></label>
				<input type="text" id="fplant-field-delimiter" class="fplant-form-control" style="width: 100px;" value=", " placeholder=", ">
				<p class="description"><?php esc_html_e( 'Separator used when displaying multiple selected values in confirmation screen and emails', 'form-plant' ); ?></p>
			</div>

			<!-- Date Range Settings (for date/date_select) -->
			<div id="fplant-field-date-range-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Year Range Settings', 'form-plant' ); ?></label>
				<div style="display: flex; gap: 10px; align-items: center;">
					<div style="flex: 1;">
						<label for="fplant-field-year-start" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Start Year (Past)', 'form-plant' ); ?></label>
						<input
							type="number"
							id="fplant-field-year-start"
							class="fplant-form-control"
							placeholder="100"
							min="0"
							max="200"
						>
						<p class="description"><?php esc_html_e( 'How many years in the past', 'form-plant' ); ?></p>
					</div>
					<div style="flex: 1;">
						<label for="fplant-field-year-end" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'End Year (Future)', 'form-plant' ); ?></label>
						<input
							type="number"
							id="fplant-field-year-end"
							class="fplant-form-control"
							placeholder="10"
							min="0"
							max="200"
						>
						<p class="description"><?php esc_html_e( 'How many years in the future', 'form-plant' ); ?></p>
					</div>
				</div>
			</div>

			<!-- File Upload Settings (for file) -->
			<div id="fplant-field-file-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'File Upload Settings', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<label for="fplant-field-max-size" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Maximum File Size (MB)', 'form-plant' ); ?></label>
					<input
						type="number"
						id="fplant-field-max-size"
						class="fplant-form-control"
						placeholder="2"
						min="0.1"
						max="100"
						step="0.1"
					>
					<p class="description"><?php esc_html_e( 'Specify the maximum file size in MB (Default: 2MB)', 'form-plant' ); ?></p>
				</div>
			</div>

			<!-- Input Field Settings (for text / email / url / password). Max Length lives in the Validation tab. -->
			<div id="fplant-field-text-settings-section" class="fplant-form-group" style="display: none;">
				<label for="fplant-field-size"><?php esc_html_e( 'Size', 'form-plant' ); ?></label>
				<input
					type="number"
					id="fplant-field-size"
					class="fplant-form-control"
					placeholder=""
					min="1"
					max="200"
				>
				<p class="description"><?php esc_html_e( 'Display width of the input field (number of characters)', 'form-plant' ); ?></p>
			</div>

			<!-- Textarea Field Settings (for textarea) -->
			<div id="fplant-field-textarea-settings-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Textarea Field Settings', 'form-plant' ); ?></label>
				<div style="display: flex; gap: 10px; align-items: flex-start; margin-top: 10px;">
					<div style="flex: 1;">
						<label for="fplant-field-rows" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Rows', 'form-plant' ); ?></label>
						<input
							type="number"
							id="fplant-field-rows"
							class="fplant-form-control"
							placeholder="5"
							min="1"
							max="50"
						>
						<p class="description"><?php esc_html_e( 'Number of visible text rows', 'form-plant' ); ?></p>
					</div>
					<div style="flex: 1;">
						<label for="fplant-field-cols" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Columns', 'form-plant' ); ?></label>
						<input
							type="number"
							id="fplant-field-cols"
							class="fplant-form-control"
							placeholder=""
							min="1"
							max="200"
						>
						<p class="description"><?php esc_html_e( 'Visible width (number of characters)', 'form-plant' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Name Parts Settings (for name_parts) -->
			<div id="fplant-field-name-parts-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Name Field Settings', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<label for="fplant-field-name-format" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Number of Inputs', 'form-plant' ); ?></label>
					<select id="fplant-field-name-format" class="fplant-form-control">
						<option value="1"><?php esc_html_e( '1 input', 'form-plant' ); ?></option>
						<option value="2" selected><?php esc_html_e( '2 inputs (First / Last)', 'form-plant' ); ?></option>
						<option value="3"><?php esc_html_e( '3 inputs (First / Middle / Last)', 'form-plant' ); ?></option>
					</select>
				</div>
				<?php
				$fplant_name_part_labels = array(
					'family' => __( 'Last Name', 'form-plant' ),
					'given'  => __( 'First Name', 'form-plant' ),
					'middle' => __( 'Middle Name', 'form-plant' ),
				);
				// Match frontend display order: ja = family, middle, given / other = given, middle, family.
				$fplant_name_parts_order = ( 0 === strpos( get_locale(), 'ja' ) )
					? array( 'family', 'middle', 'given' )
					: array( 'given', 'middle', 'family' );
				?>
				<div id="fplant-name-sublabels" style="margin-top: 15px;">
					<?php foreach ( $fplant_name_parts_order as $fplant_part ) : ?>
					<div class="fplant-name-sublabel-row" data-part="<?php echo esc_attr( $fplant_part ); ?>" style="<?php echo 'middle' === $fplant_part ? 'display: none; ' : ''; ?>margin-bottom: 10px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
						<div class="fplant-name-part-heading" style="font-weight: 600; font-size: 12px; margin-bottom: 8px;"
							<?php if ( 'family' === $fplant_part ) : ?>
								data-label-default="<?php echo esc_attr( $fplant_name_part_labels['family'] ); ?>"
								data-label-single="<?php esc_attr_e( 'Full Name', 'form-plant' ); ?>"
							<?php endif; ?>
						><?php echo esc_html( $fplant_name_part_labels[ $fplant_part ] ); ?></div>
						<div style="display: flex; gap: 10px;">
							<div style="flex: 1;">
								<label for="fplant-field-name-label-<?php echo esc_attr( $fplant_part ); ?>" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Sub-label', 'form-plant' ); ?></label>
								<input type="text" id="fplant-field-name-label-<?php echo esc_attr( $fplant_part ); ?>" class="fplant-form-control">
							</div>
							<div style="flex: 1;">
								<label for="fplant-field-name-placeholder-<?php echo esc_attr( $fplant_part ); ?>" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Placeholder', 'form-plant' ); ?></label>
								<input type="text" id="fplant-field-name-placeholder-<?php echo esc_attr( $fplant_part ); ?>" class="fplant-form-control">
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Name Kana Settings (for name_kana) -->
			<div id="fplant-field-name-kana-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Kana Field Settings', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<label for="fplant-field-kana-format" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Number of Inputs', 'form-plant' ); ?></label>
					<select id="fplant-field-kana-format" class="fplant-form-control">
						<option value="1"><?php esc_html_e( '1 input', 'form-plant' ); ?></option>
						<option value="2" selected><?php esc_html_e( '2 inputs (First / Last)', 'form-plant' ); ?></option>
						<option value="3"><?php esc_html_e( '3 inputs (First / Middle / Last)', 'form-plant' ); ?></option>
					</select>
				</div>
				<?php
				$fplant_kana_part_labels = array(
					'family' => __( 'Last Name (Kana)', 'form-plant' ),
					'given'  => __( 'First Name (Kana)', 'form-plant' ),
					'middle' => __( 'Middle Name (Kana)', 'form-plant' ),
				);
				$fplant_kana_parts_order = ( 0 === strpos( get_locale(), 'ja' ) )
					? array( 'family', 'middle', 'given' )
					: array( 'given', 'middle', 'family' );
				?>
				<div id="fplant-kana-sublabels" style="margin-top: 15px;">
					<?php foreach ( $fplant_kana_parts_order as $fplant_part ) : ?>
					<div class="fplant-kana-sublabel-row" data-part="<?php echo esc_attr( $fplant_part ); ?>" style="<?php echo 'middle' === $fplant_part ? 'display: none; ' : ''; ?>margin-bottom: 10px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
						<div class="fplant-kana-part-heading" style="font-weight: 600; font-size: 12px; margin-bottom: 8px;"
							<?php if ( 'family' === $fplant_part ) : ?>
								data-label-default="<?php echo esc_attr( $fplant_kana_part_labels['family'] ); ?>"
								data-label-single="<?php esc_attr_e( 'Full Name (Kana)', 'form-plant' ); ?>"
							<?php endif; ?>
						><?php echo esc_html( $fplant_kana_part_labels[ $fplant_part ] ); ?></div>
						<div style="display: flex; gap: 10px;">
							<div style="flex: 1;">
								<label for="fplant-field-kana-label-<?php echo esc_attr( $fplant_part ); ?>" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Sub-label', 'form-plant' ); ?></label>
								<input type="text" id="fplant-field-kana-label-<?php echo esc_attr( $fplant_part ); ?>" class="fplant-form-control">
							</div>
							<div style="flex: 1;">
								<label for="fplant-field-kana-placeholder-<?php echo esc_attr( $fplant_part ); ?>" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Placeholder', 'form-plant' ); ?></label>
								<input type="text" id="fplant-field-kana-placeholder-<?php echo esc_attr( $fplant_part ); ?>" class="fplant-form-control">
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Password Settings (for password). Min length / strength live in the Validation tab. -->
			<div id="fplant-field-password-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Password Field Settings', 'form-plant' ); ?></label>

				<div style="margin-top: 10px;">
					<div class="fplant-checkbox">
						<input type="checkbox" id="fplant-field-password-mask-email">
						<label for="fplant-field-password-mask-email">
							<?php esc_html_e( 'Mask value in email notifications', 'form-plant' ); ?>
						</label>
					</div>
					<p class="description">
						<?php esc_html_e( 'Replace the password value with masked characters in email notifications', 'form-plant' ); ?>
					</p>
				</div>

				<div style="margin-top: 10px;">
					<div class="fplant-checkbox">
						<input type="checkbox" id="fplant-field-password-mask-save">
						<label for="fplant-field-password-mask-save">
							<?php esc_html_e( 'Mask value when saving data', 'form-plant' ); ?>
						</label>
					</div>
					<p class="description">
						<?php esc_html_e( 'Replace the password value with masked characters before saving to database (irreversible)', 'form-plant' ); ?>
					</p>
				</div>
			</div>

			<!-- HTML Content Settings (for html) -->
			<div id="fplant-field-html-section" class="fplant-form-group" style="display: none;">
				<label for="fplant-field-html-content"><?php esc_html_e( 'HTML Content', 'form-plant' ); ?> <span class="required">*</span></label>
				<?php // phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings -- Placeholder example text showing HTML format ?>
				<textarea id="fplant-field-html-content" class="fplant-form-control" rows="8" placeholder="<?php esc_attr_e( '<p>Enter your HTML content here...</p>', 'form-plant' ); ?>"></textarea>
				<p class="description"><?php esc_html_e( 'Enter the HTML content to display. This field is for display only and will not be submitted.', 'form-plant' ); ?></p>
			</div>

			<!-- Custom Mail Tag Settings -->
			<div id="fplant-field-custom-mail-tag-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Custom Mail Tag Settings', 'form-plant' ); ?></label>
				<p class="description">
					<?php esc_html_e( 'Value is supplied by the `fplant_custom_mail_tag_value_{name}` PHP filter and included in the submission data / email body.', 'form-plant' ); ?>
				</p>

				<div class="fplant-checkbox" style="margin-top: 10px;">
					<input type="checkbox" id="fplant-field-cmt-display-in-form" checked>
					<label for="fplant-field-cmt-display-in-form"><?php esc_html_e( 'Display the resolved value in the form', 'form-plant' ); ?></label>
				</div>

				<div style="margin-top: 10px;">
					<label for="fplant-field-cmt-display-wrapper" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Wrapper element (when displayed)', 'form-plant' ); ?></label>
					<select id="fplant-field-cmt-display-wrapper" class="fplant-form-control">
						<option value="span"><?php esc_html_e( 'Inline (span)', 'form-plant' ); ?></option>
						<option value="div"><?php esc_html_e( 'Block (div)', 'form-plant' ); ?></option>
						<option value="hidden"><?php esc_html_e( 'Hidden input only (no visible element)', 'form-plant' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Phone Number Settings -->
			<div id="fplant-field-tel-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Phone Number Settings', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<label for="fplant-field-tel-format" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Input Format', 'form-plant' ); ?></label>
					<select id="fplant-field-tel-format" class="fplant-form-control">
						<option value="single"><?php esc_html_e( 'Single input', 'form-plant' ); ?></option>
						<option value="split3"><?php esc_html_e( 'Split input (3 fields)', 'form-plant' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Postal Code Settings -->
			<div id="fplant-field-postal-code-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Postal Code Settings', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<label for="fplant-field-postal-format" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Input Format', 'form-plant' ); ?></label>
					<select id="fplant-field-postal-format" class="fplant-form-control">
						<option value="single"><?php esc_html_e( 'Single input', 'form-plant' ); ?></option>
						<option value="split"><?php esc_html_e( 'Split input (3 + 4 digits)', 'form-plant' ); ?></option>
					</select>
				</div>
				<div style="margin-top: 10px;">
					<label class="fplant-checkbox">
						<input type="checkbox" id="fplant-field-postal-show-search-btn">
						<?php esc_html_e( 'Show search button', 'form-plant' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Display a search button next to the postal code input.', 'form-plant' ); ?></p>
				</div>
				<div style="margin-top: 10px;">
					<label class="fplant-checkbox">
						<input type="checkbox" id="fplant-field-postal-autofill">
						<?php esc_html_e( 'Enable auto-fill from postal code', 'form-plant' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Automatically fills address fields when a valid postal code is entered. Available for Japanese locale only.', 'form-plant' ); ?></p>
				</div>
				<div id="fplant-postal-autofill-targets" style="display: none; margin-top: 10px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
					<p class="description" style="margin-bottom: 10px;">
						<?php esc_html_e( 'Field 1 only (text): all address info. Field 1 only (select): prefecture only. Fields 1+2: prefecture + remaining. Fields 1+2+3: prefecture, city, street.', 'form-plant' ); ?>
					</p>
					<div style="margin-bottom: 10px;">
						<label for="fplant-field-postal-target-pref" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Auto-fill field 1', 'form-plant' ); ?></label>
						<select id="fplant-field-postal-target-pref" class="fplant-form-control fplant-postal-target-select">
							<option value=""><?php esc_html_e( '-- Not set --', 'form-plant' ); ?></option>
						</select>
					</div>
					<div style="margin-bottom: 10px;">
						<label for="fplant-field-postal-target-addr1" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Auto-fill field 2', 'form-plant' ); ?></label>
						<select id="fplant-field-postal-target-addr1" class="fplant-form-control fplant-postal-target-select">
							<option value=""><?php esc_html_e( '-- Not set --', 'form-plant' ); ?></option>
						</select>
					</div>
					<div>
						<label for="fplant-field-postal-target-addr2" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Auto-fill field 3', 'form-plant' ); ?></label>
						<select id="fplant-field-postal-target-addr2" class="fplant-form-control fplant-postal-target-select">
							<option value=""><?php esc_html_e( '-- Not set --', 'form-plant' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<!-- Prefecture Settings -->
			<div id="fplant-field-prefecture-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Prefecture Settings', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<label for="fplant-field-pref-display-type" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Display Type', 'form-plant' ); ?></label>
					<select id="fplant-field-pref-display-type" class="fplant-form-control">
						<option value="select"><?php esc_html_e( 'Dropdown', 'form-plant' ); ?></option>
						<option value="radio"><?php esc_html_e( 'Radio Button', 'form-plant' ); ?></option>
						<option value="checkbox"><?php esc_html_e( 'Checkbox', 'form-plant' ); ?></option>
					</select>
				</div>
				<div id="fplant-field-pref-layout-section" style="margin-top: 10px; display: none;">
					<label style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Layout', 'form-plant' ); ?></label>
					<div style="display: flex; gap: 20px;">
						<label style="font-weight: normal;">
							<input type="radio" name="fplant-field-pref-layout" value="vertical" checked>
							<?php esc_html_e( 'Vertical', 'form-plant' ); ?>
						</label>
						<label style="font-weight: normal;">
							<input type="radio" name="fplant-field-pref-layout" value="horizontal">
							<?php esc_html_e( 'Horizontal', 'form-plant' ); ?>
						</label>
					</div>
				</div>
				<div style="margin-top: 10px;">
					<label for="fplant-field-pref-options-textarea" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Options', 'form-plant' ); ?></label>
					<textarea id="fplant-field-pref-options-textarea" class="fplant-form-control" rows="10"></textarea>
					<p class="description"><?php esc_html_e( 'Enter one option per line. You can change the order or edit choices.', 'form-plant' ); ?></p>
				</div>
			</div>

			<!-- Address Composite Settings -->
			<div id="fplant-field-address-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Address Settings', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<label for="fplant-field-address-postal-format" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Postal Code Format', 'form-plant' ); ?></label>
					<select id="fplant-field-address-postal-format" class="fplant-form-control">
						<option value="single"><?php esc_html_e( 'Single input', 'form-plant' ); ?></option>
						<option value="split"><?php esc_html_e( 'Split input (3 + 4 digits)', 'form-plant' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Japanese locale only.', 'form-plant' ); ?></p>
				</div>
				<div style="margin-top: 10px;">
					<label class="fplant-checkbox">
						<input type="checkbox" id="fplant-field-address-show-search-btn">
						<?php esc_html_e( 'Show search button', 'form-plant' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Display a search button next to the postal code input.', 'form-plant' ); ?></p>
				</div>
				<div style="margin-top: 10px;">
					<label for="fplant-field-address-pref-type" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Prefecture Display Type', 'form-plant' ); ?></label>
					<select id="fplant-field-address-pref-type" class="fplant-form-control">
						<option value="select"><?php esc_html_e( 'Dropdown', 'form-plant' ); ?></option>
						<option value="text"><?php esc_html_e( 'Text', 'form-plant' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Japanese locale only.', 'form-plant' ); ?></p>
				</div>
				<div id="fplant-address-labels-section" style="margin-top: 15px;">
					<label style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Sub-field Labels', 'form-plant' ); ?></label>
					<?php
					$fplant_addr_sub_fields_ja = array(
						'postal_code' => __( 'Postal Code', 'form-plant' ),
						'prefecture'  => __( 'Prefecture', 'form-plant' ),
						'city'        => __( 'City', 'form-plant' ),
						'street'      => __( 'Street Address', 'form-plant' ),
						'building'    => __( 'Building / Apartment', 'form-plant' ),
					);
					$fplant_addr_sub_fields_intl = array(
						'street'      => __( 'Street Address', 'form-plant' ),
						'address2'    => __( 'Address Line 2', 'form-plant' ),
						'city'        => __( 'City', 'form-plant' ),
						'state'       => __( 'State / Province', 'form-plant' ),
						'postal_code' => __( 'Postal Code', 'form-plant' ),
						'country'     => __( 'Country', 'form-plant' ),
					);
					$fplant_addr_is_ja = ( 0 === strpos( get_locale(), 'ja' ) );
					$fplant_addr_all_sub_fields = array_merge(
						array_keys( $fplant_addr_sub_fields_ja ),
						array_keys( $fplant_addr_sub_fields_intl )
					);
					$fplant_addr_all_sub_fields = array_unique( $fplant_addr_all_sub_fields );
					$fplant_addr_all_labels = array_merge( $fplant_addr_sub_fields_ja, $fplant_addr_sub_fields_intl );
					$fplant_addr_intl_only_keys = array_diff( array_keys( $fplant_addr_sub_fields_intl ), array_keys( $fplant_addr_sub_fields_ja ) );
					foreach ( $fplant_addr_all_sub_fields as $fplant_sub_key ) :
						$fplant_addr_row_hidden = $fplant_addr_is_ja && in_array( $fplant_sub_key, $fplant_addr_intl_only_keys, true );
					?>
					<div class="fplant-address-label-row" data-sub-key="<?php echo esc_attr( $fplant_sub_key ); ?>" style="margin-bottom: 8px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 4px;<?php echo $fplant_addr_row_hidden ? ' display: none;' : ''; ?>">
						<div style="font-weight: 600; font-size: 12px; margin-bottom: 6px;"><?php echo esc_html( $fplant_addr_all_labels[ $fplant_sub_key ] ); ?></div>
						<div style="display: flex; gap: 10px;">
							<div style="flex: 1;">
								<label for="fplant-field-address-label-<?php echo esc_attr( $fplant_sub_key ); ?>" style="font-weight: normal; font-size: 11px;"><?php esc_html_e( 'Label', 'form-plant' ); ?></label>
								<input type="text" id="fplant-field-address-label-<?php echo esc_attr( $fplant_sub_key ); ?>" class="fplant-form-control">
							</div>
							<div style="flex: 1;">
								<label for="fplant-field-address-placeholder-<?php echo esc_attr( $fplant_sub_key ); ?>" style="font-weight: normal; font-size: 11px;"><?php esc_html_e( 'Placeholder', 'form-plant' ); ?></label>
								<input type="text" id="fplant-field-address-placeholder-<?php echo esc_attr( $fplant_sub_key ); ?>" class="fplant-form-control">
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Default Value Settings -->
			<div id="fplant-field-default-value-section" class="fplant-form-group" style="display: none;">
				<label for="fplant-field-default-value"><?php esc_html_e( 'Default Value', 'form-plant' ); ?></label>
				<input type="text" id="fplant-field-default-value" class="fplant-form-control">
				<?php // Multi-line variant shown for the textarea field type (toggled in admin.js). ?>
				<textarea id="fplant-field-default-value-textarea" class="fplant-form-control" rows="3" style="display: none;"></textarea>
				<p class="description"><?php esc_html_e( 'Default value for the field', 'form-plant' ); ?></p>
			</div>
		</div><!-- /[data-ftab="basic"] -->

		<div class="fplant-field-tab-panel" role="tabpanel" data-ftab="validation" hidden>
			<div class="fplant-checkbox">
				<input type="checkbox" id="fplant-field-required">
				<label for="fplant-field-required"><?php esc_html_e( 'Required Field', 'form-plant' ); ?></label>
			</div>

			<div class="fplant-form-group">
				<label for="fplant-field-validation-message"><?php esc_html_e( 'Validation Message', 'form-plant' ); ?></label>
				<input type="text" id="fplant-field-validation-message" class="fplant-form-control" placeholder="<?php esc_attr_e( 'This field is required. Please enter a value.', 'form-plant' ); ?>">
				<p class="description"><?php esc_html_e( 'Message to display when required field is empty (default message used if blank)', 'form-plant' ); ?></p>
			</div>

			<!-- Max Length Settings (for textarea) -->
			<div id="fplant-field-maxlength-section" class="fplant-form-group" style="display: none;">
				<label for="fplant-field-textarea-maxlength"><?php esc_html_e( 'Max Length', 'form-plant' ); ?></label>
				<input
					type="number"
					id="fplant-field-textarea-maxlength"
					class="fplant-form-control"
					min="1"
					max="10000"
				>
				<p class="description"><?php esc_html_e( 'Maximum number of characters allowed', 'form-plant' ); ?></p>

				<label for="fplant-field-maxlength-message" style="margin-top: 12px;"><?php esc_html_e( 'Max Length Error Message', 'form-plant' ); ?></label>
				<input type="text" id="fplant-field-maxlength-message" class="fplant-form-control">
				<p class="description"><?php esc_html_e( 'Message to display when the maximum length is exceeded (default message used if blank)', 'form-plant' ); ?></p>
			</div>

			<!-- Max Length (for text / email / url / password) -->
			<div id="fplant-field-maxlength-text-section" class="fplant-form-group" style="display: none;">
				<label for="fplant-field-maxlength"><?php esc_html_e( 'Max Length', 'form-plant' ); ?></label>
				<input type="number" id="fplant-field-maxlength" class="fplant-form-control" placeholder="" min="1" max="10000">
				<p class="description"><?php esc_html_e( 'Maximum number of characters allowed', 'form-plant' ); ?></p>
			</div>

			<!-- Validation Messages (for name_parts) -->
			<div id="fplant-field-name-parts-validation-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Validation Messages', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<?php foreach ( $fplant_name_parts_order as $fplant_part ) : ?>
					<div class="fplant-name-validation-row" data-part="<?php echo esc_attr( $fplant_part ); ?>" style="<?php echo 'middle' === $fplant_part ? 'display: none; ' : ''; ?>margin-bottom: 10px;">
						<label for="fplant-field-name-validation-<?php echo esc_attr( $fplant_part ); ?>" style="font-weight: normal; font-size: 12px;">
							<span class="fplant-name-part-heading"
								<?php if ( 'family' === $fplant_part ) : ?>
									data-label-default="<?php echo esc_attr( $fplant_name_part_labels['family'] ); ?>"
									data-label-single="<?php esc_attr_e( 'Full Name', 'form-plant' ); ?>"
								<?php endif; ?>
							><?php echo esc_html( $fplant_name_part_labels[ $fplant_part ] ); ?></span>
						</label>
						<input type="text" id="fplant-field-name-validation-<?php echo esc_attr( $fplant_part ); ?>" class="fplant-form-control" placeholder="<?php esc_attr_e( 'Default message used if blank', 'form-plant' ); ?>">
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Validation (for name_kana) -->
			<div id="fplant-field-name-kana-validation-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Validation Messages', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<?php foreach ( $fplant_kana_parts_order as $fplant_part ) : ?>
					<div class="fplant-kana-validation-row" data-part="<?php echo esc_attr( $fplant_part ); ?>" style="<?php echo 'middle' === $fplant_part ? 'display: none; ' : ''; ?>margin-bottom: 10px;">
						<label for="fplant-field-kana-validation-<?php echo esc_attr( $fplant_part ); ?>" style="font-weight: normal; font-size: 12px;">
							<span class="fplant-kana-part-heading"
								<?php if ( 'family' === $fplant_part ) : ?>
									data-label-default="<?php echo esc_attr( $fplant_kana_part_labels['family'] ); ?>"
									data-label-single="<?php esc_attr_e( 'Full Name (Kana)', 'form-plant' ); ?>"
								<?php endif; ?>
							><?php echo esc_html( $fplant_kana_part_labels[ $fplant_part ] ); ?></span>
						</label>
						<input type="text" id="fplant-field-kana-validation-<?php echo esc_attr( $fplant_part ); ?>" class="fplant-form-control" placeholder="<?php esc_attr_e( 'Default message used if blank', 'form-plant' ); ?>">
					</div>
					<?php endforeach; ?>
				</div>
				<div style="margin-top: 15px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
					<label style="font-weight: 600; font-size: 12px; margin-bottom: 8px; display: block;"><?php esc_html_e( 'Kana Validation', 'form-plant' ); ?></label>
					<label style="font-weight: normal; margin-right: 15px;">
						<input type="radio" name="fplant-field-kana-validation" value="katakana" checked>
						<?php esc_html_e( 'Katakana only', 'form-plant' ); ?>
					</label>
					<label style="font-weight: normal; margin-right: 15px;">
						<input type="radio" name="fplant-field-kana-validation" value="hiragana">
						<?php esc_html_e( 'Hiragana only', 'form-plant' ); ?>
					</label>
					<label style="font-weight: normal;">
						<input type="radio" name="fplant-field-kana-validation" value="none">
						<?php esc_html_e( 'No validation', 'form-plant' ); ?>
					</label>
				</div>
				<div id="fplant-field-kana-error-message-section" style="margin-top: 10px;">
					<label for="fplant-field-kana-error-message" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Kana Validation Error Message', 'form-plant' ); ?></label>
					<input type="text" id="fplant-field-kana-error-message" class="fplant-form-control" placeholder="<?php esc_attr_e( 'Please enter in katakana.', 'form-plant' ); ?>">
					<p class="description"><?php esc_html_e( 'Custom error message for kana validation (default message used if blank)', 'form-plant' ); ?></p>
				</div>
			</div>

			<!-- Validation Messages (for address) -->
			<div id="fplant-field-address-validation-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Validation Messages', 'form-plant' ); ?></label>
				<div style="margin-top: 10px;">
					<?php
					foreach ( $fplant_addr_all_sub_fields as $fplant_sub_key ) :
						$fplant_addr_row_hidden = $fplant_addr_is_ja && in_array( $fplant_sub_key, $fplant_addr_intl_only_keys, true );
						?>
					<div class="fplant-address-validation-row" data-sub-key="<?php echo esc_attr( $fplant_sub_key ); ?>" style="margin-bottom: 8px;<?php echo $fplant_addr_row_hidden ? ' display: none;' : ''; ?>">
						<label for="fplant-field-address-validation-<?php echo esc_attr( $fplant_sub_key ); ?>" style="font-weight: normal; font-size: 11px;"><?php echo esc_html( $fplant_addr_all_labels[ $fplant_sub_key ] ); ?></label>
						<input type="text" id="fplant-field-address-validation-<?php echo esc_attr( $fplant_sub_key ); ?>" class="fplant-form-control" placeholder="<?php esc_attr_e( 'Default message used if blank', 'form-plant' ); ?>">
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Password constraints (for password) -->
			<div id="fplant-field-password-validation-section" class="fplant-form-group" style="display: none;">
				<div>
					<label for="fplant-field-password-min-length" style="font-weight: normal; font-size: 12px;">
						<?php esc_html_e( 'Minimum Character Length', 'form-plant' ); ?>
					</label>
					<input
						type="number"
						id="fplant-field-password-min-length"
						class="fplant-form-control"
						min="1"
						max="100"
						style="width: 100px;"
					>
					<p class="description">
						<?php esc_html_e( 'Minimum number of characters required (leave blank for no limit)', 'form-plant' ); ?>
					</p>
				</div>

				<div style="margin-top: 15px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
					<div class="fplant-checkbox">
						<input type="checkbox" id="fplant-field-password-strength-meter">
						<label for="fplant-field-password-strength-meter">
							<?php esc_html_e( 'Show password strength meter', 'form-plant' ); ?>
						</label>
					</div>

					<div id="fplant-field-password-strength-level-section" style="margin-top: 10px; display: none;">
						<label style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 8px;">
							<?php esc_html_e( 'Required Strength Level', 'form-plant' ); ?>
						</label>
						<label style="font-weight: normal; margin-right: 15px;">
							<input type="radio" name="fplant-field-password-strength-level" value="none" checked>
							<?php esc_html_e( 'None', 'form-plant' ); ?>
						</label>
						<label style="font-weight: normal; margin-right: 15px;">
							<input type="radio" name="fplant-field-password-strength-level" value="weak">
							<?php esc_html_e( 'Weak', 'form-plant' ); ?>
						</label>
						<label style="font-weight: normal; margin-right: 15px;">
							<input type="radio" name="fplant-field-password-strength-level" value="fair">
							<?php esc_html_e( 'Fair', 'form-plant' ); ?>
						</label>
						<label style="font-weight: normal;">
							<input type="radio" name="fplant-field-password-strength-level" value="strong">
							<?php esc_html_e( 'Strong', 'form-plant' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Set the minimum password strength required for submission', 'form-plant' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<div class="fplant-field-tab-panel" role="tabpanel" data-ftab="advanced" hidden>
			<div class="fplant-form-group">
				<label for="fplant-field-desc-after-label"><?php esc_html_e( 'Description below label', 'form-plant' ); ?></label>
				<textarea id="fplant-field-desc-after-label" class="fplant-form-control" rows="2"></textarea>
				<p class="description"><?php esc_html_e( 'Shown directly below the field label. HTML tags are allowed.', 'form-plant' ); ?></p>
			</div>

			<div class="fplant-form-group">
				<label for="fplant-field-desc-before-input"><?php esc_html_e( 'Description above input', 'form-plant' ); ?></label>
				<textarea id="fplant-field-desc-before-input" class="fplant-form-control" rows="2"></textarea>
				<p class="description"><?php esc_html_e( 'Shown directly above the input. HTML tags are allowed.', 'form-plant' ); ?></p>
			</div>

			<div class="fplant-form-group">
				<label for="fplant-field-desc-after-input"><?php esc_html_e( 'Description below input', 'form-plant' ); ?></label>
				<textarea id="fplant-field-desc-after-input" class="fplant-form-control" rows="2"></textarea>
				<p class="description"><?php esc_html_e( 'Shown below the input (below the error message if any). HTML tags are allowed.', 'form-plant' ); ?></p>
			</div>

			<div class="fplant-form-group">
				<label for="fplant-field-custom-id"><?php esc_html_e( 'Custom ID', 'form-plant' ); ?></label>
				<input type="text" id="fplant-field-custom-id" class="fplant-form-control" placeholder="<?php esc_attr_e( 'e.g., my-custom-field', 'form-plant' ); ?>">
				<p class="description"><?php esc_html_e( 'Set a custom ID for the field (auto-generated if blank)', 'form-plant' ); ?></p>
			</div>

			<div class="fplant-form-group">
				<label for="fplant-field-custom-class"><?php esc_html_e( 'Custom Class', 'form-plant' ); ?></label>
				<input type="text" id="fplant-field-custom-class" class="fplant-form-control" placeholder="<?php esc_attr_e( 'e.g., my-class another-class', 'form-plant' ); ?>">
				<p class="description"><?php esc_html_e( 'CSS classes to add to the field (separate multiple with spaces)', 'form-plant' ); ?></p>
			</div>
		</div>

		<div class="fplant-field-editor-footer">
			<button type="button" id="fplant-save-field" class="button button-primary"><?php esc_html_e( 'Close', 'form-plant' ); ?></button>
		</div>
	</div><!-- /#fplant-field-editor -->
</div><!-- /#fplant-field-editor-host -->

<!-- Field Type Picker Modal (icon grid; choosing a type creates a field of that type) -->
<div id="fplant-field-type-picker-modal" class="fplant-modal">
	<div class="fplant-modal-content fplant-type-picker-content">
		<div class="fplant-modal-header">
			<h2><?php esc_html_e( 'Select Field Type', 'form-plant' ); ?></h2>
			<button type="button" class="fplant-modal-close">&times;</button>
		</div>
		<div class="fplant-modal-body">
			<div class="fplant-type-picker-grid">
				<?php
				// Generated from the single source of truth (fplant_field_types filter),
				// so Pro-registered types appear here automatically.
				foreach ( ( new FPLANT_Field_Manager() )->get_field_types() as $fplant_pick_type => $fplant_pick_cfg ) :
					?>
					<button type="button" class="fplant-type-picker-option" data-type="<?php echo esc_attr( $fplant_pick_type ); ?>">
						<span class="dashicons <?php echo esc_attr( $fplant_pick_cfg['icon'] ?? 'dashicons-forms' ); ?>" aria-hidden="true"></span>
						<span class="fplant-type-picker-label"><?php echo esc_html( $fplant_pick_cfg['label'] ?? $fplant_pick_type ); ?></span>
					</button>
					<?php
				endforeach;
				?>
			</div>
		</div>
	</div>
</div>

<!-- Submit Button Settings Modal -->
<div id="fplant-input-submit-modal" class="fplant-modal">
	<div class="fplant-modal-content">
		<div class="fplant-modal-header">
			<h2><?php esc_html_e( 'Submit Button Settings', 'form-plant' ); ?></h2>
			<button type="button" class="fplant-modal-close">&times;</button>
		</div>
		<div class="fplant-modal-body">
			<div class="fplant-form-group">
				<label for="fplant-input-submit-text"><?php esc_html_e( 'Button Text', 'form-plant' ); ?></label>
				<input
					type="text"
					id="fplant-input-submit-text"
					class="fplant-form-control"
					value="<?php echo esc_attr( $fplant_form['settings']['input_submit_text'] ?? __( 'Submit', 'form-plant' ) ); ?>"
				>
			</div>
			<div class="fplant-form-group">
				<label for="fplant-input-submit-class"><?php esc_html_e( 'CSS Class', 'form-plant' ); ?></label>
				<input
					type="text"
					id="fplant-input-submit-class"
					class="fplant-form-control"
					value="<?php echo esc_attr( $fplant_form['settings']['input_submit_class'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'e.g., btn btn-primary', 'form-plant' ); ?>"
				>
				<p class="description"><?php esc_html_e( 'Added to the default fplant-submit-button class', 'form-plant' ); ?></p>
			</div>
			<div class="fplant-form-group">
				<label for="fplant-input-submit-id"><?php esc_html_e( 'ID', 'form-plant' ); ?></label>
				<input
					type="text"
					id="fplant-input-submit-id"
					class="fplant-form-control"
					value="<?php echo esc_attr( $fplant_form['settings']['input_submit_id'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'e.g., my-submit-button', 'form-plant' ); ?>"
				>
			</div>
		</div>
		<div class="fplant-modal-footer">
			<button type="button" class="button fplant-button-secondary fplant-modal-close"><?php esc_html_e( 'Cancel', 'form-plant' ); ?></button>
			<button type="button" id="fplant-save-input-submit" class="button button-primary"><?php esc_html_e( 'OK', 'form-plant' ); ?></button>
		</div>
	</div>
</div>

<!-- Confirmation Screen Back Button Settings Modal -->
<div id="fplant-confirmation-back-modal" class="fplant-modal">
	<div class="fplant-modal-content">
		<div class="fplant-modal-header">
			<h2><?php esc_html_e( 'Back Button Settings', 'form-plant' ); ?></h2>
			<button type="button" class="fplant-modal-close">&times;</button>
		</div>
		<div class="fplant-modal-body">
			<div class="fplant-form-group">
				<label for="fplant-confirmation-back-text"><?php esc_html_e( 'Button Text', 'form-plant' ); ?></label>
				<input
					type="text"
					id="fplant-confirmation-back-text"
					class="fplant-form-control"
					value="<?php echo esc_attr( $fplant_form['settings']['confirmation_back_text'] ?? __( 'Back', 'form-plant' ) ); ?>"
				>
			</div>
			<div class="fplant-form-group">
				<label for="fplant-confirmation-back-class"><?php esc_html_e( 'CSS Class', 'form-plant' ); ?></label>
				<input
					type="text"
					id="fplant-confirmation-back-class"
					class="fplant-form-control"
					value="<?php echo esc_attr( $fplant_form['settings']['confirmation_back_class'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'e.g., btn btn-secondary', 'form-plant' ); ?>"
				>
				<p class="description"><?php esc_html_e( 'Added to the default fplant-back-button class', 'form-plant' ); ?></p>
			</div>
			<div class="fplant-form-group">
				<label for="fplant-confirmation-back-id"><?php esc_html_e( 'ID', 'form-plant' ); ?></label>
				<input
					type="text"
					id="fplant-confirmation-back-id"
					class="fplant-form-control"
					value="<?php echo esc_attr( $fplant_form['settings']['confirmation_back_id'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'e.g., my-back-button', 'form-plant' ); ?>"
				>
			</div>
		</div>
		<div class="fplant-modal-footer">
			<button type="button" class="button fplant-button-secondary fplant-modal-close"><?php esc_html_e( 'Cancel', 'form-plant' ); ?></button>
			<button type="button" id="fplant-save-confirmation-back" class="button button-primary"><?php esc_html_e( 'OK', 'form-plant' ); ?></button>
		</div>
	</div>
</div>

<!-- Confirmation Screen Submit Button Settings Modal -->
<div id="fplant-confirmation-submit-modal" class="fplant-modal">
	<div class="fplant-modal-content">
		<div class="fplant-modal-header">
			<h2><?php esc_html_e( 'Submit Button Settings', 'form-plant' ); ?></h2>
			<button type="button" class="fplant-modal-close">&times;</button>
		</div>
		<div class="fplant-modal-body">
			<div class="fplant-form-group">
				<label for="fplant-confirmation-submit-text"><?php esc_html_e( 'Button Text', 'form-plant' ); ?></label>
				<input
					type="text"
					id="fplant-confirmation-submit-text"
					class="fplant-form-control"
					value="<?php echo esc_attr( $fplant_form['settings']['confirmation_submit_text'] ?? __( 'Submit Form', 'form-plant' ) ); ?>"
				>
			</div>
			<div class="fplant-form-group">
				<label for="fplant-confirmation-submit-class"><?php esc_html_e( 'CSS Class', 'form-plant' ); ?></label>
				<input
					type="text"
					id="fplant-confirmation-submit-class"
					class="fplant-form-control"
					value="<?php echo esc_attr( $fplant_form['settings']['confirmation_submit_class'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'e.g., btn btn-primary', 'form-plant' ); ?>"
				>
				<p class="description"><?php esc_html_e( 'Added to the default fplant-confirm-submit-button class', 'form-plant' ); ?></p>
			</div>
			<div class="fplant-form-group">
				<label for="fplant-confirmation-submit-id"><?php esc_html_e( 'ID', 'form-plant' ); ?></label>
				<input
					type="text"
					id="fplant-confirmation-submit-id"
					class="fplant-form-control"
					value="<?php echo esc_attr( $fplant_form['settings']['confirmation_submit_id'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'e.g., my-confirm-submit-button', 'form-plant' ); ?>"
				>
			</div>
		</div>
		<div class="fplant-modal-footer">
			<button type="button" class="button fplant-button-secondary fplant-modal-close"><?php esc_html_e( 'Cancel', 'form-plant' ); ?></button>
			<button type="button" id="fplant-save-confirmation-submit" class="button button-primary"><?php esc_html_e( 'OK', 'form-plant' ); ?></button>
		</div>
	</div>
</div>

<?php if ( ! $fplant_is_new ) : ?>
<!-- Form Preview Modal (renders the saved form inside the active theme via an iframe) -->
<div id="fplant-preview-modal" class="fplant-modal fplant-preview-modal">
	<div class="fplant-modal-content fplant-preview-modal-content">
		<div class="fplant-modal-header">
			<h2><?php esc_html_e( 'Form Preview', 'form-plant' ); ?></h2>
			<button type="button" class="fplant-modal-close">&times;</button>
		</div>
		<div class="fplant-modal-body fplant-preview-modal-body">
			<p class="description fplant-preview-note"><?php esc_html_e( 'This preview shows the saved form as it appears on your site. Save the form to reflect the latest changes.', 'form-plant' ); ?></p>
			<div class="fplant-preview-toolbar">
				<div class="fplant-preview-devices" role="group" aria-label="<?php esc_attr_e( 'Preview device', 'form-plant' ); ?>">
					<button type="button" class="button fplant-preview-device active" data-width="">
						<?php esc_html_e( 'Desktop', 'form-plant' ); ?>
					</button>
					<button type="button" class="button fplant-preview-device" data-width="768">
						<?php esc_html_e( 'Tablet', 'form-plant' ); ?>
					</button>
					<button type="button" class="button fplant-preview-device" data-width="375">
						<?php esc_html_e( 'Mobile', 'form-plant' ); ?>
					</button>
				</div>
				<label class="fplant-preview-zoom-label">
					<?php esc_html_e( 'Zoom', 'form-plant' ); ?>
					<select class="fplant-preview-zoom">
						<option value="0.5">50%</option>
						<option value="0.75">75%</option>
						<option value="1" selected>100%</option>
						<option value="1.25">125%</option>
						<option value="1.5">150%</option>
					</select>
				</label>
			</div>
			<div class="fplant-preview-stage">
				<div class="fplant-preview-frame">
					<iframe class="fplant-preview-iframe" title="<?php esc_attr_e( 'Form Preview', 'form-plant' ); ?>" src="about:blank"></iframe>
				</div>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>
