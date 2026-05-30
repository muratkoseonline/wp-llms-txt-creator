<?php
/**
 * Optional, privacy-light logging of requests to the generated files.
 *
 * Stores at most 100 of the most recent hits (time, file, user agent) in a
 * single option. No IP addresses are stored.
 *
 * @package LLMs_Txt_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight request logger.
 */
class LLMs_Txt_Bot_Logger {

	const LOG_OPTION = 'llms_txt_bot_log';
	const MAX_ROWS   = 100;

	/**
	 * Settings handler.
	 *
	 * @var LLMs_Txt_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param LLMs_Txt_Settings $settings Settings handler.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Nothing to hook today; reserved for future scheduled pruning.
	 */
	public function init() {}

	/**
	 * Record a hit if logging is enabled.
	 *
	 * @param string $file Which file was served.
	 */
	public function maybe_log( $file ) {
		if ( ! $this->settings->get( 'log_bots', 0 ) ) {
			return;
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '(none)';

		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'time' => gmdate( 'Y-m-d H:i:s' ),
			'file' => $file,
			'ua'   => mb_substr( $ua, 0, 300 ),
		);

		if ( count( $log ) > self::MAX_ROWS ) {
			$log = array_slice( $log, -self::MAX_ROWS );
		}

		update_option( self::LOG_OPTION, $log, false );
	}
}
