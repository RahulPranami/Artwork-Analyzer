<?php
/**
 * Reverse Image Search Provider Interface.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

interface WPA_Art_Analyzer_Reverse_Search_Provider {

    /**
     * Get unique provider identifier.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Get human-readable provider name.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Check if provider has necessary API credentials configured.
     *
     * @return bool
     */
    public function is_configured(): bool;

    /**
     * Execute reverse image search on the provided artwork image data.
     *
     * @param array $image_data Array containing 'mimeType', 'data' (base64), 'width', 'height'.
     * @return array|WP_Error Normalized array of matches and web entities or WP_Error on failure.
     */
    public function search(array $image_data);

    /**
     * Run a lightweight connectivity test for diagnostics.
     *
     * @return array Array with 'success', 'message', 'latency'.
     */
    public function test_connection(): array;
}
