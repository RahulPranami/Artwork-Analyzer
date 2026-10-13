<?php
/**
 * Security and verification helper for WP Artwork Analyzer.
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Security {

    /**
     * Hash client IP address using WordPress salt for privacy preservation.
     *
     * @param string|null $ip
     * @return string
     */
    public static function get_client_ip_hash($ip = null) {
        if ($ip === null) {
            $ip = self::get_client_ip();
        }
        $salt = defined('NONCE_SALT') ? NONCE_SALT : 'wpa_art_salt_default';
        return hash('sha256', $ip . '|' . $salt);
    }

    /**
     * Get real client IP.
     *
     * @return string
     */
    public static function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Verify Cloudflare Turnstile response if enabled.
     *
     * @param string $token
     * @return bool|WP_Error
     */
    public static function verify_turnstile($token) {
        $enabled = (int)WPA_Art_Analyzer_Settings::get('turnstile_enabled', 0);
        if (!$enabled) {
            return true;
        }

        $secret = WPA_Art_Analyzer_Settings::get('turnstile_secret_key', '');
        if (empty($secret)) {
            return true; // If not configured, pass through
        }

        if (empty($token)) {
            return new WP_Error('CAPTCHA_FAILED', __('Security verification failed: missing verification token.', 'wp-artwork-analyzer'));
        }

        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => self::get_client_ip(),
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('CAPTCHA_FAILED', __('Security verification service could not be reached.', 'wp-artwork-analyzer'));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['success'])) {
            return new WP_Error('CAPTCHA_FAILED', __('Security verification failed. Please refresh and try again.', 'wp-artwork-analyzer'));
        }

        return true;
    }

    /**
     * Safe HTML sanitization for rendered markdown.
     *
     * @param string $html
     * @return string
     */
    public static function sanitize_rendered_html($html) {
        $allowed = [
            'h1'     => ['class' => true, 'id' => true],
            'h2'     => ['class' => true, 'id' => true],
            'h3'     => ['class' => true, 'id' => true],
            'h4'     => ['class' => true, 'id' => true],
            'p'      => ['class' => true],
            'br'     => [],
            'hr'     => ['class' => true],
            'strong' => ['class' => true],
            'b'      => [],
            'em'     => ['class' => true],
            'i'      => [],
            'u'      => [],
            'ul'     => ['class' => true],
            'ol'     => ['class' => true, 'start' => true],
            'li'     => ['class' => true],
            'blockquote' => ['class' => true],
            'code'   => ['class' => true],
            'pre'    => ['class' => true],
            'span'   => ['class' => true],
            'div'    => ['class' => true],
            'a'      => ['href' => true, 'title' => true, 'target' => true, 'rel' => true, 'class' => true],
            'table'  => ['class' => true],
            'thead'  => [],
            'tbody'  => [],
            'tr'     => ['class' => true],
            'th'     => ['class' => true, 'align' => true],
            'td'     => ['class' => true, 'align' => true],
        ];

        return wp_kses($html, $allowed);
    }
}
