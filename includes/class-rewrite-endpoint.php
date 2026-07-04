<?php
/**
 * Base class for virtual rewrite-endpoint routes (embed, preview).
 *
 * Subclasses declare the rewrite regex, the query var, and the render handler; this base
 * wires the init / query_vars / template_redirect hooks, registers the rewrite rule, and
 * provides the activation flush helper — so the endpoint boilerplate is not duplicated.
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Rewrite_Endpoint base class.
 */
abstract class FPLANT_Rewrite_Endpoint {

	/**
	 * Constructor — wire the shared hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ), $this->render_priority() );
	}

	/**
	 * Rewrite regex with a single numeric capture, e.g. '^fplant-embed/([0-9]+)/?$'.
	 *
	 * @return string
	 */
	abstract protected function rewrite_regex();

	/**
	 * Query var the captured id maps to, e.g. 'fplant_embed_form'.
	 *
	 * @return string
	 */
	abstract protected function query_var();

	/**
	 * Handle the request on template_redirect. Implementations inspect get_query_var()
	 * and return early when the request is not for this endpoint.
	 */
	abstract public function maybe_render();

	/**
	 * template_redirect hook priority. Override to run earlier/later.
	 *
	 * @return int
	 */
	protected function render_priority() {
		return 10;
	}

	/**
	 * Register the rewrite rule mapping the captured id to the query var.
	 */
	public function register_endpoint() {
		add_rewrite_rule( $this->rewrite_regex(), 'index.php?' . $this->query_var() . '=$matches[1]', 'top' );
	}

	/**
	 * Add the endpoint's query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = $this->query_var();
		return $vars;
	}

	/**
	 * Register the rule and flush rewrite rules. Called on activation (and upgrade).
	 */
	public static function flush_rewrite_rules() {
		$instance = new static();
		$instance->register_endpoint();
		flush_rewrite_rules();
	}
}
