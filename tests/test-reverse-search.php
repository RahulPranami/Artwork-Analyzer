<?php
/**
 * Test Suite: Reverse Image Search Providers & Normalization.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Test_Reverse_Search {

    public static function run() {
        echo "Running Reverse Search Tests...\n";

        self::test_google_vision_normalization();
        self::test_google_vision_deduplication();
        self::test_serpapi_normalization();
        self::test_reverse_search_factory();
    }

    private static function test_google_vision_normalization() {
        $mock_payload = [
            'bestGuessLabels' => [
                ['label' => 'the starry night vincent van gogh', 'languageCode' => 'en']
            ],
            'webEntities' => [
                ['entityId' => '/m/016629', 'score' => 11.23, 'description' => 'The Starry Night'],
                ['entityId' => '/m/07_m2', 'score' => 10.45, 'description' => 'Vincent van Gogh'],
                ['entityId' => '/m/05qdh', 'score' => 5.12, 'description' => 'Painting']
            ],
            'pagesWithMatchingImages' => [
                [
                    'url' => 'https://www.moma.org/collection/works/79802',
                    'pageTitle' => 'Vincent van Gogh. The Starry Night. 1889 | MoMA',
                    'fullMatchingImages' => [
                        ['url' => 'https://www.moma.org/media/W1siZiIsIjEzMzMwNSJdLFsicCIsImNvbnZlcnQiLCItcmVzaXplIDIwMDB4MjAwMFx1MDAzZSJdXQ.jpg']
                    ]
                ],
                [
                    'url' => 'https://en.wikipedia.org/wiki/The_Starry_Night',
                    'pageTitle' => 'The Starry Night - Wikipedia',
                    'partialMatchingImages' => [
                        ['url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/ea/Van_Gogh_-_Starry_Night_-_Google_Art_Project.jpg/1280px-Van_Gogh_-_Starry_Night_-_Google_Art_Project.jpg']
                    ]
                ],
                [
                    'url' => 'https://someblog.example.com/art/post-1',
                    'pageTitle' => 'Cool Art Blog',
                    'visuallySimilarImages' => [
                        ['url' => 'https://someblog.example.com/img.jpg']
                    ]
                ]
            ],
            'fullMatchingImages' => [
                ['url' => 'https://www.moma.org/media/W1siZiIsIjEzMzMwNSJdLFsicCIsImNvbnZlcnQiLCItcmVzaXplIDIwMDB4MjAwMFx1MDAzZSJdXQ.jpg']
            ]
        ];

        $normalized = WPA_Art_Analyzer_Google_Vision_Search::normalize_web_detection($mock_payload);

        assert(!empty($normalized['best_guess']), 'Best guess should be extracted');
        assert(str_contains($normalized['best_guess'], 'Starry Night'), 'Best guess should contain artwork title');
        assert(count($normalized['entities']) >= 2, 'Entities should be parsed');
        assert($normalized['entities'][0]['description'] === 'The Starry Night', 'Top entity description match');
        assert(count($normalized['matches']) === 3, 'Matching pages should be normalized');

        // Check reputation sorting: MoMA and Wikipedia should be ranked higher
        assert($normalized['matches'][0]['is_reputable'] === true, 'Top match should be reputable institution');
        assert($normalized['matches'][0]['match_type'] === 'exact', 'MoMA match should be exact');

        echo "  ✓ Google Vision normalization and priority ranking passed\n";
    }

    private static function test_google_vision_deduplication() {
        $duplicate_payload = [
            'pagesWithMatchingImages' => [
                [
                    'url' => 'https://www.moma.org/collection/works/79802/',
                    'pageTitle' => 'MoMA 1',
                    'fullMatchingImages' => [['url' => 'https://moma.org/img.jpg']]
                ],
                [
                    'url' => 'https://www.moma.org/collection/works/79802', // trailing slash variant
                    'pageTitle' => 'MoMA 2',
                    'fullMatchingImages' => [['url' => 'https://moma.org/img.jpg']]
                ]
            ]
        ];

        $normalized = WPA_Art_Analyzer_Google_Vision_Search::normalize_web_detection($duplicate_payload);
        assert(count($normalized['matches']) === 1, 'Duplicate URLs must be deduplicated');

        echo "  ✓ URL deduplication passed\n";
    }

    private static function test_serpapi_normalization() {
        $mock_serpapi = [
            'visual_matches' => [
                [
                    'title' => 'The Starry Night',
                    'link' => 'https://en.wikipedia.org/wiki/The_Starry_Night',
                    'source' => 'Wikipedia',
                    'thumbnail' => 'https://serpapi.com/thumb.jpg'
                ],
                [
                    'title' => 'Van Gogh Museum',
                    'link' => 'https://vangoghmuseum.nl/en/art',
                    'source' => 'Van Gogh Museum',
                    'thumbnail' => ''
                ]
            ]
        ];

        $normalized = WPA_Art_Analyzer_Serpapi_Search::normalize_serpapi_response($mock_serpapi);
        assert(count($normalized['matches']) === 2, 'SerpApi matches should be normalized');
        assert($normalized['matches'][0]['title'] === 'The Starry Night', 'Title match');
        assert($normalized['matches'][0]['domain'] === 'en.wikipedia.org', 'Domain extraction');

        echo "  ✓ SerpApi normalization passed\n";
    }

    private static function test_reverse_search_factory() {
        $providers = WPA_Art_Analyzer_Reverse_Search_Factory::get_providers();
        assert(isset($providers['google_vision']), 'Google Vision registered in factory');
        assert(isset($providers['serpapi']), 'SerpApi registered in factory');

        $active = WPA_Art_Analyzer_Reverse_Search_Factory::get_active_provider();
        assert($active instanceof WPA_Art_Analyzer_Reverse_Search_Provider, 'Active provider implements interface');

        echo "  ✓ Reverse Search factory passed\n";
    }
}
