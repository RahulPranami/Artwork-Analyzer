<?php
/**
 * Test Suite: Web Research Providers & Context Synthesis.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Test_Web_Research {

    public static function run() {
        echo "Running Web Research Tests...\n";

        self::test_web_research_factory();
        self::test_query_formulation();
        self::test_wikipedia_live_or_mock();
    }

    private static function test_web_research_factory() {
        $providers = WPA_Art_Analyzer_Web_Research_Factory::get_providers();
        assert(isset($providers['wikipedia']), 'Wikipedia provider registered');
        assert(isset($providers['google_search']), 'Google search registered');
        assert(isset($providers['tavily']), 'Tavily registered');

        $active = WPA_Art_Analyzer_Web_Research_Factory::get_active_provider();
        assert($active instanceof WPA_Art_Analyzer_Web_Research_Provider, 'Active web provider implements interface');
        assert($active->get_id() === 'wikipedia', 'Default web provider is Wikipedia');
        assert($active->is_configured() === true, 'Wikipedia is always configured (free, no key)');

        echo "  ✓ Web Research factory & configuration passed\n";
    }

    private static function test_query_formulation() {
        // Case 1: Best guess present
        $search_1 = [
            'best_guess' => 'The Starry Night Vincent van Gogh',
            'entities' => [
                ['description' => 'Vincent van Gogh'],
                ['description' => 'Painting']
            ],
            'matches' => []
        ];
        $query_1 = WPA_Art_Analyzer_Research_Orchestrator::formulate_research_query($search_1);
        assert($query_1 === 'The Starry Night Vincent van Gogh', 'Query should use best guess');

        // Case 2: Only entities, generic terms filtered out
        $search_2 = [
            'best_guess' => '',
            'entities' => [
                ['description' => 'oil painting'], // generic -> should be skipped
                ['description' => 'Claude Monet'],
                ['description' => 'Water Lilies']
            ],
            'matches' => []
        ];
        $query_2 = WPA_Art_Analyzer_Research_Orchestrator::formulate_research_query($search_2);
        assert($query_2 === 'Claude Monet Water Lilies', 'Generic terms must be filtered');

        // Case 3: Only match title with suffix
        $search_3 = [
            'best_guess' => '',
            'entities' => [],
            'matches' => [
                ['title' => 'Girl with a Pearl Earring - Mauritshuis Collection']
            ]
        ];
        $query_3 = WPA_Art_Analyzer_Research_Orchestrator::formulate_research_query($search_3);
        assert(str_contains($query_3, 'Girl with a Pearl Earring'), 'Clean title extracted');

        echo "  ✓ Query formulation passed\n";
    }

    private static function test_wikipedia_live_or_mock() {
        $wiki = new WPA_Art_Analyzer_Wikipedia_Research();
        $res  = $wiki->test_connection();
        assert(isset($res['success']), 'Test connection returned status');

        echo "  ✓ Wikipedia connectivity check: " . ($res['success'] ? 'Connected' : 'Offline') . "\n";
    }
}
