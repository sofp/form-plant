<?php
/**
 * Confirmation field template - Password
 *
 * @package Form_Plant
 * @var array  $field Field configuration
 * @var string $value Field value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! empty( $value ) ) {
	echo esc_html( str_repeat( "\xe2\x97\x8f", mb_strlen( $value ) ) );
} else {
	echo esc_html( '-' );
}
