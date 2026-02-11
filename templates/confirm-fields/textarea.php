<?php
/**
 * Confirmation field template - Textarea
 *
 * @package Form_Plant
 * @var array  $field Field configuration
 * @var string $value Field value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$display_value = ! empty( $value ) ? nl2br( esc_html( $value ) ) : '-';
echo $display_value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped with nl2br and esc_html
