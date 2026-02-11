<?php
/**
 * Confirmation screen template
 *
 * This template displays the confirmation screen before final submission.
 *
 * @package Form_Plant
 * @var array  $form             Form configuration
 * @var array  $fields           Form fields
 * @var array  $values           Submitted values
 * @var string $fields_html      Rendered all fields HTML (from all_fields.php template)
 * @var array  $settings         Form settings
 * @var string $title            Confirmation title
 * @var string $message          Confirmation message
 * @var string $back_text        Back button text
 * @var string $back_class       Back button CSS class
 * @var string $back_id          Back button ID
 * @var string $submit_text      Submit button text
 * @var string $submit_class     Submit button CSS class
 * @var string $submit_id        Submit button ID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="fplant-confirmation-header">
	<h3><?php echo esc_html( $title ); ?></h3>
	<p><?php echo esc_html( $message ); ?></p>
</div>
<div class="fplant-confirmation-body">
	<?php echo $fields_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped in all_fields.php template ?>
</div>
<div class="fplant-confirmation-footer">
	<button type="button" class="fplant-back-button <?php echo esc_attr( $back_class ); ?>"<?php echo ! empty( $back_id ) ? ' id="' . esc_attr( $back_id ) . '"' : ''; ?>>
		<?php echo esc_html( $back_text ); ?>
	</button>
	<button type="button" class="fplant-confirm-submit-button <?php echo esc_attr( $submit_class ); ?>"<?php echo ! empty( $submit_id ) ? ' id="' . esc_attr( $submit_id ) . '"' : ''; ?>>
		<?php echo esc_html( $submit_text ); ?>
	</button>
</div>
