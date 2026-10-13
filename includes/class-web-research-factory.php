<?php
/**
 * Web Research Factory.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Web_Research_Factory {

    /**
     * Get registered web research provider instances.
     *
     * @return WPA_Art_Analyzer_Web_Research_Provider[]
     */
    public static function get_providers(): array {
        return [
            'wikipedia'     => new WPA_Art_Analyzer_Wikipedia_Research(),
            'google_search' => new WPA_Art_Analyzer_Google_Custom_Search(),
            'tavily'        => new WPA_Art_Analyzer_Tavily_Research(),
        ];
    }

    /**
     * Get active configured provider instance.
     *
     * @return WPA_Art_Analyzer_Web_Research_Provider|null
     */
    public static function get_active_provider() {
        $selected = WPA_Art_Analyzer_Settings::get('web_research_provider', 'wikipedia');
        if ($selected === 'none' || empty($selected)) {
            return null;
        }

        $providers = self::get_providers();
        if (isset($providers[$selected])) {
            return $providers[$selected];
        }

        // Default to Wikipedia (built-in, free)
        return $providers['wikipedia'] ?? null;
    }
}
