<?php
/**
 * Tavily AI Research Provider.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Tavily_Research implements WPA_Art_Analyzer_Web_Research_Provider {

    const ENDPOINT = 'https://api.tavily.com/search';

    /**
     * Provider ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'tavily';
    }

    /**
     * Provider Name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Tavily AI Search (Contextual Research API)', 'wp-artwork-analyzer');
    }

    /**
     * Check if configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        $key = self::get_api_key();
        return !empty($key);
    }

    /**
     * Get API key.
     *
     * @return string
     */
    public static function get_api_key(): string {
        if (defined('WPA_TAVILY_API_KEY') && !empty(constant('WPA_TAVILY_API_KEY'))) {
            return trim(constant('WPA_TAVILY_API_KEY'));
        }
        if (defined('TAVILY_API_KEY') && !empty(constant('TAVILY_API_KEY'))) {
            return trim(constant('TAVILY_API_KEY'));
        }

        $env_key = getenv('TAVILY_API_KEY') ?: getenv('WPA_TAVILY_API_KEY');
        if (!empty($env_key)) {
            return trim($env_key);
        }

        if (!empty($_ENV['TAVILY_API_KEY'])) {
            return trim($_ENV['TAVILY_API_KEY']);
        }

        $saved = WPA_Art_Analyzer_Settings::get('tavily_api_key', '');
        return trim((string)$saved);
    }

    /**
     * Conduct research.
     *
     * @param string $query
     * @param array  $context
     * @return array|WP_Error
     */
    public function research(string $query, array $context = []) {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            return new WP_Error(
                'TAVILY_NOT_CONFIGURED',
                __('Tavily API key is not configured. Please add it in settings or .env.', 'wp-artwork-analyzer')
            );
        }

        $clean_query = trim($query);
        if (empty($clean_query)) {
            return [
                'provider' => 'tavily',
                'query'    => '',
                'findings' => [],
            ];
        }

        $payload = [
            'api_key'        => $api_key,
            'query'          => $clean_query,
            'search_depth'   => 'basic',
            'include_answer' => false,
            'max_results'    => 5,
        ];

        $response = wp_remote_post(self::ENDPOINT, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('TAVILY_REQUEST_FAILED', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $msg = $data['detail'] ?? ($data['message'] ?? "HTTP error {$code}");
            return new WP_Error('TAVILY_API_ERROR', $msg, ['status' => $code]);
        }

        $findings  = [];
        $seen_urls = [];

        if (!empty($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $item) {
                $link = $item['url'] ?? '';
                if (empty($link) || !wp_http_validate_url($link)) {
                    continue;
                }

                $link = esc_url_raw($link);
                $key  = strtolower(rtrim($link, '/'));
                if (isset($seen_urls[$key])) {
                    continue;
                }
                $seen_urls[$key] = true;

                $title   = sanitize_text_field($item['title'] ?? '');
                $snippet = sanitize_text_field($item['content'] ?? '');
                $host    = wp_parse_url($link, PHP_URL_HOST);
                $host    = $host ? strtolower(preg_replace('/^www\./i', '', $host)) : '';

                $findings[] = [
                    'title'   => $title,
                    'url'     => $link,
                    'domain'  => $host,
                    'snippet' => $snippet,
                    'topic'   => 'research',
                ];
            }
        }

        return [
            'provider' => 'tavily',
            'query'    => $clean_query,
            'findings' => $findings,
        ];
    }

    /**
     * Test connection.
     *
     * @return array
     */
    public function test_connection(): array {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('Tavily API key is not configured.', 'wp-artwork-analyzer'),
                'latency' => 0,
            ];
        }

        $start = microtime(true);
        $payload = [
            'api_key'     => $api_key,
            'query'       => 'art test',
            'max_results' => 1,
        ];

        $response = wp_remote_post(self::ENDPOINT, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        $latency = round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'latency' => $latency,
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code === 200) {
            return [
                'success' => true,
                'message' => __('Tavily AI Search responded successfully!', 'wp-artwork-analyzer'),
                'latency' => $latency,
            ];
        }

        return [
            'success' => false,
            'message' => $data['detail'] ?? "HTTP {$code}",
            'latency' => $latency,
        ];
    }
}
