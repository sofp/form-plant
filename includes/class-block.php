<?php
/**
 * Block editor integration
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Block class
 *
 * Registers the "Form Plant" Gutenberg block, a Contact Form 7 style form
 * selector. The block's save() outputs a [fplant id="..."] shortcode string
 * into post_content, so the existing shortcode handler (and the
 * has_shortcode()-driven asset enqueue in FPLANT_Form_Plant::enqueue_scripts)
 * fires exactly as if the user had typed the shortcode by hand.
 */
class FPLANT_Block {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$block_dir = FPLANT_PLUGIN_DIR . 'blocks/form-block';
		if ( ! file_exists( $block_dir . '/block.json' ) ) {
			return;
		}

		register_block_type( $block_dir );

		// The script handle follows the convention <namespace>-<slug>-editor-script.
		$editor_handle = 'form-plant-form-editor-script';

		wp_localize_script(
			$editor_handle,
			'fplantBlockData',
			array(
				'newFormUrl' => admin_url( 'admin.php?page=fplant-form-new' ),
			)
		);

		// Enable JS translations for the editor script.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				$editor_handle,
				'form-plant',
				FPLANT_PLUGIN_DIR . 'languages'
			);
		}
	}
}
