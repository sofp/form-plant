<?php
/**
 * Migrator Base
 *
 * Common base class for migration tools. Provides warning collection and report formatting.
 *
 * @package Form_Plant
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPLANT_Migrator_Base
 *
 * Future migration tools (e.g. Contact Form 7) should extend this same base class.
 *
 * @since 1.2.0
 */
abstract class FPLANT_Migrator_Base {

	const LEVEL_INFO    = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	/**
	 * Collected warning list.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $warnings = array();

	/**
	 * Add a warning.
	 *
	 * @param string $level   LEVEL_INFO / LEVEL_WARNING / LEVEL_ERROR.
	 * @param string $code    Short alphanumeric code (for UI categorisation).
	 * @param string $message User-facing message.
	 * @param array  $context Supplementary data (must be JSON-serialisable).
	 */
	public function add_warning( $level, $code, $message, $context = array() ) {
		$this->warnings[] = array(
			'level'   => $level,
			'code'    => $code,
			'message' => $message,
			'context' => $context,
		);
	}

	/**
	 * Return the collected warnings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_warnings() {
		return $this->warnings;
	}

	/**
	 * Clear the warning list.
	 */
	public function clear_warnings() {
		$this->warnings = array();
	}

	/**
	 * Run the migration.
	 *
	 * @param int $source_id Form ID on the source plugin side.
	 * @return array{form_id:int|null, warnings:array, status:string} Result.
	 */
	abstract public function migrate( $source_id );
}
