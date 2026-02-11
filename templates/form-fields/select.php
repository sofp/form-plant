<?php
/**
 * Select box field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . esc_attr( $field['name'] );
$field_class = 'fplant-field fplant-field-select';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}
$options = ! empty( $field['options'] ) ? $field['options'] : array();
?>

<select
	id="<?php echo esc_attr( $field_id ); ?>"
	name="<?php echo esc_attr( $field['name'] ); ?>"
	class="<?php echo esc_attr( $field_class ); ?>"
>
	<option value=""><?php echo esc_html( ! empty( $field['placeholder'] ) ? $field['placeholder'] : __( 'Please select', 'form-plant' ) ); ?></option>
	<?php if ( ! empty( $options ) ) : ?>
		<?php foreach ( $options as $option ) : ?>
			<?php
			$option_value = isset( $option['value'] ) ? $option['value'] : '';
			$option_label = isset( $option['label'] ) ? $option['label'] : $option_value;
			?>
			<option
				value="<?php echo esc_attr( $option_value ); ?>"
				<?php selected( $value, $option_value ); ?>
			>
				<?php echo esc_html( $option_label ); ?>
			</option>
		<?php endforeach; ?>
	<?php endif; ?>
</select>
