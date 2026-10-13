<?php
/**
 * Wikipedia / Wikimedia Web Research Provider.
 *
 * Free, authoritative, zero-credential encyclopedic research for art history,
 * artists, museums, provenance, and movements.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Wikipedia_Research implements WPA_Art_Analyzer_Web_Research_Provider {

    /**
     * Provider ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'wikipedia';
    }

    /**
     * Provider Name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Wikipedia & Wikimedia (Built-in, Free, No API Key)', 'wp-artwork-analyzer');
    }

    /**
     * Check if configured (always true for Wikipedia since no API key is needed).
     *
     * @return bool
     */
    public function is_configured(): bool {
        return true;
    }

    /**
     * Execute research for query.
     *
     * @param string $query
     * @param array  $context
     * @return array|WP_Error
     */
    public function research(string $query, array $context = []) {
        $clean_query = trim($query);
        if (empty($clean_query)) {
            return [
                'provider' => 'wikipedia',
                'query'    => '',
                'findings' => [],
            ];
        }

        $findings  = [];
        $seen_urls = [];

        // 1. Search Wikipedia full-text search API
        $search_url = add_query_arg([
            'action'   => 'query',
            'list'     => 'search',
            'srsearch' => $clean_query,
            'srlimit'  => 5,
            'format'   => 'json',
        ], 'https://en.wikipedia.org/w/api.php');

        $start_wiki = microtime(true);
        WPA_Art_Analyzer_Logger::log_request('WIKIPEDIA', 'en.wikipedia.org/w/api.php', [
            'query'   => $clean_query,
            'srlimit' => 5,
        ]);

        $response = wp_remote_get($search_url, [
            'timeout'    => 15,
            'user-agent' => 'WP-Artwork-Analyzer/1.1; (WordPress Art Research)',
        ]);

        $latency = (microtime(true) - $start_wiki) * 1000;

        if (is_wp_error($response)) {
            WPA_Art_Analyzer_Logger::error('WIKIPEDIA', 'Wikipedia search request failed', [
                'error'      => $response->get_error_message(),
                'latency_ms' => round($latency, 2),
            ]);
            return new WP_Error('WIKI_REQUEST_FAILED', $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $search_items = $data['query']['search'] ?? [];
        WPA_Art_Analyzer_Logger::log_response('WIKIPEDIA', 200, [
            'results_found' => count($search_items),
        ], $latency);

        foreach ($search_items as $item) {
            $title = $item['title'] ?? '';
            if (empty($title)) {
                continue;
            }

            // Fetch page summary for rich extract and canonical URL
            $summary = self::fetch_summary($title);
            $url     = $summary['content_urls']['desktop']['page'] ?? ('https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title)));

            if (isset($seen_urls[$url])) {
                continue;
            }
            $seen_urls[$url] = true;

            $snippet = '';
            if (!empty($summary['extract'])) {
                $snippet = sanitize_text_field($summary['extract']);
            } elseif (!empty($item['snippet'])) {
                $snippet = wp_strip_all_tags($item['snippet']);
            }

            $topic = 'general';
            $sub_desc = strtolower($summary['description'] ?? '');
            if (str_contains($sub_desc, 'painting') || str_contains($sub_desc, 'artwork') || str_contains($sub_desc, 'sculpture')) {
                $topic = 'identification';
            } elseif (str_contains($sub_desc, 'artist') || str_contains($sub_desc, 'painter')) {
                $topic = 'creator';
            } elseif (str_contains($sub_desc, 'museum') || str_contains($sub_desc, 'gallery')) {
                $topic = 'collection';
            }

            $findings[] = [
                'title'       => sanitize_text_field($title),
                'url'         => esc_url_raw($url),
                'domain'      => 'en.wikipedia.org',
                'snippet'     => $snippet,
                'description' => sanitize_text_field($summary['description'] ?? ''),
                'topic'       => $topic,
            ];

            if (count($findings) >= 4) {
                break;
            }
        }

        // Also check if context has additional entities (e.g. artist) that weren't captured
        if (!empty($context['entities']) && is_array($context['entities'])) {
            foreach ($context['entities'] as $entity) {
                if (count($findings) >= 5) {
                    break;
                }
                $ent_desc = $entity['description'] ?? '';
                if (empty($ent_desc) || strcasecmp($ent_desc, $clean_query) === 0) {
                    continue;
                }

                $ent_summary = self::fetch_summary($ent_desc);
                if (!empty($ent_summary['extract']) && !empty($ent_summary['content_urls']['desktop']['page'])) {
                    $e_url = $ent_summary['content_urls']['desktop']['page'];
                    if (!isset($seen_urls[$e_url])) {
                        $seen_urls[$e_url] = true;
                        $findings[] = [
                            'title'       => sanitize_text_field($ent_summary['title'] ?? $ent_desc),
                            'url'         => esc_url_raw($e_url),
                            'domain'      => 'en.wikipedia.org',
                            'snippet'     => sanitize_text_field($ent_summary['extract']),
                            'description' => sanitize_text_field($ent_summary['description'] ?? ''),
                            'topic'       => 'background',
                        ];
                    }
                }
            }
        }

        return [
            'provider' => 'wikipedia',
            'query'    => $clean_query,
            'findings' => $findings,
        ];
    }

    /**
     * Fetch Wikipedia REST summary API for a title.
     *
     * @param string $title
     * @return array
     */
    private static function fetch_summary(string $title): array {
        $encoded = rawurlencode(str_replace(' ', '_', $title));
        $url     = "https://en.wikipedia.org/api/rest_v1/page/summary/{$encoded}";

        $res = wp_remote_get($url, [
            'timeout'    => 10,
            'user-agent' => 'WP-Artwork-Analyzer/1.1; (WordPress Art Research)',
        ]);

        if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
            return [];
        }

        return json_decode(wp_remote_retrieve_body($res), true) ?: [];
    }

    /**
     * Test connection.
     *
     * @return array
     */
    public function test_connection(): array {
        $start = microtime(true);
        $res   = self::fetch_summary('Visual arts');
        $latency = round((microtime(true) - $start) * 1000);

        if (!empty($res['title'])) {
            return [
                'success' => true,
                'message' => __('Wikipedia REST API is connected and responding normally.', 'wp-artwork-analyzer'),
                'latency' => $latency,
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to reach Wikipedia API.', 'wp-artwork-analyzer'),
            'latency' => $latency,
        ];
    }
}
