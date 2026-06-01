<?php
/**
 * MW WP Form Parser
 *
 * Extracts shortcodes from a MW WP Form post_content and converts them to a field array.
 *
 * @package Form_Plant
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPLANT_MWWPForm_Parser
 *
 * @since 1.2.0
 */
class FPLANT_MWWPForm_Parser {

	/**
	 * Shortcodes to convert as Form Plant fields.
	 *
	 * @var array<int, string>
	 */
	private static $field_shortcodes = array(
		'mwform_text',
		'mwform_textarea',
		'mwform_email',
		'mwform_password',
		'mwform_tel',
		'mwform_url',
		'mwform_zip',
		'mwform_number',
		'mwform_range',
		'mwform_select',
		'mwform_radio',
		'mwform_checkbox',
		'mwform_hidden',
		'mwform_file',
		'mwform_image',
		'mwform_datepicker',
		'mwform_monthpicker',
		'mwform_custom_mail_tag',
	);

	/**
	 * Shortcodes to skip because Form Plant generates them automatically.
	 *
	 * @var array<int, string>
	 */
	private static $skip_shortcodes = array(
		// input-tag variants
		'mwform_submit',
		'mwform_submitButton',
		'mwform_confirmButton',
		'mwform_backButton',
		'mwform_button',
		// button-tag variants
		'mwform_bsubmit',
		'mwform_bconfirm',
		'mwform_bback',
		'mwform_bbutton',
		// other
		'mwform_error',
		'mwform_akismet_error',
	);

	/**
	 * Map of button shortcodes to role names (R2).
	 *
	 * @var array<string, string>
	 */
	private static $button_shortcode_role = array(
		// input-tag variants
		'mwform_submit'        => 'submit',
		'mwform_submitButton'  => 'submit',
		'mwform_confirmButton' => 'confirm',
		'mwform_backButton'    => 'back',
		'mwform_button'        => 'generic',
		// button-tag variants
		'mwform_bsubmit'       => 'submit',
		'mwform_bconfirm'      => 'confirm',
		'mwform_bback'         => 'back',
		'mwform_bbutton'       => 'generic',
	);

	/**
	 * button-tag shortcodes (R2: used to determine tag_type).
	 *
	 * @var array<int, string>
	 */
	private static $button_tag_shortcodes = array(
		'mwform_bsubmit',
		'mwform_bconfirm',
		'mwform_bback',
		'mwform_bbutton',
	);

	/**
	 * Map of shortcode names to token roles (R3: used by Template_Builder).
	 *
	 * @var array<string, string>
	 */
	private static $error_shortcodes = array(
		'mwform_error'         => 'error',
		'mwform_akismet_error' => 'akismet_error',
	);

	/**
	 * Parse a post_content string and return a list of shortcodes.
	 *
	 * @param string $post_content The post_content of a MW WP Form form.
	 * @return array{
	 *     fields: array<int, array{shortcode:string, attrs:array<string,string>}>,
	 *     skipped: array<int, array{shortcode:string, attrs:array<string,string>}>,
	 *     unknown: array<int, array{shortcode:string, attrs:array<string,string>}>,
	 *     buttons: array<string, array<int, array{shortcode:string, attrs:array<string,string>, tag_type:string}>>,
	 *     tokens: array<int, array{shortcode:string, attrs:array<string,string>, raw:string, offset:int, length:int, role:string}>,
	 *     template_source: string
	 * }
	 */
	public function parse( $post_content ) {
		$result = array(
			'fields'          => array(),
			'skipped'         => array(),
			'unknown'         => array(),
			'buttons'         => array(
				'submit'  => array(),
				'confirm' => array(),
				'back'    => array(),
				'generic' => array(),
			),
			'tokens'          => array(),
			'template_source' => is_string( $post_content ) ? $post_content : '',
		);

		if ( ! is_string( $post_content ) || '' === $post_content ) {
			return $result;
		}

		// Match the opening tag ([tag attrs]) and optionally an inner content + closing tag ([/tag]).
		// This handles the enclosing form [mwform_bbutton ...]Back[/mwform_bbutton] as well.
		$pattern = '/\[(mwform_[a-zA-Z][a-zA-Z0-9_]*)\b((?:[^\]\[]|\[(?!\/?mwform_)[^\]\[]*\])*?)\](?:([^\[]*)\[\/\1\])?/u';
		if ( ! preg_match_all( $pattern, $post_content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $result;
		}

		foreach ( $matches as $match ) {
			$shortcode = $match[1][0];
			$attr_str  = isset( $match[2][0] ) ? trim( $match[2][0] ) : '';
			$attrs     = $this->parse_attributes( $attr_str );
			$raw       = $match[0][0];
			$offset    = (int) $match[0][1];
			$length    = strlen( $raw );

			// For enclosing shortcodes, store the inner content as element_content in attrs (used by button-tag variants).
			if ( isset( $match[3] ) && '' !== $match[3][0] && ! isset( $attrs['element_content'] ) ) {
				$attrs['element_content'] = $match[3][0];
			}

			$entry = array(
				'shortcode' => $shortcode,
				'attrs'     => $attrs,
			);

			$role = 'unknown';

			if ( in_array( $shortcode, self::$field_shortcodes, true ) ) {
				$result['fields'][] = $entry;
				$role = 'field';
			} elseif ( in_array( $shortcode, self::$skip_shortcodes, true ) ) {
				$result['skipped'][] = $entry;
				if ( isset( self::$button_shortcode_role[ $shortcode ] ) ) {
					$button_role = self::$button_shortcode_role[ $shortcode ];
					$tag_type    = in_array( $shortcode, self::$button_tag_shortcodes, true ) ? 'button' : 'input';
					$button_entry = array(
						'shortcode' => $shortcode,
						'attrs'     => $attrs,
						'tag_type'  => $tag_type,
					);
					$result['buttons'][ $button_role ][] = $button_entry;
					$role = 'button_' . $button_role;
				} elseif ( isset( self::$error_shortcodes[ $shortcode ] ) ) {
					$role = self::$error_shortcodes[ $shortcode ];
				} else {
					$role = 'skip_other';
				}
			} else {
				$result['unknown'][] = $entry;
				$role = 'unknown';
			}

			$result['tokens'][] = array(
				'shortcode' => $shortcode,
				'attrs'     => $attrs,
				'raw'       => $raw,
				'offset'    => $offset,
				'length'    => $length,
				'role'      => $role,
			);
		}

		return $result;
	}

	/**
	 * Parse a shortcode attribute string.
	 *
	 * Delegates to WordPress's shortcode_parse_atts() when available,
	 * with a fallback for environments where that function is not loaded.
	 *
	 * @param string $attr_str The attribute portion of the shortcode string.
	 * @return array<string, string>
	 */
	private function parse_attributes( $attr_str ) {
		if ( '' === $attr_str ) {
			return array();
		}

		if ( function_exists( 'shortcode_parse_atts' ) ) {
			$parsed = shortcode_parse_atts( $attr_str );
			if ( is_array( $parsed ) ) {
				return $parsed;
			}
		}

		return $this->fallback_parse_attributes( $attr_str );
	}

	/**
	 * Minimal attribute parser for environments where shortcode_parse_atts() is unavailable.
	 *
	 * @param string $attr_str The attribute portion of the shortcode string.
	 * @return array<string, string>
	 */
	private function fallback_parse_attributes( $attr_str ) {
		$atts = array();
		$pattern = '/([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\'"]+))/';
		if ( preg_match_all( $pattern, $attr_str, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$key = $m[1];
				if ( '' !== $m[2] ) {
					$value = $m[2];
				} elseif ( '' !== $m[3] ) {
					$value = $m[3];
				} else {
					$value = $m[4];
				}
				$atts[ $key ] = $value;
			}
		}
		return $atts;
	}

	/**
	 * Return the list of shortcodes that are converted to Form Plant fields.
	 *
	 * @return array<int, string>
	 */
	public static function get_field_shortcodes() {
		return self::$field_shortcodes;
	}

	/**
	 * Return the list of shortcodes that are skipped during migration.
	 *
	 * @return array<int, string>
	 */
	public static function get_skip_shortcodes() {
		return self::$skip_shortcodes;
	}
}
