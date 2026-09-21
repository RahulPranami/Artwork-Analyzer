<?php
/**
 * Fired when the plugin is uninstalled.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Delete plugin settings
delete_option('wpa_art_analyzer_settings');

// Clean up transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpa_rate_%' OR option_name LIKE '_transient_timeout_wpa_rate_%'");

// Drop custom table
$table_name = $wpdb->prefix . 'artwork_analyses';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");
