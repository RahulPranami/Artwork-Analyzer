<?php
/**
 * Test Suite: End-to-End Workflow & Pipeline Integration.
 *
 * Verifies:
 * 1. Image preparation & downscaling pipeline
 * 2. Reverse search -> Web research -> AI prompt orchestration
 * 3. Provider switching across all registered providers
 * 4. Failure handling (bad files, empty inputs, unconfigured providers)
 * 5. REST route dispatch via rest_do_request()
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

class WPA_Test_End_To_End_Workflow {

    public static function run() {
        echo "Running End-to-End Workflow & Integration Tests...\n";

        self::test_image_preparation_pipeline();
        self::test_full_orchestration_dataflow();
        self::test_provider_switching();
        self::test_failure_handling_and_guards();
        self::test_rest_dispatch_flow();
    }

    /**
     * Test 1: Image preparation & downscaling.
     */
    private static function test_image_preparation_pipeline() {
        // Create a test JPEG image
        $tmp_file = wp_tempnam('wpa_test_img');
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(400, 300);
            $bg = imagecolorallocate($img, 120, 150, 200);
            imagefilledrectangle($img, 0, 0, 400, 300, $bg);
            imagejpeg($img, $tmp_file, 90);
            imagedestroy($img);
        } else {
            // Minimal valid 1x1 JPEG fallback
            $jpeg_bytes = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
            file_put_contents($tmp_file, $jpeg_bytes);
        }

        $file_entry = [
            'name'     => 'test-artwork.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $tmp_file,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmp_file),
        ];

        $image_data = WPA_Art_Analyzer_Image_Handler::process_upload($file_entry);

        assert(!is_wp_error($image_data), 'Image processing should succeed: ' . (is_wp_error($image_data) ? $image_data->get_error_message() : ''));
        assert(isset($image_data['mimeType']) && $image_data['mimeType'] === 'image/jpeg', 'MIME type should be image/jpeg');
        assert(!empty($image_data['data']), 'Base64 image data must not be empty');
        assert(!empty($image_data['image_hash']), 'Image hash must be calculated');
        assert(file_exists($image_data['stored_path']), 'Stored temp file must exist');

        // Cleanup
        WPA_Art_Analyzer_Image_Handler::cleanup();
        assert(!file_exists($image_data['stored_path']), 'Image handler cleanup must delete temporary files');

        if (file_exists($tmp_file)) {
            wp_delete_file($tmp_file);
        }

        echo "  ✓ Image preparation, downscaling, and cleanup pipeline passed\n";
    }

    /**
     * Test 2: Full orchestration dataflow (Search -> Research -> Prompt).
     */
    private static function test_full_orchestration_dataflow() {
        // Simulate reverse search output from Google Vision
        $simulated_search = [
            'provider'   => 'google_vision',
            'configured' => true,
            'best_guess' => 'Girl with a Pearl Earring Johannes Vermeer',
            'entities'   => [
                ['description' => 'Johannes Vermeer', 'score' => 9.8],
                ['description' => 'Dutch Golden Age', 'score' => 7.5],
            ],
            'matches'    => [
                [
                    'title'         => 'Girl with a Pearl Earring | Mauritshuis',
                    'url'           => 'https://www.mauritshuis.nl/en/our-collection/artworks/670-girl-with-a-pearl-earring/',
                    'thumbnail_url' => 'https://www.mauritshuis.nl/thumb.jpg',
                    'domain'        => 'mauritshuis.nl',
                    'match_type'    => 'exact',
                    'is_reputable'  => true,
                    'snippet'       => '',
                ],
                [
                    'title'         => 'Girl with a Pearl Earring - Wikipedia',
                    'url'           => 'https://en.wikipedia.org/wiki/Girl_with_a_Pearl_Earring',
                    'thumbnail_url' => 'https://upload.wikimedia.org/thumb.jpg',
                    'domain'        => 'en.wikipedia.org',
                    'match_type'    => 'partial',
                    'is_reputable'  => true,
                    'snippet'       => '',
                ],
            ],
            'raw_count'  => 2,
        ];

        // 1. Verify query formulation feeds from search results
        $query = WPA_Art_Analyzer_Research_Orchestrator::formulate_research_query($simulated_search);
        assert($query === 'Girl with a Pearl Earring Johannes Vermeer', 'Orchestrator must extract best guess as research query');

        // 2. Run web research stage using active provider (Wikipedia)
        $research = WPA_Art_Analyzer_Research_Orchestrator::run_web_research($simulated_search);
        assert(isset($research['findings']), 'Web research must return findings array');
        assert($research['configured'] === true, 'Wikipedia research must be configured');

        // 3. Synthesize research context prompt
        $prompt = WPA_Art_Analyzer_Research_Orchestrator::build_research_context_prompt($simulated_search, $research);
        assert(str_contains($prompt, 'UNTRUSTED EXTERNAL RESEARCH EVIDENCE'), 'Prompt must contain untrusted demarcation');
        assert(str_contains($prompt, 'Johannes Vermeer'), 'Prompt must contain artist name');
        assert(str_contains($prompt, 'mauritshuis.nl'), 'Prompt must contain museum source URL');
        assert(str_contains($prompt, 'Category A: Visual Observations'), 'System prompt or context must reference Category A');
        assert(str_contains($prompt, 'Category B (Research-Supported Findings)'), 'Prompt must instruct Category B synthesis');
        assert(str_contains($prompt, 'Category C (Uncertain Conclusions)'), 'Prompt must instruct Category C caveats');

        echo "  ✓ Search -> Research -> AI prompt dataflow passed\n";
    }

    /**
     * Test 3: Provider switching and factory resilience.
     */
    private static function test_provider_switching() {
        $original_settings = WPA_Art_Analyzer_Settings::get_all();

        // 1. Switch reverse search provider to SerpApi
        WPA_Art_Analyzer_Settings::update(['reverse_search_provider' => 'serpapi']);
        $p1 = WPA_Art_Analyzer_Reverse_Search_Factory::get_active_provider();
        assert($p1 instanceof WPA_Art_Analyzer_Serpapi_Search, 'Factory should return SerpApi provider');
        assert($p1->get_id() === 'serpapi', 'Provider ID should be serpapi');

        // 2. Switch reverse search provider to none
        WPA_Art_Analyzer_Settings::update(['reverse_search_provider' => 'none']);
        $p2 = WPA_Art_Analyzer_Reverse_Search_Factory::get_active_provider();
        assert($p2 === null, 'Factory should return null when reverse search is disabled');

        $empty_search = WPA_Art_Analyzer_Research_Orchestrator::run_reverse_search(['data' => 'dummy']);
        assert($empty_search['provider'] === 'none', 'Orchestrator should gracefully return provider=none');
        assert($empty_search['configured'] === false, 'Orchestrator should report configured=false');

        // 3. Switch web research provider to Google Custom Search
        WPA_Art_Analyzer_Settings::update(['web_research_provider' => 'google_search']);
        $w1 = WPA_Art_Analyzer_Web_Research_Factory::get_active_provider();
        assert($w1 instanceof WPA_Art_Analyzer_Google_Custom_Search, 'Factory should return Google CSE provider');

        // 4. Switch web research provider to Tavily
        WPA_Art_Analyzer_Settings::update(['web_research_provider' => 'tavily']);
        $w2 = WPA_Art_Analyzer_Web_Research_Factory::get_active_provider();
        assert($w2 instanceof WPA_Art_Analyzer_Tavily_Research, 'Factory should return Tavily provider');

        // 5. Switch web research provider to none
        WPA_Art_Analyzer_Settings::update(['web_research_provider' => 'none']);
        $w3 = WPA_Art_Analyzer_Web_Research_Factory::get_active_provider();
        assert($w3 === null, 'Factory should return null when web research is disabled');

        // Restore original settings
        WPA_Art_Analyzer_Settings::update($original_settings);

        echo "  ✓ Provider switching across all providers passed\n";
    }

    /**
     * Test 4: Failure handling, invalid inputs, and security guards.
     */
    private static function test_failure_handling_and_guards() {
        // 1. Invalid file MIME type (text file passed as image)
        $bad_file = wp_tempnam('wpa_test_bad');
        file_put_contents($bad_file, '<?php phpinfo(); ?>');
        $file_entry = [
            'name'     => 'script.php',
            'type'     => 'text/plain',
            'tmp_name' => $bad_file,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($bad_file),
        ];

        $res = WPA_Art_Analyzer_Image_Handler::process_upload($file_entry);
        assert(is_wp_error($res), 'Uploading non-image file must return WP_Error');
        assert($res->get_error_code() === 'INVALID_FILE_TYPE', 'Error code must be INVALID_FILE_TYPE');
        wp_delete_file($bad_file);

        // 2. Empty upload entry
        $res_empty = WPA_Art_Analyzer_Image_Handler::process_upload(null);
        assert(is_wp_error($res_empty), 'Empty upload entry must return WP_Error');
        assert($res_empty->get_error_code() === 'NO_FILE_UPLOADED', 'Error code must be NO_FILE_UPLOADED');

        // 3. Web research with empty query
        $wiki = new WPA_Art_Analyzer_Wikipedia_Research();
        $empty_research = $wiki->research('');
        assert(is_array($empty_research) && empty($empty_research['findings']), 'Empty query should return empty findings array');

        echo "  ✓ Error handling, file validation, and input guards passed\n";
    }

    /**
     * Test 5: REST request dispatch via rest_do_request().
     */
    private static function test_rest_dispatch_flow() {
        // Dispatch GET /art-analyzer/v1/health
        $health_req = new WP_REST_Request('GET', '/art-analyzer/v1/health');
        $health_res = rest_do_request($health_req);

        assert($health_res->get_status() === 200, 'Health endpoint should return 200');
        $health_data = $health_res->get_data();
        assert($health_data['status'] === 'ok', 'Health response status should be ok');
        assert(isset($health_data['reverse_search']), 'Health response must include reverse_search');
        assert(isset($health_data['web_research']), 'Health response must include web_research');

        // Dispatch POST /art-analyzer/v1/web-research with query
        $research_req = new WP_REST_Request('POST', '/art-analyzer/v1/web-research');
        $research_req->set_param('query', 'The Starry Night');
        $research_res = rest_do_request($research_req);

        assert($research_res->get_status() === 200, 'Web research endpoint should return 200');
        $research_data = $research_res->get_data();
        assert(isset($research_data['findings']), 'Web research endpoint must return findings');

        echo "  ✓ REST endpoint dispatch flow passed\n";
    }
}
