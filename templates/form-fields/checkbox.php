<?php
/**
 * Checkbox field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_name   = esc_attr( $field['name'] );
$field_id_base = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . $field_name;
$layout       = isset( $field['layout'] ) ? $field['layout'] : 'vertical';
$field_class  = 'fplant-field fplant-field-checkbox fplant-layout-' . esc_attr( $layout );
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}
$options = ! empty( $field['options'] ) ? $field['options'] : array();
$values  = is_array( $value ) ? $value : array();
?>

<div class="<?php echo esc_attr( $field_class ); ?>">
	<?php if ( ! empty( $options ) && is_array( $options ) ) : ?>
		<?php foreach ( $options as $index => $option ) : ?>
			<?php
			$option_id    = $field_id_base . '-' . $index;
			$option_value = isset( $option['value'] ) ? $option['value'] : '';
			$option_label = isset( $option['label'] ) ? $option['label'] : $option_value;
			?>
			<label class="fplant-checkbox-label">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $option_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>[]"
					value="<?php echo esc_attr( $option_value ); ?>"
					<?php checked( in_array( $option_value, $values, true ) ); ?>
				>
				<span><?php echo esc_html( $option_label ); ?></span>
			</label>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
