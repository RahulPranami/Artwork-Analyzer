<?php
/**
 * Contact Form 7 integration for WP Artwork Analyzer.
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_CF7_Integration {

    /**
     * Initialize CF7 hooks.
     */
    public function init() {
        // Register shortcode
        add_shortcode('wp_artwork_analyzer', [$this, 'render_shortcode']);

        // Add hidden fields and container to CF7 forms
        add_filter('wpcf7_form_elements', [$this, 'inject_form_elements']);

        // Enqueue frontend scripts conditionally
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Fix upload tmp dir if hosting environment has read-only uploads directory (e.g. systemd ProtectHome)
        add_filter('wpcf7_upload_dir', [$this, 'fix_upload_dir_if_not_writable']);
    }

    /**
     * Ensure Contact Form 7 has a writable temporary directory for file processing.
     *
     * @param array $uploads
     * @return array
     */
    public function fix_upload_dir_if_not_writable($uploads) {
        if (!wp_is_writable($uploads['dir'])) {
            $uploads['dir'] = get_temp_dir();
        }
        return $uploads;
    }

    /**
     * Check if Contact Form 7 is installed and activated.
     *
     * @return bool
     */
    public static function is_cf7_active() {
        return defined('WPCF7_VERSION') || class_exists('WPCF7');
    }

    /**
     * Get list of all Contact Form 7 forms.
     *
     * @return array [id => title]
     */
    public static function get_forms_list() {
        if (!self::is_cf7_active()) {
            return [];
        }

        $forms = get_posts([
            'post_type'      => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        $list = [];
        foreach ($forms as $form) {
            $list[$form->ID] = $form->post_title . " (ID: {$form->ID})";
        }

        return $list;
    }

    /**
     * Check if a specific form is our targeted analyzer form.
     *
     * @param int|string $form_id
     * @return bool
     */
    public static function is_target_form($form_id) {
        $configured_id = (int)WPA_Art_Analyzer_Settings::get('cf7_form_id', 0);
        if ($configured_id > 0) {
            return (int)$form_id === $configured_id;
        }

        // If not set, check if form contains artwork field
        return true;
    }

    /**
     * Inject hidden fields and analyzer output container into CF7 form elements.
     *
     * @param string $content
     * @return string
     */
    public function inject_form_elements($content) {
        $current_form = wpcf7_get_current_contact_form();
        if (!$current_form) {
            return $content;
        }

        $form_id = $current_form->id();
        if (!self::is_target_form($form_id)) {
            return $content;
        }

        // Hidden fields for analysis tracking
        $hidden_fields = '<input type="hidden" name="analysis-status" id="analysis-status" value="idle" />' . "\n" .
                         '<input type="hidden" name="analysis-id" id="analysis-id" value="" />' . "\n";

        // Load analyzer output template
        ob_start();
        include WPA_ART_ANALYZER_PATH . 'templates/analyzer-output.php';
        $output_template = ob_get_clean();

        return $content . $hidden_fields . $output_template;
    }

    /**
     * Render standalone shortcode [wp_artwork_analyzer].
     *
     * @param array $atts
     * @return string
     */
    public function render_shortcode($atts) {
        wp_enqueue_script('wpa-artwork-analyzer');
        wp_enqueue_style('wpa-artwork-analyzer');

        ob_start();
        include WPA_ART_ANALYZER_PATH . 'templates/analyzer-output.php';
        return ob_get_clean();
    }

    /**
     * Enqueue public scripts and styles.
     */
    public function enqueue_assets() {
        wp_register_style(
            'wpa-artwork-analyzer',
            WPA_ART_ANALYZER_URL . 'public/css/artwork-analyzer.css',
            [],
            WPA_ART_ANALYZER_VERSION
        );

        wp_register_script(
            'wpa-artwork-analyzer',
            WPA_ART_ANALYZER_URL . 'public/js/artwork-analyzer.js',
            ['jquery'],
            WPA_ART_ANALYZER_VERSION,
            true
        );

        $turnstile_enabled = (int)WPA_Art_Analyzer_Settings::get('turnstile_enabled', 0);
        $turnstile_key     = WPA_Art_Analyzer_Settings::get('turnstile_site_key', '');

        if ($turnstile_enabled && !empty($turnstile_key)) {
            wp_enqueue_script('cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', [], null, true);
        }

        wp_localize_script('wpa-artwork-analyzer', 'wpaArtworkAnalyzerConfig', [
            'restUrl'          => esc_url_raw(rest_url('art-analyzer/v1/')),
            'nonce'            => wp_create_nonce('wp_rest'),
            'configuredFormId' => (int)WPA_Art_Analyzer_Settings::get('cf7_form_id', 0),
            'maxFileSize'      => (float)WPA_Art_Analyzer_Settings::get('max_file_size', 10),
            'turnstileEnabled' => (bool)$turnstile_enabled,
            'turnstileSiteKey' => esc_attr($turnstile_key),
            'i18n'             => [
                'analyzing'        => __('Analyzing artwork...', 'wp-artwork-analyzer'),
                'stopAnalysis'     => __('Stop Analysis', 'wp-artwork-analyzer'),
                'analyzeAnother'   => __('Analyze Another Artwork', 'wp-artwork-analyzer'),
                'tryAgain'         => __('Try Again', 'wp-artwork-analyzer'),
                'preparing'        => __('Preparing artwork analysis...', 'wp-artwork-analyzer'),
                'uploading'        => __('Optimizing & uploading image...', 'wp-artwork-analyzer'),
                'streaming'        => __('Generating live critique...', 'wp-artwork-analyzer'),
                'complete'         => __('Artwork analysis complete.', 'wp-artwork-analyzer'),
                'aborted'          => __('Analysis was stopped.', 'wp-artwork-analyzer'),
                'fileTooLarge'     => __('File exceeds maximum allowed size.', 'wp-artwork-analyzer'),
                'invalidFormat'    => __('Only JPG, PNG, and WEBP files are supported.', 'wp-artwork-analyzer'),
                'noFile'           => __('Please select an artwork image to analyze.', 'wp-artwork-analyzer'),
                'chooseFile'        => __('Choose Image File', 'wp-artwork-analyzer'),
                'takePhoto'         => __('Take Photo', 'wp-artwork-analyzer'),
                'captureArtwork'    => __('Capture Artwork with Camera', 'wp-artwork-analyzer'),
                'snapPhoto'         => __('Snap Photo', 'wp-artwork-analyzer'),
                'cancel'            => __('Cancel', 'wp-artwork-analyzer'),
                'remove'            => __('Remove', 'wp-artwork-analyzer'),
                'cameraUnavailable' => __('Camera not available or access denied. Please choose a file instead.', 'wp-artwork-analyzer'),
            ],
        ]);

        // Automatically enqueue on singular pages or always if target form might be present
        wp_enqueue_style('wpa-artwork-analyzer');
        wp_enqueue_script('wpa-artwork-analyzer');
    }
}
