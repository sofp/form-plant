<?php
/**
 * Embed template
 *
 * Lightweight HTML template used for iframe embedding.
 * Does not depend on WordPress theme.
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Form data is passed via the $form variable
$form_id  = $form['id'];
$fields   = $form['fields'] ?? array();
$settings = $form['settings'] ?? array();

// Set global form context for shortcodes (required for [fplant_field] etc.)
global $fplant_current_form;
$fplant_current_form = $form;

// Initialize field manager (for getting initial values)
$field_manager = new FPLANT_Field_Manager();

// CSS/JS URLs
$css_url = FPLANT_PLUGIN_URL . 'assets/css/form.css';
$js_url  = FPLANT_PLUGIN_URL . 'assets/js/form.js';

// Custom CSS processing
$custom_css_mode = $settings['custom_css_mode'] ?? 'none';
$load_default_css = ( 'replace' !== $custom_css_mode );
$custom_css_file_url = $settings['custom_css_file_url'] ?? '';
$custom_css_inline = $settings['custom_css_inline'] ?? '';

// Form settings
$use_confirmation = ! empty( $settings['use_confirmation'] );
$nonce = wp_create_nonce( 'fplant_form_nonce' );

// reCAPTCHA settings
$recaptcha_enabled  = ! empty( $settings['recaptcha_enabled'] );
$recaptcha_site_key = get_option( 'fplant_recaptcha_site_key', '' );
$recaptcha_version  = $settings['recaptcha_version'] ?? 'v3';

?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $form['title'] ?? __( 'Form', 'form-plant' ) ); ?></title>

	<?php if ( $load_default_css ) : ?>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Embed template outside of WordPress theme ?>
	<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>?ver=<?php echo esc_attr( FPLANT_VERSION ); ?>">
	<?php endif; ?>

	<?php if ( 'none' !== $custom_css_mode && ! empty( $custom_css_file_url ) ) : ?>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Embed template outside of WordPress theme ?>
	<link rel="stylesheet" href="<?php echo esc_url( $custom_css_file_url ); ?>">
	<?php endif; ?>

	<?php if ( 'none' !== $custom_css_mode && ! empty( $custom_css_inline ) ) : ?>
	<style><?php echo esc_html( wp_strip_all_tags( $custom_css_inline ) ); ?></style>
	<?php endif; ?>

	<style>
		/* Additional styles for embed */
		html, body {
			margin: 0;
			padding: 0;
			background: transparent;
		}
		.fplant-form-wrapper {
			max-width: 100%;
			padding: 20px;
			box-sizing: border-box;
		}
	</style>

	<?php if ( $recaptcha_enabled && ! empty( $recaptcha_site_key ) ) : ?>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- External reCAPTCHA script in embed template ?>
	<script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr( $recaptcha_site_key ); ?>"></script>
	<?php endif; ?>
</head>
<body>
	<div class="fplant-form-wrapper" data-form-id="<?php echo esc_attr( $form_id ); ?>">
		<form class="fplant-form" method="post" enctype="multipart/form-data" data-form-id="<?php echo esc_attr( $form_id ); ?>" data-use-confirmation="<?php echo esc_attr( $use_confirmation ? '1' : '0' ); ?>">
			<!-- Messages must be inside form for form.js to find them -->
			<div class="fplant-messages">
				<div class="fplant-errors" data-show-field-errors="false" style="display: none;"></div>
				<div class="fplant-success" style="display: none;"></div>
			</div>

			<input type="hidden" name="fplant_form_id" value="<?php echo esc_attr( $form_id ); ?>">
			<input type="hidden" name="fplant_embed_nonce" value="<?php echo esc_attr( $nonce ); ?>">
			<input type="hidden" name="fplant_embed_mode" value="1">

			<?php if ( ! empty( $form['html_template'] ) && ! empty( $settings['use_html_template'] ) ) : ?>
				<?php
				// Process shortcodes in the input screen HTML template
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in shortcode handlers
				echo do_shortcode( $form['html_template'] );
				?>
			<?php else : ?>
				<?php
				// Render fields using template loader (same structure as form-wrapper.php)
				foreach ( $fields as $field ) :
					$field_name = $field['name'] ?? '';

					// Skip field group structure for html and hidden fields
					if ( 'html' === $field['type'] || 'hidden' === $field['type'] ) {
						continue;
					}

					// Get initial value via field manager
					$field_value = $field_manager->get_field_initial_value( $field, $form_id, $settings );
					?>
					<div class="fplant-field-group" data-field-name="<?php echo esc_attr( $field_name ); ?>">
						<?php if ( ! empty( $field['label'] ) ) : ?>
							<label for="fplant-field-<?php echo esc_attr( $field_name ); ?>">
								<?php echo esc_html( $field['label'] ); ?>
								<?php if ( ! empty( $field['required'] ) ) : ?>
									<span class="required">*</span>
								<?php endif; ?>
							</label>
						<?php endif; ?>

						<?php
						// Render using template via render_field() method
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render_field()
						echo $field_manager->render_field( $field, $field_value, $form_id, $settings );
						?>
						<div class="fplant-field-error" style="display: none;"></div>
					</div>
					<?php
				endforeach;
				?>

				<div class="fplant-submit-wrapper">
					<?php
					$submit_text  = $settings['input_submit_text'] ?? __( 'Submit', 'form-plant' );
					$submit_class = 'fplant-submit-button';
					if ( ! empty( $settings['input_submit_class'] ) ) {
						$submit_class .= ' ' . $settings['input_submit_class'];
					}
					$submit_id = $settings['input_submit_id'] ?? '';
					?>
					<button
						type="submit"
						class="<?php echo esc_attr( $submit_class ); ?>"
						<?php echo ! empty( $submit_id ) ? 'id="' . esc_attr( $submit_id ) . '"' : ''; ?>
					>
						<?php echo esc_html( $submit_text ); ?>
					</button>
				</div>
			<?php endif; ?>
		</form>

		<?php
		// Confirmation screen HTML is dynamically inserted by form.js from server response,
		// so static HTML is no longer needed.
		?>
	</div>

	<script>
	// Form settings for embed
	var wpfplantData = {
		ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
		restUrl: '<?php echo esc_url( rest_url( 'form-plant/v1/' ) ); ?>',
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- (int) cast ensures integer for JavaScript
		formId: <?php echo (int) $form_id; ?>,
		nonce: '<?php echo esc_js( $nonce ); ?>',
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Boolean literal string for JavaScript
		useConfirmation: <?php echo $use_confirmation ? 'true' : 'false'; ?>,
		embedMode: true,
		settings: <?php echo wp_json_encode( $settings ); ?>,
		fields: <?php echo wp_json_encode( $fields ); ?>,
		i18n: {
			validationError: '<?php echo esc_js( __( 'There are errors in your input', 'form-plant' ) ); ?>',
			requiredCheckbox: '<?php echo esc_js( __( 'This field is required. Please select at least one option.', 'form-plant' ) ); ?>',
			requiredRadio: '<?php echo esc_js( __( 'This field is required. Please make a selection.', 'form-plant' ) ); ?>',
			requiredSelect: '<?php echo esc_js( __( 'This field is required. Please make a selection.', 'form-plant' ) ); ?>',
			requiredFile: '<?php echo esc_js( __( 'This field is required. Please select a file.', 'form-plant' ) ); ?>',
			requiredText: '<?php echo esc_js( __( 'This field is required. Please enter a value.', 'form-plant' ) ); ?>',
			<?php /* translators: %s: maximum file size in MB */ ?>
			fileTooLarge: '<?php echo esc_js( __( 'File size is too large. Please select a file under %sMB.', 'form-plant' ) ); ?>',
			imageRequired: '<?php echo esc_js( __( 'Please select an image file.', 'form-plant' ) ); ?>',
			serverError: '<?php echo esc_js( __( 'A server error occurred. Please try again.', 'form-plant' ) ); ?>',
			errorOccurred: '<?php echo esc_js( __( 'An error occurred. Please try again.', 'form-plant' ) ); ?>',
			recaptchaError: '<?php echo esc_js( __( 'reCAPTCHA verification failed. Please reload the page and try again.', 'form-plant' ) ); ?>',
			confirmationTitle: '<?php echo esc_js( __( 'Confirm Your Input', 'form-plant' ) ); ?>',
			confirmationMessage: '<?php echo esc_js( __( 'If the information below is correct, please click the "Submit" button.', 'form-plant' ) ); ?>',
			back: '<?php echo esc_js( __( 'Back', 'form-plant' ) ); ?>',
			submitForm: '<?php echo esc_js( __( 'Submit', 'form-plant' ) ); ?>'
		}
	};

	// reCAPTCHA configuration
	var wpfplantRecaptchaConfig = {};
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- (int) cast ensures integer for JavaScript object key
	wpfplantRecaptchaConfig[<?php echo (int) $form_id; ?>] = {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Boolean literal string for JavaScript
		enabled: <?php echo $recaptcha_enabled ? 'true' : 'false'; ?>,
		version: <?php echo wp_json_encode( $recaptcha_version ); ?>,
		siteKey: <?php echo wp_json_encode( $recaptcha_site_key ); ?>
	};
	</script>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Embed template outside of WordPress theme ?>
	<script src="<?php echo esc_url( $js_url ); ?>?ver=<?php echo esc_attr( FPLANT_VERSION ); ?>"></script>
</body>
</html>
