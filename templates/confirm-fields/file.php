<?php
/**
 * Confirmation field template - File
 *
 * @package Form_Plant
 * @var array  $field    Field configuration
 * @var string $value    Field value (filename)
 * @var string $filename Optional. Original filename for display.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$display_value = ! empty( $filename ) ? $filename : ( ! empty( $value ) ? $value : '-' );
echo esc_html( $display_value );
