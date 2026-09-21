=== WP Artwork Analyzer ===
Contributors: rahulpranami
Tags: artwork, gemini, contact form 7, ai, streaming
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.1
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered visual artwork critique with realtime ChatGPT-style SSE streaming using Google Gemini Vision and Contact Form 7.

== Description ==

**WP Artwork Analyzer** is a production-ready WordPress plugin that connects Contact Form 7 with Google Gemini multimodal AI vision models to deliver progressive, live streaming critique of paintings, drawings, digital art, and sculptures.

Instead of waiting 30–60 seconds for an AI analysis to complete behind a loading spinner, visitors see the critique appear instantly word-by-word with live section progress indicators (Overview, Possible Identification, Subject Matter, Composition, Color Analysis, Technique & Style, Mood & Emotion, Meaning & Symbolism, and Summary).

### Key Features
* **Decoupled Architecture**: Contact Form 7 independently handles contact information and email delivery, while the analyzer manages the streaming AI vision pipeline.
* **Server-Side API Key Protection**: Your Gemini API key remains strictly on the server and is never exposed to visitors or client-side code.
* **Flexible Key Discovery**: Configure your key via WordPress Admin settings, `wp-config.php` constant (`WPA_GEMINI_API_KEY`), or standard `.env` file.
* **Progressive Streaming (SSE)**: Uses Server-Sent Events (SSE) via PHP cURL write callbacks and client-side `ReadableStream` reader with packet buffer handling.
* **Intelligent Image Preprocessing**: Strict server-side MIME type verification, automatic downsampling of high-resolution uploads to 2048px before sending to Gemini, and automatic cleanup of temporary files.
* **Built-in Abuse Protection**: Configurable IP-hash rate limiting and optional Cloudflare Turnstile bot verification.
* **Admin Diagnostics Suite**: Interactive diagnostic tools in WP Admin to verify proxy buffering, chunk latency, and Gemini API connectivity.

== External Services ==

This plugin relies on external third-party cloud services to generate AI analyses and provide optional bot protection:

1. **Google Gemini API**:
   * **Purpose**: Performs multimodal visual analysis and text critique of the uploaded artwork.
   * **Data transmitted**: The uploaded artwork image (base64-encoded) and system evaluation prompt instructions.
   * **Service Provider**: Google LLC.
   * **Terms of Service**: https://ai.google.dev/terms
   * **Privacy Policy**: https://policies.google.com/privacy

2. **Cloudflare Turnstile** (Optional):
   * **Purpose**: Protects the public analysis endpoint from automated bot abuse. Only active if enabled by the administrator in plugin settings.
   * **Data transmitted**: Turnstile verification challenge token and client IP address.
   * **Service Provider**: Cloudflare, Inc.
   * **Terms of Service**: https://www.cloudflare.com/terms/
   * **Privacy Policy**: https://www.cloudflare.com/privacypolicy/

== Installation ==

1. Upload the `wp-artwork-analyzer` folder to your `/wp-content/plugins/` directory, or install the ZIP through **Plugins → Add New Plugin → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. In **WP Admin → Artwork Analyzer → AI Provider**, configure your Google Gemini API key (or define `WPA_GEMINI_API_KEY` in your `.env` or `wp-config.php`).
4. Select your target Contact Form 7 form in **WP Admin → Artwork Analyzer → Contact Form 7**.

== Frequently Asked Questions ==

= Where do I get a Google Gemini API key? =
You can generate a free API key at [Google AI Studio](https://aistudio.google.com/).

= Which image formats are supported? =
The plugin accepts JPG, JPEG, PNG, and WEBP formats up to 10 MB (configurable in settings). Images exceeding the maximum dimension (default 2048px) are automatically resized before processing to conserve bandwidth and minimize latency.

= Are visitors' artwork images saved on my server? =
No, by default uploaded images are processed temporarily in memory/disk and immediately deleted once the Gemini stream completes. Permanent storage can be enabled under **Artwork Analyzer → Storage & Privacy** if desired.

= Is Contact Form 7 required? =
Contact Form 7 provides the standard form interface, but you can also place the analyzer container standalone on any page using the shortcode `[wp_artwork_analyzer]`.

= Why does the critique stream word-by-word instead of appearing all at once? =
The plugin uses Server-Sent Events (SSE) to forward generated tokens progressively from Gemini to the browser, providing a responsive ChatGPT-style experience.

== Screenshots ==

1. Frontend live streaming artwork analysis with progressive section tracker.
2. Contact Form 7 form with artwork file upload field and live preview.
3. WordPress Admin AI Provider settings and model selection.
4. Built-in Hosting Diagnostics suite with chunk latency test and Gemini API ping.

== Changelog ==

= 1.0.1 =
* Fixed form ID loose comparison for Contact Form 7 event binding.
* Removed unnecessary session nonce verification on public REST endpoint.
* Added automatic fallback for uploads directory on isolated systemd hosting environments.
* Refactored cURL handle cleanup for full PHP 8.5 compatibility.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.1 =
Recommended update: fixes frontend form binding and improves upload compatibility.
