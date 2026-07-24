<?php
/**
 * Submit button template
 *
 * Override this template by copying it to your theme:
 * wp-content/themes/<your-theme>/form-plant/form-fields/submit.php
 *
 * @package Form_Plant
 *
 * @var string $submit_text  Button label text.
 * @var string $submit_class Button CSS class (in addition to fplant-submit-button).
 * @var string $submit_id    Button ID attribute value.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$fplant_button_class = 'fplant-submit-button';
if ( ! empty( $submit_class ) ) {
	$fplant_button_class .= ' ' . $submit_class;
}
?>
<?php // No whitespace inside the button tag: themes styling buttons with white-space: pre-wrap would render it. ?>
<button type="submit"<?php echo ! empty( $submit_id ) ? ' id="' . esc_attr( $submit_id ) . '"' : ''; ?> class="<?php echo esc_attr( $fplant_button_class ); ?>"><?php echo esc_html( $submit_text ); ?></button>
