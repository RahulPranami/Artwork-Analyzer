<?php
/**
 * Rate Limiter for public analysis requests.
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Rate_Limiter {

    /**
     * Check if the current request is allowed under rate limits.
     *
     * @return true|WP_Error
     */
    public static function check_limit() {
        // Administrators bypass rate limiting
        if (current_user_can('manage_options')) {
            return true;
        }

        $max_requests = (int)WPA_Art_Analyzer_Settings::get('rate_limit_requests', 3);
        $window       = (int)WPA_Art_Analyzer_Settings::get('rate_limit_window', 600);

        if ($max_requests <= 0) {
            return true; // Disabled
        }

        $ip_hash       = WPA_Art_Analyzer_Security::get_client_ip_hash();
        $transient_key = 'wpa_rate_' . substr($ip_hash, 0, 32);

        $current_count = (int)get_transient($transient_key);

        if ($current_count >= $max_requests) {
            return new WP_Error(
                'RATE_LIMITED',
                sprintf(
                    /* translators: 1: maximum allowed request count, 2: rate limit duration in minutes */
                    __('Rate limit exceeded. You may only perform %1$d artwork analyses per %2$d minutes. Please wait before trying again.', 'wp-artwork-analyzer'),
                    $max_requests,
                    round($window / 60)
                ),
                ['status' => 429]
            );
        }

        return true;
    }

    /**
     * Increment the request count for the current client.
     */
    public static function increment() {
        if (current_user_can('manage_options')) {
            return;
        }

        $max_requests = (int)WPA_Art_Analyzer_Settings::get('rate_limit_requests', 3);
        $window       = (int)WPA_Art_Analyzer_Settings::get('rate_limit_window', 600);

        if ($max_requests <= 0) {
            return;
        }

        $ip_hash       = WPA_Art_Analyzer_Security::get_client_ip_hash();
        $transient_key = 'wpa_rate_' . substr($ip_hash, 0, 32);

        $current_count = (int)get_transient($transient_key);
        if ($current_count <= 0) {
            set_transient($transient_key, 1, $window);
        } else {
            set_transient($transient_key, $current_count + 1, $window);
        }
    }
}
