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

$fplant_is_new = empty( $fplant_form );
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
	<div class="fplant-page-header">
		<h1><?php echo esc_html( $fplant_page_title ); ?></h1>
	</div>

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
				<?php endif; ?>
			</div>
			<div class="fplant-publish-box-right">
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
			<button type="button" class="fplant-tab" data-tab="tab-email">
				<?php esc_html_e( 'Email Settings', 'form-plant' ); ?>
			</button>
			<button type="button" class="fplant-tab" data-tab="tab-settings">
				<?php esc_html_e( 'Form Settings', 'form-plant' ); ?>
			</button>
		</div>

		<!-- Field Settings tab -->
		<div id="tab-fields" class="fplant-tab-content active">
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Fields', 'form-plant' ); ?>
				</div>

				<button type="button" class="fplant-button fplant-add-field" style="margin-bottom: 20px;">
					<?php esc_html_e( '+ Add Field', 'form-plant' ); ?>
				</button>

				<div class="fplant-field-list">
					<?php if ( ! empty( $fplant_form['fields'] ) ) : ?>
						<?php foreach ( $fplant_form['fields'] as $fplant_index => $fplant_field ) : ?>
							<div class="fplant-field-item" data-field-index="<?php echo esc_attr( $fplant_index ); ?>">
								<div class="fplant-field-item-header">
									<div class="fplant-field-item-title">
										<?php echo esc_html( $fplant_field['label'] ?? $fplant_field['name'] ); ?>
										<span style="color: #646970; font-weight: normal;">
											(<?php echo esc_html( $fplant_field['type'] ); ?>)
										</span>
									</div>
									<div class="fplant-field-item-actions">
										<button type="button" class="button fplant-edit-field">
											<?php esc_html_e( 'Edit', 'form-plant' ); ?>
										</button>
										<button type="button" class="button fplant-delete-field" style="color: #d63638;">
											<?php esc_html_e( 'Delete', 'form-plant' ); ?>
										</button>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="fplant-no-fields"><?php esc_html_e( 'No fields yet. Click "Add Field" button to add fields.', 'form-plant' ); ?></p>
					<?php endif; ?>
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

			<!-- Custom CSS Settings -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'Custom CSS Settings', 'form-plant' ); ?>
				</div>

				<div class="fplant-form-group">
					<label><?php esc_html_e( 'CSS Mode', 'form-plant' ); ?></label>
					<div class="fplant-radio">
						<input
							type="radio"
							name="custom_css_mode"
							value="none"
							id="css-mode-none"
							<?php checked( ( $fplant_form['settings']['custom_css_mode'] ?? 'none' ) === 'none' ); ?>
						>
						<label for="css-mode-none">
							<?php esc_html_e( 'Use default CSS only', 'form-plant' ); ?>
						</label>
					</div>
					<div class="fplant-radio">
						<input
							type="radio"
							name="custom_css_mode"
							value="append"
							id="css-mode-append"
							<?php checked( ( $fplant_form['settings']['custom_css_mode'] ?? 'none' ) === 'append' ); ?>
						>
						<label for="css-mode-append">
							<?php esc_html_e( 'Append custom CSS (Default + Custom)', 'form-plant' ); ?>
						</label>
					</div>
					<div class="fplant-radio">
						<input
							type="radio"
							name="custom_css_mode"
							value="replace"
							id="css-mode-replace"
							<?php checked( ( $fplant_form['settings']['custom_css_mode'] ?? 'none' ) === 'replace' ); ?>
						>
						<label for="css-mode-replace">
							<?php esc_html_e( 'Replace with custom CSS (Do not load default)', 'form-plant' ); ?>
						</label>
					</div>
				</div>

				<div class="fplant-custom-css-fields<?php echo ( $fplant_form['settings']['custom_css_mode'] ?? 'none' ) === 'none' ? ' fplant-disabled' : ''; ?>">
					<div class="fplant-form-group">
						<label><?php esc_html_e( 'CSS File', 'form-plant' ); ?></label>
						<div class="fplant-css-upload-wrapper">
							<input
								type="file"
								class="fplant-css-file-input"
								accept=".css"
								<?php echo ( $fplant_form['settings']['custom_css_mode'] ?? 'none' ) === 'none' ? 'disabled' : ''; ?>
							>
							<span class="fplant-css-upload-status"></span>
						</div>
						<?php if ( ! empty( $fplant_form['settings']['custom_css_file_url'] ) ) : ?>
						<div class="fplant-css-current-file" style="margin-top: 10px;">
							<strong><?php esc_html_e( 'Current file:', 'form-plant' ); ?></strong>
							<code class="fplant-custom-css-file-url-display"><?php echo esc_html( basename( $fplant_form['settings']['custom_css_file_url'] ?? '' ) ); ?></code>
							<button type="button" class="button button-small fplant-remove-css-file" <?php echo ( $fplant_form['settings']['custom_css_mode'] ?? 'none' ) === 'none' ? 'disabled' : ''; ?>>
								<?php esc_html_e( 'Remove', 'form-plant' ); ?>
							</button>
						</div>
						<?php endif; ?>
						<input type="hidden" class="fplant-custom-css-file-url" value="<?php echo esc_attr( $fplant_form['settings']['custom_css_file_url'] ?? '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Please upload a CSS file (.css)', 'form-plant' ); ?>
						</p>
					</div>

					<div class="fplant-form-group">
						<label><?php esc_html_e( 'Inline CSS', 'form-plant' ); ?></label>
						<textarea
							class="fplant-form-control fplant-custom-css-inline"
							rows="10"
							placeholder="<?php esc_attr_e( 'Enter custom CSS directly (can be used with file upload)', 'form-plant' ); ?>"
							style="font-family: monospace; font-size: 13px;"
							<?php echo ( $fplant_form['settings']['custom_css_mode'] ?? 'none' ) === 'none' ? 'readonly' : ''; ?>
						><?php echo esc_textarea( $fplant_form['settings']['custom_css_inline'] ?? '' ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'When both CSS file and inline CSS are specified, they are loaded in order: File then Inline', 'form-plant' ); ?>
						</p>
					</div>
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
						type="email"
						class="fplant-form-control fplant-email-admin-to"
						value="<?php echo esc_attr( $fplant_form['email_admin']['to'] ?? get_option( 'admin_email' ) ); ?>"
						placeholder="admin@example.com"
					>
					<p class="description"><?php esc_html_e( 'Separate multiple addresses with commas', 'form-plant' ); ?></p>
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
						<?php esc_html_e( 'Available tags: {form_title}, {submission_id}, {site_name}', 'form-plant' ); ?>
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
						<?php esc_html_e( 'Available tags: {all_fields}, {field:field_name}, {submission_id}, {submission_date}, {ip_address}', 'form-plant' ); ?>
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
						<?php esc_html_e( 'Available tags: {form_title}, {field:field_name}, {site_name}', 'form-plant' ); ?>
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
						<?php esc_html_e( 'Available tags: {all_fields}, {field:field_name}, {site_name}, {site_url}', 'form-plant' ); ?>
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

			<!-- reCAPTCHA Settings -->
			<div class="fplant-card">
				<div class="fplant-card-header">
					<?php esc_html_e( 'reCAPTCHA Settings', 'form-plant' ); ?>
				</div>

				<?php
				$fplant_recaptcha_site_key = get_option( 'fplant_recaptcha_site_key' );
				$fplant_recaptcha_enabled  = ! empty( $fplant_form['settings']['recaptcha_enabled'] );
				$fplant_recaptcha_version  = $fplant_form['settings']['recaptcha_version'] ?? 'v3';
				?>

				<?php if ( empty( $fplant_recaptcha_site_key ) ) : ?>
					<p class="description" style="color: #d63638;">
						<?php
						printf(
							/* translators: %s: settings page link */
							esc_html__( 'To use reCAPTCHA, please first set up the API keys on the %s.', 'form-plant' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=fplant-settings' ) ) . '">' . esc_html__( 'Settings page', 'form-plant' ) . '</a>'
						);
						?>
					</p>
				<?php else : ?>
					<div class="fplant-checkbox">
						<input
							type="checkbox"
							id="recaptcha-enabled"
							class="fplant-setting-recaptcha-enabled"
							<?php checked( $fplant_recaptcha_enabled ); ?>
						>
						<label for="recaptcha-enabled">
							<?php esc_html_e( 'Enable reCAPTCHA', 'form-plant' ); ?>
						</label>
					</div>

					<div class="fplant-recaptcha-settings<?php echo ! $fplant_recaptcha_enabled ? ' fplant-disabled' : ''; ?>" style="margin-top: 15px; margin-left: 25px;">
						<p class="description">
							<?php esc_html_e( 'Uses reCAPTCHA v3 (invisible/score-based) for automatic background verification.', 'form-plant' ); ?>
						</p>
						<!-- v3 fixed (hidden) -->
						<input type="hidden" class="fplant-setting-recaptcha-version" value="v3">
					</div>
				<?php endif; ?>
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
								<?php
								// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Embed code template for users to copy, not executed script
								?>
								<textarea
									class="fplant-form-control fplant-embed-js-code"
									rows="5"
									readonly
									onclick="this.select();"
								><div id="fplant-form-<?php echo esc_attr( $fplant_form['id'] ); ?>"></div>
<script src="<?php echo esc_url( FPLANT_PLUGIN_URL . 'assets/js/embed.js' ); ?>"></script>
<script>WPFPlantEmbed.render(<?php echo esc_js( $fplant_form['id'] ); ?>, '#fplant-form-<?php echo esc_attr( $fplant_form['id'] ); ?>', '<?php echo esc_url( home_url() ); ?>');</script></textarea>
								<?php // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
								<button type="button" class="button fplant-copy-embed-code" data-target=".fplant-embed-js-code">
									<?php esc_html_e( 'Copy', 'form-plant' ); ?>
								</button>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Field Edit Modal -->
<div id="fplant-field-modal" class="fplant-modal">
	<div class="fplant-modal-content fplant-field-modal-content">
		<div class="fplant-modal-header">
			<h2 id="fplant-field-modal-title"><?php esc_html_e( 'Add Field', 'form-plant' ); ?></h2>
			<button type="button" class="fplant-modal-close">&times;</button>
		</div>
		<div class="fplant-modal-body">
			<div id="fplant-field-modal-errors" class="fplant-notice fplant-notice-error" style="display: none;"></div>
			<div class="fplant-form-group">
				<label for="fplant-field-type"><?php esc_html_e( 'Field Type', 'form-plant' ); ?> <span class="required">*</span></label>
				<select id="fplant-field-type" class="fplant-form-control">
					<option value="text"><?php esc_html_e( 'Text', 'form-plant' ); ?></option>
					<option value="email"><?php esc_html_e( 'Email Address', 'form-plant' ); ?></option>
					<option value="tel"><?php esc_html_e( 'Phone Number', 'form-plant' ); ?></option>
					<option value="url"><?php esc_html_e( 'URL', 'form-plant' ); ?></option>
					<option value="number"><?php esc_html_e( 'Number', 'form-plant' ); ?></option>
					<option value="textarea"><?php esc_html_e( 'Textarea', 'form-plant' ); ?></option>
					<option value="select"><?php esc_html_e( 'Select Box', 'form-plant' ); ?></option>
					<option value="radio"><?php esc_html_e( 'Radio Button', 'form-plant' ); ?></option>
					<option value="checkbox"><?php esc_html_e( 'Checkbox', 'form-plant' ); ?></option>
					<option value="date"><?php esc_html_e( 'Date (Calendar)', 'form-plant' ); ?></option>
					<option value="date_select"><?php esc_html_e( 'Date (Dropdown)', 'form-plant' ); ?></option>
					<option value="time"><?php esc_html_e( 'Time', 'form-plant' ); ?></option>
					<option value="file"><?php esc_html_e( 'File Upload', 'form-plant' ); ?></option>
					<option value="hidden"><?php esc_html_e( 'Hidden', 'form-plant' ); ?></option>
					<option value="html"><?php esc_html_e( 'HTML', 'form-plant' ); ?></option>
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
			</div>

			<div class="fplant-checkbox">
				<input type="checkbox" id="fplant-field-required">
				<label for="fplant-field-required"><?php esc_html_e( 'Required Field', 'form-plant' ); ?></label>
			</div>

			<div class="fplant-form-group">
				<label for="fplant-field-validation-message"><?php esc_html_e( 'Validation Message', 'form-plant' ); ?></label>
				<input type="text" id="fplant-field-validation-message" class="fplant-form-control" placeholder="<?php esc_attr_e( 'This field is required. Please enter a value.', 'form-plant' ); ?>">
				<p class="description"><?php esc_html_e( 'Message to display when required field is empty (default message used if blank)', 'form-plant' ); ?></p>
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

			<!-- Text Field Settings (for text) -->
			<div id="fplant-field-text-settings-section" class="fplant-form-group" style="display: none;">
				<label><?php esc_html_e( 'Text Field Settings', 'form-plant' ); ?></label>
				<div style="display: flex; gap: 10px; align-items: flex-start; margin-top: 10px;">
					<div style="flex: 1;">
						<label for="fplant-field-size" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Size', 'form-plant' ); ?></label>
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
					<div style="flex: 1;">
						<label for="fplant-field-maxlength" style="font-weight: normal; font-size: 12px;"><?php esc_html_e( 'Max Length', 'form-plant' ); ?></label>
						<input
							type="number"
							id="fplant-field-maxlength"
							class="fplant-form-control"
							placeholder=""
							min="1"
							max="10000"
						>
						<p class="description"><?php esc_html_e( 'Maximum number of characters allowed', 'form-plant' ); ?></p>
					</div>
				</div>
			</div>

			<!-- HTML Content Settings (for html) -->
			<div id="fplant-field-html-section" class="fplant-form-group" style="display: none;">
				<label for="fplant-field-html-content"><?php esc_html_e( 'HTML Content', 'form-plant' ); ?> <span class="required">*</span></label>
				<?php // phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings -- Placeholder example text showing HTML format ?>
				<textarea id="fplant-field-html-content" class="fplant-form-control" rows="8" placeholder="<?php esc_attr_e( '<p>Enter your HTML content here...</p>', 'form-plant' ); ?>"></textarea>
				<p class="description"><?php esc_html_e( 'Enter the HTML content to display. This field is for display only and will not be submitted.', 'form-plant' ); ?></p>
			</div>

			<!-- Default Value Settings -->
			<div id="fplant-field-default-value-section" class="fplant-form-group" style="display: none;">
				<label for="fplant-field-default-value"><?php esc_html_e( 'Default Value', 'form-plant' ); ?></label>
				<input type="text" id="fplant-field-default-value" class="fplant-form-control">
				<p class="description"><?php esc_html_e( 'Default value for the field', 'form-plant' ); ?></p>
			</div>
		</div>
		<div class="fplant-modal-footer">
			<button type="button" class="button fplant-button-secondary fplant-modal-close"><?php esc_html_e( 'Cancel', 'form-plant' ); ?></button>
			<button type="button" id="fplant-save-field" class="button button-primary"><?php esc_html_e( 'Save', 'form-plant' ); ?></button>
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
