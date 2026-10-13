<?php
/**
 * Test Runner for WP Artwork Analyzer.
 *
 * Can be run via:
 *   wp eval-file wp-content/plugins/wp-artwork-analyzer/tests/run-tests.php --path=/home/rahul/public_html/artpress
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/test-reverse-search.php';
require_once __DIR__ . '/test-web-research.php';
require_once __DIR__ . '/test-pipeline-orchestrator.php';
require_once __DIR__ . '/test-security-and-rest.php';
require_once __DIR__ . '/test-end-to-end-workflow.php';

echo "============================================================\n";
echo "WP Artwork Analyzer - Automated Verification Suite\n";
echo "============================================================\n\n";

$start_time = microtime(true);
$errors = [];

try {
    WPA_Test_Reverse_Search::run();
    echo "\n";
} catch (Throwable $e) {
    $errors[] = ['suite' => 'Reverse Search', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    echo "  ✕ Reverse Search Suite Failed: " . $e->getMessage() . "\n\n";
}

try {
    WPA_Test_Web_Research::run();
    echo "\n";
} catch (Throwable $e) {
    $errors[] = ['suite' => 'Web Research', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    echo "  ✕ Web Research Suite Failed: " . $e->getMessage() . "\n\n";
}

try {
    WPA_Test_Pipeline_Orchestrator::run();
    echo "\n";
} catch (Throwable $e) {
    $errors[] = ['suite' => 'Pipeline & Prompt Security', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    echo "  ✕ Pipeline Suite Failed: " . $e->getMessage() . "\n\n";
}

try {
    WPA_Test_Security_And_Rest::run();
    echo "\n";
} catch (Throwable $e) {
    $errors[] = ['suite' => 'Security & REST', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    echo "  ✕ Security & REST Suite Failed: " . $e->getMessage() . "\n\n";
}

try {
    WPA_Test_End_To_End_Workflow::run();
    echo "\n";
} catch (Throwable $e) {
    $errors[] = ['suite' => 'End-to-End Workflow', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    echo "  ✕ End-to-End Workflow Suite Failed: " . $e->getMessage() . "\n\n";
}

$elapsed = round((microtime(true) - $start_time) * 1000, 2);

echo "============================================================\n";
if (empty($errors)) {
    echo "✓ ALL TESTS PASSED! ({$elapsed} ms)\n";
    echo "============================================================\n";
} else {
    echo "✕ " . count($errors) . " TEST SUITES FAILED:\n";
    foreach ($errors as $err) {
        echo "  - [{$err['suite']}] {$err['error']} in {$err['file']}:{$err['line']}\n";
    }
    echo "============================================================\n";
    exit(1);
}
