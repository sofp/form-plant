<?php
/**
 * MW WP Form Migrator (orchestrator)
 *
 * Converts a single MW WP Form post into a Form Plant form.
 *
 * @package Form_Plant
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPLANT_MWWPForm_Migrator
 *
 * @since 1.2.0
 */
class FPLANT_MWWPForm_Migrator extends FPLANT_Migrator_Base {

	const MW_POST_TYPE       = 'mw-wp-form';
	const MW_SETTINGS_META   = 'mw-wp-form';
	const MIGRATED_FROM_META = '_fplant_migrated_from_mwwp_id';
	const MIGRATION_LOG_META = '_fplant_migration_log';
	const MIGRATION_LOG_VERSION = 1;

	/** @var FPLANT_Name_Translator */
	private $translator;

	/** @var FPLANT_MWWPForm_Parser */
	private $parser;

	/** @var FPLANT_MWWPForm_Field_Mapper */
	private $field_mapper;

	/** @var FPLANT_MWWPForm_Email_Mapper */
	private $email_mapper;

	/** @var FPLANT_MWWPForm_Validation_Merger */
	private $validation_merger;

	/** @var FPLANT_MWWPForm_Template_Builder */
	private $template_builder;

	/** @var FPLANT_Form_Manager */
	private $form_manager;

	/**
	 * Constructor.
	 *
	 * @param FPLANT_Form_Manager|null $form_manager Optional existing manager to inject (useful for testing).
	 */
	public function __construct( $form_manager = null ) {
		$this->translator        = new FPLANT_Name_Translator();
		$this->parser            = new FPLANT_MWWPForm_Parser();
		$this->field_mapper      = new FPLANT_MWWPForm_Field_Mapper( $this->translator, $this );
		$this->email_mapper      = new FPLANT_MWWPForm_Email_Mapper( $this->translator, $this );
		$this->validation_merger = new FPLANT_MWWPForm_Validation_Merger( $this->translator, $this );
		$this->template_builder  = new FPLANT_MWWPForm_Template_Builder( $this->translator, $this );
		$this->form_manager      = $form_manager instanceof FPLANT_Form_Manager
			? $form_manager
			: new FPLANT_Form_Manager();
	}

	/**
	 * Returns a list of MW WP Form posts available for migration.
	 *
	 * @return array<int, array{id:int, title:string, field_count:int, migrated_to:int|null}>
	 */
	public function list_mwwpform_forms() {
		$posts = get_posts(
			array(
				'post_type'      => self::MW_POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$result = array();
		foreach ( $posts as $post ) {
			$parsed     = $this->parser->parse( $post->post_content );
			$migrations = $this->find_all_migrations( (int) $post->ID );
			$result[]   = array(
				'id'          => (int) $post->ID,
				'title'       => '' !== $post->post_title ? $post->post_title : sprintf( '(No title #%d)', $post->ID ),
				'field_count' => count( $parsed['fields'] ),
				'migrated_to' => ! empty( $migrations ) ? $migrations[0]['form_id'] : null,
				'migrations'  => $migrations,
			);
		}
		return $result;
	}

	/**
	 * Runs the migration.
	 *
	 * @param int $source_id MW WP Form post ID.
	 * @return array{form_id:int|null, warnings:array, status:string, mw_form_id:int, mw_title:string}
	 */
	public function migrate( $source_id ) {
		$source_id = (int) $source_id;
		$this->clear_warnings();
		$this->translator->reset();

		$post = get_post( $source_id );
		if ( ! $post || self::MW_POST_TYPE !== $post->post_type ) {
			$this->add_warning(
				self::LEVEL_ERROR,
				'source_not_found',
				sprintf(
					/* translators: %d: source post id. */
					__( 'The specified MW WP Form post (ID: %d) was not found.', 'form-plant' ),
					$source_id
				)
			);
			return array(
				'form_id'    => null,
				'warnings'   => $this->get_warnings(),
				'status'     => 'failed',
				'mw_form_id' => $source_id,
				'mw_title'   => '',
			);
		}

		$mw_settings = get_post_meta( $source_id, self::MW_SETTINGS_META, true );
		if ( ! is_array( $mw_settings ) ) {
			$mw_settings = array();
		}

		// 1. Parse shortcodes
		$parsed = $this->parser->parse( $post->post_content );

		// R2: Submit / confirm / back button shortcodes are transferred to Form Plant's
		// built-in buttons in build_form_settings() (their text and class are carried over).
		$migrated_button_shortcodes = array(
			'mwform_submit',
			'mwform_submitButton',
			'mwform_confirmButton',
			'mwform_backButton',
			'mwform_bsubmit',
			'mwform_bconfirm',
			'mwform_bback',
		);
		// Generic buttons have no submit action and are not migrated.
		$generic_button_shortcodes = array( 'mwform_button', 'mwform_bbutton' );
		foreach ( $parsed['skipped'] as $skipped ) {
			$shortcode = $skipped['shortcode'];

			if ( in_array( $shortcode, $migrated_button_shortcodes, true ) ) {
				$this->add_warning(
					self::LEVEL_INFO,
					'button_migrated',
					sprintf(
						/* translators: %s: shortcode name. */
						__( '[%s] is migrated to Form Plant\'s built-in button feature (its text and class are carried over).', 'form-plant' ),
						$shortcode
					),
					array( 'shortcode' => $shortcode )
				);
				continue;
			}

			if ( in_array( $shortcode, $generic_button_shortcodes, true ) ) {
				continue;
			}

			$this->add_warning(
				self::LEVEL_INFO,
				'shortcode_skipped',
				sprintf(
					/* translators: %s: shortcode name. */
					__( '[%s] is auto-generated by Form Plant and is therefore excluded from migration.', 'form-plant' ),
					$shortcode
				),
				array( 'shortcode' => $shortcode )
			);
		}
		foreach ( $parsed['unknown'] as $unknown ) {
			$this->add_warning(
				self::LEVEL_WARNING,
				'shortcode_unknown',
				sprintf(
					/* translators: %s: shortcode name. */
					__( '[%s] is an unsupported shortcode and was skipped.', 'form-plant' ),
					$unknown['shortcode']
				),
				array( 'shortcode' => $unknown['shortcode'] )
			);
		}

		// 2. Map each field
		$fields = array();
		foreach ( $parsed['fields'] as $entry ) {
			$mapped = $this->field_mapper->map( $entry );
			if ( null !== $mapped ) {
				$fields[] = $mapped;
			}
		}

		// 3. Merge validation settings
		$mw_validation = array();
		if ( isset( $mw_settings['validation'] ) && is_array( $mw_settings['validation'] ) ) {
			$mw_validation = $mw_settings['validation'];
		}
		$fields = $this->validation_merger->merge( $fields, $mw_validation );

		// 3-2. Warn if multiple fields ended up sharing the same name (e.g. the source
		// form had duplicate field names). Same-named inputs collapse to a single value
		// on submission, so the user's input can be lost.
		$this->warn_duplicate_field_names( $fields );

		// 4. Convert email settings (Name_Translator holds the state after field mapping)
		$email_admin = $this->email_mapper->map_admin_email( $mw_settings );
		$email_user  = $this->email_mapper->map_user_email( $mw_settings );

		// 5. Detect any previously migrated form (R1: leave it as-is to keep its published state)
		$existing = $this->find_existing_migration( $source_id );
		if ( null !== $existing ) {
			$this->add_warning(
				self::LEVEL_INFO,
				're_migration_existing_form_kept',
				sprintf(
					/* translators: %d: form id. */
					__( 'The previously migrated form (ID: %d) will be kept as-is; a new Form Plant form will be created.', 'form-plant' ),
					$existing
				),
				array( 'old_form_id' => $existing )
			);
		}

		// 5-2. R2: Determine whether a confirmation screen exists from button information
		$buttons          = isset( $parsed['buttons'] ) ? (array) $parsed['buttons'] : array();
		$use_confirmation = $this->determine_use_confirmation( $buttons );

		// 5-3. R3: Generate input screen / confirmation screen HTML via Template_Builder
		$template_source = isset( $parsed['template_source'] ) ? (string) $parsed['template_source'] : '';
		$tokens          = isset( $parsed['tokens'] ) ? (array) $parsed['tokens'] : array();
		$templates       = $this->template_builder->build( $template_source, $tokens, $use_confirmation );

		// 5-4. R2 second half + R5: Assemble settings (button info + URL/message + template flags)
		$settings = $this->build_form_settings(
			$buttons,
			$mw_settings,
			$use_confirmation,
			$templates
		);

		// 6. Create Form Plant form (R1: append timestamp to title)
		// Created as published so the form can be placed and used immediately
		// (non-published forms do not appear in the block editor form picker).
		$new_form_id = $this->form_manager->create_form(
			$this->build_new_form_title( $post->post_title ),
			array( 'status' => 'publish' )
		);
		if ( is_wp_error( $new_form_id ) ) {
			$this->add_warning(
				self::LEVEL_ERROR,
				'form_create_failed',
				sprintf(
					/* translators: %s: error message. */
					__( 'Failed to create the form: %s', 'form-plant' ),
					$new_form_id->get_error_message()
				)
			);
			return array(
				'form_id'    => null,
				'warnings'   => $this->get_warnings(),
				'status'     => 'failed',
				'mw_form_id' => $source_id,
				'mw_title'   => $post->post_title,
			);
		}

		// Let the user know the form is ready to use right away.
		$this->add_warning(
			self::LEVEL_INFO,
			'form_published',
			__( 'The form was created as published. You can try it right away by selecting it in the block editor or pasting its shortcode.', 'form-plant' )
		);

		$update_data = array(
			'fields'        => $fields,
			'email_admin'   => $email_admin,
			'email_user'    => $email_user,
			'html_template' => isset( $templates['input_template'] ) ? (string) $templates['input_template'] : '',
		);
		if ( ! empty( $settings ) ) {
			$update_data['settings'] = $settings;
		}

		$update_result = $this->form_manager->update_form(
			$new_form_id,
			$update_data
		);
		if ( false === $update_result ) {
			$this->add_warning(
				self::LEVEL_ERROR,
				'form_update_failed',
				__( 'Failed to save form settings.', 'form-plant' )
			);
			$this->save_migration_log( (int) $new_form_id, $post, 'failed' );
			return array(
				'form_id'    => (int) $new_form_id,
				'warnings'   => $this->get_warnings(),
				'status'     => 'failed',
				'mw_form_id' => $source_id,
				'mw_title'   => $post->post_title,
			);
		}

		update_post_meta( $new_form_id, self::MIGRATED_FROM_META, $source_id );

		$status = $this->has_errors() ? 'failed' : ( $this->has_warnings() ? 'partial' : 'success' );

		// R4: Persist migration log to post meta
		$this->save_migration_log( (int) $new_form_id, $post, $status );

		return array(
			'form_id'    => (int) $new_form_id,
			'warnings'   => $this->get_warnings(),
			'status'     => $status,
			'mw_form_id' => $source_id,
			'mw_title'   => $post->post_title,
		);
	}

	/**
	 * Finds the ID of a Form Plant form that was migrated from the given MW form.
	 *
	 * @param int $mw_form_id MW WP Form post ID.
	 * @return int|null Migrated Form Plant form ID, or null if none exists.
	 */
	private function find_existing_migration( $mw_form_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'fplant_form',
				'posts_per_page' => 1,
				'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
				'meta_key'       => self::MIGRATED_FROM_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (string) $mw_form_id,     // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0];
		}
		return null;
	}

	/**
	 * Returns all Form Plant forms derived from the given MW form, ordered newest first.
	 *
	 * Due to the R1 revision, the same MW form can be migrated multiple times, so multiple derived forms may exist.
	 *
	 * @param int $mw_form_id MW WP Form post ID.
	 * @return array<int, array{form_id:int, post_title:string, post_status:string, migrated_at:string, has_log:bool}>
	 */
	public function find_all_migrations( $mw_form_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'fplant_form',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
				'meta_key'       => self::MIGRATED_FROM_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (string) $mw_form_id,     // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		if ( empty( $query->posts ) ) {
			return array();
		}

		$results = array();
		foreach ( $query->posts as $p ) {
			$log = get_post_meta( $p->ID, self::MIGRATION_LOG_META, true );
			$migrated_at = '';
			if ( is_array( $log ) && isset( $log['migrated_at'] ) ) {
				$migrated_at = (string) $log['migrated_at'];
			}
			$results[] = array(
				'form_id'     => (int) $p->ID,
				'post_title'  => (string) $p->post_title,
				'post_status' => (string) $p->post_status,
				'migrated_at' => $migrated_at,
				'has_log'     => is_array( $log ) && ! empty( $log ),
			);
		}
		return $results;
	}

	/**
	 * Returns whether the warnings contain an error-level entry.
	 *
	 * @return bool
	 */
	private function has_errors() {
		foreach ( $this->warnings as $w ) {
			if ( self::LEVEL_ERROR === $w['level'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns whether the warnings contain a non-info (warning or error) level entry.
	 *
	 * @return bool
	 */
	private function has_warnings() {
		foreach ( $this->warnings as $w ) {
			if ( self::LEVEL_INFO !== $w['level'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Warns when multiple migrated fields share the same name.
	 *
	 * The Name_Translator intentionally maps an identical source name to the same key
	 * (to keep mail merge tags consistent). When the source form contains two fields
	 * with the same name, the migrated form therefore ends up with duplicate field
	 * names. HTML forms submit same-named inputs as a single value, so the user's input
	 * can be lost. Surface this so the user can rename them to unique names.
	 *
	 * @param array $fields Final Form Plant field array.
	 */
	private function warn_duplicate_field_names( array $fields ) {
		$counts = array();
		foreach ( $fields as $field ) {
			if ( ! isset( $field['name'] ) || '' === $field['name'] ) {
				continue;
			}
			$name            = (string) $field['name'];
			$counts[ $name ] = isset( $counts[ $name ] ) ? $counts[ $name ] + 1 : 1;
		}
		foreach ( $counts as $name => $count ) {
			if ( $count < 2 ) {
				continue;
			}
			$this->add_warning(
				self::LEVEL_WARNING,
				'duplicate_field_name',
				sprintf(
					/* translators: 1: field name, 2: number of fields sharing it. */
					__( 'After migration, %2$d fields share the same name "%1$s". HTML forms send same-named fields as a single value, so their input may be lost on submission. Please give them unique names in the migrated form.', 'form-plant' ),
					$name,
					$count
				),
				array( 'name' => $name, 'count' => $count )
			);
		}
	}

	/**
	 * R2: Determines whether a confirmation screen should be used based on button information.
	 *
	 * - submitButton has a confirm_value attribute
	 * - confirmButton / bconfirm is present
	 * - bsubmit is present and display_input is not "true"
	 * - backButton / bback is present (a back button implies a confirmation screen)
	 *
	 * @param array $buttons Buttons bucket from the Parser.
	 * @return bool
	 */
	private function determine_use_confirmation( array $buttons ) {
		// A back button means a confirmation screen is present
		if ( ! empty( $buttons['back'] ) ) {
			return true;
		}
		// confirmButton / bconfirm
		if ( ! empty( $buttons['confirm'] ) ) {
			return true;
		}
		// Detailed check for submit-type buttons
		if ( ! empty( $buttons['submit'] ) ) {
			foreach ( $buttons['submit'] as $btn ) {
				$attrs    = isset( $btn['attrs'] ) ? (array) $btn['attrs'] : array();
				$tag_type = isset( $btn['tag_type'] ) ? $btn['tag_type'] : 'input';
				if ( 'input' === $tag_type ) {
					if ( isset( $attrs['confirm_value'] ) && '' !== $attrs['confirm_value'] ) {
						return true;
					}
				} else {
					// bsubmit: if display_input != 'true', treat as the confirmation screen submit button
					$display_input = isset( $attrs['display_input'] ) ? (string) $attrs['display_input'] : '';
					if ( 'true' !== $display_input ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * R2 second half + R5: Assembles button info, URL/message, and template flags into settings.
	 *
	 * @param array $buttons          Buttons bucket from the Parser.
	 * @param array $mw_meta          MW WP Form settings meta.
	 * @param bool  $use_confirmation Whether a confirmation screen is used.
	 * @param array $templates        Result of Template_Builder::build().
	 * @return array Form Plant settings array.
	 */
	private function build_form_settings( array $buttons, array $mw_meta, $use_confirmation, array $templates ) {
		$settings = array();

		// During MW WP Form migration, use Form Plant's standard ("normal") design so
		// the migrated form works out of the box with a clean look. The layout
		// reproduced from MW WP Form is still saved as HTML templates (see below and
		// html_template), but kept disabled by default; it can be enabled later from
		// the layout settings.
		$settings['design_type'] = 'normal';
		$this->add_warning(
			self::LEVEL_INFO,
			'form_design_set_normal',
			__( 'The migrated form uses Form Plant\'s standard design. The layout reproduced from MW WP Form was saved as an HTML template but is turned off by default; enable it in the layout settings to use the original layout.', 'form-plant' )
		);

		// R5: URL settings and completion message -> post-submit action
		$this->map_post_submit_action( $mw_meta, $settings );

		// R2: Whether to use the confirmation screen
		$settings['use_confirmation'] = (bool) $use_confirmation;

		// R3: The input screen HTML template body is saved (see html_template),
		// but "use input HTML template" stays off by default.

		// R3: The confirmation screen HTML template body is saved, but
		// "use confirmation HTML template" stays off by default.
		if ( $use_confirmation && isset( $templates['confirmation_template'] ) && '' !== $templates['confirmation_template'] ) {
			$settings['confirmation_template'] = (string) $templates['confirmation_template'];
		}

		// R2: Input screen submit / confirm button
		// Priority: submit (input) confirm_value > submit (button) display_input=true > confirmButton (input) > bconfirm (button)
		$input_submit_set = false;
		if ( ! empty( $buttons['submit'] ) ) {
			foreach ( $buttons['submit'] as $btn ) {
				$attrs    = isset( $btn['attrs'] ) ? (array) $btn['attrs'] : array();
				$tag_type = isset( $btn['tag_type'] ) ? $btn['tag_type'] : 'input';
				if ( 'mwform_submit' === $btn['shortcode'] ) {
					// mwform_submit is a simple submit button with only a value attribute.
					// If a confirmation screen is used, migrate as the confirmation screen submit button; otherwise as the input screen submit button.
					$label      = FPLANT_MWWPForm_Template_Builder::extract_button_label( $btn['shortcode'], $attrs );
					$class_attr = isset( $attrs['class'] ) ? (string) $attrs['class'] : '';
					if ( $use_confirmation ) {
						if ( '' !== $label ) {
							$settings['confirmation_submit_text'] = $label;
							if ( '' !== $class_attr ) {
								$settings['confirmation_submit_class'] = $class_attr;
							}
						}
					} elseif ( '' !== $label ) {
						$settings['input_submit_text'] = $label;
						if ( '' !== $class_attr ) {
							$settings['input_submit_class'] = $class_attr;
						}
						$input_submit_set = true;
					}
				} elseif ( 'input' === $tag_type ) {
					// input-tag-type submitButton
					if ( isset( $attrs['confirm_value'] ) && '' !== $attrs['confirm_value'] ) {
						$settings['input_submit_text'] = (string) $attrs['confirm_value'];
						if ( isset( $attrs['class'] ) && '' !== $attrs['class'] ) {
							$settings['input_submit_class'] = (string) $attrs['class'];
						}
						$input_submit_set = true;
					} elseif ( isset( $attrs['submit_value'] ) && '' !== $attrs['submit_value'] ) {
						// No confirm_value -> direct submission
						$settings['input_submit_text'] = (string) $attrs['submit_value'];
						if ( isset( $attrs['class'] ) && '' !== $attrs['class'] ) {
							$settings['input_submit_class'] = (string) $attrs['class'];
						}
						$input_submit_set = true;
					}
					// submit_value (label for the confirmation screen submit button)
					if ( isset( $attrs['submit_value'] ) && '' !== $attrs['submit_value'] ) {
						$settings['confirmation_submit_text'] = (string) $attrs['submit_value'];
						if ( isset( $attrs['class'] ) && '' !== $attrs['class'] ) {
							$settings['confirmation_submit_class'] = (string) $attrs['class'];
						}
					}
				} else {
					// button-tag-type bsubmit
					$label         = FPLANT_MWWPForm_Template_Builder::extract_button_label( $btn['shortcode'], $attrs );
					$display_input = isset( $attrs['display_input'] ) ? (string) $attrs['display_input'] : '';
					$class_attr    = isset( $attrs['class'] ) ? (string) $attrs['class'] : '';
					if ( 'true' === $display_input ) {
						if ( '' !== $label ) {
							$settings['input_submit_text'] = $label;
							if ( '' !== $class_attr ) {
								$settings['input_submit_class'] = $class_attr;
							}
							$input_submit_set = true;
						}
					} else {
						if ( '' !== $label ) {
							$settings['confirmation_submit_text'] = $label;
							if ( '' !== $class_attr ) {
								$settings['confirmation_submit_class'] = $class_attr;
							}
						}
					}
				}
			}
		}

		// If the input screen button was not determined from submit buttons, fall back to confirm buttons
		if ( ! $input_submit_set && ! empty( $buttons['confirm'] ) ) {
			$btn   = $buttons['confirm'][0];
			$attrs = isset( $btn['attrs'] ) ? (array) $btn['attrs'] : array();
			$label = FPLANT_MWWPForm_Template_Builder::extract_button_label( $btn['shortcode'], $attrs );
			if ( '' !== $label ) {
				$settings['input_submit_text'] = $label;
			}
			if ( isset( $attrs['class'] ) && '' !== $attrs['class'] ) {
				$settings['input_submit_class'] = (string) $attrs['class'];
			}
		}

		// Back button (mwform_backButton / mwform_bback)
		if ( ! empty( $buttons['back'] ) ) {
			$btn   = $buttons['back'][0];
			$attrs = isset( $btn['attrs'] ) ? (array) $btn['attrs'] : array();
			$label = FPLANT_MWWPForm_Template_Builder::extract_button_label( $btn['shortcode'], $attrs );
			if ( '' !== $label ) {
				$settings['confirmation_back_text'] = $label;
			}
			if ( isset( $attrs['class'] ) && '' !== $attrs['class'] ) {
				$settings['confirmation_back_class'] = (string) $attrs['class'];
			}
		}

		// Generic buttons emit a warning on the Template_Builder side when removed, so no additional warning is issued here.
		return $settings;
	}

	/**
	 * Generates the new form title in the format "<original title>(from MW WP Form YYYY-MM-DD HH:MM:SS)".
	 *
	 * @param string      $source_title The original MW WP Form post_title.
	 * @param string|null $timestamp    Timestamp string (injectable for testing).
	 * @return string
	 */
	private function build_new_form_title( $source_title, $timestamp = null ) {
		if ( null === $timestamp ) {
			if ( function_exists( 'wp_date' ) ) {
				$timestamp = wp_date( 'Y-m-d H:i:s' );
			} else {
				$timestamp = gmdate( 'Y-m-d H:i:s' );
			}
		}
		$base_title = ( is_string( $source_title ) && '' !== $source_title ) ? $source_title : __( '(Untitled)', 'form-plant' );
		return sprintf(
			/* translators: 1: original form title, 2: timestamp YYYY-MM-DD HH:MM:SS */
			__( '%1$s(from MW WP Form %2$s)', 'form-plant' ),
			$base_title,
			$timestamp
		);
	}

	/**
	 * R5: Applies MW WP Form URL settings and completion screen message to Form Plant post-submit action.
	 *
	 * - complete_url is non-empty -> action_type='redirect', redirect_url=<url>
	 * - complete_url is empty AND complete_message is non-empty -> action_type='custom_page', success_page_html=<message>
	 * - input_url / confirmation_url / validation_error_url is non-empty -> notify via info that migration is
	 *   not required because Form Plant handles these within the page that hosts the form
	 *
	 * @param array $mw_meta  MW WP Form settings meta (value of the mw-wp-form meta key).
	 * @param array $settings Form Plant settings array (written to by reference).
	 */
	private function map_post_submit_action( array $mw_meta, array &$settings ) {
		$complete_url     = isset( $mw_meta['complete_url'] ) ? trim( (string) $mw_meta['complete_url'] ) : '';
		$complete_message = isset( $mw_meta['complete_message'] ) ? (string) $mw_meta['complete_message'] : '';

		if ( '' !== $complete_url ) {
			$settings['action_type']  = 'redirect';
			$settings['redirect_url'] = esc_url_raw( $complete_url );

			if ( '' !== trim( $complete_message ) ) {
				$preview = function_exists( 'mb_substr' )
					? mb_substr( wp_strip_all_tags( $complete_message ), 0, 100 )
					: substr( wp_strip_all_tags( $complete_message ), 0, 100 );
				$this->add_warning(
					self::LEVEL_INFO,
					'complete_message_skipped_by_url',
					__( 'A completion URL is set, so redirect takes priority. The completion screen message will not be used (MW WP Form also prioritises the URL over the message).', 'form-plant' ),
					array( 'complete_message_preview' => $preview )
				);
			}
		} elseif ( '' !== trim( $complete_message ) ) {
			$settings['action_type']       = 'custom_page';
			$settings['success_page_html'] = $complete_message;
		}

		// The input screen, confirmation screen, and error screen URLs do not need to be migrated
		// because Form Plant handles all of these within the page that hosts the form.
		// The wording should convey "not needed" rather than "failed".
		$skipped_url_keys = array(
			'input_url'            => array(
				'code'    => 'url_input_not_supported',
				/* translators: %s: URL value */
				'message' => __( 'The MW WP Form input screen URL "%s" will not be migrated because in Form Plant the page where the form is placed serves as the input screen.', 'form-plant' ),
			),
			'confirmation_url'     => array(
				'code'    => 'url_confirmation_not_supported',
				/* translators: %s: URL value */
				'message' => __( 'The MW WP Form confirmation screen URL "%s" will not be migrated because in Form Plant the confirmation screen is displayed within the form\'s page.', 'form-plant' ),
			),
			'validation_error_url' => array(
				'code'    => 'url_validation_error_not_supported',
				/* translators: %s: URL value */
				'message' => __( 'The MW WP Form validation error screen URL "%s" will not be migrated because in Form Plant validation errors are displayed within the form\'s page.', 'form-plant' ),
			),
		);
		foreach ( $skipped_url_keys as $meta_key => $info ) {
			$url = isset( $mw_meta[ $meta_key ] ) ? trim( (string) $mw_meta[ $meta_key ] ) : '';
			if ( '' === $url ) {
				continue;
			}
			$this->add_warning(
				self::LEVEL_INFO,
				$info['code'],
				sprintf( $info['message'], $url ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Context array key for the warning report, not a query argument.
				array( 'meta_key' => $meta_key, 'url' => $url )
			);
		}
	}

	/**
	 * R4: Saves the migration log to the new form's post meta (_fplant_migration_log).
	 *
	 * @param int     $form_id The newly created Form Plant form ID.
	 * @param WP_Post $source  The source MW WP Form post.
	 * @param string  $status  'success' | 'partial' | 'failed'.
	 */
	private function save_migration_log( $form_id, $source, $status ) {
		if ( ! $form_id ) {
			return;
		}

		if ( function_exists( 'wp_date' ) ) {
			$migrated_at = wp_date( DATE_ATOM );
		} else {
			$migrated_at = gmdate( DATE_ATOM );
		}

		$log = array(
			'source_post_id'    => (int) ( isset( $source->ID ) ? $source->ID : 0 ),
			'source_post_title' => (string) ( isset( $source->post_title ) ? $source->post_title : '' ),
			'migrated_at'       => $migrated_at,
			'status'            => (string) $status,
			'warnings'          => $this->get_warnings(),
			'log_version'       => self::MIGRATION_LOG_VERSION,
		);

		update_post_meta( $form_id, self::MIGRATION_LOG_META, $log );
	}

	/**
	 * R4: Retrieves the migration log stored on the specified form.
	 *
	 * @param int $form_id Form Plant form ID.
	 * @return array Migration log array, or an empty array if none exists.
	 */
	public static function get_migration_log( $form_id ) {
		$log = get_post_meta( (int) $form_id, self::MIGRATION_LOG_META, true );
		if ( ! is_array( $log ) ) {
			return array();
		}
		return $log;
	}
}
