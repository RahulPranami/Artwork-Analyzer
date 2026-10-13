<?php
/**
 * REST API Controller for WP Artwork Analyzer.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_REST_Controller {

    const NAMESPACE = 'art-analyzer/v1';

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Public endpoint for artwork analysis (progressive multi-stage SSE stream)
        register_rest_route(self::NAMESPACE, '/analyze', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_analyze'],
            'permission_callback' => '__return_true',
        ]);

        // Public endpoint for standalone reverse image search
        register_rest_route(self::NAMESPACE, '/reverse-search', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_reverse_search'],
            'permission_callback' => '__return_true',
        ]);

        // Public endpoint for standalone web research
        register_rest_route(self::NAMESPACE, '/web-research', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_web_research'],
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

        // Admin diagnostics: Reverse search connection test
        register_rest_route(self::NAMESPACE, '/reverse-search-test', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_reverse_search_test'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Admin diagnostics: Web research connection test
        register_rest_route(self::NAMESPACE, '/web-research-test', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_web_research_test'],
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
     * Handle the main multi-stage artwork streaming analysis request.
     *
     * Pipeline stages:
     * 1. Preparing & validating image
     * 2. Reverse image search (visual matching)
     * 3. Targeted web research (artist, background, collection)
     * 4. AI vision analysis (streaming critique with research evidence)
     * 5. Completion & cleanup
     *
     * @param WP_REST_Request $request
     */
    public function handle_analyze(WP_REST_Request $request) {
        $start_time = microtime(true);
        WPA_Art_Analyzer_Logger::info('REST_ANALYZE', 'New artwork analysis request received', [
            'client_ip_hash' => WPA_Art_Analyzer_Security::get_client_ip_hash(),
            'form_id'        => (int)$request->get_param('form_id'),
            'has_file'       => !empty($_FILES['artwork']),
            'file_name'      => !empty($_FILES['artwork']['name']) ? sanitize_file_name($_FILES['artwork']['name']) : null,
            'file_size'      => !empty($_FILES['artwork']['size']) ? (int)$_FILES['artwork']['size'] : 0,
        ]);

        // 1. Rate limiting check
        $rate_check = WPA_Art_Analyzer_Rate_Limiter::check_limit();
        if (is_wp_error($rate_check)) {
            WPA_Art_Analyzer_Logger::error('REST_ANALYZE', 'Rate limit exceeded for client', [
                'error' => $rate_check->get_error_message(),
            ]);
            $this->send_early_error_response($rate_check->get_error_code(), $rate_check->get_error_message(), 429);
            return;
        }

        // 2. CAPTCHA verification (Cloudflare Turnstile)
        $turnstile_token = $request->get_param('cf_turnstile_response') ?: ($request->get_param('turnstile_token') ?: '');
        $captcha_check   = WPA_Art_Analyzer_Security::verify_turnstile($turnstile_token);
        if (is_wp_error($captcha_check)) {
            WPA_Art_Analyzer_Logger::error('REST_ANALYZE', 'Turnstile CAPTCHA verification failed', [
                'error' => $captcha_check->get_error_message(),
            ]);
            $this->send_early_error_response($captcha_check->get_error_code(), $captcha_check->get_error_message(), 403);
            return;
        }

        // 3. Image validation and preprocessing
        $file_entry = !empty($_FILES['artwork']) ? $_FILES['artwork'] : null;
        $image_data = WPA_Art_Analyzer_Image_Handler::process_upload($file_entry);
        if (is_wp_error($image_data)) {
            WPA_Art_Analyzer_Logger::error('REST_ANALYZE', 'Image validation / preprocessing failed', [
                'error' => $image_data->get_error_message(),
            ]);
            $this->send_early_error_response($image_data->get_error_code(), $image_data->get_error_message(), 400);
            return;
        }

        WPA_Art_Analyzer_Logger::info('REST_ANALYZE', 'Image preprocessed successfully', [
            'mimeType'   => $image_data['mimeType'] ?? '',
            'image_hash' => $image_data['image_hash'] ?? '',
            'width'      => $image_data['width'] ?? null,
            'height'     => $image_data['height'] ?? null,
        ]);

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

        // Stage 1: Preparing image
        $this->send_sse_event('stage', [
            'stage'    => 'preparing',
            'label'    => __('Preparing and optimizing image...', 'wp-artwork-analyzer'),
            'progress' => 15,
        ]);

        // Stage 2: Reverse image search
        $this->send_sse_event('stage', [
            'stage'    => 'reverse_search',
            'label'    => __('Searching for visual artwork matches online...', 'wp-artwork-analyzer'),
            'progress' => 35,
        ]);

        WPA_Art_Analyzer_Logger::info('REVERSE_SEARCH', 'Starting reverse image search stage', [
            'provider' => WPA_Art_Analyzer_Settings::get('reverse_search_provider', 'google_vision'),
        ]);

        $search_results = WPA_Art_Analyzer_Research_Orchestrator::run_reverse_search($image_data);

        WPA_Art_Analyzer_Logger::info('REVERSE_SEARCH', 'Reverse search stage completed', [
            'configured'   => $search_results['configured'] ?? false,
            'best_guess'   => $search_results['best_guess'] ?? '',
            'entities_cnt' => count($search_results['entities'] ?? []),
            'matches_cnt'  => count($search_results['matches'] ?? []),
        ]);

        $this->send_sse_event('matches', $search_results);

        // Stage 3: Web research
        $this->send_sse_event('stage', [
            'stage'    => 'web_research',
            'label'    => __('Researching artist, history, and museum collection records...', 'wp-artwork-analyzer'),
            'progress' => 55,
        ]);

        WPA_Art_Analyzer_Logger::info('WEB_RESEARCH', 'Starting web research stage', [
            'provider' => WPA_Art_Analyzer_Settings::get('web_research_provider', 'wikipedia'),
        ]);

        $research_results = WPA_Art_Analyzer_Research_Orchestrator::run_web_research($search_results);

        WPA_Art_Analyzer_Logger::info('WEB_RESEARCH', 'Web research stage completed', [
            'configured'   => $research_results['configured'] ?? false,
            'query'        => $research_results['query'] ?? '',
            'findings_cnt' => count($research_results['findings'] ?? []),
        ]);

        $this->send_sse_event('research', $research_results);

        // Stage 4: AI Analysis with research context
        $this->send_sse_event('stage', [
            'stage'    => 'analyzing',
            'label'    => __('Generating research-aware visual critique...', 'wp-artwork-analyzer'),
            'progress' => 75,
        ]);

        $research_prompt = WPA_Art_Analyzer_Research_Orchestrator::build_research_context_prompt($search_results, $research_results);

        WPA_Art_Analyzer_Logger::info('GEMINI_AI', 'Built research context prompt for Gemini', [
            'model'         => WPA_Art_Analyzer_Settings::get('model', 'gemini-3.5-flash-lite'),
            'prompt_length' => strlen($research_prompt),
        ]);

        $chunks_count = 0;

        // Callback for generated text chunks
        $on_text = function ($text) use (&$full_analysis, &$chunks_count) {
            $chunks_count++;
            $full_analysis .= $text;
            $this->send_sse_event('text', ['text' => $text]);
        };

        // Callback for stream errors
        $on_error = function ($code, $message) {
            WPA_Art_Analyzer_Logger::error('GEMINI_AI', "Stream generation error: {$message}", ['code' => $code]);
            $this->send_sse_event('error', ['code' => $code, 'message' => $message]);
        };

        // Execute streaming analysis with Gemini
        $success = WPA_Art_Analyzer_Gemini_Client::stream_analysis($image_data, $on_text, $on_error, $research_prompt);

        if ($success) {
            $total_duration_ms = round((microtime(true) - $start_time) * 1000, 2);

            WPA_Art_Analyzer_Logger::info('REST_ANALYZE', 'Gemini analysis streaming completed successfully', [
                'chunks_received' => $chunks_count,
                'analysis_length' => strlen($full_analysis),
                'duration_ms'     => $total_duration_ms,
            ]);

            // Send completed stage and done event
            $this->send_sse_event('stage', [
                'stage'    => 'completed',
                'label'    => __('Artwork analysis complete.', 'wp-artwork-analyzer'),
                'progress' => 100,
            ]);

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
                WPA_Art_Analyzer_Logger::info('REST_ANALYZE', 'Persisted analysis record to database table', ['uuid' => $uuid]);
            }
        }

        // Cleanup temporary files
        WPA_Art_Analyzer_Image_Handler::cleanup();
        WPA_Art_Analyzer_Logger::info('REST_ANALYZE', 'Temporary files cleaned up');

        exit;
    }

    /**
     * Standalone reverse image search endpoint.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_reverse_search(WP_REST_Request $request) {
        $rate_check = WPA_Art_Analyzer_Rate_Limiter::check_limit();
        if (is_wp_error($rate_check)) {
            return rest_ensure_response($rate_check);
        }

        $file_entry = !empty($_FILES['artwork']) ? $_FILES['artwork'] : null;
        $image_data = WPA_Art_Analyzer_Image_Handler::process_upload($file_entry);
        if (is_wp_error($image_data)) {
            return rest_ensure_response($image_data);
        }

        WPA_Art_Analyzer_Rate_Limiter::increment();

        $results = WPA_Art_Analyzer_Research_Orchestrator::run_reverse_search($image_data);
        WPA_Art_Analyzer_Image_Handler::cleanup();

        return rest_ensure_response($results);
    }

    /**
     * Standalone web research endpoint.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_web_research(WP_REST_Request $request) {
        $rate_check = WPA_Art_Analyzer_Rate_Limiter::check_limit();
        if (is_wp_error($rate_check)) {
            return rest_ensure_response($rate_check);
        }

        $query = sanitize_text_field($request->get_param('query') ?? '');
        if (empty($query)) {
            return new WP_Error('EMPTY_QUERY', __('Please provide a search query.', 'wp-artwork-analyzer'), ['status' => 400]);
        }

        WPA_Art_Analyzer_Rate_Limiter::increment();

        $provider = WPA_Art_Analyzer_Web_Research_Factory::get_active_provider();
        if (!$provider) {
            return rest_ensure_response([
                'provider'   => 'none',
                'configured' => false,
                'findings'   => [],
            ]);
        }

        $results = $provider->research($query);
        return rest_ensure_response($results);
    }

    /**
     * Admin diagnostics: test reverse search.
     *
     * @return WP_REST_Response
     */
    public function handle_reverse_search_test() {
        $provider = WPA_Art_Analyzer_Reverse_Search_Factory::get_active_provider();
        if (!$provider) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('No reverse image search provider is currently active.', 'wp-artwork-analyzer'),
            ]);
        }

        $result = $provider->test_connection();
        $result['provider'] = $provider->get_name();
        return rest_ensure_response($result);
    }

    /**
     * Admin diagnostics: test web research.
     *
     * @return WP_REST_Response
     */
    public function handle_web_research_test() {
        $provider = WPA_Art_Analyzer_Web_Research_Factory::get_active_provider();
        if (!$provider) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('No web research provider is currently active.', 'wp-artwork-analyzer'),
            ]);
        }

        $result = $provider->test_connection();
        $result['provider'] = $provider->get_name();
        return rest_ensure_response($result);
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
        $rev_p   = WPA_Art_Analyzer_Reverse_Search_Factory::get_active_provider();
        $web_p   = WPA_Art_Analyzer_Web_Research_Factory::get_active_provider();

        return rest_ensure_response([
            'status'            => 'ok',
            'version'           => WPA_ART_ANALYZER_VERSION,
            'gemini_configured' => !empty($api_key),
            'model'             => WPA_Art_Analyzer_Settings::get('model', 'gemini-3.5-flash-lite'),
            'reverse_search'    => $rev_p ? ['id' => $rev_p->get_id(), 'configured' => $rev_p->is_configured()] : null,
            'web_research'      => $web_p ? ['id' => $web_p->get_id(), 'configured' => $web_p->is_configured()] : null,
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
