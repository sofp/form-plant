<?php
/**
 * Confirmation field template - Address (International layout)
 *
 * For Japanese locale, see address-ja.php.
 * Displays address sub-field values stored as {field_name}_{sub_key}.
 *
 * @package Form_Plant
 * @var array  $field  Field configuration
 * @var string $value  Field value (unused for composite display)
 * @var array  $values All submitted values (available in all_fields context)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$fplant_fn       = $field['name'];
$fplant_all_vals = isset( $values ) ? $values : array();

$fplant_street  = isset( $fplant_all_vals[ $fplant_fn . '_street' ] ) ? $fplant_all_vals[ $fplant_fn . '_street' ] : '';
$fplant_addr2   = isset( $fplant_all_vals[ $fplant_fn . '_address2' ] ) ? $fplant_all_vals[ $fplant_fn . '_address2' ] : '';
$fplant_city    = isset( $fplant_all_vals[ $fplant_fn . '_city' ] ) ? $fplant_all_vals[ $fplant_fn . '_city' ] : '';
$fplant_state   = isset( $fplant_all_vals[ $fplant_fn . '_state' ] ) ? $fplant_all_vals[ $fplant_fn . '_state' ] : '';
$fplant_postal  = isset( $fplant_all_vals[ $fplant_fn . '_postal_code' ] ) ? $fplant_all_vals[ $fplant_fn . '_postal_code' ] : '';
$fplant_country = isset( $fplant_all_vals[ $fplant_fn . '_country' ] ) ? $fplant_all_vals[ $fplant_fn . '_country' ] : '';

$fplant_lines = array();
if ( ! empty( $fplant_street ) ) {
	$fplant_lines[] = $fplant_street;
}
if ( ! empty( $fplant_addr2 ) ) {
	$fplant_lines[] = $fplant_addr2;
}
$fplant_city_line = '';
if ( ! empty( $fplant_city ) ) {
	$fplant_city_line .= $fplant_city;
}
if ( ! empty( $fplant_state ) ) {
	$fplant_city_line .= ( ! empty( $fplant_city_line ) ? ', ' : '' ) . $fplant_state;
}
if ( ! empty( $fplant_postal ) ) {
	$fplant_city_line .= ( ! empty( $fplant_city_line ) ? ' ' : '' ) . $fplant_postal;
}
if ( ! empty( $fplant_city_line ) ) {
	$fplant_lines[] = $fplant_city_line;
}
if ( ! empty( $fplant_country ) ) {
	$fplant_lines[] = $fplant_country;
}

if ( ! empty( $fplant_lines ) ) {
	echo nl2br( esc_html( implode( "\n", $fplant_lines ) ) );
} else {
	echo esc_html( '-' );
}
