<?php
/**
 * Date (calendar) field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_name  = esc_attr( $field['name'] );
$field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . $field_name;
$field_class = 'fplant-field fplant-field-date';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}
$placeholder = ! empty( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : '';

// Set year range (default: 100 years in the past to 10 years in the future)
$current_year = (int) gmdate( 'Y' );
$year_start_offset = isset( $field['year_start'] ) ? (int) $field['year_start'] : 100;
$year_end_offset   = isset( $field['year_end'] ) ? (int) $field['year_end'] : 10;
$min_date = ( $current_year - $year_start_offset ) . '-01-01';
$max_date = ( $current_year + $year_end_offset ) . '-12-31';
?>

<input
	type="date"
	id="<?php echo esc_attr( $field_id ); ?>"
	name="<?php echo esc_attr( $field_name ); ?>"
	class="<?php echo esc_attr( $field_class ); ?>"
	value="<?php echo esc_attr( $value ); ?>"
	min="<?php echo esc_attr( $min_date ); ?>"
	max="<?php echo esc_attr( $max_date ); ?>"
	<?php if ( $placeholder ) : ?>
		placeholder="<?php echo esc_attr( $placeholder ); ?>"
	<?php endif; ?>
>
