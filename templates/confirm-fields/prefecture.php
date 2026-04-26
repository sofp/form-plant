<?php
/**
 * Confirmation field template - Prefecture
 *
 * @package Form_Plant
 * @var array        $field Field configuration
 * @var string|array $value Field value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( is_array( $value ) ) {
	$display_value = ! empty( $value ) ? implode( ', ', array_map( 'esc_html', $value ) ) : '-';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each item escaped by esc_html above.
	echo $display_value;
} else {
	echo esc_html( ! empty( $value ) ? $value : '-' );
}
