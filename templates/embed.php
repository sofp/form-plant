<?php
/**
 * Embed template
 *
 * Lightweight HTML template used for iframe embedding.
 * CSS/JS are enqueued via class-embed.php enqueue_embed_assets().
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

// Form settings
$use_confirmation = ! empty( $settings['use_confirmation'] );
$nonce = wp_create_nonce( 'fplant_form_nonce' );

?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $form['title'] ?? __( 'Form', 'form-plant' ) ); ?></title>
	<?php wp_head(); ?>
</head>
<body>
	<?php
	$fplant_embed_wrapper_class = 'fplant-form-wrapper';
	?>
	<div class="<?php echo esc_attr( $fplant_embed_wrapper_class ); ?>" id="fplant-form-<?php echo esc_attr( $form_id ); ?>" data-form-id="<?php echo esc_attr( $form_id ); ?>">
		<?php
		$fplant_embed_form_class = 'fplant-form';
		if ( ! empty( $settings['form_tag_class'] ) ) {
			$fplant_embed_form_class .= ' ' . $settings['form_tag_class'];
		}
		$fplant_embed_form_tag_id = $settings['form_tag_id'] ?? '';
		?>
		<form
			class="<?php echo esc_attr( $fplant_embed_form_class ); ?>"
			<?php if ( ! empty( $fplant_embed_form_tag_id ) ) : ?>
				id="<?php echo esc_attr( $fplant_embed_form_tag_id ); ?>"
			<?php endif; ?>
			method="post"
			enctype="multipart/form-data"
			data-form-id="<?php echo esc_attr( $form_id ); ?>"
			data-use-confirmation="<?php echo esc_attr( $use_confirmation ? '1' : '0' ); ?>"
		>
			<!-- Messages must be inside form for form.js to find them -->
			<div class="fplant-messages">
				<div class="fplant-errors" data-show-field-errors="false" style="display: none;"></div>
				<div class="fplant-success" style="display: none;"></div>
			</div>

			<input type="hidden" name="fplant_form_id" value="<?php echo esc_attr( $form_id ); ?>">
			<input type="hidden" name="fplant_embed_nonce" value="<?php echo esc_attr( $nonce ); ?>">
			<input type="hidden" name="fplant_embed_mode" value="1">

			<?php if ( ( $settings['spam_honeypot_enabled'] ?? true ) !== false ) : ?>
				<?php $fplant_hp_name = $settings['spam_honeypot_field_name'] ?? 'fplant_website_url'; ?>
				<div class="fplant-field-wrap fplant-field-url" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden;">
					<label for="fplant_field_url_<?php echo esc_attr( $form_id ); ?>">Website URL</label>
					<input type="text" name="<?php echo esc_attr( $fplant_hp_name ); ?>" id="fplant_field_url_<?php echo esc_attr( $form_id ); ?>" value="" tabindex="-1" autocomplete="off">
				</div>
			<?php endif; ?>

			<?php
			// CAPTCHA token
			$fplant_embed_captcha_type = $settings['captcha_type'] ?? 'none';
			if ( 'none' === $fplant_embed_captcha_type && ! empty( $settings['recaptcha_enabled'] ) ) {
				$fplant_embed_captcha_type = 'recaptcha';
			}
			if ( 'none' !== $fplant_embed_captcha_type ) :
				?>
				<input type="hidden" name="fplant_captcha_token" class="fplant-captcha-token" value="">
				<?php
			endif;
			?>

			<?php if ( ! empty( $form['html_template'] ) && ! empty( $settings['use_html_template'] ) ) : ?>
				<?php
				// Process shortcodes in the input screen HTML template
				echo wp_kses( do_shortcode( $form['html_template'] ), fplant_get_allowed_form_html() );
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
									<span class="required"><?php echo esc_html( $settings['required_mark_text'] ?? '*' ); ?></span>
								<?php endif; ?>
							</label>
						<?php endif; ?>

						<?php echo wp_kses_post( $field_manager->render_field_description( $field, 'after_label' ) ); ?>
						<?php echo wp_kses_post( $field_manager->render_field_description( $field, 'before_input' ) ); ?>
						<?php
						// Render using template via render_field() method
						echo wp_kses( $field_manager->render_field( $field, $field_value, $form_id, $settings ), fplant_get_allowed_form_html() );
						?>
						<div class="fplant-field-error" style="display: none;"></div>
						<?php echo wp_kses_post( $field_manager->render_field_description( $field, 'after_input' ) ); ?>
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
			<input type="hidden" name="fplant_form_ts" class="fplant-form-ts" value="">
		</form>

		<?php
		// Confirmation screen HTML is dynamically inserted by form.js from server response,
		// so static HTML is no longer needed.
		?>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
