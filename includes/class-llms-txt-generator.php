<?php
/**
 * Core controller: rewrite endpoints, request handling, cache invalidation.
 *
 * @package LLMs_Txt_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin controller (singleton).
 */
class LLMs_Txt_Generator {

	/**
	 * Singleton instance.
	 *
	 * @var LLMs_Txt_Generator|null
	 */
	private static $instance = null;

	/**
	 * Settings handler.
	 *
	 * @var LLMs_Txt_Settings
	 */
	private $settings;

	/**
	 * Bot logger.
	 *
	 * @var LLMs_Txt_Bot_Logger
	 */
	private $bot_logger;

	/**
	 * Get the singleton instance.
	 *
	 * @return LLMs_Txt_Generator
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up hooks.
	 */
	private function __construct() {
		$this->settings   = new LLMs_Txt_Settings();
		$this->bot_logger = new LLMs_Txt_Bot_Logger( $this->settings );

		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_file' ) );

		// Invalidate cache whenever content changes.
		add_action( 'save_post', array( $this, 'flush_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_cache' ) );
		add_action( 'update_option_' . LLMS_TXT_OPTION, array( $this, 'flush_cache' ) );

		$this->settings->init();
		$this->bot_logger->init();
	}

	/**
	 * Register the /llms.txt and /llms-full.txt rewrite endpoints.
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?llms_txt=basic', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?llms_txt=full', 'top' );
	}

	/**
	 * Whitelist our query var.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'llms_txt';
		return $vars;
	}

	/**
	 * If the request is for one of our endpoints, render and exit.
	 */
	public function maybe_serve_file() {
		$mode = get_query_var( 'llms_txt' );
		if ( ! $mode ) {
			return;
		}

		$full = ( 'full' === $mode );

		// Hard off-switch via settings.
		if ( $full && ! $this->settings->get( 'enable_full', true ) ) {
			status_header( 404 );
			return;
		}

		$this->bot_logger->maybe_log( $full ? 'llms-full.txt' : 'llms.txt' );

		$output = $this->get_output( $full );

		nocache_headers();
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		status_header( 200 );
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text/markdown body.
		exit;
	}

	/**
	 * Get cached output, rebuilding if needed.
	 *
	 * @param bool $full Whether to return the full-content variant.
	 * @return string
	 */
	private function get_output( $full ) {
		$cache_key = $full ? LLMS_TXT_FULL_CACHE_KEY : LLMS_TXT_CACHE_KEY;
		$ttl       = (int) $this->settings->get( 'cache_ttl', 12 * HOUR_IN_SECONDS );

		if ( $ttl > 0 ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$renderer = new LLMs_Txt_Renderer( $this->settings );
		$output   = $full ? $renderer->render_full() : $renderer->render_basic();

		if ( $ttl > 0 ) {
			set_transient( $cache_key, $output, $ttl );
		}

		return $output;
	}

	/**
	 * Clear both cached files.
	 */
	public function flush_cache() {
		delete_transient( LLMS_TXT_CACHE_KEY );
		delete_transient( LLMS_TXT_FULL_CACHE_KEY );
	}

	/**
	 * Expose settings for other classes/tests.
	 *
	 * @return LLMs_Txt_Settings
	 */
	public function settings() {
		return $this->settings;
	}
}
