<?php
/**
 * Reverse Image Search Factory.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Reverse_Search_Factory {

    /**
     * Get registered provider instances.
     *
     * @return WPA_Art_Analyzer_Reverse_Search_Provider[]
     */
    public static function get_providers(): array {
        return [
            'google_vision' => new WPA_Art_Analyzer_Google_Vision_Search(),
            'serpapi'       => new WPA_Art_Analyzer_Serpapi_Search(),
        ];
    }

    /**
     * Get the active configured provider instance.
     *
     * @return WPA_Art_Analyzer_Reverse_Search_Provider|null
     */
    public static function get_active_provider() {
        $selected = WPA_Art_Analyzer_Settings::get('reverse_search_provider', 'google_vision');
        if ($selected === 'none' || empty($selected)) {
            return null;
        }

        $providers = self::get_providers();
        if (isset($providers[$selected])) {
            return $providers[$selected];
        }

        // Default to Google Vision
        return $providers['google_vision'] ?? null;
    }
}
