# AI Vision, Reverse Search & Web Research Providers Guide

This guide covers multimodal vision models, reverse image search providers, and web research providers supported by WP Artwork Analyzer, including how to add new provider adapters.

---

## 1. AI Vision Model Comparison

| Provider & Model | Vision Quality & Nuance | TTFT (Time to First Token) | Streaming Speed | Free Tier / Cost | Best For |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Google Gemini 3.5 Flash** *(Default)* | Excellent (composition, colors, broad art history) | **~200–350 ms** (Fastest) | **~80 tokens/sec** | **Free tier** (15 RPM / 1M TPM) | Real-time web streaming, high throughput, zero running cost. |
| **Google Gemini 2.5 Pro** | Outstanding (deep iconographic analysis, subtle techniques) | ~800–1200 ms | ~45 tokens/sec | Free tier (2 RPM) / Low cost | In-depth museum-grade academic critiques where 1s initial latency is acceptable. |
| **Anthropic Claude 3.5 Sonnet** | **Superior** (poetic prose, art history vocabulary, emotional depth) | ~400–600 ms | ~60 tokens/sec | Paid only ($3 / $15 per MTok) | Expressive art critic tone. |
| **OpenAI GPT-4o** | Very Good (precise identification of famous artists, styles) | ~300–500 ms | ~70 tokens/sec | Paid only ($2.50 / $10 per MTok) | Standard OpenAI enterprise agreements. |

---

## 2. Reverse Image Search Providers

Reverse image search executes **prior to AI vision analysis** to discover online matching artwork, museum collections, and artist entities.

| Provider | Mechanism | Image Input | Free Tier / Pricing | Key Strengths |
| :--- | :--- | :--- | :--- | :--- |
| **Google Cloud Vision Web Detection** *(Default)* | Official Google Cloud Vision API (`images:annotate` with `WEB_DETECTION`) | Base64 encoded in JSON request (no public URL required) | **1,000 requests/month free**, then $1.50 per 1k requests | Institutional collection detection, exact/partial/similar match ranking, best guess candidate, entity detection. |
| **SerpApi (Google Lens)** | Commercial SerpApi scraping gateway | Multipart upload or URL | 100 free searches/month, then paid subscriptions | Broad consumer visual search coverage. |

### How to Add a New Reverse Search Provider
Implement `WPA_Art_Analyzer_Reverse_Search_Provider`:
```php
<?php
defined('ABSPATH') || exit;

class My_Custom_Reverse_Search implements WPA_Art_Analyzer_Reverse_Search_Provider {
    public function get_id(): string { return 'my_provider'; }
    public function get_name(): string { return 'My Custom Provider'; }
    public function is_configured(): bool { return !empty(get_option('my_key')); }

    public function search(array $image_data) {
        // Return normalized array:
        return [
            'provider'   => 'my_provider',
            'best_guess' => 'Artwork Title Candidate',
            'entities'   => [['description' => 'Artist Name', 'score' => 9.5]],
            'matches'    => [
                [
                    'title'         => 'Museum Record',
                    'url'           => 'https://museum.org/...',
                    'thumbnail_url' => 'https://...',
                    'domain'        => 'museum.org',
                    'match_type'    => 'exact', // exact, partial, or similar
                    'is_reputable'  => true,
                    'snippet'       => 'Exhibition record...',
                ]
            ],
            'raw_count'  => 1,
        ];
    }

    public function test_connection(): array {
        return ['success' => true, 'message' => 'Connected', 'latency' => 120];
    }
}
```
Register the provider in `WPA_Art_Analyzer_Reverse_Search_Factory::get_providers()`.

---

## 3. Web Research Providers

Web research synthesizes encyclopedic records and provenance history from detected art entities.

| Provider | Source Data | Credentials Required | Cost | Best For |
| :--- | :--- | :--- | :--- | :--- |
| **Wikipedia & Wikimedia REST API** *(Default)* | Wikipedia OpenSearch & page summary REST API | **None** (100% Free out-of-the-box) | $0.00 / Unlimited fair use | Verified artist biographies, art movement overviews, museum ownership. |
| **Google Custom Search JSON API** | Programmable Search Engine | API Key + Search Engine ID (cx) | 100 free queries/day | Broader web coverage across gallery catalogues. |
| **Tavily AI Search** | Realtime agentic research API | Tavily API Key | 1,000 free searches/month | Fact-focused excerpts optimized for LLM contexts. |

### How to Add a New Web Research Provider
Implement `WPA_Art_Analyzer_Web_Research_Provider`:
```php
<?php
defined('ABSPATH') || exit;

class My_Custom_Web_Research implements WPA_Art_Analyzer_Web_Research_Provider {
    public function get_id(): string { return 'custom_research'; }
    public function get_name(): string { return 'Custom Research Provider'; }
    public function is_configured(): bool { return true; }

    public function research(string $query, array $context = []) {
        return [
            'provider' => 'custom_research',
            'query'    => $query,
            'findings' => [
                [
                    'title'   => 'Documented Record',
                    'url'     => 'https://example.org/...',
                    'domain'  => 'example.org',
                    'snippet' => 'Extracted excerpt...',
                    'topic'   => 'history',
                ]
            ],
        ];
    }

    public function test_connection(): array {
        return ['success' => true, 'message' => 'Operational', 'latency' => 95];
    }
}
```
Register the provider in `WPA_Art_Analyzer_Web_Research_Factory::get_providers()`.
