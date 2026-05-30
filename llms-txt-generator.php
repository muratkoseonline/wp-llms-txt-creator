<?php
/**
 * Plugin Name:       LLMs.txt Generator
 * Plugin URI:        https://github.com/muratkoseonline/wp-llms-txt-creator
 * Description:       Generates and serves /llms.txt and /llms-full.txt for your site so AI models (ChatGPT, Claude, Gemini, Perplexity…) can understand your content with less effort. Respects Yoast / Rank Math noindex, caches output, and can log AI bot visits.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            Murat Köse
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       llms-txt-generator
 *
 * @package LLMs_Txt_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'LLMS_TXT_VERSION', '1.0.0' );
define( 'LLMS_TXT_FILE', __FILE__ );
define( 'LLMS_TXT_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLMS_TXT_URL', plugin_dir_url( __FILE__ ) );
define( 'LLMS_TXT_OPTION', 'llms_txt_settings' );
define( 'LLMS_TXT_CACHE_KEY', 'llms_txt_cache' );
define( 'LLMS_TXT_FULL_CACHE_KEY', 'llms_txt_full_cache' );

require_once LLMS_TXT_DIR . 'includes/class-llms-txt-settings.php';
require_once LLMS_TXT_DIR . 'includes/class-llms-txt-renderer.php';
require_once LLMS_TXT_DIR . 'includes/class-llms-txt-bot-logger.php';
require_once LLMS_TXT_DIR . 'includes/class-llms-txt-generator.php';

/**
 * Boot the plugin once all plugins are loaded.
 */
function llms_txt_bootstrap() {
	LLMs_Txt_Generator::instance();
}
add_action( 'plugins_loaded', 'llms_txt_bootstrap' );

/**
 * Activation: register rewrite rules then flush so the endpoints resolve.
 */
function llms_txt_activate() {
	LLMs_Txt_Generator::instance()->register_rewrite_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'llms_txt_activate' );

/**
 * Deactivation: drop our rewrite rules and clear caches.
 */
function llms_txt_deactivate() {
	delete_transient( LLMS_TXT_CACHE_KEY );
	delete_transient( LLMS_TXT_FULL_CACHE_KEY );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'llms_txt_deactivate' );
