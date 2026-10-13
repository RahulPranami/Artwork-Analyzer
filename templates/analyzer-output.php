<?php
/**
 * Output container template for WP Artwork Analyzer.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;
?>

<!-- Launcher Button Container -->
<div class="wpa-launcher-bar" id="wpa-launcher-bar">
    <button type="button" class="wpa-btn wpa-btn-primary wpa-drawer-launcher-btn" id="wpa-drawer-launcher-btn" aria-haspopup="dialog">
        <span class="dashicons dashicons-art" aria-hidden="true"></span>
        <span><?php esc_html_e('Analyze Artwork', 'wp-artwork-analyzer'); ?></span>
    </button>
</div>

<!-- Inline Output Wrapper (Preserved for backwards-compatibility and inline embedding) -->
<div id="wpa-analyzer-wrapper" class="wpa-analyzer-wrapper" style="display:none;" data-state="idle">
    <!-- Header / Status Banner -->
    <div class="wpa-header">
        <div class="wpa-status-badge">
            <span class="wpa-spinner" aria-hidden="true"></span>
            <span class="wpa-status-text"><?php esc_html_e('Analyzing artwork...', 'wp-artwork-analyzer'); ?></span>
        </div>
        <div class="wpa-header-actions">
            <button type="button" class="wpa-btn wpa-btn-secondary wpa-open-drawer-btn" id="wpa-open-drawer-btn">
                <span class="dashicons dashicons-slides" aria-hidden="true"></span>
                <span><?php esc_html_e('Open Sidebar', 'wp-artwork-analyzer'); ?></span>
            </button>
            <button type="button" class="wpa-btn wpa-btn-stop" id="wpa-btn-stop">
                <span class="dashicons dashicons-controls-pause" aria-hidden="true"></span>
                <span><?php esc_html_e('Stop Analysis', 'wp-artwork-analyzer'); ?></span>
            </button>
            <button type="button" class="wpa-btn wpa-btn-reset" id="wpa-btn-reset" style="display:none;">
                <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>
                <span><?php esc_html_e('Analyze Another', 'wp-artwork-analyzer'); ?></span>
            </button>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="wpa-body">
        <!-- Artwork Preview Column -->
        <div class="wpa-preview-col">
            <div class="wpa-preview-card">
                <div class="wpa-preview-image-wrapper">
                    <img id="wpa-artwork-preview" src="" alt="<?php esc_attr_e('Uploaded Artwork Preview', 'wp-artwork-analyzer'); ?>" />
                </div>
                <div class="wpa-preview-meta">
                    <span id="wpa-preview-filename" class="wpa-filename"></span>
                    <span id="wpa-preview-filesize" class="wpa-filesize"></span>
                </div>
            </div>

            <!-- Progress Tracker -->
            <div class="wpa-sections-tracker" id="wpa-sections-tracker">
                <div class="wpa-tracker-title"><?php esc_html_e('Analysis Progress', 'wp-artwork-analyzer'); ?></div>
                <ul class="wpa-tracker-list">
                    <li data-section="overview"><span class="wpa-dot"></span> <?php esc_html_e('Overview', 'wp-artwork-analyzer'); ?></li>
                    <li data-section="identification"><span class="wpa-dot"></span> <?php esc_html_e('Identification', 'wp-artwork-analyzer'); ?></li>
                    <li data-section="subject"><span class="wpa-dot"></span> <?php esc_html_e('Subject Matter', 'wp-artwork-analyzer'); ?></li>
                    <li data-section="composition"><span class="wpa-dot"></span> <?php esc_html_e('Composition', 'wp-artwork-analyzer'); ?></li>
                    <li data-section="color"><span class="wpa-dot"></span> <?php esc_html_e('Color Analysis', 'wp-artwork-analyzer'); ?></li>
                    <li data-section="technique"><span class="wpa-dot"></span> <?php esc_html_e('Technique & Style', 'wp-artwork-analyzer'); ?></li>
                    <li data-section="mood"><span class="wpa-dot"></span> <?php esc_html_e('Mood & Emotion', 'wp-artwork-analyzer'); ?></li>
                    <li data-section="meaning"><span class="wpa-dot"></span> <?php esc_html_e('Meaning & Context', 'wp-artwork-analyzer'); ?></li>
                    <li data-section="summary"><span class="wpa-dot"></span> <?php esc_html_e('Summary', 'wp-artwork-analyzer'); ?></li>
                </ul>
            </div>
        </div>

        <!-- Analysis Text Stream Column -->
        <div class="wpa-stream-col">
            <!-- Streaming Content Container -->
            <div id="wpa-stream-content" class="wpa-stream-content" tabindex="0" aria-live="polite">
                <div class="wpa-stream-placeholder">
                    <div class="wpa-pulse-bar"></div>
                    <div class="wpa-pulse-bar short"></div>
                    <div class="wpa-pulse-bar medium"></div>
                </div>
            </div>

            <!-- Error Box -->
            <div id="wpa-error-container" class="wpa-error-box" style="display:none;">
                <div class="wpa-error-icon">⚠️</div>
                <div class="wpa-error-details">
                    <div class="wpa-error-title"><?php esc_html_e('Analysis Notice', 'wp-artwork-analyzer'); ?></div>
                    <div id="wpa-error-message" class="wpa-error-message"></div>
                </div>
                <button type="button" class="wpa-btn wpa-btn-retry" id="wpa-btn-retry">
                    <?php esc_html_e('Try Again', 'wp-artwork-analyzer'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Render the slide-in drawer once per request
global $wpa_drawer_rendered;
if (empty($wpa_drawer_rendered)) {
    $wpa_drawer_rendered = true;
    include WPA_ART_ANALYZER_PATH . 'templates/analyzer-drawer.php';
}
?>
