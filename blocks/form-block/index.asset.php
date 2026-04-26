<?php
/**
 * Block editor script dependencies.
 *
 * Hand-maintained (no build pipeline). When adding wp.* APIs to index.js,
 * also add the matching wp-* script handle here.
 *
 * @package Form_Plant
 */

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-i18n',
		'wp-api-fetch',
		'wp-data',
	),
	'version'      => '1.1.0',
);
