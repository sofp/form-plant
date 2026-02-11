<?php
/**
 * Text field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . esc_attr( $field['name'] );
$field_class = 'fplant-field fplant-field-text';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}
?>

<input
	type="text"
	id="<?php echo esc_attr( $field_id ); ?>"
	name="<?php echo esc_attr( $field['name'] ); ?>"
	class="<?php echo esc_attr( $field_class ); ?>"
	<?php if ( ! empty( $field['placeholder'] ) ) : ?>
		placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
	<?php endif; ?>
	<?php if ( ! empty( $field['size'] ) ) : ?>
		size="<?php echo esc_attr( $field['size'] ); ?>"
	<?php endif; ?>
	<?php if ( ! empty( $field['maxlength'] ) ) : ?>
		maxlength="<?php echo esc_attr( $field['maxlength'] ); ?>"
	<?php endif; ?>
	<?php if ( ! empty( $field['default'] ) && empty( $value ) ) : ?>
		value="<?php echo esc_attr( $field['default'] ); ?>"
	<?php else : ?>
		value="<?php echo esc_attr( $value ); ?>"
	<?php endif; ?>
>
