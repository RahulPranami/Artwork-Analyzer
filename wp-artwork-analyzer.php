<?php
/**
 * Plugin Name: WP Artwork Analyzer
 * Plugin URI: https://wordpress.org/plugins/wp-artwork-analyzer/
 * Description: AI-powered artwork analysis with realtime streaming responses using Google Gemini and Contact Form 7.
 * Version: 1.0.1
 * Author: Antigravity
 * Author URI: https://wordpress.org/plugins/wp-artwork-analyzer/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-artwork-analyzer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('WPA_ART_ANALYZER_VERSION', '1.0.1');
define('WPA_ART_ANALYZER_PATH', plugin_dir_path(__FILE__));
define('WPA_ART_ANALYZER_URL', plugin_dir_url(__FILE__));
define('WPA_ART_ANALYZER_BASENAME', plugin_basename(__FILE__));

// Require the main plugin container
require_once WPA_ART_ANALYZER_PATH . 'includes/class-plugin.php';

/**
 * Main instance helper.
 *
 * @return WPA_Art_Analyzer_Plugin
 */
function wpa_art_analyzer() {
    return WPA_Art_Analyzer_Plugin::instance();
}

// Activation and deactivation hooks
register_activation_hook(__FILE__, ['WPA_Art_Analyzer_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['WPA_Art_Analyzer_Plugin', 'deactivate']);

// Initialize the plugin
add_action('plugins_loaded', 'wpa_art_analyzer');
