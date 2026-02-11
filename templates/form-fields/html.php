<?php
/**
 * HTML field template
 *
 * Displays custom HTML content (not an input field).
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_class = 'fplant-field fplant-field-html';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}

$content = isset( $field['content'] ) ? $field['content'] : '';
?>

<div class="<?php echo esc_attr( $field_class ); ?>">
	<?php echo wp_kses_post( $content ); ?>
</div>
