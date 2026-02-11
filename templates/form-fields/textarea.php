<?php
/**
 * Textarea field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . esc_attr( $field['name'] );
$field_class = 'fplant-field fplant-field-textarea';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}
$rows = ! empty( $field['rows'] ) ? absint( $field['rows'] ) : 5;
?>

<textarea
	id="<?php echo esc_attr( $field_id ); ?>"
	name="<?php echo esc_attr( $field['name'] ); ?>"
	class="<?php echo esc_attr( $field_class ); ?>"
	rows="<?php echo esc_attr( $rows ); ?>"
	<?php if ( ! empty( $field['placeholder'] ) ) : ?>
		placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
	<?php endif; ?>
><?php
if ( ! empty( $field['default'] ) && empty( $value ) ) {
	echo esc_textarea( $field['default'] );
} else {
	echo esc_textarea( $value );
}
?></textarea>
