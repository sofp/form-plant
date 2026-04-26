<?php
/**
 * Confirmation field template - Postal Code
 *
 * @package Form_Plant
 * @var array  $field Field configuration
 * @var string $value Field value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$fplant_is_ja = ( 0 === strpos( get_locale(), 'ja' ) );

if ( ! empty( $value ) ) {
	$display_value = $value;
	// Format with hyphen for Japanese postal codes
	if ( $fplant_is_ja ) {
		$clean = preg_replace( '/[^0-9]/', '', $value );
		if ( 7 === strlen( $clean ) ) {
			$display_value = substr( $clean, 0, 3 ) . '-' . substr( $clean, 3 );
		}
	}
	echo esc_html( $display_value );
} else {
	echo esc_html( '-' );
}
