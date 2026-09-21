<?php
/**
 * Admin management and settings page for WP Artwork Analyzer.
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Admin {

    /**
     * Initialize admin hooks.
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_save_settings']);
    }

    /**
     * Add admin menu.
     */
    public function add_menu_page() {
        add_menu_page(
            __('Artwork Analyzer', 'wp-artwork-analyzer'),
            __('Artwork Analyzer', 'wp-artwork-analyzer'),
            'manage_options',
            'wp-artwork-analyzer',
            [$this, 'render_settings_page'],
            'dashicons-art',
            58
        );
    }

    /**
     * Enqueue admin styles and diagnostic scripts.
     *
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_wp-artwork-analyzer') {
            return;
        }

        wp_enqueue_style(
            'wpa-admin-css',
            WPA_ART_ANALYZER_URL . 'admin/css/admin.css',
            [],
            WPA_ART_ANALYZER_VERSION
        );

        wp_enqueue_script(
            'wpa-admin-js',
            WPA_ART_ANALYZER_URL . 'admin/js/admin.js',
            ['jquery'],
            WPA_ART_ANALYZER_VERSION,
            true
        );

        wp_localize_script('wpa-admin-js', 'wpaAdminConfig', [
            'restUrl' => esc_url_raw(rest_url('art-analyzer/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Handle saving settings form submission.
     */
    public function handle_save_settings() {
        if (!isset($_POST['wpa_save_settings'])) {
            return;
        }

        check_admin_referer('wpa_save_settings_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'wp-artwork-analyzer'));
        }

        $new_settings = [
            'ai_provider'         => sanitize_text_field($_POST['ai_provider'] ?? 'gemini'),
            'gemini_api_key'      => sanitize_text_field($_POST['gemini_api_key'] ?? ''),
            'model'               => sanitize_text_field($_POST['model'] ?? 'gemini-3.5-flash-lite'),
            'max_tokens'          => max(256, (int)($_POST['max_tokens'] ?? 2048)),
            'temperature'         => floatval($_POST['temperature'] ?? 0.4),
            'max_dimension'       => max(512, (int)($_POST['max_dimension'] ?? 2048)),
            'max_file_size'       => max(1, floatval($_POST['max_file_size'] ?? 10)),
            'cf7_form_id'         => (int)($_POST['cf7_form_id'] ?? 0),
            'rate_limit_requests' => (int)($_POST['rate_limit_requests'] ?? 3),
            'rate_limit_window'   => max(60, (int)($_POST['rate_limit_window'] ?? 600)),
            'turnstile_enabled'   => isset($_POST['turnstile_enabled']) ? 1 : 0,
            'turnstile_site_key'  => sanitize_text_field($_POST['turnstile_site_key'] ?? ''),
            'turnstile_secret_key'=> sanitize_text_field($_POST['turnstile_secret_key'] ?? ''),
            'save_analysis'       => isset($_POST['save_analysis']) ? 1 : 0,
            'save_images'         => isset($_POST['save_images']) ? 1 : 0,
            'system_prompt'       => sanitize_textarea_field($_POST['system_prompt'] ?? ''),
        ];

        WPA_Art_Analyzer_Settings::update($new_settings);

        add_settings_error('wpa_messages', 'wpa_settings_updated', __('Settings saved successfully.', 'wp-artwork-analyzer'), 'updated');
    }

    /**
     * Render the admin view.
     */
    public function render_settings_page() {
        include WPA_ART_ANALYZER_PATH . 'admin/views/settings.php';
    }
}
