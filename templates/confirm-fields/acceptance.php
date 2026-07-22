<?php
/**
 * Confirmation field template - Acceptance
 *
 * @package Form_Plant
 * @var array        $field Field configuration
 * @var array|string $value Field value ('1' when agreed)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Unchecked acceptance never passes validation, so the value is always truthy
// here; keep the fallback for defensive rendering.
$display_value = ( ! empty( $value ) && '0' !== $value )
	? FPLANT_Field_Manager::acceptance_display_value()
	: '-';
echo esc_html( $display_value );
