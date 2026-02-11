<?php
/**
 * Confirmation field template - Radio
 *
 * @package Form_Plant
 * @var array  $field Field configuration
 * @var string $value Field value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Convert value to label.
$display_value = '-';
if ( ! empty( $value ) && ! empty( $field['options'] ) ) {
	foreach ( $field['options'] as $option ) {
		if ( isset( $option['value'] ) && (string) $option['value'] === (string) $value ) {
			$display_value = $option['label'];
			break;
		}
	}
} elseif ( ! empty( $value ) ) {
	$display_value = $value;
}

echo esc_html( $display_value );
