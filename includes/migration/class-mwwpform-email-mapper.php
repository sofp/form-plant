<?php
/**
 * MW WP Form Email Mapper
 *
 * Converts MW WP Form email settings (the associative array stored under the
 * single meta key `mw-wp-form`) into Form Plant's email_admin / email_user arrays.
 *
 * @package Form_Plant
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPLANT_MWWPForm_Email_Mapper
 *
 * @since 1.2.0
 */
class FPLANT_MWWPForm_Email_Mapper {

	/**
	 * Name translator (used for body tag conversion and auto-reply recipient field name translation).
	 *
	 * @var FPLANT_Name_Translator
	 */
	private $translator;

	/**
	 * Logger for collecting warnings.
	 *
	 * @var FPLANT_Migrator_Base
	 */
	private $logger;

	/**
	 * Tags already warned about as unresolved, to avoid duplicate warnings across the
	 * multiple subjects/bodies converted for a single form.
	 *
	 * @var array<string, bool>
	 */
	private $warned_unresolved = array();

	/**
	 * Form Plant system tags that resolve at send time (see FPLANT_Email_Handler::replace_tags).
	 *
	 * @var array<int, string>
	 */
	private static $system_tags = array(
		'all_fields',
		'form_title',
		'submission_id',
		'submission_date',
		'ip_address',
		'user_agent',
		'site_name',
		'site_url',
		'admin_email',
	);

	/**
	 * Constructor.
	 *
	 * @param FPLANT_Name_Translator $translator Name translator.
	 * @param FPLANT_Migrator_Base   $logger     Logger for collecting warnings.
	 */
	public function __construct( FPLANT_Name_Translator $translator, FPLANT_Migrator_Base $logger ) {
		$this->translator = $translator;
		$this->logger     = $logger;
	}

	/**
	 * Converts admin email settings into a Form Plant email_admin array.
	 *
	 * @param array $mw_settings MW WP Form settings associative array.
	 * @return array Form Plant email_admin array.
	 */
	public function map_admin_email( array $mw_settings ) {
		$from_email = '';
		$from_name  = '';
		if ( ! empty( $mw_settings['admin_mail_from'] ) ) {
			list( $from_email, $from_name ) = $this->parse_from_header( (string) $mw_settings['admin_mail_from'] );
		}
		if ( ! empty( $mw_settings['admin_mail_sender'] ) && '' === $from_name ) {
			$from_name = (string) $mw_settings['admin_mail_sender'];
		}

		$to      = isset( $mw_settings['mail_to'] ) ? (string) $mw_settings['mail_to'] : '';
		$enabled = '' !== $to;

		if ( ! empty( $mw_settings['mail_return_path'] ) ) {
			$this->logger->add_warning(
				FPLANT_Migrator_Base::LEVEL_INFO,
				'return_path_skipped',
				__( 'The Return-Path for the admin email will not be migrated. Form Plant follows the WordPress default Return-Path.', 'form-plant' )
			);
		}

		return array(
			'enabled'    => $enabled,
			'to'         => $to,
			'cc'         => isset( $mw_settings['mail_cc'] ) ? (string) $mw_settings['mail_cc'] : '',
			'bcc'        => isset( $mw_settings['mail_bcc'] ) ? (string) $mw_settings['mail_bcc'] : '',
			'subject'    => $this->convert_body( isset( $mw_settings['admin_mail_subject'] ) ? (string) $mw_settings['admin_mail_subject'] : '' ),
			'body'       => $this->convert_body( isset( $mw_settings['admin_mail_content'] ) ? (string) $mw_settings['admin_mail_content'] : '' ),
			'from_email' => $from_email,
			'from_name'  => $from_name,
			'reply_to'   => isset( $mw_settings['admin_mail_reply_to'] ) ? (string) $mw_settings['admin_mail_reply_to'] : '',
		);
	}

	/**
	 * Converts auto-reply email settings into a Form Plant email_user array.
	 *
	 * @param array $mw_settings MW WP Form settings associative array.
	 * @return array Form Plant email_user array.
	 */
	public function map_user_email( array $mw_settings ) {
		$from_email = '';
		$from_name  = '';
		if ( ! empty( $mw_settings['mail_from'] ) ) {
			list( $from_email, $from_name ) = $this->parse_from_header( (string) $mw_settings['mail_from'] );
		}
		if ( ! empty( $mw_settings['mail_sender'] ) && '' === $from_name ) {
			$from_name = (string) $mw_settings['mail_sender'];
		}

		$to_field_original = isset( $mw_settings['automatic_reply_email'] )
			? (string) $mw_settings['automatic_reply_email']
			: '';
		$to_field          = '' !== $to_field_original
			? $this->translator->translate( $to_field_original, 'email' )
			: '';
		$enabled           = '' !== $to_field;

		return array(
			'enabled'    => $enabled,
			'to_field'   => $to_field,
			'cc'         => '',
			'bcc'        => '',
			'subject'    => $this->convert_body( isset( $mw_settings['mail_subject'] ) ? (string) $mw_settings['mail_subject'] : '' ),
			'body'       => $this->convert_body( isset( $mw_settings['mail_content'] ) ? (string) $mw_settings['mail_content'] : '' ),
			'from_email' => $from_email,
			'from_name'  => $from_name,
			'reply_to'   => isset( $mw_settings['mail_reply_to'] ) ? (string) $mw_settings['mail_reply_to'] : '',
		);
	}

	/**
	 * Converts mail tags in an email body or subject from MW WP Form notation to Form Plant notation.
	 *
	 * 1. For each (jp_name => en_name) pair from translator.get_map(): {jp_name} → {field:en_name}
	 * 2. Tags already in alphanumeric form (e.g. {email}) are left as-is (Form Plant backward-compatible notation)
	 * 3. MW-specific system tags (e.g. {post_title}) trigger a warning only
	 *
	 * @param string $body Email body or subject string.
	 * @return string Converted string.
	 */
	public function convert_body( $body ) {
		$body = (string) $body;
		if ( '' === $body ) {
			return '';
		}

		foreach ( $this->translator->get_map() as $jp_name => $en_name ) {
			if ( $jp_name === $en_name ) {
				continue;
			}
			$body = str_replace( '{' . $jp_name . '}', '{field:' . $en_name . '}', $body );
		}

		$mw_specific = array( 'post_title', 'post_id', 'post_url', 'user_login', 'user_email' );
		foreach ( $mw_specific as $tag ) {
			if ( false !== strpos( $body, '{' . $tag . '}' ) ) {
				$this->logger->add_warning(
					FPLANT_Migrator_Base::LEVEL_WARNING,
					'url_param_tag_in_body',
					sprintf(
						/* translators: %s: tag name. */
						__( 'The email body contains the "{%s}" tag. Please enable the URL parameter feature in Form Plant and create a corresponding hidden field.', 'form-plant' ),
						$tag
					),
					array( 'tag' => $tag )
				);
			}
		}

		// Warn about any remaining {tag} that will not resolve at send time
		// (neither {field:...}, a known field name, nor a Form Plant system tag).
		// Such tags are left verbatim in the sent email.
		$this->warn_unresolved_tags( $body, $mw_specific );

		return $body;
	}

	/**
	 * Warns about merge tags in the body that will not resolve when the email is sent.
	 *
	 * A {tag} resolves if it is a {field:name} tag, a known field name {name}
	 * (Form Plant's backward-compatible notation), or a Form Plant system tag.
	 * Anything else is left unchanged in the sent email, so it is surfaced as a
	 * warning. Tags already reported with a more specific message (the MW-specific
	 * tags) are skipped here to avoid duplicate warnings.
	 *
	 * @param string $body        Converted body or subject.
	 * @param array  $mw_specific MW-specific tags already warned about separately.
	 */
	private function warn_unresolved_tags( $body, array $mw_specific ) {
		if ( ! preg_match_all( '/\{([^{}]+)\}/', $body, $matches ) ) {
			return;
		}

		$known_field_names = array_values( $this->translator->get_map() );

		foreach ( $matches[1] as $tag ) {
			if ( 0 === strpos( $tag, 'field:' ) ) {
				continue;
			}
			if ( in_array( $tag, $known_field_names, true ) ) {
				continue;
			}
			if ( in_array( $tag, self::$system_tags, true ) ) {
				continue;
			}
			if ( in_array( $tag, $mw_specific, true ) ) {
				continue;
			}
			if ( isset( $this->warned_unresolved[ $tag ] ) ) {
				continue;
			}
			$this->warned_unresolved[ $tag ] = true;

			$this->logger->add_warning(
				FPLANT_Migrator_Base::LEVEL_WARNING,
				'unresolved_mail_tag',
				sprintf(
					/* translators: %s: tag name. */
					__( 'The email body contains the tag "{%s}", which does not match any field. It will be left unchanged in the sent email; please remove it or point it at a field.', 'form-plant' ),
					$tag
				),
				array( 'tag' => $tag )
			);
		}
	}

	/**
	 * Parses a "Name <email@example.com>" string or a plain email address.
	 *
	 * @param string $from_string From header string.
	 * @return array{0:string, 1:string} Array in [email, name] format.
	 */
	public function parse_from_header( $from_string ) {
		$from_string = trim( (string) $from_string );
		if ( '' === $from_string ) {
			return array( '', '' );
		}

		if ( preg_match( '/^\s*(.*?)\s*<\s*([^>]+)\s*>\s*$/u', $from_string, $m ) ) {
			$name  = trim( $m[1], " \t\n\r\0\x0B\"" );
			$email = trim( $m[2] );
			return array( $email, $name );
		}

		return array( $from_string, '' );
	}
}
