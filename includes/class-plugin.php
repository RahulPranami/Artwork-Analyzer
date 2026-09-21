<?php
/**
 * Main Plugin Bootstrap Class.
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Plugin {

    /**
     * Singleton instance.
     *
     * @var WPA_Art_Analyzer_Plugin|null
     */
    private static $instance = null;

    /**
     * REST controller instance.
     *
     * @var WPA_Art_Analyzer_REST_Controller|null
     */
    public $rest_controller = null;

    /**
     * CF7 integration instance.
     *
     * @var WPA_Art_Analyzer_CF7_Integration|null
     */
    public $cf7_integration = null;

    /**
     * Admin instance.
     *
     * @var WPA_Art_Analyzer_Admin|null
     */
    public $admin = null;

    /**
     * Get or initialize singleton instance.
     *
     * @return WPA_Art_Analyzer_Plugin
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        self::load_dependencies();
        $this->init_components();
    }

    /**
     * Require all component classes.
     */
    public static function load_dependencies() {
        require_once WPA_ART_ANALYZER_PATH . 'includes/class-settings.php';
        require_once WPA_ART_ANALYZER_PATH . 'includes/class-security.php';
        require_once WPA_ART_ANALYZER_PATH . 'includes/class-rate-limiter.php';
        require_once WPA_ART_ANALYZER_PATH . 'includes/class-image-handler.php';
        require_once WPA_ART_ANALYZER_PATH . 'includes/class-gemini-client.php';
        require_once WPA_ART_ANALYZER_PATH . 'includes/class-rest-controller.php';
        require_once WPA_ART_ANALYZER_PATH . 'includes/class-cf7-integration.php';

        if (is_admin()) {
            require_once WPA_ART_ANALYZER_PATH . 'admin/class-admin.php';
        }
    }

    /**
     * Initialize components and hooks.
     */
    private function init_components() {
        // Initialize REST API
        $this->rest_controller = new WPA_Art_Analyzer_REST_Controller();
        add_action('rest_api_init', [$this->rest_controller, 'register_routes']);

        // Initialize CF7 integration
        $this->cf7_integration = new WPA_Art_Analyzer_CF7_Integration();
        $this->cf7_integration->init();

        // Initialize Admin
        if (is_admin()) {
            $this->admin = new WPA_Art_Analyzer_Admin();
            $this->admin->init();
        }
    }

    /**
     * Fired on plugin activation.
     */
    public static function activate() {
        self::load_dependencies();

        // Create custom database table if storage is enabled or for future use
        self::create_tables();

        // Set default settings if not exists
        if (get_option(WPA_Art_Analyzer_Settings::OPTION_KEY) === false) {
            update_option(WPA_Art_Analyzer_Settings::OPTION_KEY, WPA_Art_Analyzer_Settings::get_defaults());
        }

        // Flush rewrites for REST endpoints
        flush_rewrite_rules();
    }

    /**
     * Create custom analyses table.
     */
    public static function create_tables() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'artwork_analyses';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid varchar(64) NOT NULL DEFAULT '',
            form_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            email varchar(191) NOT NULL DEFAULT '',
            image_path text NOT NULL,
            image_hash varchar(64) NOT NULL DEFAULT '',
            provider varchar(32) NOT NULL DEFAULT 'gemini',
            model varchar(64) NOT NULL DEFAULT '',
            status varchar(32) NOT NULL DEFAULT 'pending',
            analysis longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            ip_hash varchar(64) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY uuid (uuid),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Fired on plugin deactivation.
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
