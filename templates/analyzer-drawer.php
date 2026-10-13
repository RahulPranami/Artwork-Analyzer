<?php
/**
 * Slide-In Artwork Analyzer Sidebar Drawer Template.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;
?>

<!-- Slide-In Drawer Backdrop -->
<div id="wpa-drawer-backdrop" class="wpa-drawer-backdrop" aria-hidden="true"></div>

<!-- Slide-In Drawer Container -->
<aside id="wpa-drawer" class="wpa-drawer" role="dialog" aria-modal="true" aria-labelledby="wpa-drawer-title" aria-hidden="true" tabindex="-1">
    <!-- Drawer Header -->
    <div class="wpa-drawer-header">
        <div class="wpa-drawer-header-info">
            <h2 id="wpa-drawer-title" class="wpa-drawer-title">
                <span class="dashicons dashicons-art" aria-hidden="true"></span>
                <span><?php esc_html_e('Artwork Analyzer', 'wp-artwork-analyzer'); ?></span>
            </h2>
            <span class="wpa-drawer-subtitle"><?php esc_html_e('Visual Reverse Search, Web Research & AI Critique', 'wp-artwork-analyzer'); ?></span>
        </div>
        <button type="button" class="wpa-drawer-close" id="wpa-drawer-close" aria-label="<?php esc_attr_e('Close Sidebar', 'wp-artwork-analyzer'); ?>" title="<?php esc_attr_e('Close (Esc)', 'wp-artwork-analyzer'); ?>">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <!-- Drawer Body -->
    <div class="wpa-drawer-body">
        <!-- 1. Upload & Media Management Area -->
        <div class="wpa-drawer-upload-section" id="wpa-drawer-upload-section">
            <!-- Drag and Drop Dropzone -->
            <div class="wpa-dropzone" id="wpa-drawer-dropzone" tabindex="0" role="region" aria-label="<?php esc_attr_e('Artwork Image Upload Area', 'wp-artwork-analyzer'); ?>">
                <input type="file" id="wpa-drawer-file-input" class="wpa-drawer-file-input" accept="image/jpeg,image/png,image/webp,image/*" style="display:none;" />
                <input type="file" id="wpa-drawer-mobile-camera" accept="image/*" capture="environment" style="display:none;" />
                <div class="wpa-dropzone-icon" aria-hidden="true">🖼️</div>
                <div class="wpa-dropzone-text">
                    <strong><?php esc_html_e('Drag and drop artwork here', 'wp-artwork-analyzer'); ?></strong>
                    <span><?php esc_html_e('or choose from your device', 'wp-artwork-analyzer'); ?></span>
                </div>
                <div class="wpa-dropzone-actions">
                    <button type="button" class="wpa-btn wpa-btn-secondary" id="wpa-drawer-browse-btn">
                        📁 <?php esc_html_e('Browse Files', 'wp-artwork-analyzer'); ?>
                    </button>
                    <button type="button" class="wpa-btn wpa-btn-secondary" id="wpa-drawer-camera-btn">
                        📷 <?php esc_html_e('Snap Photo', 'wp-artwork-analyzer'); ?>
                    </button>
                </div>
                <div class="wpa-dropzone-hint">
                    <?php esc_html_e('Supports JPG, PNG, and WEBP up to 10 MB. High-res images are automatically downscaled for analysis.', 'wp-artwork-analyzer'); ?>
                </div>
            </div>

            <!-- Image Preview Card -->
            <div class="wpa-drawer-preview-card" id="wpa-drawer-preview-card" style="display:none;">
                <div class="wpa-drawer-preview-thumb">
                    <img id="wpa-drawer-preview-img" src="" alt="<?php esc_attr_e('Artwork Preview', 'wp-artwork-analyzer'); ?>" />
                </div>
                <div class="wpa-drawer-preview-details">
                    <div class="wpa-drawer-preview-filename" id="wpa-drawer-filename">artwork.jpg</div>
                    <div class="wpa-drawer-preview-meta">
                        <span id="wpa-drawer-dimensions">-</span> &bull;
                        <span id="wpa-drawer-filesize">-</span>
                    </div>
                    <div class="wpa-drawer-preview-actions">
                        <button type="button" class="wpa-btn-link" id="wpa-drawer-replace-btn">
                            <?php esc_html_e('Replace Image', 'wp-artwork-analyzer'); ?>
                        </button>
                        <button type="button" class="wpa-btn-link wpa-btn-link-danger" id="wpa-drawer-remove-btn">
                            <?php esc_html_e('Remove', 'wp-artwork-analyzer'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Explicit Primary Action Button -->
            <div class="wpa-drawer-action-bar">
                <button type="button" class="wpa-btn wpa-btn-primary wpa-btn-analyze-main" id="wpa-drawer-analyze-btn" disabled>
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <span class="wpa-btn-text"><?php esc_html_e('Analyze Artwork', 'wp-artwork-analyzer'); ?></span>
                </button>
            </div>
        </div>

        <!-- 2. Multi-Stage Pipeline Progress Indicator -->
        <div class="wpa-drawer-pipeline-status" id="wpa-drawer-pipeline-status" style="display:none;">
            <div class="wpa-pipeline-header">
                <div class="wpa-pipeline-current-stage">
                    <span class="wpa-spinner" aria-hidden="true"></span>
                    <span id="wpa-pipeline-stage-label"><?php esc_html_e('Preparing analysis...', 'wp-artwork-analyzer'); ?></span>
                </div>
                <button type="button" class="wpa-btn wpa-btn-stop" id="wpa-drawer-stop-btn">
                    <span class="dashicons dashicons-controls-pause" aria-hidden="true"></span>
                    <span><?php esc_html_e('Stop', 'wp-artwork-analyzer'); ?></span>
                </button>
            </div>

            <div class="wpa-pipeline-track" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                <div class="wpa-pipeline-bar" id="wpa-pipeline-progress-bar" style="width: 15%;"></div>
            </div>

            <ul class="wpa-pipeline-steps" id="wpa-pipeline-steps">
                <li data-step="preparing" class="wpa-step-active">
                    <span class="wpa-step-dot"></span>
                    <span class="wpa-step-text"><?php esc_html_e('1. Prepare', 'wp-artwork-analyzer'); ?></span>
                </li>
                <li data-step="reverse_search">
                    <span class="wpa-step-dot"></span>
                    <span class="wpa-step-text"><?php esc_html_e('2. Visual Search', 'wp-artwork-analyzer'); ?></span>
                </li>
                <li data-step="web_research">
                    <span class="wpa-step-dot"></span>
                    <span class="wpa-step-text"><?php esc_html_e('3. Web Research', 'wp-artwork-analyzer'); ?></span>
                </li>
                <li data-step="analyzing">
                    <span class="wpa-step-dot"></span>
                    <span class="wpa-step-text"><?php esc_html_e('4. AI Critique', 'wp-artwork-analyzer'); ?></span>
                </li>
            </ul>
        </div>

        <!-- 3. Results Container with Tabs -->
        <div class="wpa-drawer-results" id="wpa-drawer-results" style="display:none;">
            <!-- Tab Navigation -->
            <div class="wpa-results-tabs" role="tablist">
                <button type="button" role="tab" class="wpa-results-tab wpa-tab-active" id="wpa-tab-critique-btn" aria-selected="true" aria-controls="wpa-panel-critique">
                    <span><?php esc_html_e('AI Critique', 'wp-artwork-analyzer'); ?></span>
                    <span class="wpa-tab-badge wpa-badge-streaming" id="wpa-badge-critique">Live</span>
                </button>
                <button type="button" role="tab" class="wpa-results-tab" id="wpa-tab-matches-btn" aria-selected="false" aria-controls="wpa-panel-matches">
                    <span><?php esc_html_e('Visual Matches', 'wp-artwork-analyzer'); ?></span>
                    <span class="wpa-tab-badge" id="wpa-badge-matches">0</span>
                </button>
                <button type="button" role="tab" class="wpa-results-tab" id="wpa-tab-research-btn" aria-selected="false" aria-controls="wpa-panel-research">
                    <span><?php esc_html_e('Research Sources', 'wp-artwork-analyzer'); ?></span>
                    <span class="wpa-tab-badge" id="wpa-badge-research">0</span>
                </button>
            </div>

            <!-- Tab Panel 1: AI Critique -->
            <div role="tabpanel" class="wpa-tab-panel wpa-panel-active" id="wpa-panel-critique" aria-labelledby="wpa-tab-critique-btn">
                <div class="wpa-drawer-stream-content" id="wpa-drawer-stream-content" tabindex="0" aria-live="polite">
                    <div class="wpa-stream-placeholder">
                        <div class="wpa-pulse-bar"></div>
                        <div class="wpa-pulse-bar short"></div>
                        <div class="wpa-pulse-bar medium"></div>
                    </div>
                </div>
            </div>

            <!-- Tab Panel 2: Visual Matches -->
            <div role="tabpanel" class="wpa-tab-panel" id="wpa-panel-matches" aria-labelledby="wpa-tab-matches-btn" style="display:none;">
                <!-- Best Guess Banner -->
                <div class="wpa-best-guess-card" id="wpa-best-guess-card" style="display:none;">
                    <div class="wpa-best-guess-label"><?php esc_html_e('Visual Search Identification Candidate', 'wp-artwork-analyzer'); ?></div>
                    <div class="wpa-best-guess-title" id="wpa-best-guess-text"></div>
                </div>

                <!-- Detected Web Entities -->
                <div class="wpa-entities-wrapper" id="wpa-entities-wrapper" style="display:none;">
                    <div class="wpa-entities-title"><?php esc_html_e('Detected Art Entities & Tags', 'wp-artwork-analyzer'); ?>:</div>
                    <div class="wpa-entities-tags" id="wpa-entities-tags"></div>
                </div>

                <!-- Matches List -->
                <div class="wpa-matches-list" id="wpa-matches-list">
                    <div class="wpa-empty-state" id="wpa-matches-empty">
                        <div class="wpa-empty-icon">🔎</div>
                        <div class="wpa-empty-text"><?php esc_html_e('Searching for visual matches online...', 'wp-artwork-analyzer'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Tab Panel 3: Research Findings -->
            <div role="tabpanel" class="wpa-tab-panel" id="wpa-panel-research" aria-labelledby="wpa-tab-research-btn" style="display:none;">
                <div class="wpa-research-list" id="wpa-research-list">
                    <div class="wpa-empty-state" id="wpa-research-empty">
                        <div class="wpa-empty-icon">📚</div>
                        <div class="wpa-empty-text"><?php esc_html_e('Gathering encyclopedic art records...', 'wp-artwork-analyzer'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Error Box -->
        <div class="wpa-drawer-error-box" id="wpa-drawer-error-box" style="display:none;">
            <div class="wpa-drawer-error-icon" aria-hidden="true">⚠️</div>
            <div class="wpa-drawer-error-details">
                <div class="wpa-drawer-error-title"><?php esc_html_e('Analysis Notice', 'wp-artwork-analyzer'); ?></div>
                <div class="wpa-drawer-error-message" id="wpa-drawer-error-message"></div>
            </div>
            <div class="wpa-drawer-error-actions">
                <button type="button" class="wpa-btn wpa-btn-retry" id="wpa-drawer-retry-btn">
                    <?php esc_html_e('Try Again', 'wp-artwork-analyzer'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Drawer Footer -->
    <div class="wpa-drawer-footer">
        <button type="button" class="wpa-btn wpa-btn-secondary" id="wpa-drawer-startover-btn">
            <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>
            <span><?php esc_html_e('Analyze Another Artwork', 'wp-artwork-analyzer'); ?></span>
        </button>
        <span class="wpa-footer-note"><?php esc_html_e('Powered by Google Gemini & Visual Web Detection', 'wp-artwork-analyzer'); ?></span>
    </div>
</aside>
