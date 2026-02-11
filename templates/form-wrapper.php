<?php
/**
 * Form wrapper template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Store in global variable (so shortcodes can reference it)
global $fplant_current_form;
$fplant_current_form = $form;
?>

<div class="fplant-form-wrapper" id="fplant-form-<?php echo esc_attr( $form['id'] ); ?>">
	<form
		class="fplant-form"
		data-form-id="<?php echo esc_attr( $form['id'] ); ?>"
		data-use-confirmation="<?php echo esc_attr( ! empty( $form['settings']['use_confirmation'] ) ? '1' : '0' ); ?>"
		data-confirmation-title="<?php echo esc_attr( $form['settings']['confirmation_title'] ?? __( 'Confirm Your Input', 'form-plant' ) ); ?>"
		data-confirmation-message="<?php echo esc_attr( $form['settings']['confirmation_message'] ?? __( 'If the information below is correct, please click the "Submit" button.', 'form-plant' ) ); ?>"
		method="post">

		<?php if ( ! empty( $form['html_template'] ) && ! empty( $form['settings']['use_html_template'] ) ) : ?>
			<?php
			// Process shortcodes in the input screen HTML template
			echo do_shortcode( $form['html_template'] );
			?>
		<?php else : ?>
			<!-- Default layout -->
			<div class="fplant-messages">
				<div class="fplant-errors" data-show-field-errors="false" style="display:none;"></div>
				<div class="fplant-success" style="display:none;"></div>
			</div>

			<?php
			$field_manager = new FPLANT_Field_Manager();
			foreach ( $form['fields'] as $field ) :
				if ( 'html' === $field['type'] || 'hidden' === $field['type'] ) {
					continue;
				}
				?>
				<div class="fplant-field-group" data-field-name="<?php echo esc_attr( $field['name'] ); ?>">
					<?php if ( ! empty( $field['label'] ) ) : ?>
						<label for="fplant-field-<?php echo esc_attr( $field['name'] ); ?>">
							<?php echo esc_html( $field['label'] ); ?>
							<?php if ( ! empty( $field['required'] ) ) : ?>
								<span class="required">*</span>
							<?php endif; ?>
						</label>
					<?php endif; ?>

					<?php echo do_shortcode( '[fplant_field name="' . esc_attr( $field['name'] ) . '"]' ); ?>
					<div class="fplant-field-error" style="display: none;"></div>
				</div>
			<?php endforeach; ?>

			<div class="fplant-submit-wrapper">
				<?php
				$submit_text  = $form['settings']['input_submit_text'] ?? __( 'Submit', 'form-plant' );
				$submit_class = $form['settings']['input_submit_class'] ?? '';
				$submit_id    = $form['settings']['input_submit_id'] ?? '';

				$submit_shortcode = '[fplant_submit text="' . esc_attr( $submit_text ) . '"';
				if ( ! empty( $submit_class ) ) {
					$submit_shortcode .= ' class="' . esc_attr( $submit_class ) . '"';
				}
				if ( ! empty( $submit_id ) ) {
					$submit_shortcode .= ' id="' . esc_attr( $submit_id ) . '"';
				}
				$submit_shortcode .= ']';
				echo do_shortcode( $submit_shortcode );
				?>
			</div>
		<?php endif; ?>

		<?php
		// Check reCAPTCHA v3 settings
		$recaptcha_enabled  = ! empty( $form['settings']['recaptcha_enabled'] );
		$recaptcha_site_key = get_option( 'fplant_recaptcha_site_key' );

		if ( $recaptcha_enabled && ! empty( $recaptcha_site_key ) ) :
			?>
			<!-- reCAPTCHA v3 (hidden) -->
			<input type="hidden" name="fplant_recaptcha_token" class="fplant-recaptcha-token" value="">
			<?php
		endif;
		?>

		<?php wp_nonce_field( 'fplant_form_nonce', 'fplant_nonce' ); ?>
		<input type="hidden" name="fplant_form_id" value="<?php echo esc_attr( $form['id'] ); ?>">
	</form>
</div>

<script>
// Pass field settings to JavaScript
if (typeof window.wpfplantFieldsConfig === 'undefined') {
	window.wpfplantFieldsConfig = {};
}
window.wpfplantFieldsConfig[<?php echo absint( $form['id'] ); ?>] = <?php echo wp_json_encode( $form['fields'], JSON_UNESCAPED_UNICODE ); ?>;

// Pass confirmation screen template to JavaScript
if (typeof window.wpfplantConfirmationTemplate === 'undefined') {
	window.wpfplantConfirmationTemplate = {};
}
window.wpfplantConfirmationTemplate[<?php echo absint( $form['id'] ); ?>] = <?php echo wp_json_encode( $form['settings']['confirmation_template'] ?? '', JSON_UNESCAPED_UNICODE ); ?>;

// Pass confirmation screen button text to JavaScript
if (typeof window.wpfplantConfirmationButtons === 'undefined') {
	window.wpfplantConfirmationButtons = {};
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- absint() returns integer for JS object key
window.wpfplantConfirmationButtons[<?php echo absint( $form['id'] ); ?>] = {
	back: <?php echo wp_json_encode( $form['settings']['confirmation_back_text'] ?? __( 'Back', 'form-plant' ), JSON_UNESCAPED_UNICODE ); ?>,
	back_class: <?php echo wp_json_encode( $form['settings']['confirmation_back_class'] ?? '', JSON_UNESCAPED_UNICODE ); ?>,
	back_id: <?php echo wp_json_encode( $form['settings']['confirmation_back_id'] ?? '', JSON_UNESCAPED_UNICODE ); ?>,
	submit: <?php echo wp_json_encode( $form['settings']['confirmation_submit_text'] ?? __( 'Submit Form', 'form-plant' ), JSON_UNESCAPED_UNICODE ); ?>,
	submit_class: <?php echo wp_json_encode( $form['settings']['confirmation_submit_class'] ?? '', JSON_UNESCAPED_UNICODE ); ?>,
	submit_id: <?php echo wp_json_encode( $form['settings']['confirmation_submit_id'] ?? '', JSON_UNESCAPED_UNICODE ); ?>
};

// Pass reCAPTCHA settings to JavaScript
if (typeof window.wpfplantRecaptchaConfig === 'undefined') {
	window.wpfplantRecaptchaConfig = {};
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- absint() returns integer for JS object key
window.wpfplantRecaptchaConfig[<?php echo absint( $form['id'] ); ?>] = {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Boolean literal string for JavaScript
	enabled: <?php echo ! empty( $form['settings']['recaptcha_enabled'] ) ? 'true' : 'false'; ?>,
	version: <?php echo wp_json_encode( $form['settings']['recaptcha_version'] ?? 'v3' ); ?>,
	siteKey: <?php echo wp_json_encode( get_option( 'fplant_recaptcha_site_key', '' ) ); ?>
};
</script>
