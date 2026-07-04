<?php
/**
 * Design options CSS generator
 *
 * Turns the per-form visual adjustments edited on the form edit screen
 * (settings.design_options: form frame / buttons / error messages) into a CSS
 * string scoped to a single form. Selectors are prefixed with the form wrapper
 * ID (#fplant-form-{id}) so the generated rules (specificity 1,1,0) win over
 * the design preset CSS, theme CSS and preset media queries, while untouched
 * options emit nothing and leave the preset defaults intact.
 *
 * The schema below is the single source of truth: it is also localized to the
 * admin script, where admin.js mirrors build_css() to render the live part
 * previews. Validation happens here at output time (the final defense even
 * against tampered post meta): colors must be hex, lengths are clamped
 * integers, keywords are whitelisted.
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Design_Options class
 */
class FPLANT_Design_Options {

	/**
	 * Box-shadow value for a shadow intensity step.
	 *
	 * Offset, blur and opacity grow with the intensity. Equal x/y offsets so
	 * the shadow extends evenly to the right and bottom (down-right 45°) on
	 * the form frame and buttons alike. The constants are tuned so the
	 * pre-slider presets sit on the scale by strength: 1 = old "sm",
	 * 3 ≈ old "md", 8 = old "lg". Mirrored in admin.js (designShadowCss) —
	 * keep the two in sync (positive half-way values round up in both).
	 *
	 * @param int $intensity Intensity (0 = no shadow, 1-10 = increasing).
	 * @return string
	 */
	public static function shadow_css( $intensity ) {
		if ( $intensity <= 0 ) {
			return 'none';
		}
		$offset = (int) round( 0.75 * $intensity );
		$blur   = (int) round( 2.5 * $intensity );
		// The negative spread cancels the blur's spill past the offset
		// (blur/2 - offset - spread <= 0 at every step), so no shadow bleeds
		// out of the top/left edges and it reads strictly right + bottom.
		$spread = (int) round( 0.5 * $intensity );
		// Alpha built as the integer digits of 0.XX (12-25) — float-to-string
		// would be locale-dependent on PHP 7.x.
		$alpha = (int) round( 10 + 1.5 * $intensity );
		return $offset . 'px ' . $offset . 'px ' . $blur . 'px -' . $spread . 'px rgba(0,0,0,0.' . $alpha . ')';
	}

	/**
	 * Declarative schema: section => rules (selector suffixes appended to the
	 * prefix) and props (option key => rule + value type + CSS property).
	 *
	 * Prop types: color / px / px-pair (one value, two properties) / shadow /
	 * weight / btn-width / border-width + border-color (composed into one
	 * border shorthand per rule) / bg-padded (background plus padding so text
	 * does not touch the newly visible box).
	 *
	 * @return array
	 */
	public static function get_schema() {
		$button_rules = function ( $selector ) {
			return array(
				'rules' => array(
					'base'  => array( ' ' . $selector ),
					'hover' => array( ' ' . $selector . ':hover' ),
				),
				'props' => array(
					'color'            => array(
						'rule' => 'base',
						'type' => 'color',
						'css'  => 'color',
					),
					'background'       => array(
						'rule' => 'base',
						'type' => 'color',
						'css'  => 'background',
					),
					'border_color'     => array(
						'rule' => 'base',
						'type' => 'border-color',
					),
					'border_width'     => array(
						'rule' => 'base',
						'type' => 'border-width',
						'min'  => 0,
						'max'  => 10,
					),
					'hover_color'      => array(
						'rule' => 'hover',
						'type' => 'color',
						'css'  => 'color',
					),
					'hover_background' => array(
						'rule' => 'hover',
						'type' => 'color',
						'css'  => 'background',
					),
					'border_radius'    => array(
						'rule' => 'base',
						'type' => 'px',
						'css'  => 'border-radius',
						'min'  => 0,
						'max'  => 50,
					),
					'box_shadow'       => array(
						'rule' => 'base',
						'type' => 'shadow',
						'css'  => 'box-shadow',
						'min'  => 0,
						'max'  => 10,
					),
					'font_size'        => array(
						'rule' => 'base',
						'type' => 'px',
						'css'  => 'font-size',
						'min'  => 10,
						'max'  => 32,
					),
					'padding_v'        => array(
						'rule' => 'base',
						'type' => 'px-pair',
						'css'  => array( 'padding-top', 'padding-bottom' ),
						'min'  => 0,
						'max'  => 40,
					),
					'padding_h'        => array(
						'rule' => 'base',
						'type' => 'px-pair',
						'css'  => array( 'padding-left', 'padding-right' ),
						'min'  => 0,
						'max'  => 80,
					),
					'width'            => array(
						'rule' => 'base',
						'type' => 'btn-width',
					),
				),
			);
		};

		return array(
			'form'    => array(
				'rules' => array(
					'root'  => array( '' ),
					'box'   => array( ' .fplant-form', ' .fplant-confirmation' ),
					'label' => array( ' .fplant-field-group > label' ),
					'desc'  => array( ' .fplant-field-desc' ),
				),
				'props' => array(
					'max_width'        => array(
						'rule' => 'root',
						'type' => 'px',
						'css'  => 'max-width',
						'min'  => 300,
						'max'  => 1200,
					),
					'background'       => array(
						'rule' => 'box',
						'type' => 'color',
						'css'  => 'background',
					),
					'border_width'     => array(
						'rule' => 'box',
						'type' => 'border-width',
						'min'  => 0,
						'max'  => 10,
					),
					'border_color'     => array(
						'rule' => 'box',
						'type' => 'border-color',
					),
					'border_radius'    => array(
						'rule' => 'box',
						'type' => 'px',
						'css'  => 'border-radius',
						'min'  => 0,
						'max'  => 50,
					),
					'box_shadow'       => array(
						'rule' => 'box',
						'type' => 'shadow',
						'css'  => 'box-shadow',
						'min'  => 0,
						'max'  => 10,
					),
					'label_color'      => array(
						'rule' => 'label',
						'type' => 'color',
						'css'  => 'color',
					),
					'label_bold'       => array(
						'rule' => 'label',
						'type' => 'weight',
						'css'  => 'font-weight',
					),
					'label_background' => array(
						'rule'   => 'label',
						'type'   => 'bg-padded',
						'css'    => 'background',
						'pad'    => '4px 8px',
						'radius' => '3px',
					),
					'label_font_size'  => array(
						'rule' => 'label',
						'type' => 'px',
						'css'  => 'font-size',
						'min'  => 10,
						'max'  => 32,
					),
					'desc_color'       => array(
						'rule' => 'desc',
						'type' => 'color',
						'css'  => 'color',
					),
					'desc_font_size'   => array(
						'rule' => 'desc',
						'type' => 'px',
						'css'  => 'font-size',
						'min'  => 10,
						'max'  => 32,
					),
				),
			),
			'input'   => array(
				'rules' => array(
					'base'        => array( ' input.fplant-field', ' textarea.fplant-field', ' select.fplant-field' ),
					'focus'       => array( ' input.fplant-field:focus', ' textarea.fplant-field:focus', ' select.fplant-field:focus' ),
					'error'       => array( ' .fplant-field-group.fplant-field-has-error input.fplant-field', ' .fplant-field-group.fplant-field-has-error textarea.fplant-field', ' .fplant-field-group.fplant-field-has-error select.fplant-field' ),
					'placeholder' => array( ' input.fplant-field::placeholder', ' textarea.fplant-field::placeholder' ),
				),
				'props' => array(
					'color'              => array(
						'rule' => 'base',
						'type' => 'color',
						'css'  => 'color',
					),
					'background'         => array(
						'rule' => 'base',
						'type' => 'color',
						'css'  => 'background',
					),
					'font_size'          => array(
						'rule' => 'base',
						'type' => 'px',
						'css'  => 'font-size',
						'min'  => 10,
						'max'  => 32,
					),
					'border_color'       => array(
						'rule' => 'base',
						'type' => 'color',
						'css'  => 'border-color',
					),
					'focus_border_color' => array(
						'rule' => 'focus',
						'type' => 'color',
						'css'  => 'border-color',
					),
					'error_border_color' => array(
						'rule' => 'error',
						'type' => 'color',
						'css'  => 'border-color',
					),
					'placeholder_color'  => array(
						'rule' => 'placeholder',
						'type' => 'color',
						'css'  => 'color',
					),
				),
			),
			'submit'  => $button_rules( '.fplant-submit-button' ),
			'back'    => $button_rules( '.fplant-back-button' ),
			'confirm' => $button_rules( '.fplant-confirm-submit-button' ),
			'error'   => array(
				'rules' => array(
					'base' => array( ' .fplant-errors', ' .fplant-field-error' ),
				),
				'props' => array(
					'color'      => array(
						'rule' => 'base',
						'type' => 'color',
						'css'  => 'color',
					),
					'background' => array(
						'rule'   => 'base',
						'type'   => 'bg-padded',
						'css'    => 'background',
						'pad'    => '10px 14px',
						'radius' => '4px',
					),
				),
			),
		);
	}

	/**
	 * Build the scoped CSS for every section of a form's design options.
	 *
	 * @param string $prefix  Selector prefix, e.g. '#fplant-form-12'.
	 * @param array  $options settings.design_options value ({ form: {...}, ... }).
	 * @return string Generated CSS, or '' when nothing is set.
	 */
	public static function build_css( $prefix, $options ) {
		if ( ! is_array( $options ) || empty( $options ) ) {
			return '';
		}

		$css = array();
		foreach ( array_keys( self::get_schema() ) as $section ) {
			$values = isset( $options[ $section ] ) && is_array( $options[ $section ] )
				? $options[ $section ]
				: array();
			$rule   = self::build_section_css( $prefix, $section, $values );
			if ( '' !== $rule ) {
				$css[] = $rule;
			}
		}

		return implode( "\n", $css );
	}

	/**
	 * Build the CSS for a single section. Mirrored by buildSectionCss() in
	 * admin.js for the live part previews — keep the two in sync.
	 *
	 * @param string $prefix  Selector prefix.
	 * @param string $section Section key in the schema.
	 * @param array  $values  Option key => raw value for this section.
	 * @return string
	 */
	public static function build_section_css( $prefix, $section, $values ) {
		$schema = self::get_schema();
		if ( ! isset( $schema[ $section ] ) || ! is_array( $values ) ) {
			return '';
		}

		$decls  = array(); // rule key => list of declarations.
		$border = array(); // rule key => array( width, color ) awaiting composition.

		foreach ( $schema[ $section ]['props'] as $key => $def ) {
			$raw = isset( $values[ $key ] ) ? trim( (string) $values[ $key ] ) : '';
			if ( '' === $raw ) {
				continue;
			}
			$rule = $def['rule'];

			switch ( $def['type'] ) {
				case 'color':
					$color = self::sanitize_color( $raw );
					if ( '' !== $color ) {
						$decls[ $rule ][] = $def['css'] . ':' . $color;
						// Secondary outputs (e.g. error text color → errored input border)
						if ( isset( $def['also'] ) ) {
							foreach ( $def['also'] as $extra ) {
								$decls[ $extra['rule'] ][] = $extra['css'] . ':' . $color;
							}
						}
					}
					break;

				case 'px':
					$number = self::sanitize_px( $raw, $def['min'], $def['max'] );
					if ( null !== $number ) {
						$decls[ $rule ][] = $def['css'] . ':' . $number . 'px';
					}
					break;

				case 'px-pair':
					$number = self::sanitize_px( $raw, $def['min'], $def['max'] );
					if ( null !== $number ) {
						foreach ( $def['css'] as $css_prop ) {
							$decls[ $rule ][] = $css_prop . ':' . $number . 'px';
						}
					}
					break;

				case 'shadow':
					// Legacy keyword values (pre-slider selects) map onto the intensity scale.
					$legacy = array(
						'none' => '0',
						'sm'   => '1',
						'md'   => '3',
						'lg'   => '8',
					);
					if ( isset( $legacy[ $raw ] ) ) {
						$raw = $legacy[ $raw ];
					}
					$number = self::sanitize_px( $raw, $def['min'], $def['max'] );
					if ( null !== $number ) {
						$decls[ $rule ][] = $def['css'] . ':' . self::shadow_css( $number );
					}
					break;

				case 'weight':
					if ( 'bold' === $raw || 'normal' === $raw ) {
						$decls[ $rule ][] = $def['css'] . ':' . ( 'bold' === $raw ? '700' : '400' );
					}
					break;

				case 'btn-width':
					if ( 'auto' === $raw || 'full' === $raw ) {
						$decls[ $rule ][] = 'width:' . ( 'full' === $raw ? '100%' : 'auto' );
					}
					break;

				case 'border-width':
					$number = self::sanitize_px( $raw, $def['min'], $def['max'] );
					if ( null !== $number ) {
						$border[ $rule ]['width'] = $number;
					}
					break;

				case 'border-color':
					$color = self::sanitize_color( $raw );
					if ( '' !== $color ) {
						$border[ $rule ]['color'] = $color;
					}
					break;

				case 'bg-padded':
					$color = self::sanitize_color( $raw );
					if ( '' !== $color ) {
						$decls[ $rule ][] = $def['css'] . ':' . $color;
						$decls[ $rule ][] = 'padding:' . $def['pad'];
						$decls[ $rule ][] = 'border-radius:' . $def['radius'];
					}
					break;
			}
		}

		// Compose the border shorthand: either width or color alone is enough,
		// the missing half falls back to a sensible default.
		foreach ( $border as $rule => $parts ) {
			$width            = isset( $parts['width'] ) ? $parts['width'] : 1;
			$color            = isset( $parts['color'] ) ? $parts['color'] : '#dcdcde';
			$decls[ $rule ][] = 'border:' . $width . 'px solid ' . $color;
		}

		// Hover fallback: a custom background with no custom hover background
		// would otherwise freeze the button on hover, because the ID-prefixed
		// base rule beats the preset's :hover rule. Derive a darker shade.
		if ( isset( $schema[ $section ]['props']['hover_background'] ) ) {
			$base_bg  = isset( $values['background'] ) ? self::sanitize_color( $values['background'] ) : '';
			$hover_bg = isset( $values['hover_background'] ) ? self::sanitize_color( $values['hover_background'] ) : '';
			if ( '' !== $base_bg && '' === $hover_bg ) {
				$decls['hover'][] = 'background:' . self::darken( $base_bg, 0.12 );
			}
		}

		$css = '';
		foreach ( $schema[ $section ]['rules'] as $rule => $suffixes ) {
			if ( empty( $decls[ $rule ] ) ) {
				continue;
			}
			$selectors = array();
			foreach ( $suffixes as $suffix ) {
				$selectors[] = $prefix . $suffix;
			}
			$css .= implode( ', ', $selectors ) . '{' . implode( ';', $decls[ $rule ] ) . '}';
		}

		return $css;
	}

	/**
	 * Whitelist one section's raw values against the schema. Unknown keys and
	 * empty values are dropped; everything else is stored as a plain string
	 * (build_css() validates per-type again at output time).
	 *
	 * @param string $section Section key in the schema.
	 * @param array  $values  Raw key => value pairs.
	 * @return array Sanitized values.
	 */
	public static function sanitize_section_values( $section, $values ) {
		$schema = self::get_schema();
		$clean  = array();
		if ( ! isset( $schema[ $section ] ) || ! is_array( $values ) ) {
			return $clean;
		}
		foreach ( array_keys( $schema[ $section ]['props'] ) as $key ) {
			if ( ! isset( $values[ $key ] ) ) {
				continue;
			}
			$value = trim( sanitize_text_field( (string) $values[ $key ] ) );
			if ( '' !== $value ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}

	/**
	 * Replace one section of a form's saved design options (the partial AJAX
	 * save behind the per-section Save button). Empty $values removes the
	 * section; other sections and all other settings stay untouched.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $section Section key in the schema.
	 * @param array  $values  Raw key => value pairs for this section.
	 * @return array|null Sanitized section values, or null when the form does not exist.
	 */
	public static function update_section( $form_id, $section, $values ) {
		$schema = self::get_schema();
		if ( ! isset( $schema[ $section ] ) ) {
			return null;
		}

		$design = self::update_sections( $form_id, array( $section => $values ) );
		if ( null === $design ) {
			return null;
		}

		return isset( $design[ $section ] ) ? $design[ $section ] : array();
	}

	/**
	 * Replace several sections of a form's saved design options in a single
	 * read-modify-write. This is the atomic save behind a grouped accordion
	 * (e.g. the confirmation screen's back/submit buttons): saving each section
	 * as its own concurrent AJAX request would race on the shared settings meta
	 * and lose one section's values. Unknown sections are skipped; an empty
	 * section is removed. Other sections and all other settings stay untouched.
	 *
	 * @param int   $form_id  Form ID.
	 * @param array $sections Map of section key => raw key/value pairs.
	 * @return array|null The resulting design_options map, or null when the form does not exist.
	 */
	public static function update_sections( $form_id, $sections ) {
		if ( ! is_array( $sections ) ) {
			return null;
		}

		$form = FPLANT_Database::get_form( $form_id );
		if ( ! $form ) {
			return null;
		}

		$schema   = self::get_schema();
		$settings = isset( $form['settings'] ) && is_array( $form['settings'] ) ? $form['settings'] : array();
		$design   = isset( $settings['design_options'] ) && is_array( $settings['design_options'] ) ? $settings['design_options'] : array();

		foreach ( $sections as $section => $values ) {
			if ( ! isset( $schema[ $section ] ) ) {
				continue;
			}
			$clean = self::sanitize_section_values( $section, is_array( $values ) ? $values : array() );
			if ( $clean ) {
				$design[ $section ] = $clean;
			} else {
				unset( $design[ $section ] );
			}
		}

		if ( $design ) {
			$settings['design_options'] = $design;
		} else {
			unset( $settings['design_options'] );
		}

		FPLANT_Database::update_form_meta( $form_id, FPLANT_Database::META_SETTINGS, $settings );

		return $design;
	}

	/**
	 * Darken a hex color by the given ratio (0–1).
	 *
	 * @param string $hex   Hex color (#rgb or #rrggbb).
	 * @param float  $ratio Amount to darken, e.g. 0.12.
	 * @return string Darkened #rrggbb color, or '' for invalid input.
	 */
	public static function darken( $hex, $ratio ) {
		$hex = self::sanitize_color( $hex );
		if ( '' === $hex ) {
			return '';
		}
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$out = '#';
		foreach ( array( 0, 2, 4 ) as $offset ) {
			$channel = (int) round( hexdec( substr( $hex, $offset, 2 ) ) * ( 1 - $ratio ) );
			$out    .= str_pad( dechex( max( 0, min( 255, $channel ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	/**
	 * Validate a hex color. Own implementation because sanitize_hex_color()
	 * is not loaded on the front end before WP 5.4.
	 *
	 * @param string $value Raw value.
	 * @return string Lowercased hex color, or '' when invalid.
	 */
	private static function sanitize_color( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
			return strtolower( $value );
		}
		return '';
	}

	/**
	 * Validate and clamp a pixel value.
	 *
	 * @param string $value Raw value.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @return int|null Clamped integer, or null when not numeric.
	 */
	private static function sanitize_px( $value, $min, $max ) {
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		return max( $min, min( $max, (int) round( (float) $value ) ) );
	}
}
