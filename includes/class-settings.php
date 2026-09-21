<?php
/**
 * Settings management for WP Artwork Analyzer.
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Settings {

    const OPTION_KEY = 'wpa_art_analyzer_settings';

    /**
     * Cached settings array.
     *
     * @var array|null
     */
    private static $settings = null;

    /**
     * Default configuration.
     *
     * @return array
     */
    public static function get_defaults() {
        return [
            'ai_provider'         => 'gemini',
            'gemini_api_key'      => '',
            'model'               => 'gemini-3.5-flash',
            'max_tokens'          => 2048,
            'temperature'         => 0.4,
            'max_dimension'       => 2048,
            'max_file_size'       => 10, // in MB
            'cf7_form_id'         => 0,
            'rate_limit_requests' => 3,
            'rate_limit_window'   => 600, // in seconds (10 minutes)
            'turnstile_enabled'   => 0,
            'turnstile_site_key'  => '',
            'turnstile_secret_key'=> '',
            'save_analysis'       => 0,
            'save_images'         => 0,
            'system_prompt'       => self::get_default_prompt(),
        ];
    }

    /**
     * Master art analysis system instruction.
     *
     * @return string
     */
    public static function get_default_prompt() {
        return "You are an expert visual-art analysis assistant.\n\n" .
            "Analyze the uploaded artwork carefully.\n\n" .
            "Your response must distinguish between:\n" .
            "1. What can be directly observed.\n" .
            "2. What can reasonably be inferred.\n" .
            "3. What cannot be reliably determined.\n\n" .
            "Never invent an artist, title, date, provenance, ownership, museum, historical event, or cultural context.\n\n" .
            "If identification is uncertain, explicitly say so.\n\n" .
            "Analyze the artwork using these sections:\n\n" .
            "## Artwork Overview\n" .
            "Describe what is visibly present.\n\n" .
            "## Possible Identification\n" .
            "If the artwork appears recognizable, provide:\n" .
            "- possible title\n" .
            "- possible artist\n" .
            "- approximate period\n" .
            "- confidence level (High / Medium / Low / Unknown)\n\n" .
            "Do not claim identification as fact unless the visual evidence supports it strongly.\n\n" .
            "## Subject Matter\n" .
            "Explain what the artwork depicts.\n\n" .
            "## Composition\n" .
            "Analyze:\n" .
            "- layout\n" .
            "- focal point\n" .
            "- perspective\n" .
            "- balance\n" .
            "- symmetry/asymmetry\n" .
            "- depth\n\n" .
            "## Color Analysis\n" .
            "Discuss:\n" .
            "- dominant colors\n" .
            "- secondary colors\n" .
            "- contrast\n" .
            "- saturation\n" .
            "- temperature\n\n" .
            "## Technique and Style\n" .
            "Discuss visible:\n" .
            "- brushwork\n" .
            "- texture\n" .
            "- line\n" .
            "- shape\n" .
            "- rendering\n" .
            "- artistic style\n\n" .
            "## Mood and Emotional Qualities\n" .
            "Describe the visual mood without making claims about the artist's psychological state.\n\n" .
            "## Symbolism\n" .
            "Identify possible symbols and explain that interpretations are speculative when appropriate.\n\n" .
            "## Meaning and Interpretation\n" .
            "Provide plausible interpretations based on visible evidence.\n\n" .
            "## Historical or Cultural Context\n" .
            "Only provide context when reasonably supported by the image or reliable identification.\n\n" .
            "## Interesting Details\n" .
            "List notable visual details a viewer may miss.\n\n" .
            "## Summary\n" .
            "Give a concise overall explanation.\n\n" .
            "Important: Never present speculation as established fact.";
    }

    /**
     * Get all settings merged with defaults.
     *
     * @return array
     */
    public static function get_all() {
        if (self::$settings === null) {
            $saved = get_option(self::OPTION_KEY, []);
            if (!is_array($saved)) {
                $saved = [];
            }
            self::$settings = wp_parse_args($saved, self::get_defaults());
        }
        return self::$settings;
    }

    /**
     * Get a specific setting value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null) {
        $settings = self::get_all();
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        return $default;
    }

    /**
     * Update settings.
     *
     * @param array $new_settings
     * @return bool
     */
    public static function update($new_settings) {
        $current = self::get_all();
        $updated = array_merge($current, $new_settings);
        self::$settings = $updated;
        return update_option(self::OPTION_KEY, $updated);
    }

    /**
     * Determine active Gemini API Key with precedence:
     * 1. Constant WPA_GEMINI_API_KEY
     * 2. Constant GEMINI_API_KEY
     * 3. Environment variable GEMINI_API_KEY or WPA_GEMINI_API_KEY
     * 4. .env file in ABSPATH or document root
     * 5. Saved setting in database
     *
     * @return string
     */
    public static function get_gemini_api_key() {
        // 1. PHP Constants
        if (defined('WPA_GEMINI_API_KEY') && !empty(constant('WPA_GEMINI_API_KEY'))) {
            return trim(constant('WPA_GEMINI_API_KEY'));
        }
        if (defined('GEMINI_API_KEY') && !empty(constant('GEMINI_API_KEY'))) {
            return trim(constant('GEMINI_API_KEY'));
        }

        // 2. getenv / $_ENV
        $env_key = getenv('GEMINI_API_KEY') ?: getenv('WPA_GEMINI_API_KEY');
        if (!empty($env_key)) {
            return trim($env_key);
        }
        if (!empty($_ENV['GEMINI_API_KEY'])) {
            return trim($_ENV['GEMINI_API_KEY']);
        }
        if (!empty($_ENV['WPA_GEMINI_API_KEY'])) {
            return trim($_ENV['WPA_GEMINI_API_KEY']);
        }

        // 3. Check .env file directly
        $env_paths = [
            ABSPATH . '.env',
            dirname(ABSPATH) . '/.env',
            WPA_ART_ANALYZER_PATH . '.env',
        ];

        foreach ($env_paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line) || str_starts_with($line, '#')) {
                            continue;
                        }
                        if (preg_match('/^(?:GEMINI_API_KEY|WPA_GEMINI_API_KEY)\s*=\s*(["\']?)(.*)\1$/', $line, $matches)) {
                            if (!empty($matches[2])) {
                                return trim($matches[2]);
                            }
                        }
                    }
                }
            }
        }

        // 4. Saved setting in database
        $saved = self::get('gemini_api_key', '');
        return trim((string)$saved);
    }

    /**
     * Check if the API key is provided via environment/constant.
     *
     * @return bool
     */
    public static function is_api_key_from_env() {
        if (defined('WPA_GEMINI_API_KEY') && !empty(constant('WPA_GEMINI_API_KEY'))) {
            return true;
        }
        if (defined('GEMINI_API_KEY') && !empty(constant('GEMINI_API_KEY'))) {
            return true;
        }
        if (getenv('GEMINI_API_KEY') || getenv('WPA_GEMINI_API_KEY')) {
            return true;
        }
        if (!empty($_ENV['GEMINI_API_KEY']) || !empty($_ENV['WPA_GEMINI_API_KEY'])) {
            return true;
        }

        // Check .env file
        $env_paths = [
            ABSPATH . '.env',
            dirname(ABSPATH) . '/.env',
        ];
        foreach ($env_paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $content = file_get_contents($path);
                if ($content !== false && (str_contains($content, 'GEMINI_API_KEY=') || str_contains($content, 'WPA_GEMINI_API_KEY='))) {
                    return true;
                }
            }
        }
        return false;
    }
}
