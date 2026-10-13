<?php
/**
 * Structured Logging System for WP Artwork Analyzer.
 *
 * Provides granular step-by-step request/response logging for debugging,
 * latency profiling, and audit tracking across all pipeline stages.
 * Automatically masks sensitive secrets and API keys.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Logger {

    const PREFIX = '[WP Artwork Analyzer]';

    /**
     * In-memory buffer of logs for the current request.
     *
     * @var array
     */
    private static $recent_logs = [];

    /**
     * Keys whose values should be redacted for security.
     *
     * @var string[]
     */
    private static $sensitive_keys = [
        'key',
        'api_key',
        'apiKey',
        'gemini_api_key',
        'google_vision_api_key',
        'serpapi_api_key',
        'google_search_api_key',
        'tavily_api_key',
        'turnstile_secret_key',
        'turnstile_token',
        'cf_turnstile_response',
        'authorization',
        'password',
    ];

    /**
     * Log an informational message.
     *
     * @param string $stage
     * @param string $message
     * @param array  $context
     */
    public static function info(string $stage, string $message, array $context = []) {
        self::write('INFO', $stage, $message, $context);
    }

    /**
     * Log a debug message (always logged when WP_DEBUG is true or in development).
     *
     * @param string $stage
     * @param string $message
     * @param array  $context
     */
    public static function debug(string $stage, string $message, array $context = []) {
        self::write('DEBUG', $stage, $message, $context);
    }

    /**
     * Log an error or failure.
     *
     * @param string $stage
     * @param string $message
     * @param array  $context
     */
    public static function error(string $stage, string $message, array $context = []) {
        self::write('ERROR', $stage, $message, $context);
    }

    /**
     * Log an outgoing request.
     *
     * @param string $stage
     * @param string $target
     * @param array  $payload_summary
     */
    public static function log_request(string $stage, string $target, array $payload_summary = []) {
        self::write('REQUEST', $stage, "Outgoing request to {$target}", $payload_summary);
    }

    /**
     * Log an incoming response.
     *
     * @param string $stage
     * @param int    $status_code
     * @param mixed  $data_summary
     * @param float  $latency_ms
     */
    public static function log_response(string $stage, int $status_code, $data_summary = [], float $latency_ms = 0.0) {
        $meta = [
            'http_status' => $status_code,
            'latency_ms'  => $latency_ms > 0 ? round($latency_ms, 2) : null,
            'summary'     => $data_summary,
        ];
        $level = ($status_code >= 200 && $status_code < 400) ? 'RESPONSE' : 'ERROR';
        self::write($level, $stage, "Received response [HTTP {$status_code}]", $meta);
    }

    /**
     * Write formatted log entry to PHP error_log.
     *
     * @param string $level
     * @param string $stage
     * @param string $message
     * @param array  $context
     */
    private static function write(string $level, string $stage, string $message, array $context = []) {
        $timestamp = gmdate('Y-m-d H:i:s');
        $stage_tag = strtoupper($stage);

        $clean_context = self::redact_sensitive_data($context);

        $entry = [
            'timestamp' => $timestamp,
            'level'     => $level,
            'stage'     => $stage,
            'message'   => $message,
            'context'   => $clean_context,
        ];

        self::$recent_logs[] = $entry;

        $context_str = !empty($clean_context) ? ' | data: ' . wp_json_encode($clean_context) : '';
        $log_line    = sprintf(
            '%s [%s] [%s] %s%s',
            self::PREFIX,
            $level,
            $stage_tag,
            $message,
            $context_str
        );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($log_line);
    }

    /**
     * Recursively mask sensitive credentials and truncate massive base64 payloads.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function redact_sensitive_data($data) {
        if (!is_array($data)) {
            return $data;
        }

        $redacted = [];
        foreach ($data as $key => $val) {
            $key_lower = strtolower((string)$key);

            // Check if key is sensitive
            $is_sensitive = false;
            foreach (self::$sensitive_keys as $sk) {
                if ($key_lower === strtolower($sk) || str_contains($key_lower, strtolower($sk))) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ($is_sensitive && is_string($val)) {
                $len = strlen($val);
                if ($len > 8) {
                    $redacted[$key] = substr($val, 0, 4) . '...' . substr($val, -4) . " [REDACTED len={$len}]";
                } else {
                    $redacted[$key] = '[REDACTED]';
                }
                continue;
            }

            // Truncate base64 image data strings if present
            if (($key === 'data' || $key === 'content' || $key === 'image_base64') && is_string($val) && strlen($val) > 100) {
                $redacted[$key] = '[BASE64_IMAGE_DATA len=' . strlen($val) . ' bytes]';
                continue;
            }

            if (is_array($val)) {
                $redacted[$key] = self::redact_sensitive_data($val);
            } else {
                $redacted[$key] = $val;
            }
        }

        return $redacted;
    }

    /**
     * Retrieve all logs collected during the current execution.
     *
     * @return array
     */
    public static function get_recent_logs(): array {
        return self::$recent_logs;
    }
}
