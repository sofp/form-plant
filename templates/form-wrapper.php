<?php
/**
 * Form wrapper template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Store in global variable (so shortcodes can reference it)
global $fplant_current_form;
$fplant_current_form = $form;
?>

<?php
$fplant_form_class = 'fplant-form';
if ( ! empty( $form['settings']['form_tag_class'] ) ) {
	$fplant_form_class .= ' ' . $form['settings']['form_tag_class'];
}
$fplant_form_tag_id = $form['settings']['form_tag_id'] ?? '';
$fplant_wrapper_class = 'fplant-form-wrapper';
?>
<div class="<?php echo esc_attr( $fplant_wrapper_class ); ?>" id="fplant-form-<?php echo esc_attr( $form['id'] ); ?>">
	<form
		class="<?php echo esc_attr( $fplant_form_class ); ?>"
		<?php if ( ! empty( $fplant_form_tag_id ) ) : ?>
			id="<?php echo esc_attr( $fplant_form_tag_id ); ?>"
		<?php endif; ?>
		data-form-id="<?php echo esc_attr( $form['id'] ); ?>"
		data-use-confirmation="<?php echo esc_attr( ! empty( $form['settings']['use_confirmation'] ) ? '1' : '0' ); ?>"
		data-confirmation-title="<?php echo esc_attr( $form['settings']['confirmation_title'] ?? __( 'Confirm Your Input', 'form-plant' ) ); ?>"
		data-confirmation-message="<?php echo esc_attr( $form['settings']['confirmation_message'] ?? __( 'If the information below is correct, please click the "Submit" button.', 'form-plant' ) ); ?>"
		method="post">

		<?php if ( ! empty( $form['html_template'] ) && ! empty( $form['settings']['use_html_template'] ) ) : ?>
			<?php
			// Replace {{key}} template values, then process shortcodes
			$fplant_template_html = fplant_replace_template_values( $form['html_template'], $form['id'] );
			echo wp_kses( do_shortcode( $fplant_template_html ), fplant_get_allowed_form_html() );
			?>
		<?php else : ?>
			<!-- Default layout -->
			<div class="fplant-messages">
				<div class="fplant-errors" data-show-field-errors="false" style="display:none;"></div>
				<div class="fplant-success" style="display:none;"></div>
			</div>

			<?php
			$fplant_field_manager = new FPLANT_Field_Manager();
			foreach ( $form['fields'] as $fplant_field ) :
				if ( 'hidden' === $fplant_field['type'] ) {
					continue;
				}
				if ( 'html' === $fplant_field['type'] ) :
					// HTML field: output directly without label or error display.
					echo wp_kses( do_shortcode( '[fplant_field name="' . esc_attr( $fplant_field['name'] ) . '"]' ), fplant_get_allowed_form_html() );
					continue;
				endif;
				?>
				<div class="fplant-field-group" data-field-name="<?php echo esc_attr( $fplant_field['name'] ); ?>">
					<?php if ( ! empty( $fplant_field['label'] ) ) : ?>
						<label for="fplant-field-<?php echo esc_attr( $fplant_field['name'] ); ?>">
							<?php echo esc_html( $fplant_field['label'] ); ?>
							<?php if ( ! empty( $fplant_field['required'] ) ) : ?>
								<span class="required"><?php echo esc_html( $form['settings']['required_mark_text'] ?? '*' ); ?></span>
							<?php endif; ?>
						</label>
					<?php endif; ?>

					<?php echo wp_kses_post( $fplant_field_manager->render_field_description( $fplant_field, 'after_label' ) ); ?>
					<?php echo wp_kses_post( $fplant_field_manager->render_field_description( $fplant_field, 'before_input' ) ); ?>
					<?php echo wp_kses( do_shortcode( '[fplant_field name="' . esc_attr( $fplant_field['name'] ) . '"]' ), fplant_get_allowed_form_html() ); ?>
					<div class="fplant-field-error" style="display: none;"></div>
					<?php echo wp_kses_post( $fplant_field_manager->render_field_description( $fplant_field, 'after_input' ) ); ?>
				</div>
			<?php endforeach; ?>

			<div class="fplant-submit-wrapper">
				<?php
				$fplant_submit_text  = $form['settings']['input_submit_text'] ?? __( 'Submit', 'form-plant' );
				$fplant_submit_class = $form['settings']['input_submit_class'] ?? '';
				$fplant_submit_id    = $form['settings']['input_submit_id'] ?? '';

				$fplant_submit_shortcode = '[fplant_submit text="' . esc_attr( $fplant_submit_text ) . '"';
				if ( ! empty( $fplant_submit_class ) ) {
					$fplant_submit_shortcode .= ' class="' . esc_attr( $fplant_submit_class ) . '"';
				}
				if ( ! empty( $fplant_submit_id ) ) {
					$fplant_submit_shortcode .= ' id="' . esc_attr( $fplant_submit_id ) . '"';
				}
				$fplant_submit_shortcode .= ']';
				echo wp_kses( do_shortcode( $fplant_submit_shortcode ), fplant_get_allowed_form_html() );
				?>
			</div>
		<?php endif; ?>

		<?php
		// Check CAPTCHA settings
		$fplant_captcha_type = $form['settings']['captcha_type'] ?? 'none';
		// Backward compatibility
		if ( 'none' === $fplant_captcha_type && ! empty( $form['settings']['recaptcha_enabled'] ) ) {
			$fplant_captcha_type = 'recaptcha';
		}

		if ( 'none' !== $fplant_captcha_type ) :
			?>
			<!-- CAPTCHA token (hidden) -->
			<input type="hidden" name="fplant_captcha_token" class="fplant-captcha-token" value="">
			<?php
		endif;
		?>

		<?php wp_nonce_field( 'fplant_form_nonce', 'fplant_nonce' ); ?>
		<input type="hidden" name="fplant_form_id" value="<?php echo esc_attr( $form['id'] ); ?>">

		<?php if ( ( $form['settings']['spam_honeypot_enabled'] ?? true ) !== false ) : ?>
			<?php $fplant_hp_name = $form['settings']['spam_honeypot_field_name'] ?? 'fplant_website_url'; ?>
			<div class="fplant-field-wrap fplant-field-url" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden;">
				<label for="fplant_field_url_<?php echo esc_attr( $form['id'] ); ?>">Website URL</label>
				<input type="text" name="<?php echo esc_attr( $fplant_hp_name ); ?>" id="fplant_field_url_<?php echo esc_attr( $form['id'] ); ?>" value="" tabindex="-1" autocomplete="off">
			</div>
		<?php endif; ?>
		<input type="hidden" name="fplant_form_ts" class="fplant-form-ts" value="">
	</form>
</div>
