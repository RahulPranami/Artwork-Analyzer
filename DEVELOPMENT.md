# WP Artwork Analyzer - Developer & Contributor Guide

This guide covers local development, testing, packaging for WordPress.org, and version control best practices.

---

## 1. Local Development Setup

### Requirements
- WordPress 6.0 or later (tested up to 7.1)
- PHP 8.0 or later (tested on PHP 8.5)
- PHP cURL and GD or Imagick extensions enabled
- Contact Form 7 plugin installed and activated (optional if using standalone shortcode)
- Google Gemini API key (free at [Google AI Studio](https://aistudio.google.com/))
- (Optional) Google Cloud Vision API key for reverse image search

### Configuration Priority
The plugin automatically discovers keys in the following priority order:
1. `wp-config.php`:
   - `define('WPA_GEMINI_API_KEY', 'AIza...');`
   - `define('WPA_GOOGLE_VISION_API_KEY', 'AIza...');`
2. Environment variables:
   - `export GEMINI_API_KEY="AIza..."`
   - `export GOOGLE_VISION_API_KEY="AIza..."`
3. Root `.env` file in WordPress directory:
   ```env
   GEMINI_API_KEY=AIza...
   GOOGLE_VISION_API_KEY=AIza...
   ```
4. WordPress Admin: **WP Admin -> Artwork Analyzer**

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

## 3. Automated Testing & Verification

### Running the Test Suite
The repository includes an automated verification test suite covering providers, factories, normalization, prompt security, HTML sanitization, and REST routes:

```bash
wp eval-file /home/rahul/public_html/artpress/wp-content/plugins/wp-artwork-analyzer/tests/run-tests.php --path=/home/rahul/public_html/artpress
```

### Check PHP Syntax Across All Files
```bash
find wp-content/plugins/wp-artwork-analyzer/ -name "*.php" -exec php -l {} \;
```

### Re-generate Translation POT File
```bash
wp i18n make-pot wp-content/plugins/wp-artwork-analyzer wp-content/plugins/wp-artwork-analyzer/languages/wp-artwork-analyzer.pot --domain=wp-artwork-analyzer --path=/home/rahul/public_html/artpress
```

---

## 4. Testing Endpoints Locally

### Test Health Endpoint
```bash
curl -s http://localhost/wp-json/art-analyzer/v1/health | jq .
```

### Test Web Research Endpoint Directly
```bash
curl -s -X POST http://localhost/wp-json/art-analyzer/v1/web-research \
  -H "Content-Type: application/json" \
  -d '{"query":"Vincent van Gogh The Starry Night"}' | jq .
```

### Test Reverse Image Search Endpoint
```bash
curl -s -X POST http://localhost/wp-json/art-analyzer/v1/reverse-search \
  -F "artwork=@path/to/artwork.jpg" | jq .
```

---

## 5. Building the WordPress.org Distribution ZIP

To generate a clean production archive ready for deployment (`/wp-content/plugins/wp-artwork-analyzer.zip`):

```bash
php -r '
$zip = new ZipArchive();
$zipPath = dirname(__DIR__) . "/wp-artwork-analyzer.zip";
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Failed to create zip file\n");
}
$source = realpath(__DIR__);
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($files as $file) {
    $filePath = $file->getRealPath();
    if (str_contains($filePath, "/.git/") || str_contains($filePath, "/tests/")) continue;
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
