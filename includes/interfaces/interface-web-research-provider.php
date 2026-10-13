<?php
/**
 * Web Research Provider Interface.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

interface WPA_Art_Analyzer_Web_Research_Provider {

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
     * Conduct web research for the given query and optional contextual entities.
     *
     * @param string $query Search query (e.g. artist and artwork title).
     * @param array  $context Additional contextual metadata (e.g. entities from reverse search).
     * @return array|WP_Error Array of normalized findings with source URLs or WP_Error.
     */
    public function research(string $query, array $context = []);

    /**
     * Run a lightweight connectivity test for diagnostics.
     *
     * @return array Array with 'success', 'message', 'latency'.
     */
    public function test_connection(): array;
}
