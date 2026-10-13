<?php
/**
 * Test Suite: Security, Sanitization, REST Routes & Templates.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Test_Security_And_Rest {

    public static function run() {
        echo "Running Security, REST & Template Tests...\n";

        self::test_html_sanitization_and_links();
        self::test_rest_routes_registration();
        self::test_health_endpoint();
        self::test_rate_limiter();
        self::test_template_rendering();
        self::test_api_key_leak_protection();
    }

    private static function test_html_sanitization_and_links() {
        // Safe link with markdown source
        $input = '<p>The painting depicts night sky. See <a href="https://www.moma.org/collection/works/79802" target="_blank" rel="noopener noreferrer" class="wpa-citation-link">MoMA Collection ↗</a> for details.</p>';
        $sanitized = WPA_Art_Analyzer_Security::sanitize_rendered_html($input);

        assert(str_contains($sanitized, '<a href="https://www.moma.org/collection/works/79802"'), 'Citation links must be allowed in sanitized output');
        assert(str_contains($sanitized, 'class="wpa-citation-link"'), 'Class attribute must be preserved');

        // Malicious script injection
        $malicious = '<p>Normal text</p><script>alert("xss")</script><img src="x" onerror="alert(1)" /><iframe src="https://evil.com"></iframe>';
        $safe = WPA_Art_Analyzer_Security::sanitize_rendered_html($malicious);

        assert(!str_contains($safe, '<script>'), 'Script tags must be stripped');
        assert(!str_contains($safe, 'onerror'), 'Event handlers must be stripped');
        assert(!str_contains($safe, '<iframe>'), 'Iframes must be stripped');

        echo "  ✓ HTML sanitization & secure citation link handling passed\n";
    }

    private static function test_rest_routes_registration() {
        $server = rest_get_server();
        $routes = $server->get_routes();

        $expected = [
            '/art-analyzer/v1/analyze',
            '/art-analyzer/v1/reverse-search',
            '/art-analyzer/v1/web-research',
            '/art-analyzer/v1/health',
            '/art-analyzer/v1/stream-test',
            '/art-analyzer/v1/gemini-test',
            '/art-analyzer/v1/reverse-search-test',
            '/art-analyzer/v1/web-research-test',
        ];

        foreach ($expected as $route) {
            assert(isset($routes[$route]), "REST route {$route} must be registered");
        }

        echo "  ✓ REST API routes registration passed\n";
    }

    private static function test_health_endpoint() {
        $controller = new WPA_Art_Analyzer_REST_Controller();
        $res = $controller->handle_health();
        $data = $res->get_data();

        assert($data['status'] === 'ok', 'Health status should be ok');
        assert(isset($data['version']), 'Health response contains version');
        assert(isset($data['reverse_search']), 'Health response contains reverse search status');
        assert(isset($data['web_research']), 'Health response contains web research status');

        echo "  ✓ Health endpoint passed\n";
    }

    private static function test_rate_limiter() {
        // As administrator in tests, rate limiter passes
        $check = WPA_Art_Analyzer_Rate_Limiter::check_limit();
        assert($check === true, 'Admin should bypass rate limiting');

        echo "  ✓ Rate limiter passed\n";
    }

    private static function test_template_rendering() {
        ob_start();
        include WPA_ART_ANALYZER_PATH . 'templates/analyzer-output.php';
        $html = ob_get_clean();

        assert(str_contains($html, 'wpa-drawer-launcher-btn'), 'Template must include launcher button');
        assert(str_contains($html, 'id="wpa-drawer"'), 'Template must include slide-in drawer container');
        assert(str_contains($html, 'id="wpa-drawer-dropzone"'), 'Template must include dropzone');
        assert(str_contains($html, 'id="wpa-drawer-analyze-btn"'), 'Template must include explicit Analyze Artwork button');
        assert(str_contains($html, 'id="wpa-tab-critique-btn"'), 'Template must include AI Critique tab');
        assert(str_contains($html, 'id="wpa-tab-matches-btn"'), 'Template must include Visual Matches tab');
        assert(str_contains($html, 'id="wpa-tab-research-btn"'), 'Template must include Research Sources tab');

        echo "  ✓ Template rendering and drawer markup passed\n";
    }

    private static function test_api_key_leak_protection() {
        // Enqueue assets and check localized data does not contain raw API keys
        $cf7 = new WPA_Art_Analyzer_CF7_Integration();
        $cf7->enqueue_assets();

        global $wp_scripts;
        $data = $wp_scripts->get_data('wpa-artwork-analyzer', 'data');

        if (!empty($data)) {
            assert(!str_contains($data, 'AIza'), 'Raw Google API keys must not be in localized script data');
            assert(!str_contains($data, 'tvly-'), 'Tavily API key must not be in localized script data');
        }

        echo "  ✓ API key leak prevention passed\n";
    }
}
