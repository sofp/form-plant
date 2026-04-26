<?php
/**
 * Confirmation field template - Address (Japanese locale)
 *
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

$fplant_postal = isset( $fplant_all_vals[ $fplant_fn . '_postal_code' ] ) ? $fplant_all_vals[ $fplant_fn . '_postal_code' ] : '';
$fplant_pref   = isset( $fplant_all_vals[ $fplant_fn . '_prefecture' ] ) ? $fplant_all_vals[ $fplant_fn . '_prefecture' ] : '';
$fplant_city   = isset( $fplant_all_vals[ $fplant_fn . '_city' ] ) ? $fplant_all_vals[ $fplant_fn . '_city' ] : '';
$fplant_street = isset( $fplant_all_vals[ $fplant_fn . '_street' ] ) ? $fplant_all_vals[ $fplant_fn . '_street' ] : '';
$fplant_bldg   = isset( $fplant_all_vals[ $fplant_fn . '_building' ] ) ? $fplant_all_vals[ $fplant_fn . '_building' ] : '';

// Format postal code
if ( ! empty( $fplant_postal ) ) {
	$fplant_clean_postal = preg_replace( '/[^0-9]/', '', $fplant_postal );
	if ( 7 === strlen( $fplant_clean_postal ) ) {
		$fplant_postal = substr( $fplant_clean_postal, 0, 3 ) . '-' . substr( $fplant_clean_postal, 3 );
	}
}

$fplant_lines = array();
if ( ! empty( $fplant_postal ) ) {
	$fplant_lines[] = $fplant_postal;
}
$fplant_addr_line = $fplant_pref . $fplant_city . $fplant_street;
if ( ! empty( $fplant_addr_line ) ) {
	$fplant_lines[] = $fplant_addr_line;
}
if ( ! empty( $fplant_bldg ) ) {
	$fplant_lines[] = $fplant_bldg;
}

if ( ! empty( $fplant_lines ) ) {
	echo nl2br( esc_html( implode( "\n", $fplant_lines ) ) );
} else {
	echo esc_html( '-' );
}
