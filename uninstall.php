<?php
/**
 * Clean up plugin data on uninstall.
 *
 * @package LLMs_Txt_Generator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'llms_txt_settings' );
delete_option( 'llms_txt_bot_log' );
delete_transient( 'llms_txt_cache' );
delete_transient( 'llms_txt_full_cache' );
