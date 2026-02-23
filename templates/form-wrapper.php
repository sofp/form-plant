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
			echo wp_kses( do_shortcode( $form['html_template'] ), fplant_get_allowed_form_html() );
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
					// HTMLフィールドはラベル・エラー表示なしで直接出力
					echo wp_kses( do_shortcode( '[fplant_field name="' . esc_attr( $fplant_field['name'] ) . '"]' ), fplant_get_allowed_form_html() );
					continue;
				endif;
				?>
				<div class="fplant-field-group" data-field-name="<?php echo esc_attr( $fplant_field['name'] ); ?>">
					<?php if ( ! empty( $fplant_field['label'] ) ) : ?>
						<label for="fplant-field-<?php echo esc_attr( $fplant_field['name'] ); ?>">
							<?php echo esc_html( $fplant_field['label'] ); ?>
							<?php if ( ! empty( $fplant_field['required'] ) ) : ?>
								<span class="required">*</span>
							<?php endif; ?>
						</label>
					<?php endif; ?>

					<?php echo wp_kses( do_shortcode( '[fplant_field name="' . esc_attr( $fplant_field['name'] ) . '"]' ), fplant_get_allowed_form_html() ); ?>
					<div class="fplant-field-error" style="display: none;"></div>
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
		// Check reCAPTCHA v3 settings
		$fplant_recaptcha_enabled  = ! empty( $form['settings']['recaptcha_enabled'] );
		$fplant_recaptcha_site_key = get_option( 'fplant_recaptcha_site_key' );

		if ( $fplant_recaptcha_enabled && ! empty( $fplant_recaptcha_site_key ) ) :
			?>
			<!-- reCAPTCHA v3 (hidden) -->
			<input type="hidden" name="fplant_recaptcha_token" class="fplant-recaptcha-token" value="">
			<?php
		endif;
		?>

		<?php wp_nonce_field( 'fplant_form_nonce', 'fplant_nonce' ); ?>
		<input type="hidden" name="fplant_form_id" value="<?php echo esc_attr( $form['id'] ); ?>">

		<div class="fplant-field-wrap fplant-field-url" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden;">
			<label for="fplant_field_url_<?php echo esc_attr( $form['id'] ); ?>">Website URL</label>
			<input type="text" name="fplant_website_url" id="fplant_field_url_<?php echo esc_attr( $form['id'] ); ?>" value="" tabindex="-1" autocomplete="off">
		</div>
	</form>
</div>
