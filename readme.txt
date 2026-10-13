=== WP Artwork Analyzer ===
Contributors: rahulpranami
Tags: artwork, gemini, reverse image search, contact form 7, ai
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered visual artwork critique with reverse image search, web research, and realtime ChatGPT-style SSE streaming using Google Gemini Vision.

== Description ==

**WP Artwork Analyzer** connects WordPress and Contact Form 7 with Google Gemini multimodal AI vision models, Google Cloud Vision Web Detection, and encyclopedic art research to deliver a comprehensive, research-backed artwork critique.

Features an accessible **Analyze Artwork** workflow and a sleek, modern **Slide-In Artwork Analyzer Sidebar** supporting drag-and-drop uploads, live image previews with dimension and file-size detection, webcam capture, and multi-stage analysis progress.

### How the Multi-Stage Pipeline Works
1. **Visual Reverse Image Search**: Before writing the critique, the plugin searches the web for matching or identical artwork using Google Cloud Vision Web Detection or SerpApi to identify recognized paintings, artist entities, and museum collection records.
2. **Authoritative Web Research**: Queries Wikipedia/Wikimedia or Google Custom Search / Tavily for verified historical background, medium, creation dates, and provenance.
3. **Research-Aware AI Critique**: Submits both the uploaded artwork image and retrieved research evidence to Google Gemini Vision.
4. **Three-Tier Categorization**: The critique strictly delineates:
   * **Category A: Visual Observations** (Composition, subject matter, color palette, technique, texture, and mood).
   * **Category B: Research-Supported Findings** (Verified title, creator, medium, date, museum collection with clickable source citations).
   * **Category C: Uncertain Conclusions & Caveats** (Limits of visual evidence, stylistic comparisons, and unverified attribution).

### Key Features
* **Slide-In Artwork Analyzer Drawer**: Smooth slide-in sidebar with keyboard navigation (Esc to close), backdrop dismiss, drag-and-drop file upload, and live webcam snapshot support.
* **Explicit Analyze Artwork Action**: Previews images with filenames, dimensions, and filesize before starting analysis. No unwanted expensive runs on file select.
* **True Reverse Image Search**: Documented, server-side Google Cloud Vision Web Detection (1,000 free requests/month, base64 payload) and SerpApi integration.
* **Built-in Web Research**: Free, zero-configuration Wikipedia REST API integration, with options for Google Custom Search or Tavily AI Search.
* **Progressive Streaming (SSE)**: Server-Sent Events stream tokens word-by-word with zero delay.
* **Decoupled Architecture**: Works seamlessly with Contact Form 7 or standalone via the `[wp_artwork_analyzer]` shortcode.
* **Server-Side API Key Protection**: All credentials remain strictly server-side.
* **Intelligent Abuse Protection**: Sliding-window IP rate limiting and optional Cloudflare Turnstile bot verification.
* **Diagnostics Suite**: WP Admin testing suite for streaming chunk latency, Gemini API, Reverse Search, and Web Research connectivity.

== External Services ==

This plugin integrates with third-party cloud services to provide artwork analysis, reverse visual search, research, and abuse protection:

1. **Google Gemini API**:
   * **Purpose**: Generates multimodal visual analysis and streaming critique.
   * **Data Transmitted**: Uploaded artwork image (base64) and system instructions.
   * **Service Provider**: Google LLC.
   * **Terms of Service**: https://ai.google.dev/terms
   * **Privacy Policy**: https://policies.google.com/privacy

2. **Google Cloud Vision API** (Optional, Default Reverse Search Provider):
   * **Purpose**: Performs visual web matching to discover online museum collections and artist entities.
   * **Data Transmitted**: Artwork image data (base64) via server-side POST.
   * **Service Provider**: Google LLC.
   * **Terms of Service**: https://cloud.google.com/terms
   * **Privacy Policy**: https://policies.google.com/privacy

3. **Wikipedia & Wikimedia REST API** (Default Web Research Provider):
   * **Purpose**: Retrieves authoritative, public domain encyclopedic art history and collection summaries.
   * **Data Transmitted**: Search query (artist name and artwork title).
   * **Service Provider**: Wikimedia Foundation.
   * **Terms of Use**: https://foundation.wikimedia.org/wiki/Policy:Terms_of_Use
   * **Privacy Policy**: https://foundation.wikimedia.org/wiki/Policy:Privacy_policy

4. **SerpApi** (Optional Alternative Reverse Search Provider):
   * **Purpose**: Reverse image search and Google Lens integration.
   * **Data Transmitted**: Image file data via server-to-server POST.
   * **Service Provider**: SerpApi, LLC.
   * **Terms of Service**: https://serpapi.com/terms

5. **Cloudflare Turnstile** (Optional):
   * **Purpose**: Protects public analysis endpoints from automated bot spam.
   * **Data Transmitted**: Turnstile token and client IP.
   * **Service Provider**: Cloudflare, Inc.
   * **Terms of Service**: https://www.cloudflare.com/terms/
   * **Privacy Policy**: https://www.cloudflare.com/privacypolicy/

== Installation ==

1. Upload the `wp-artwork-analyzer` folder to `/wp-content/plugins/` or install via **Plugins → Add New Plugin → Upload Plugin**.
2. Activate the plugin in WordPress.
3. In **WP Admin → Artwork Analyzer → AI Vision Provider**, configure your Google Gemini API key (or define `WPA_GEMINI_API_KEY` in `.env` or `wp-config.php`).
4. (Optional) In **Reverse Image Search**, select Google Cloud Vision and enter an API key (or reuse your Google Gemini key).
5. (Optional) In **Web Research**, Wikipedia is enabled by default with zero setup. You can also configure Google Custom Search or Tavily.
6. Insert `[wp_artwork_analyzer]` on any page or connect your Contact Form 7 form in settings.

== Frequently Asked Questions ==

= Does reverse image search cost money? =
Google Cloud Vision provides 1,000 free web detection units every month. Wikipedia web research is 100% free with no API key or account required.

= Can I use the analyzer without Contact Form 7? =
Yes. The plugin includes a standalone shortcode `[wp_artwork_analyzer]` that renders the launcher button and slide-in drawer anywhere on your site.

= What happens if reverse image search finds no matches? =
If an artwork is original, private, or not yet cataloged online, the plugin gracefully falls back to visual observation and art historical critique without inventing facts.

= Are user uploads kept on the server? =
By default, uploaded files are processed temporarily and deleted immediately once analysis completes. Permanent storage can be enabled under **Storage & Privacy**.

== Changelog ==

= 1.1.0 =
* Added slide-in Artwork Analyzer sidebar drawer with smooth transitions and keyboard accessibility (Esc).
* Added explicit "Analyze Artwork" button preventing unwanted automatic analysis on file selection.
* Added drag-and-drop media upload area with natural image dimension and filesize calculation.
* Implemented Google Cloud Vision Web Detection reverse image search provider.
* Implemented SerpApi reverse image search provider adapter.
* Added authoritative web research providers: Wikipedia REST API, Google Custom Search, and Tavily.
* Updated AI analysis prompt to structure Category A (Visual Observations), Category B (Research-Supported Findings with source citations), and Category C (Uncertain Conclusions).
* Added multi-tab drawer results view (AI Critique, Visual Matches, Research Sources).
* Added diagnostics tests for Reverse Image Search and Web Research in WP Admin.

= 1.0.1 =
* Fixed form ID loose comparison for Contact Form 7 event binding.
* Removed unnecessary session nonce verification on public REST endpoint.
* Added automatic fallback for uploads directory on isolated systemd hosting environments.
* Refactored cURL handle cleanup for full PHP 8.5 compatibility.

= 1.0.0 =
* Initial release.
