<?php
/**
 * Custom Mail Tag field template
 *
 * Value is fetched via the `fplant_custom_mail_tag_value_{name}` filter
 * (resolved in FPLANT_Field_Manager::get_field_initial_value()). The value
 * is always persisted via a hidden input so that it reaches the submission
 * data and email body. Optionally the value is also shown in the form
 * (display_in_form = true) wrapped in span/div for visibility.
 *
 * @package Form_Plant
 *
 * @var array  $field Field configuration.
 * @var string $value Initial value (already resolved through the filter).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_value     = isset( $value ) ? (string) $value : '';
$display_in_form = ! empty( $field['display_in_form'] );
$wrapper         = isset( $field['display_wrapper'] ) ? $field['display_wrapper'] : 'span';
if ( ! in_array( $wrapper, array( 'span', 'div', 'hidden' ), true ) ) {
	$wrapper = 'span';
}

$id_attr    = ! empty( $field['custom_id'] ) ? ' id="' . esc_attr( $field['custom_id'] ) . '"' : '';
$class_attr = ' class="fplant-custom-mail-tag' . ( ! empty( $field['custom_class'] ) ? ' ' . esc_attr( $field['custom_class'] ) : '' ) . '"';

if ( $display_in_form && 'hidden' !== $wrapper ) :
	?>
	<<?php echo esc_attr( $wrapper ); ?><?php echo $id_attr . $class_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped above. ?> data-name="<?php echo esc_attr( $field['name'] ); ?>">
		<?php echo esc_html( $field_value ); ?>
	</<?php echo esc_attr( $wrapper ); ?>>
<?php endif; ?>
<input type="hidden" name="<?php echo esc_attr( $field['name'] ); ?>" value="<?php echo esc_attr( $field_value ); ?>">
