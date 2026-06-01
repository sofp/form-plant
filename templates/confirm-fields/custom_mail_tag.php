<?php
/**
 * Confirmation field template - Custom Mail Tag
 *
 * Renders the value as plain text along with a hidden input so the value
 * is carried through to the final submission.
 *
 * @package Form_Plant
 *
 * @var array  $field Field configuration.
 * @var string $value Field value (already resolved).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_value     = isset( $value ) ? (string) $value : '';
$display_in_form = ! empty( $field['display_in_form'] );

if ( $display_in_form ) :
	?>
	<span class="fplant-confirm-custom-mail-tag"><?php echo esc_html( $field_value ); ?></span>
<?php endif; ?>
<input type="hidden" name="<?php echo esc_attr( $field['name'] ); ?>" value="<?php echo esc_attr( $field_value ); ?>">
