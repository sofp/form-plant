<?php
/**
 * Confirmation field template - Name Parts
 *
 * @package Form_Plant
 * @var array  $field Field configuration
 * @var string $value Field value (space-separated combined name)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$display_value = ! empty( $value ) ? $value : '-';
echo esc_html( $display_value );
