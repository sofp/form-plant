<?php
/**
 * Prefecture field template
 *
 * @package Form_Plant
 * @var array  $field Field configuration
 * @var string $value Field value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$fplant_field_name  = esc_attr( $field['name'] );
$fplant_field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . $fplant_field_name;
$fplant_field_class = 'fplant-field fplant-field-prefecture';
if ( ! empty( $field['custom_class'] ) ) {
	$fplant_field_class .= ' ' . esc_attr( $field['custom_class'] );
}

$fplant_display_type = isset( $field['pref_display_type'] ) ? $field['pref_display_type'] : 'select';
$fplant_layout       = isset( $field['layout'] ) ? $field['layout'] : 'vertical';
$fplant_options      = ! empty( $field['options'] ) ? $field['options'] : array();

if ( 'select' === $fplant_display_type ) : ?>
	<select
		id="<?php echo esc_attr( $fplant_field_id ); ?>"
		name="<?php echo esc_attr( $fplant_field_name ); ?>"
		class="<?php echo esc_attr( $fplant_field_class ); ?>"
		autocomplete="address-level1"
	>
		<option value=""><?php echo esc_html( ! empty( $field['placeholder'] ) ? $field['placeholder'] : __( 'Please select', 'form-plant' ) ); ?></option>
		<?php foreach ( $fplant_options as $fplant_opt ) :
			$fplant_opt_value = isset( $fplant_opt['value'] ) ? $fplant_opt['value'] : '';
			$fplant_opt_label = isset( $fplant_opt['label'] ) ? $fplant_opt['label'] : $fplant_opt_value;
			?>
			<option value="<?php echo esc_attr( $fplant_opt_value ); ?>" <?php selected( $value, $fplant_opt_value ); ?>>
				<?php echo esc_html( $fplant_opt_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>

<?php elseif ( 'radio' === $fplant_display_type ) : ?>
	<div id="<?php echo esc_attr( $fplant_field_id ); ?>" class="<?php echo esc_attr( $fplant_field_class ); ?> fplant-layout-<?php echo esc_attr( $fplant_layout ); ?>">
		<?php foreach ( $fplant_options as $fplant_opt ) :
			$fplant_opt_value = isset( $fplant_opt['value'] ) ? $fplant_opt['value'] : '';
			$fplant_opt_label = isset( $fplant_opt['label'] ) ? $fplant_opt['label'] : $fplant_opt_value;
			?>
			<label class="fplant-radio-label">
				<input
					type="radio"
					name="<?php echo esc_attr( $fplant_field_name ); ?>"
					value="<?php echo esc_attr( $fplant_opt_value ); ?>"
					<?php checked( $value, $fplant_opt_value ); ?>
				>
				<?php echo esc_html( $fplant_opt_label ); ?>
			</label>
		<?php endforeach; ?>
	</div>

<?php elseif ( 'checkbox' === $fplant_display_type ) :
	$fplant_checked_values = is_array( $value ) ? $value : ( ! empty( $value ) ? array( $value ) : array() );
	?>
	<div id="<?php echo esc_attr( $fplant_field_id ); ?>" class="<?php echo esc_attr( $fplant_field_class ); ?> fplant-layout-<?php echo esc_attr( $fplant_layout ); ?>">
		<?php foreach ( $fplant_options as $fplant_opt ) :
			$fplant_opt_value = isset( $fplant_opt['value'] ) ? $fplant_opt['value'] : '';
			$fplant_opt_label = isset( $fplant_opt['label'] ) ? $fplant_opt['label'] : $fplant_opt_value;
			?>
			<label class="fplant-checkbox-label">
				<input
					type="checkbox"
					name="<?php echo esc_attr( $fplant_field_name ); ?>[]"
					value="<?php echo esc_attr( $fplant_opt_value ); ?>"
					<?php checked( in_array( $fplant_opt_value, $fplant_checked_values, true ) ); ?>
				>
				<?php echo esc_html( $fplant_opt_label ); ?>
			</label>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
