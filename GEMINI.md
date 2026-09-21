# WP Artwork Analyzer - Agent Context & Rules

This document provides Antigravity (`agy`) with architectural context, code standards, and operational guidelines for the **WP Artwork Analyzer** WordPress plugin.

---

## Project Overview
- **Plugin Slug**: `wp-artwork-analyzer`
- **Purpose**: AI-powered artwork analysis delivering real-time streaming critiques (Server-Sent Events) via Google Gemini Vision API, integrated seamlessly with Contact Form 7.
- **Minimum Requirements**: WordPress 6.0+, PHP 8.0+ (compatible with PHP 8.5).
- **License**: GPLv2 or later.

---

## Key Architecture & Components

```
wp-artwork-analyzer/
|-- wp-artwork-analyzer.php       # Bootstrap, plugin headers, constants, lifecycle hooks
|-- includes/
|   |-- class-plugin.php          # Singleton container, DB table migration
|   |-- class-settings.php        # Settings store, multi-tier API key discovery (.env, constants, options)
|   |-- class-security.php        # Hashed IP identification, Cloudflare Turnstile token validation, wp_kses sanitization
|   |-- class-rate-limiter.php    # Transient sliding-window rate limiter per client IP
|   |-- class-image-handler.php   # finfo / wp_getimagesize MIME sniffing, downscale to 2048px, temp cleanup
|   |-- class-gemini-client.php   # Direct cURL SSE streaming client to Gemini Vision API
|   |-- class-rest-controller.php # /analyze, /health, /stream-test, /gemini-test endpoints
|   `-- class-cf7-integration.php # CF7 hook binding, script localization, systemd upload fallback
|-- admin/
|   |-- class-admin.php           # Admin menu and settings save handler
|   `-- views/settings.php        # Tabbed settings UI and latency diagnostics
|-- public/
|   |-- js/artwork-analyzer.js    # Dual camera/file selector, WebRTC camera modal, SSE reader, Markdown parser
|   `-- css/artwork-analyzer.css  # Public styling, camera viewport, progress badges
|-- templates/
|   `-- analyzer-output.php       # HTML stream container template
|-- languages/
|   `-- wp-artwork-analyzer.pot   # i18n translation template (0 warnings)
|-- readme.txt                    # WordPress.org standard validator syntax (includes Guideline #7 External Services)
`-- uninstall.php                 # Options, transients, and DB table removal on deletion
```

---

## Coding Rules & WordPress.org Standards

1. **Security & Input Sanitization**:
   - Every file must start with `defined('ABSPATH') || exit;` (or `defined('WP_UNINSTALL_PLUGIN') || exit;` in `uninstall.php`).
   - All inputs must be sanitized using `sanitize_text_field()`, `sanitize_textarea_field()`, `absint()`, or `floatval()`.
   - Never use raw `$_REQUEST`, `$_GET`, or `$_POST` without nonce verification (`check_admin_referer()`) or `WP_REST_Request` parameter extraction.
   - All outputs in views must be escaped (`esc_html()`, `esc_attr()`, `esc_url()`).

2. **No Error Suppression**:
   - Do NOT use the PHP `@` error suppression operator anywhere in the codebase (strictly flagged by WordPress.org reviewers).
   - Use defensive functions like `wp_getimagesize()` and `wp_delete_file()`.

3. **Internationalization (i18n)**:
   - Text domain must always be `'wp-artwork-analyzer'`.
   - When using multiple placeholders in format strings, use ordered arguments (`%1$s`, `%2$s`) accompanied by `/* translators: ... */` comment.
   - Keep `languages/wp-artwork-analyzer.pot` up to date using `wp i18n make-pot`.

4. **Secret Management**:
   - NEVER commit `.env`, API keys, or credentials to git.
   - The plugin retrieves the key dynamically via `WPA_Art_Analyzer_Settings::get_api_key()`.

5. **Streaming & Buffering**:
   - The `/analyze` endpoint sends `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`.
   - Always check `connection_aborted()` inside the cURL write callback to cancel upstream generation if the client disconnects.
