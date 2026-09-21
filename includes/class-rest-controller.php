<?php
/**
 * REST API Controller for WP Artwork Analyzer.
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_REST_Controller {

    const NAMESPACE = 'art-analyzer/v1';

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Public endpoint for artwork analysis
        register_rest_route(self::NAMESPACE, '/analyze', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_analyze'],
            'permission_callback' => '__return_true',
        ]);

        // Health check endpoint
        register_rest_route(self::NAMESPACE, '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_health'],
            'permission_callback' => '__return_true',
        ]);

        // Admin diagnostics: streaming test
        register_rest_route(self::NAMESPACE, '/stream-test', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_stream_test'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Admin diagnostics: Gemini connection test
        register_rest_route(self::NAMESPACE, '/gemini-test', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_gemini_test'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);
    }

    /**
     * Check if user is an administrator.
     *
     * @return bool
     */
    public function admin_permission_check() {
        return current_user_can('manage_options');
    }

    /**
     * Handle the main artwork streaming analysis request.
     *
     * @param WP_REST_Request $request
     */
    public function handle_analyze(WP_REST_Request $request) {
        // 1. Rate limiting check
        $rate_check = WPA_Art_Analyzer_Rate_Limiter::check_limit();
        if (is_wp_error($rate_check)) {
            $this->send_early_error_response($rate_check->get_error_code(), $rate_check->get_error_message(), 429);
            return;
        }

        // 2. CAPTCHA verification (Cloudflare Turnstile)
        $turnstile_token = $request->get_param('cf_turnstile_response') ?: ($request->get_param('turnstile_token') ?: '');
        $captcha_check   = WPA_Art_Analyzer_Security::verify_turnstile($turnstile_token);
        if (is_wp_error($captcha_check)) {
            $this->send_early_error_response($captcha_check->get_error_code(), $captcha_check->get_error_message(), 403);
            return;
        }

        // 3. Image validation and preprocessing
        $file_entry = !empty($_FILES['artwork']) ? $_FILES['artwork'] : null;
        $image_data = WPA_Art_Analyzer_Image_Handler::process_upload($file_entry);
        if (is_wp_error($image_data)) {
            $this->send_early_error_response($image_data->get_error_code(), $image_data->get_error_message(), 400);
            return;
        }

        // 4. Register rate limit count
        WPA_Art_Analyzer_Rate_Limiter::increment();

        // 5. Prepare streaming headers & buffer flush
        $this->prepare_stream_environment();

        $uuid          = wp_generate_uuid4();
        $full_analysis = '';
        $form_id       = (int)$request->get_param('form_id');
        $user_email    = sanitize_email($request->get_param('your-email') ?? '');

        // Emit start event
        $this->send_sse_event('start', ['status' => 'started', 'uuid' => $uuid]);

        // Callback for generated text chunks
        $on_text = function ($text) use (&$full_analysis) {
            $full_analysis .= $text;
            $this->send_sse_event('text', ['text' => $text]);
        };

        // Callback for stream errors
        $on_error = function ($code, $message) {
            $this->send_sse_event('error', ['code' => $code, 'message' => $message]);
        };

        // Execute streaming analysis with Gemini
        $success = WPA_Art_Analyzer_Gemini_Client::stream_analysis($image_data, $on_text, $on_error);

        if ($success) {
            // Send complete event
            $this->send_sse_event('done', ['status' => 'complete', 'uuid' => $uuid]);

            // Save to database if storage is enabled
            if ((int)WPA_Art_Analyzer_Settings::get('save_analysis', 0)) {
                $this->save_analysis_record([
                    'uuid'        => $uuid,
                    'form_id'     => $form_id,
                    'user_id'     => get_current_user_id(),
                    'email'       => $user_email,
                    'image_path'  => $image_data['stored_path'],
                    'image_hash'  => $image_data['image_hash'],
                    'provider'    => 'gemini',
                    'model'       => WPA_Art_Analyzer_Settings::get('model', 'gemini-3.5-flash-lite'),
                    'status'      => 'completed',
                    'analysis'    => $full_analysis,
                    'ip_hash'     => WPA_Art_Analyzer_Security::get_client_ip_hash(),
                ]);
            }
        }

        // Cleanup temporary files
        WPA_Art_Analyzer_Image_Handler::cleanup();

        exit;
    }

    /**
     * Send an immediate non-streaming error (e.g. rate limit or bad image before stream begins).
     *
     * @param string $code
     * @param string $message
     * @param int $status_code
     */
    private function send_early_error_response($code, $message, $status_code = 400) {
        $this->prepare_stream_environment($status_code);
        $this->send_sse_event('error', [
            'code'    => $code,
            'message' => $message,
        ]);
        exit;
    }

    /**
     * Prepare environment and send streaming headers.
     *
     * @param int $http_code
     */
    private function prepare_stream_environment($http_code = 200) {
        // Disable output buffering
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Disable script timeouts
        if (function_exists('set_time_limit')) {
            set_time_limit(180);
        }
        if (function_exists('ini_set')) {
            ini_set('zlib.output_compression', 'Off');
            ini_set('output_buffering', 'Off');
            ini_set('implicit_flush', '1');
        }

        // Send streaming headers
        status_header($http_code);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Initial padding for proxies that buffer small packets
        echo ":" . str_repeat(" ", 1024) . "\n\n";
        flush();
    }

    /**
     * Send an SSE event packet and flush immediately.
     *
     * @param string $event
     * @param array $data
     */
    private function send_sse_event($event, $data) {
        echo "event: {$event}\n";
        echo "data: " . wp_json_encode($data) . "\n\n";
        flush();
    }

    /**
     * Diagnostic stream test endpoint.
     * Progressively streams 5 chunks with 500ms intervals.
     */
    public function handle_stream_test() {
        $this->prepare_stream_environment();

        $this->send_sse_event('start', ['status' => 'started']);
        usleep(500000); // 500ms

        $chunks = ['This ', 'is ', 'a ', 'streaming ', 'test.'];

        foreach ($chunks as $chunk) {
            $this->send_sse_event('text', ['text' => $chunk]);
            usleep(500000); // 500ms
        }

        $this->send_sse_event('done', ['status' => 'complete']);
        exit;
    }

    /**
     * Diagnostic Gemini connection test endpoint.
     */
    public function handle_gemini_test() {
        $result = WPA_Art_Analyzer_Gemini_Client::test_connection();
        return rest_ensure_response($result);
    }

    /**
     * Health check endpoint.
     */
    public function handle_health() {
        $api_key = WPA_Art_Analyzer_Settings::get_gemini_api_key();
        return rest_ensure_response([
            'status'            => 'ok',
            'version'           => WPA_ART_ANALYZER_VERSION,
            'gemini_configured' => !empty($api_key),
            'model'             => WPA_Art_Analyzer_Settings::get('model', 'gemini-3.5-flash-lite'),
            'cf7_active'        => defined('WPCF7_VERSION'),
        ]);
    }

    /**
     * Persist analysis record to wp_artwork_analyses table.
     *
     * @param array $data
     */
    private function save_analysis_record($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'artwork_analyses';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return;
        }

        $wpdb->insert(
            $table_name,
            [
                'uuid'         => $data['uuid'],
                'form_id'      => $data['form_id'],
                'user_id'      => $data['user_id'],
                'email'        => $data['email'],
                'image_path'   => $data['image_path'],
                'image_hash'   => $data['image_hash'],
                'provider'     => $data['provider'],
                'model'        => $data['model'],
                'status'       => $data['status'],
                'analysis'     => $data['analysis'],
                'created_at'   => current_time('mysql'),
                'completed_at' => current_time('mysql'),
                'ip_hash'      => $data['ip_hash'],
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
}
