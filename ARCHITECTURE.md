# WP Artwork Analyzer - Architecture & Technical Specification

## 1. System Overview

WP Artwork Analyzer connects WordPress (specifically Contact Form 7) with Google Gemini multimodal AI vision models to deliver realtime, progressive streaming critiques (Server-Sent Events) of user-uploaded visual art.

The plugin adopts a **decoupled architecture**:
- Contact Form 7 handles user input, email delivery, and CRM logging independently.
- WP Artwork Analyzer intercepts the artwork upload client-side, streams an instant ChatGPT-style AI analysis via SSE, and bridges the file upload into CF7's native submission flow.

```
+-----------------------------------------------------------------------------------+
|                                 USER BROWSER                                      |
|                                                                                   |
|   +--------------------------+               +--------------------------------+   |
|   | Dual Media Selector      |               | Streaming Output Container     |   |
|   | [📁 Choose]  [📷 Camera] |               | - Progressive Section Tracker  |   |
|   +------------+-------------+               | - Live Markdown Renderer       |   |
|                |                             +---------------+----------------+   |
+----------------|---------------------------------------------^--------------------+
                 | File (DataTransfer / FormData)              | SSE Stream
                 v                                             | (text/event-stream)
+--------------------------------------------------------------+--------------------+
|                         WORDPRESS SERVER (REST API)                               |
|                                                                                   |
|   [POST /wp-json/art-analyzer/v1/analyze]                                         |
|       |                                                                           |
|       +--> 1. Rate Limiter (Class_Rate_Limiter: Transient IP hashing)             |
|       |                                                                           |
|       +--> 2. Cloudflare Turnstile (Class_Security: bot verification)             |
|       |                                                                           |
|       +--> 3. Image Handler (Class_Image_Handler: MIME sniff + 2048px resize)     |
|       |                                                                           |
|       +--> 4. Gemini Client (Class_Gemini_Client: cURL SSE upstream relay)        |
|                |                                                                  |
+----------------|------------------------------------------------------------------+
                 | POST streamGenerateContent?alt=sse (Base64 inlineData)
                 v
+-----------------------------------------------------------------------------------+
|                           GOOGLE GEMINI CLOUD                                     |
|                                                                                   |
|   gemini-3.5-flash-lite / gemini-2.5-flash                                        |
+-----------------------------------------------------------------------------------+
```

---

## 2. Component Directory

| File / Directory | Role | Key Functions / Responsibilities |
| :--- | :--- | :--- |
| `wp-artwork-analyzer.php` | Main bootstrap | Plugin header, constants, activation/deactivation hooks. |
| `includes/class-plugin.php` | Singleton orchestrator | Dependency loading, table creation via `dbDelta()`. |
| `includes/class-settings.php` | Configuration manager | Multi-tier API key discovery, option defaults, admin form handler. |
| `includes/class-security.php` | Security layer | Salted IP hashing, Cloudflare Turnstile token validation, `wp_kses()` markdown escaping. |
| `includes/class-rate-limiter.php` | Abuse protection | Sliding window transient-based request limiter per IP hash. |
| `includes/class-image-handler.php` | Media processing | Server-side MIME verification (`finfo`, `wp_getimagesize`), downscaling high-res images to 2048px max dimension via `wp_get_image_editor()`, and temp file cleanup. |
| `includes/class-gemini-client.php` | Upstream AI relay | Upstream streaming cURL connection, client abort detection (`connection_aborted()`), chunk buffer parser. |
| `includes/class-rest-controller.php`| REST endpoints | `/analyze`, `/health`, `/stream-test`, `/gemini-test`. |
| `includes/class-cf7-integration.php`| Form integration | CF7 shortcode injection, script localization, systemd upload directory fallback. |
| `admin/` | WP Admin dashboard | Tabbed settings UI, hosting proxy buffering tester, Gemini ping tool. |
| `public/js/artwork-analyzer.js` | Frontend controller | Dual camera/file selector, WebRTC camera modal, `ReadableStream` SSE parser, progressive markdown renderer. |
| `public/css/artwork-analyzer.css`| Stylesheet | Responsive layout, camera modal, progress pills, status indicators. |
| `templates/analyzer-output.php` | View markup | HTML card container rendered above or below CF7. |
| `uninstall.php` | Cleanup script | Drops custom table, deletes transients and settings on plugin deletion. |

---

## 3. Database Schema

The plugin creates a custom table `{$wpdb->prefix}artwork_analyses` upon activation:

```sql
CREATE TABLE wp_artwork_analyses (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    uuid varchar(64) NOT NULL DEFAULT '',
    form_id bigint(20) unsigned NOT NULL DEFAULT 0,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    email varchar(191) NOT NULL DEFAULT '',
    image_path text NOT NULL,
    image_hash varchar(64) NOT NULL DEFAULT '',
    provider varchar(32) NOT NULL DEFAULT 'gemini',
    model varchar(64) NOT NULL DEFAULT '',
    status varchar(32) NOT NULL DEFAULT 'pending',
    analysis longtext NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at datetime NULL,
    ip_hash varchar(64) NOT NULL DEFAULT '',
    PRIMARY KEY  (id),
    KEY uuid (uuid),
    KEY status (status),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. Streaming Protocol (SSE Specification)

The `/analyze` endpoint sends `Content-Type: text/event-stream` with disabled output buffering.

### Event Format:
Each SSE payload uses standard event-message blocks:

```
event: start
data: {"status":"streaming","model":"gemini-3.5-flash-lite"}

event: text
data: {"text":"### 1. Overview\nThe painting depicts..."}

event: done
data: {"status":"complete","total_characters":1420}

event: error
data: {"error":"Rate limit exceeded. Please wait a few minutes."}
```

### Buffer Handling:
- Client abort detection: PHP monitors `connection_aborted()`. If the user closes the tab or clicks "Stop Analysis", the upstream cURL request terminates immediately, conserving Gemini API quota.
- Reverse proxy bypass: Outputs `X-Accel-Buffering: no` for NGINX and sends implicit padding chunks to bypass Apache/Cloudflare buffer thresholds.

---

## 5. Camera & File Integration Mechanism

1. **File Selection**: Standard OS file picker opens when clicking **Choose Image File**.
2. **Camera Capture**:
   - **Mobile Devices**: Activates a hidden `<input type="file" accept="image/*" capture="environment">`. iOS and Android launch the native camera app immediately with full camera hardware access.
   - **Desktop / Laptop**: If `navigator.mediaDevices.getUserMedia` is present, opens an inline modal with live video feed. Visitors hold their physical artwork in front of the webcam and click **Snap Photo**.
3. **DataTransfer Synchronization**: When an image is selected or captured, JavaScript constructs a `File` object and assigns it to the native Contact Form 7 `<input type="file" name="artwork">` using the HTML5 `DataTransfer` API. This guarantees that Contact Form 7 continues to email the image attachment upon standard form submission without requiring custom CF7 handlers.
