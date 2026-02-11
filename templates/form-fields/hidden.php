<?php
/**
 * Hidden field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_value = ! empty( $value ) ? $value : ( ! empty( $field['default'] ) ? $field['default'] : '' );
?>

<input
	type="hidden"
	name="<?php echo esc_attr( $field['name'] ); ?>"
	value="<?php echo esc_attr( $field_value ); ?>"
	<?php if ( ! empty( $field['custom_id'] ) ) : ?>
		id="<?php echo esc_attr( $field['custom_id'] ); ?>"
	<?php endif; ?>
	<?php if ( ! empty( $field['custom_class'] ) ) : ?>
		class="<?php echo esc_attr( $field['custom_class'] ); ?>"
	<?php endif; ?>
>
