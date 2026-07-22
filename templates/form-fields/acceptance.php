<?php
/**
 * Acceptance field template
 *
 * Single consent checkbox with the consent text (acceptance_text, limited
 * inline HTML) rendered next to it. The field label is a plain item name; the
 * field-group wrappers show it only when acceptance_show_label is enabled
 * (FPLANT_Field_Manager::shows_group_label()).
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_name  = esc_attr( $field['name'] );
$field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . $field_name;
$field_class = 'fplant-field fplant-field-acceptance';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}

// Consent text (falls back to the escaped label when empty), kses-sanitized.
$text_html = FPLANT_Field_Manager::acceptance_text_html( $field );

$is_checked = ! empty( $value ) && '0' !== $value;

// No required mark next to the checkbox: acceptance is always required, so
// the mark carries no information here (the optional group label shows the
// standard mark). Front-end JS treats .fplant-field-acceptance as
// structurally required (form.js validateField), independent of any mark.
?>

<div class="<?php echo esc_attr( $field_class ); ?>">
	<label class="fplant-acceptance-label" for="<?php echo esc_attr( $field_id ); ?>">
		<input
			type="checkbox"
			id="<?php echo esc_attr( $field_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			value="1"
			<?php checked( $is_checked ); ?>
		>
		<span class="fplant-acceptance-text"><?php echo $text_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via wp_kses in acceptance_text_html(). ?></span>
	</label>
</div>
