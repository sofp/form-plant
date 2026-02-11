<?php
/**
 * Confirmation field template - Checkbox
 *
 * @package Form_Plant
 * @var array        $field Field configuration
 * @var array|string $value Field value (array of selected values)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Ensure value is array.
$values = is_array( $value ) ? $value : ( ! empty( $value ) ? array( $value ) : array() );

// Convert values to labels.
$display_labels = array();
if ( ! empty( $values ) && ! empty( $field['options'] ) ) {
	foreach ( $values as $val ) {
		foreach ( $field['options'] as $option ) {
			if ( isset( $option['value'] ) && (string) $option['value'] === (string) $val ) {
				$display_labels[] = $option['label'];
				break;
			}
		}
	}
}

// Fallback to values if no labels found.
if ( empty( $display_labels ) && ! empty( $values ) ) {
	$display_labels = $values;
}

$delimiter     = isset( $field['delimiter'] ) ? $field['delimiter'] : ', ';
$display_value = ! empty( $display_labels ) ? implode( $delimiter, $display_labels ) : '-';
echo esc_html( $display_value );
