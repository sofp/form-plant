<?php
/**
 * Confirmation field template - Date Select
 *
 * @package Form_Plant
 * @var array  $field Field configuration
 * @var string $value Field value (YYYY-MM-DD format)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$display_value = ! empty( $value ) ? $value : '-';
echo esc_html( $display_value );
