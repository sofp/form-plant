<?php
/**
 * File upload field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . esc_attr( $field['name'] );
$field_class = 'fplant-field fplant-field-file';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}

// File type setting (default allows any file type)
$accept = ! empty( $field['accept'] ) ? $field['accept'] : '';

// Maximum file size (convert MB to bytes if set in MB, default is 2MB)
if ( ! empty( $field['max_size'] ) ) {
	$max_size_mb = (float) $field['max_size'];
	$max_size    = (int) ( $max_size_mb * 1048576 ); // MB to bytes
} else {
	$max_size_mb = 2.0;
	$max_size    = 2097152; // 2MB in bytes
}
?>

<input
	type="file"
	id="<?php echo esc_attr( $field_id ); ?>"
	name="<?php echo esc_attr( $field['name'] ); ?>"
	class="<?php echo esc_attr( $field_class ); ?>"
	<?php if ( ! empty( $accept ) ) : ?>
	accept="<?php echo esc_attr( $accept ); ?>"
	<?php endif; ?>
	data-max-size="<?php echo esc_attr( $max_size ); ?>"
>
<p class="fplant-file-info">
	<?php
	printf(
		/* translators: %s: maximum file size in MB */
		esc_html__( 'Maximum file size: %sMB', 'form-plant' ),
		esc_html( number_format( $max_size_mb, 1 ) )
	);
	?>
</p>
