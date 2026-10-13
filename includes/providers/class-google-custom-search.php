<?php
/**
 * Google Custom Search JSON API Provider.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Google_Custom_Search implements WPA_Art_Analyzer_Web_Research_Provider {

    const ENDPOINT = 'https://www.googleapis.com/customsearch/v1';

    /**
     * Provider ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'google_search';
    }

    /**
     * Provider Name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Google Custom Search JSON API', 'wp-artwork-analyzer');
    }

    /**
     * Check if configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        $key = self::get_api_key();
        $cx  = self::get_cx();
        return !empty($key) && !empty($cx);
    }

    /**
     * Get API key.
     *
     * @return string
     */
    public static function get_api_key(): string {
        if (defined('WPA_GOOGLE_SEARCH_API_KEY') && !empty(constant('WPA_GOOGLE_SEARCH_API_KEY'))) {
            return trim(constant('WPA_GOOGLE_SEARCH_API_KEY'));
        }
        if (defined('GOOGLE_SEARCH_API_KEY') && !empty(constant('GOOGLE_SEARCH_API_KEY'))) {
            return trim(constant('GOOGLE_SEARCH_API_KEY'));
        }

        $env_key = getenv('GOOGLE_SEARCH_API_KEY') ?: getenv('WPA_GOOGLE_SEARCH_API_KEY');
        if (!empty($env_key)) {
            return trim($env_key);
        }

        if (!empty($_ENV['GOOGLE_SEARCH_API_KEY'])) {
            return trim($_ENV['GOOGLE_SEARCH_API_KEY']);
        }

        $saved = WPA_Art_Analyzer_Settings::get('google_search_api_key', '');
        return trim((string)$saved);
    }

    /**
     * Get Custom Search Engine ID (cx).
     *
     * @return string
     */
    public static function get_cx(): string {
        if (defined('WPA_GOOGLE_SEARCH_CX') && !empty(constant('WPA_GOOGLE_SEARCH_CX'))) {
            return trim(constant('WPA_GOOGLE_SEARCH_CX'));
        }
        if (defined('GOOGLE_SEARCH_CX') && !empty(constant('GOOGLE_SEARCH_CX'))) {
            return trim(constant('GOOGLE_SEARCH_CX'));
        }

        $env_cx = getenv('GOOGLE_SEARCH_CX') ?: getenv('WPA_GOOGLE_SEARCH_CX');
        if (!empty($env_cx)) {
            return trim($env_cx);
        }

        if (!empty($_ENV['GOOGLE_SEARCH_CX'])) {
            return trim($_ENV['GOOGLE_SEARCH_CX']);
        }

        $saved = WPA_Art_Analyzer_Settings::get('google_search_cx', '');
        return trim((string)$saved);
    }

    /**
     * Execute research.
     *
     * @param string $query
     * @param array  $context
     * @return array|WP_Error
     */
    public function research(string $query, array $context = []) {
        $api_key = self::get_api_key();
        $cx      = self::get_cx();

        if (empty($api_key) || empty($cx)) {
            return new WP_Error(
                'GOOGLE_SEARCH_NOT_CONFIGURED',
                __('Google Custom Search API Key or Engine ID (cx) is missing.', 'wp-artwork-analyzer')
            );
        }

        $clean_query = trim($query);
        if (empty($clean_query)) {
            return [
                'provider' => 'google_search',
                'query'    => '',
                'findings' => [],
            ];
        }

        $url = add_query_arg([
            'key' => $api_key,
            'cx'  => $cx,
            'q'   => $clean_query,
            'num' => 6,
        ], self::ENDPOINT);

        $response = wp_remote_get($url, ['timeout' => 20]);

        if (is_wp_error($response)) {
            return new WP_Error('GOOGLE_SEARCH_REQUEST_FAILED', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $msg = $data['error']['message'] ?? "HTTP error {$code}";
            return new WP_Error('GOOGLE_SEARCH_API_ERROR', $msg, ['status' => $code]);
        }

        $findings  = [];
        $seen_urls = [];

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $link = $item['link'] ?? '';
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
                $snippet = sanitize_text_field($item['snippet'] ?? '');
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
            'provider' => 'google_search',
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
        $cx      = self::get_cx();

        if (empty($api_key) || empty($cx)) {
            return [
                'success' => false,
                'message' => __('Google Custom Search API Key or Engine ID (cx) is missing.', 'wp-artwork-analyzer'),
                'latency' => 0,
            ];
        }

        $start = microtime(true);
        $url   = add_query_arg([
            'key' => $api_key,
            'cx'  => $cx,
            'q'   => 'art history',
            'num' => 1,
        ], self::ENDPOINT);

        $response = wp_remote_get($url, ['timeout' => 15]);
        $latency  = round((microtime(true) - $start) * 1000);

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
                'message' => __('Google Custom Search API responded successfully!', 'wp-artwork-analyzer'),
                'latency' => $latency,
            ];
        }

        $err_msg = $data['error']['message'] ?? "HTTP error {$code}";
        return [
            'success' => false,
            'message' => $err_msg,
            'latency' => $latency,
        ];
    }
}
