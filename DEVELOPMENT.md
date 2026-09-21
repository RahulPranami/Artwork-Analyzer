# WP Artwork Analyzer - Developer & Contributor Guide

This guide covers local development, testing, packaging for WordPress.org, and version control best practices.

---

## 1. Local Development Setup

### Requirements
- WordPress 6.0 or later
- PHP 8.0 or later (tested up to PHP 8.5)
- PHP cURL and GD or Imagick extensions enabled
- Contact Form 7 plugin installed and activated
- Google Gemini API key (obtain free at [Google AI Studio](https://aistudio.google.com/))

### Configuration Priority
The plugin detects your Gemini API key in the following order:
1. `wp-config.php`: `define('WPA_GEMINI_API_KEY', 'AIza...');`
2. Environment variable: `export GEMINI_API_KEY="AIza..."`
3. Root `.env` file: `GEMINI_API_KEY=AIza...`
4. WordPress Admin: **WP Admin -> Artwork Analyzer -> AI Provider**

---

## 2. Git & Secret Hygiene (CRITICAL)

> [!WARNING]
> **NEVER commit `.env` or hardcoded API keys to GitHub or public repositories.**
> - Keep your `.env` in the WordPress root directory or local environment.
> - The plugin repository includes `.gitignore` to prevent committing `.env`, `.log`, or archive files.
> - When deploying or publishing to GitHub, verify with:
>   ```bash
>   git status
>   git diff --cached
>   ```

---

## 3. Useful WP-CLI Commands

### Re-generate Translation POT File
Run whenever translatable strings are added or modified:
```bash
wp i18n make-pot wp-content/plugins/wp-artwork-analyzer wp-content/plugins/wp-artwork-analyzer/languages/wp-artwork-analyzer.pot
```

### Check PHP Syntax
```bash
find wp-content/plugins/wp-artwork-analyzer/ -name "*.php" -exec php -l {} \;
```

### Inspect Database Table
```bash
wp db query "DESCRIBE wp_artwork_analyses;"
wp db query "SELECT id, uuid, status, provider, created_at FROM wp_artwork_analyses ORDER BY id DESC LIMIT 10;"
```

---

## 4. Building the WordPress.org Distribution ZIP

To generate a clean production archive ready for upload to WordPress.org (`/wp-content/plugins/wp-artwork-analyzer.zip`):

```bash
php -r '
$zip = new ZipArchive();
$zipPath = __DIR__ . "/wp-content/plugins/wp-artwork-analyzer.zip";
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Failed to create zip file\n");
}
$source = realpath(__DIR__ . "/wp-content/plugins/wp-artwork-analyzer");
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($files as $file) {
    $filePath = $file->getRealPath();
    $relativePath = "wp-artwork-analyzer/" . substr($filePath, strlen($source) + 1);
    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
    } else {
        $zip->addFile($filePath, $relativePath);
    }
}
$zip->close();
echo "Generated $zipPath\n";
'
```

---

## 5. Troubleshooting & Diagnostics

### Reverse Proxy Buffering
If the AI critique appears all at once instead of streaming word-by-word:
1. Open **WP Admin -> Artwork Analyzer -> Diagnostics**.
2. Click **Run Latency Test**.
3. If chunk timestamps arrive simultaneously, your web server or proxy is buffering responses:
   - **NGINX**: Set `proxy_buffering off;` or `fastcgi_buffering off;`.
   - **Cloudflare**: Disable "Rocket Loader" and buffer features for `/wp-json/art-analyzer/v1/analyze`.
   - **Apache**: Ensure `mod_deflate` is not aggressively compressing `text/event-stream`.

### Read-Only Filesystem Errors (systemd)
On Linux servers running Apache under systemd with `ProtectHome=read-only`, PHP cannot write temporary uploads to `/home/user/public_html/...`. The plugin automatically falls back to `get_temp_dir()` (`/tmp`) to ensure file uploads succeed seamlessly.
