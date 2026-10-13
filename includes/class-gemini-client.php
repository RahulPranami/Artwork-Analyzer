<?php
/**
 * Gemini Vision Streaming Client for WP Artwork Analyzer.
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Gemini_Client {

    /**
     * Stream artwork analysis directly from Gemini to callbacks.
     *
     * @param array $image_data Array with 'mimeType' and 'data' (base64).
     * @param callable $on_text_chunk Callback invoked when text is generated: function(string $text): void
     * @param callable $on_error Callback invoked on error: function(string $code, string $message): void
     * @param string $research_context Optional structured external research context from reverse search and web research.
     * @return bool True if stream completed normally, false otherwise.
     */
    public static function stream_analysis($image_data, $on_text_chunk, $on_error, $research_context = '') {
        $api_key = WPA_Art_Analyzer_Settings::get_gemini_api_key();
        if (empty($api_key)) {
            call_user_func($on_error, 'GEMINI_AUTH_ERROR', __('Gemini API key is not configured. Please configure it in settings or .env.', 'wp-artwork-analyzer'));
            return false;
        }

        $model       = WPA_Art_Analyzer_Settings::get('model', 'gemini-3.5-flash-lite');
        $max_tokens  = (int)WPA_Art_Analyzer_Settings::get('max_tokens', 2048);
        $temperature = (float)WPA_Art_Analyzer_Settings::get('temperature', 0.4);
        $prompt      = WPA_Art_Analyzer_Settings::get('system_prompt', WPA_Art_Analyzer_Settings::get_default_prompt());

        // Construct request endpoint with alt=sse
        $clean_model = trim(str_replace('models/', '', $model));
        $endpoint    = "https://generativelanguage.googleapis.com/v1beta/models/{$clean_model}:streamGenerateContent?alt=sse";

        $user_prompt_text = 'Please provide a comprehensive visual analysis of this artwork according to the instructions provided.';
        if (!empty($research_context)) {
            $user_prompt_text .= "\n\n" . $research_context;
        }

        // Build payload
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $prompt]
                ]
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $image_data['mimeType'],
                                'data'     => $image_data['data'],
                            ]
                        ],
                        [
                            'text' => $user_prompt_text
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => $max_tokens,
            ]
        ];

        $json_payload = wp_json_encode($payload);

        $start_gemini = microtime(true);
        WPA_Art_Analyzer_Logger::log_request('GEMINI_STREAM', $endpoint, [
            'model'           => $clean_model,
            'temperature'     => $temperature,
            'maxOutputTokens' => $max_tokens,
            'image_mime'      => $image_data['mimeType'],
            'payload_size'    => strlen($json_payload),
            'has_research'    => !empty($research_context),
        ]);

        $curl = curl_init($endpoint);

        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $api_key,
        ];

        // Persistent buffer for incoming SSE chunks
        $buffer = '';
        $had_error = false;
        $total_text_emitted = 0;

        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $json_payload,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buffer, &$had_error, &$total_text_emitted, $on_text_chunk, $on_error) {
                // Client disconnection check
                if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) {
                    return 0; // Abort cURL transfer immediately
                }

                $buffer .= $chunk;

                // Process lines from buffer
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    // Direct JSON error detection (non-SSE error response)
                    if (str_starts_with($line, '{"error"')) {
                        $err_data = json_decode($line, true);
                        if (!empty($err_data['error'])) {
                            $had_error = true;
                            $code = self::map_http_code_to_error($err_data['error']['code'] ?? 500);
                            $msg = $err_data['error']['message'] ?? __('An upstream Gemini AI error occurred.', 'wp-artwork-analyzer');
                            call_user_func($on_error, $code, $msg);
                            return 0;
                        }
                    }

                    // Handle SSE data lines
                    if (str_starts_with($line, 'data: ')) {
                        $json_str = substr($line, 6);
                        $data = json_decode($json_str, true);

                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                            continue;
                        }

                        // Check for error inside data
                        if (!empty($data['error'])) {
                            $had_error = true;
                            $code = self::map_http_code_to_error($data['error']['code'] ?? 500);
                            $msg = $data['error']['message'] ?? __('An upstream Gemini AI error occurred.', 'wp-artwork-analyzer');
                            call_user_func($on_error, $code, $msg);
                            return 0;
                        }

                        // Extract text from candidates
                        if (!empty($data['candidates']) && is_array($data['candidates'])) {
                            foreach ($data['candidates'] as $candidate) {
                                if (!empty($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
                                    foreach ($candidate['content']['parts'] as $part) {
                                        // Ignore internal thoughts if separate
                                        if (isset($part['thought']) && $part['thought'] === true) {
                                            continue;
                                        }

                                        if (isset($part['text']) && strlen($part['text']) > 0) {
                                            $text = $part['text'];
                                            $total_text_emitted += strlen($text);
                                            call_user_func($on_text_chunk, $text);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                return strlen($chunk);
            },
        ]);

        $result = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_errno = curl_errno($curl);
        $curl_error = curl_error($curl);
        $gemini_latency = (microtime(true) - $start_gemini) * 1000;

        if (PHP_VERSION_ID < 80000 && is_resource($curl)) {
            curl_close($curl);
        }
        unset($curl);

        WPA_Art_Analyzer_Logger::log_response('GEMINI_STREAM', (int)$http_code, [
            'total_text_emitted' => $total_text_emitted,
            'curl_errno'         => $curl_errno,
            'had_error'          => $had_error,
        ], $gemini_latency);

        // Check if aborted by client
        if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) {
            return false;
        }

        if ($curl_errno !== CURLE_OK) {
            if ($curl_errno === CURLE_OPERATION_TIMEDOUT) {
                call_user_func($on_error, 'GEMINI_TIMEOUT', __('The artwork analysis timed out. Please try again.', 'wp-artwork-analyzer'));
            } elseif (!$had_error) {
                call_user_func($on_error, 'GEMINI_STREAM_ERROR', __('Failed communicating with Gemini AI: ', 'wp-artwork-analyzer') . $curl_error);
            }
            return false;
        }

        if ($http_code !== 200 && !$had_error) {
            $code = self::map_http_code_to_error($http_code);
            if ((int)$http_code === 402) {
                $msg = __('Gemini API Prepay credit balance is depleted or billing account requires payment (HTTP 402 Payment Required). Please add credits or check billing in Google AI Studio or Google Cloud Console.', 'wp-artwork-analyzer');
            } elseif ((int)$http_code === 429) {
                $msg = __('Gemini API rate limit or quota exceeded (HTTP 429). Please wait a moment before trying again.', 'wp-artwork-analyzer');
            } elseif ((int)$http_code === 403 || (int)$http_code === 401) {
                $msg = __('Gemini API authentication failed (HTTP ' . (int)$http_code . '). Please check your API key in settings or .env.', 'wp-artwork-analyzer');
            } else {
                /* translators: %d: HTTP status code */
                $msg = sprintf(__('Gemini returned HTTP status %d.', 'wp-artwork-analyzer'), $http_code);
            }
            call_user_func($on_error, $code, $msg);
            return false;
        }

        return !$had_error && ($total_text_emitted > 0);
    }

    /**
     * Map HTTP response codes to standardized plugin error codes.
     *
     * @param int $http_code
     * @return string
     */
    public static function map_http_code_to_error($http_code) {
        switch ((int)$http_code) {
            case 401:
            case 403:
                return 'GEMINI_AUTH_ERROR';
            case 402:
                return 'GEMINI_PAYMENT_REQUIRED';
            case 429:
                return 'GEMINI_RATE_LIMIT';
            case 408:
            case 504:
                return 'GEMINI_TIMEOUT';
            default:
                return 'GEMINI_STREAM_ERROR';
        }
    }

    /**
     * Quick test for admin diagnostics.
     *
     * @return array Array with success, latency, and message.
     */
    public static function test_connection() {
        $api_key = WPA_Art_Analyzer_Settings::get_gemini_api_key();
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('API key is missing or not configured.', 'wp-artwork-analyzer'),
            ];
        }

        $model       = WPA_Art_Analyzer_Settings::get('model', 'gemini-3.5-flash-lite');
        $clean_model = trim(str_replace('models/', '', $model));
        $endpoint    = "https://generativelanguage.googleapis.com/v1beta/models/{$clean_model}:generateContent";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Ping. Reply with: OK']
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 10,
            ]
        ];

        $start_time = microtime(true);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $api_key,
            ],
            CURLOPT_POSTFIELDS     => wp_json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 35,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        if (PHP_VERSION_ID < 80000 && is_resource($ch)) {
            curl_close($ch);
        }
        unset($ch);

        $latency = round((microtime(true) - $start_time) * 1000);

        if ($curl_errno !== CURLE_OK) {
            return [
                'success' => false,
                'model'   => $clean_model,
                'message' => $curl_error ?: 'cURL connection error',
                'latency' => $latency,
            ];
        }

        $data = json_decode($body, true);

        if ($code === 200) {
            return [
                'success' => true,
                'model'   => $clean_model,
                'message' => __('Successfully connected to Gemini API!', 'wp-artwork-analyzer'),
                'latency' => $latency,
            ];
        }

        if ($code === 402) {
            $err_msg = __('Prepay credit balance is depleted or billing account requires payment (HTTP 402 Payment Required). Please add credits or manage billing at aistudio.google.com/billing.', 'wp-artwork-analyzer');
        } elseif ($code === 429) {
            $err_msg = __('Rate limit or quota exceeded (HTTP 429). Please wait a moment or request a quota increase.', 'wp-artwork-analyzer');
        } else {
            $err_msg = $data['error']['message'] ?? "HTTP error {$code}";
        }
        return [
            'success' => false,
            'model'   => $clean_model,
            'message' => $err_msg,
            'latency' => $latency,
        ];
    }
}
