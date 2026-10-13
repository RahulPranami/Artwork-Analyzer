<?php
/**
 * SerpApi Reverse Image Search Provider.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Serpapi_Search implements WPA_Art_Analyzer_Reverse_Search_Provider {

    const ENDPOINT = 'https://serpapi.com/search.json';

    /**
     * Provider ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'serpapi';
    }

    /**
     * Provider Name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('SerpApi (Google Lens & Reverse Image)', 'wp-artwork-analyzer');
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
     * Retrieve API key with discovery hierarchy.
     *
     * @return string
     */
    public static function get_api_key(): string {
        if (defined('WPA_SERPAPI_API_KEY') && !empty(constant('WPA_SERPAPI_API_KEY'))) {
            return trim(constant('WPA_SERPAPI_API_KEY'));
        }
        if (defined('SERPAPI_API_KEY') && !empty(constant('SERPAPI_API_KEY'))) {
            return trim(constant('SERPAPI_API_KEY'));
        }

        $env_key = getenv('SERPAPI_API_KEY') ?: getenv('WPA_SERPAPI_API_KEY');
        if (!empty($env_key)) {
            return trim($env_key);
        }

        if (!empty($_ENV['SERPAPI_API_KEY'])) {
            return trim($_ENV['SERPAPI_API_KEY']);
        }

        $saved = WPA_Art_Analyzer_Settings::get('serpapi_api_key', '');
        return trim((string)$saved);
    }

    /**
     * Execute reverse image search using SerpApi.
     *
     * @param array $image_data
     * @return array|WP_Error
     */
    public function search(array $image_data) {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            return new WP_Error(
                'SERPAPI_NOT_CONFIGURED',
                __('SerpApi key is not configured. Please add it in settings or .env.', 'wp-artwork-analyzer')
            );
        }

        // SerpApi Google Lens accepts image file or public url.
        // We can create a secure temporary local upload with short expiry if needed, or send multipart.
        // For direct search:
        $query_args = [
            'engine'  => 'google_lens',
            'api_key' => $api_key,
            'hl'      => 'en',
        ];

        // Send request using temporary image file if available
        $boundary = wp_generate_password(24, false);
        $payload  = '';

        $img_bytes = base64_decode($image_data['data']);
        $mime      = $image_data['mimeType'] ?? 'image/jpeg';
        $filename  = 'artwork.' . ($mime === 'image/png' ? 'png' : 'jpg');

        $payload .= "--{$boundary}\r\n";
        $payload .= 'Content-Disposition: form-data; name="image"; filename="' . $filename . "\"\r\n";
        $payload .= "Content-Type: {$mime}\r\n\r\n";
        $payload .= $img_bytes . "\r\n";
        $payload .= "--{$boundary}--\r\n";

        $url = add_query_arg($query_args, self::ENDPOINT);

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body'    => $payload,
            'timeout' => 35,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('SERPAPI_REQUEST_FAILED', $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);
        $data        = json_decode($body, true);

        if ($status_code !== 200) {
            $msg = $data['error'] ?? sprintf(
                /* translators: %d: HTTP status code */
                __('SerpApi returned HTTP error %d.', 'wp-artwork-analyzer'),
                $status_code
            );
            return new WP_Error('SERPAPI_API_ERROR', $msg, ['status' => $status_code]);
        }

        return self::normalize_serpapi_response($data);
    }

    /**
     * Normalize SerpApi response.
     *
     * @param array $data
     * @return array
     */
    public static function normalize_serpapi_response(array $data): array {
        $best_guess = '';
        $entities   = [];
        $matches    = [];
        $seen_urls  = [];

        // Check visual_matches
        if (!empty($data['visual_matches']) && is_array($data['visual_matches'])) {
            foreach ($data['visual_matches'] as $item) {
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

                $title = sanitize_text_field($item['title'] ?? '');
                $host  = wp_parse_url($link, PHP_URL_HOST);
                $host  = $host ? strtolower(preg_replace('/^www\./i', '', $host)) : '';

                if (empty($best_guess) && !empty($title)) {
                    $best_guess = $title;
                }

                $thumb = $item['thumbnail'] ?? '';

                $matches[] = [
                    'title'         => $title ?: ($host ?: 'Visual Match'),
                    'url'           => $link,
                    'thumbnail_url' => !empty($thumb) && wp_http_validate_url($thumb) ? esc_url_raw($thumb) : '',
                    'domain'        => $host,
                    'match_type'    => 'similar',
                    'is_reputable'  => false,
                    'snippet'       => sanitize_text_field($item['source'] ?? ''),
                ];
            }
        }

        return [
            'provider'   => 'serpapi',
            'best_guess' => $best_guess,
            'entities'   => $entities,
            'matches'    => array_slice($matches, 0, 15),
            'raw_count'  => count($matches),
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
                'message' => __('SerpApi API key is not configured.', 'wp-artwork-analyzer'),
                'latency' => 0,
            ];
        }

        $start = microtime(true);
        $url   = add_query_arg([
            'engine'  => 'google',
            'q'       => 'test',
            'api_key' => $api_key,
            'num'     => 1,
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

        if ($code === 200 && empty($data['error'])) {
            return [
                'success' => true,
                'message' => __('SerpApi is connected and functioning normally.', 'wp-artwork-analyzer'),
                'latency' => $latency,
            ];
        }

        return [
            'success' => false,
            'message' => $data['error'] ?? "HTTP {$code}",
            'latency' => $latency,
        ];
    }
}
