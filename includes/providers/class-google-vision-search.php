<?php
/**
 * Google Cloud Vision Web Detection Reverse Image Search Provider.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Google_Vision_Search implements WPA_Art_Analyzer_Reverse_Search_Provider {

    const ENDPOINT = 'https://vision.googleapis.com/v1/images:annotate';

    /**
     * Highly reputable art-related domains for priority ranking.
     *
     * @var string[]
     */
    private static $reputable_domains = [
        'moma.org',
        'metmuseum.org',
        'louvre.fr',
        'nationalgallery.org.uk',
        'tate.org.uk',
        'rijksmuseum.nl',
        'artic.edu',
        'getty.edu',
        'nga.gov',
        'britishmuseum.org',
        'musee-orsay.fr',
        'pradomuseum.net',
        'museodelprado.es',
        'uffizi.it',
        'wikipedia.org',
        'wikimedia.org',
        'wikidata.org',
        'wga.hu',
        'artsandculture.google.com',
        'artnet.com',
        'wikiart.org',
        'mutualart.com',
        'artcyclopedia.com',
        'christies.com',
        'sothebys.com',
    ];

    /**
     * Provider ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'google_vision';
    }

    /**
     * Provider Name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Google Cloud Vision (Web Detection)', 'wp-artwork-analyzer');
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
        if (defined('WPA_GOOGLE_VISION_API_KEY') && !empty(constant('WPA_GOOGLE_VISION_API_KEY'))) {
            return trim(constant('WPA_GOOGLE_VISION_API_KEY'));
        }
        if (defined('GOOGLE_VISION_API_KEY') && !empty(constant('GOOGLE_VISION_API_KEY'))) {
            return trim(constant('GOOGLE_VISION_API_KEY'));
        }

        $env_key = getenv('GOOGLE_VISION_API_KEY') ?: getenv('WPA_GOOGLE_VISION_API_KEY');
        if (!empty($env_key)) {
            return trim($env_key);
        }

        if (!empty($_ENV['GOOGLE_VISION_API_KEY'])) {
            return trim($_ENV['GOOGLE_VISION_API_KEY']);
        }
        if (!empty($_ENV['WPA_GOOGLE_VISION_API_KEY'])) {
            return trim($_ENV['WPA_GOOGLE_VISION_API_KEY']);
        }

        // Check database setting
        $saved = WPA_Art_Analyzer_Settings::get('google_vision_api_key', '');
        if (!empty($saved)) {
            return trim((string)$saved);
        }

        // Fallback: Check if Gemini API key works (same Google Cloud project often enables both)
        $gemini_key = WPA_Art_Analyzer_Settings::get_gemini_api_key();
        if (!empty($gemini_key)) {
            return trim($gemini_key);
        }

        return '';
    }

    /**
     * Execute reverse image search.
     *
     * @param array $image_data Array containing 'mimeType', 'data' (base64).
     * @return array|WP_Error
     */
    public function search(array $image_data) {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            return new WP_Error(
                'VISION_NOT_CONFIGURED',
                __('Google Cloud Vision API key is not configured. Please add it in settings or .env.', 'wp-artwork-analyzer')
            );
        }

        if (empty($image_data['data'])) {
            return new WP_Error(
                'VISION_NO_DATA',
                __('No image data provided for reverse search.', 'wp-artwork-analyzer')
            );
        }

        $payload = [
            'requests' => [
                [
                    'image' => [
                        'content' => $image_data['data'],
                    ],
                    'features' => [
                        [
                            'type'       => 'WEB_DETECTION',
                            'maxResults' => 30,
                        ],
                    ],
                ],
            ],
        ];

        $url = add_query_arg(['key' => $api_key], self::ENDPOINT);

        $start_req = microtime(true);
        WPA_Art_Analyzer_Logger::log_request('GOOGLE_VISION', self::ENDPOINT, [
            'feature'      => 'WEB_DETECTION',
            'maxResults'   => 30,
            'payload_size' => strlen($image_data['data']),
        ]);

        $response = wp_remote_post($url, [
            'headers'     => [
                'Content-Type' => 'application/json',
            ],
            'body'        => wp_json_encode($payload),
            'timeout'     => 30,
            'sslverify'   => true,
            'httpversion' => '1.1',
        ]);

        $latency = (microtime(true) - $start_req) * 1000;

        if (is_wp_error($response)) {
            WPA_Art_Analyzer_Logger::error('GOOGLE_VISION', 'Vision cURL request failed', [
                'error'      => $response->get_error_message(),
                'latency_ms' => round($latency, 2),
            ]);
            return new WP_Error(
                'VISION_REQUEST_FAILED',
                sprintf(
                    /* translators: %s: error message */
                    __('Google Vision request failed: %s', 'wp-artwork-analyzer'),
                    $response->get_error_message()
                )
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);
        $data        = json_decode($body, true);

        if ($status_code !== 200) {
            $msg = $data['error']['message'] ?? sprintf(
                /* translators: %d: HTTP status code */
                __('Google Vision returned HTTP error %d.', 'wp-artwork-analyzer'),
                $status_code
            );
            WPA_Art_Analyzer_Logger::log_response('GOOGLE_VISION', $status_code, ['error' => $msg], $latency);
            return new WP_Error('VISION_API_ERROR', $msg, ['status' => $status_code]);
        }

        $web_detection = $data['responses'][0]['webDetection'] ?? [];
        $normalized    = self::normalize_web_detection($web_detection);

        WPA_Art_Analyzer_Logger::log_response('GOOGLE_VISION', 200, [
            'best_guess'   => $normalized['best_guess'] ?? '',
            'entities_cnt' => count($normalized['entities'] ?? []),
            'matches_cnt'  => count($normalized['matches'] ?? []),
        ], $latency);

        return $normalized;
    }

    /**
     * Normalize web detection payload into standard format.
     *
     * @param array $web
     * @return array
     */
    public static function normalize_web_detection(array $web): array {
        $best_guess = '';
        if (!empty($web['bestGuessLabels'][0]['label'])) {
            $best_guess = ucwords(sanitize_text_field($web['bestGuessLabels'][0]['label']));
        }

        // Entities (Artists, titles, styles)
        $entities = [];
        if (!empty($web['webEntities']) && is_array($web['webEntities'])) {
            foreach ($web['webEntities'] as $entity) {
                if (!empty($entity['description'])) {
                    $desc  = sanitize_text_field($entity['description']);
                    $score = isset($entity['score']) ? floatval($entity['score']) : 0.0;
                    $entities[] = [
                        'description' => $desc,
                        'score'       => round($score, 2),
                    ];
                }
            }
        }

        // Normalize matches from pagesWithMatchingImages
        $matches  = [];
        $seen_urls = [];

        if (!empty($web['pagesWithMatchingImages']) && is_array($web['pagesWithMatchingImages'])) {
            foreach ($web['pagesWithMatchingImages'] as $page) {
                $raw_url = $page['url'] ?? '';
                if (empty($raw_url) || !wp_http_validate_url($raw_url)) {
                    continue;
                }

                $url = esc_url_raw($raw_url);
                $normalized_key = strtolower(rtrim($url, '/'));

                if (isset($seen_urls[$normalized_key])) {
                    continue;
                }
                $seen_urls[$normalized_key] = true;

                $title = !empty($page['pageTitle']) ? sanitize_text_field($page['pageTitle']) : '';
                $host  = wp_parse_url($url, PHP_URL_HOST);
                $host  = $host ? strtolower(preg_replace('/^www\./i', '', $host)) : '';

                // Determine match confidence / type
                $match_type = 'similar';
                $thumb_url  = '';

                if (!empty($page['fullMatchingImages'])) {
                    $match_type = 'exact';
                    $thumb_url  = $page['fullMatchingImages'][0]['url'] ?? '';
                } elseif (!empty($page['partialMatchingImages'])) {
                    $match_type = 'partial';
                    $thumb_url  = $page['partialMatchingImages'][0]['url'] ?? '';
                }

                $matches[] = [
                    'title'         => $title ?: ($host ?: __('Matching Source Page', 'wp-artwork-analyzer')),
                    'url'           => $url,
                    'thumbnail_url' => !empty($thumb_url) && wp_http_validate_url($thumb_url) ? esc_url_raw($thumb_url) : '',
                    'domain'        => $host,
                    'match_type'    => $match_type,
                    'is_reputable'  => self::is_reputable_domain($host),
                    'snippet'       => '',
                ];
            }
        }

        // Also check fullMatchingImages if not already captured
        if (!empty($web['fullMatchingImages']) && is_array($web['fullMatchingImages'])) {
            foreach ($web['fullMatchingImages'] as $img) {
                $img_url = $img['url'] ?? '';
                if (empty($img_url) || !wp_http_validate_url($img_url)) {
                    continue;
                }
                $img_url = esc_url_raw($img_url);
                $key = strtolower(rtrim($img_url, '/'));
                if (!isset($seen_urls[$key])) {
                    $seen_urls[$key] = true;
                    $host = wp_parse_url($img_url, PHP_URL_HOST);
                    $host = $host ? strtolower(preg_replace('/^www\./i', '', $host)) : '';

                    $matches[] = [
                        'title'         => sprintf(
                            /* translators: %s: domain name */
                            __('Exact Visual Match (%s)', 'wp-artwork-analyzer'),
                            $host ?: 'Web'
                        ),
                        'url'           => $img_url,
                        'thumbnail_url' => $img_url,
                        'domain'        => $host,
                        'match_type'    => 'exact',
                        'is_reputable'  => self::is_reputable_domain($host),
                        'snippet'       => '',
                    ];
                }
            }
        }

        // Sort matches: reputable domains first, then exact matches, then partial, then similar
        usort($matches, function ($a, $b) {
            if ($a['is_reputable'] !== $b['is_reputable']) {
                return $b['is_reputable'] <=> $a['is_reputable'];
            }
            $weight = ['exact' => 3, 'partial' => 2, 'similar' => 1];
            $wA = $weight[$a['match_type']] ?? 0;
            $wB = $weight[$b['match_type']] ?? 0;
            return $wB <=> $wA;
        });

        return [
            'provider'   => 'google_vision',
            'best_guess' => $best_guess,
            'entities'   => array_slice($entities, 0, 10),
            'matches'    => array_slice($matches, 0, 15),
            'raw_count'  => count($matches),
        ];
    }

    /**
     * Check if a domain is an authoritative art institution.
     *
     * @param string $domain
     * @return bool
     */
    private static function is_reputable_domain(string $domain): bool {
        if (empty($domain)) {
            return false;
        }
        foreach (self::$reputable_domains as $reputable) {
            if ($domain === $reputable || str_ends_with($domain, '.' . $reputable)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Test connection using lightweight ping.
     *
     * @return array
     */
    public function test_connection(): array {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('Google Cloud Vision API key is not configured.', 'wp-artwork-analyzer'),
                'latency' => 0,
            ];
        }

        $start = microtime(true);

        // Send a minimal 1x1 GIF to test auth
        $tiny_gif = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $payload = [
            'requests' => [
                [
                    'image' => [
                        'content' => $tiny_gif,
                    ],
                    'features' => [
                        [
                            'type'       => 'WEB_DETECTION',
                            'maxResults' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $url = add_query_arg(['key' => $api_key], self::ENDPOINT);

        $response = wp_remote_post($url, [
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
                'message' => __('Google Cloud Vision API responded successfully! Reverse image search is ready.', 'wp-artwork-analyzer'),
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
